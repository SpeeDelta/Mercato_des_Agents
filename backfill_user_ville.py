#!/usr/bin/env python3

import argparse
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
import zipfile
from typing import Dict, Iterable, Tuple


NS = {"a": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}


def load_dotenv(path: str) -> None:
    if not os.path.exists(path):
        return

    with open(path, "r", encoding="utf-8") as fh:
        for raw_line in fh:
            line = raw_line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            key = key.strip()
            value = value.strip()
            if value.startswith(("'", '"')) and value.endswith(("'", '"')) and len(value) >= 2:
                value = value[1:-1]
            os.environ.setdefault(key, value)


def read_shared_strings(zf: zipfile.ZipFile) -> list[str]:
    if "xl/sharedStrings.xml" not in zf.namelist():
        return []

    shared = []
    root = ET.fromstring(zf.read("xl/sharedStrings.xml"))
    for si in root.findall("a:si", NS):
        shared.append("".join(t.text or "" for t in si.findall(".//a:t", NS)))
    return shared


def read_cell_value(cell: ET.Element, shared: list[str]) -> str:
    raw = cell.find("a:v", NS)
    value = raw.text if raw is not None else ""
    if cell.attrib.get("t") == "s" and value.isdigit():
        idx = int(value)
        return shared[idx] if idx < len(shared) else ""
    return value


def extract_city_map(xlsx_path: str) -> Dict[str, str]:
    city_by_name: Dict[str, str] = {}

    with zipfile.ZipFile(xlsx_path) as zf:
        shared = read_shared_strings(zf)
        sheet = ET.fromstring(zf.read("xl/worksheets/sheet1.xml"))
        rows = sheet.findall(".//a:sheetData/a:row", NS)

        # Expected structure in sheet1: A = subId/name, B = city
        for row in rows[1:]:
            cells: Dict[str, str] = {}
            for cell in row.findall("a:c", NS):
                ref = cell.attrib.get("r", "")
                col = "".join(ch for ch in ref if ch.isalpha())
                cells[col] = read_cell_value(cell, shared).strip()

            name = cells.get("A", "").strip()
            city = cells.get("B", "").strip()
            if name and city:
                city_by_name[name] = city

    return city_by_name


def http_json(url: str, method: str = "GET", payload: dict | None = None) -> dict:
    body = None
    headers = {"Accept": "application/json"}

    if payload is not None:
        body = json.dumps(payload).encode("utf-8")
        headers["Content-Type"] = "application/json"

    req = urllib.request.Request(url, data=body, headers=headers, method=method)
    with urllib.request.urlopen(req, timeout=20) as resp:
        content = resp.read().decode("utf-8")
        return json.loads(content) if content else {}


def list_all_users(project_id: str, api_key: str) -> Iterable[dict]:
    base = f"https://firestore.googleapis.com/v1/projects/{project_id}/databases/mercato/documents/users"
    next_page = None

    while True:
        params = {"key": api_key}
        if next_page:
            params["pageToken"] = next_page
        url = f"{base}?{urllib.parse.urlencode(params)}"
        payload = http_json(url)

        for doc in payload.get("documents", []):
            yield doc

        next_page = payload.get("nextPageToken")
        if not next_page:
            break


def patch_ville(document_name: str, city: str, api_key: str) -> None:
    encoded_path = "/".join(urllib.parse.quote(part, safe="") for part in document_name.split("/"))
    query = urllib.parse.urlencode([
        ("key", api_key),
        ("updateMask.fieldPaths", "ville"),
    ])
    url = f"https://firestore.googleapis.com/v1/{encoded_path}?{query}"
    payload = {"fields": {"ville": {"stringValue": city}}}
    http_json(url, method="PATCH", payload=payload)


def extract_subid_and_ville(doc: dict) -> Tuple[str, str]:
    fields = doc.get("fields", {})
    sub_id = (fields.get("subId") or {}).get("stringValue", "").strip()
    ville = (fields.get("ville") or {}).get("stringValue", "").strip()
    return sub_id, ville


def main() -> int:
    parser = argparse.ArgumentParser(description="Backfill Firestore users.ville from Mercato_des_Agents.xlsx")
    parser.add_argument("--xlsx", default="Mercato_des_Agents.xlsx", help="Path to source XLSX file")
    parser.add_argument("--env", default=".env", help="Path to .env file")
    parser.add_argument("--dry-run", action="store_true", help="Only print planned changes")
    args = parser.parse_args()

    load_dotenv(args.env)
    project_id = os.environ.get("FIREBASE_PROJECT_ID", "").strip()
    api_key = os.environ.get("FIREBASE_API_KEY", "").strip()

    if not project_id or not api_key:
        print("Missing FIREBASE_PROJECT_ID or FIREBASE_API_KEY in environment/.env", file=sys.stderr)
        return 1

    if not os.path.exists(args.xlsx):
        print(f"XLSX file not found: {args.xlsx}", file=sys.stderr)
        return 1

    city_by_name = extract_city_map(args.xlsx)
    if not city_by_name:
        print("No name/city entries found in XLSX, nothing to do.")
        return 0

    updated = 0
    skipped_same = 0
    skipped_no_city = 0
    skipped_no_subid = 0
    errors = 0

    for doc in list_all_users(project_id, api_key):
        doc_name = doc.get("name", "")
        sub_id, current_city = extract_subid_and_ville(doc)

        if not sub_id:
            skipped_no_subid += 1
            continue

        target_city = city_by_name.get(sub_id, "").strip()
        if not target_city:
            skipped_no_city += 1
            continue

        if current_city == target_city:
            skipped_same += 1
            continue

        if args.dry_run:
            print(f"[DRY-RUN] {sub_id}: '{current_city}' -> '{target_city}'")
            updated += 1
            continue

        try:
            patch_ville(doc_name, target_city, api_key)
            print(f"[UPDATED] {sub_id}: '{current_city}' -> '{target_city}'")
            updated += 1
        except urllib.error.HTTPError as exc:
            errors += 1
            detail = exc.read().decode("utf-8", errors="ignore")
            print(f"[ERROR] {sub_id}: HTTP {exc.code} {detail}", file=sys.stderr)
        except Exception as exc:
            errors += 1
            print(f"[ERROR] {sub_id}: {exc}", file=sys.stderr)

    print(
        "Done. "
        f"planned_or_updated={updated}, "
        f"already_ok={skipped_same}, "
        f"missing_city_in_xlsx={skipped_no_city}, "
        f"missing_subid={skipped_no_subid}, "
        f"errors={errors}"
    )

    return 1 if errors else 0


if __name__ == "__main__":
    raise SystemExit(main())

