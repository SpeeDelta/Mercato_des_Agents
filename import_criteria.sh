#!/usr/bin/env bash

set -euo pipefail

set -a
source .env
set +a

DATABASE="${FIRESTORE_DATABASE:-mercato}"
API_BASE="https://firestore.googleapis.com/v1/projects/${FIREBASE_PROJECT_ID}/databases/${DATABASE}/documents/criteria"

urlencode() {
  python3 - "$1" <<'PY'
import sys
from urllib.parse import quote
print(quote(sys.argv[1], safe=''))
PY
}

create_crit() {
  local ID="$1"
  local DESC="$2"
  local CAT="$3"
  local DOC_ID
  DOC_ID="$(urlencode "$ID")"

  if curl -sS -f -X PATCH \
    -H "Content-Type: application/json" \
    -d "{
      \"fields\": {
        \"id\": { \"stringValue\": \"$ID\" },
        \"description\": { \"stringValue\": \"$DESC\" },
        \"categorie\": { \"stringValue\": \"$CAT\" }
      }
    }" \
    "${API_BASE}/${DOC_ID}?key=${FIREBASE_API_KEY}" >/dev/null; then
    echo "✔ Critère créé / mis à jour : $ID"
  else
    echo "✖ Erreur lors de l'import : $ID" >&2
    return 1
  fi
}

# --- Catégorie A ---
create_crit "CRIT-01" "Un maillot avec du bleu dominant" "A"
create_crit "CRIT-02" "Un maillot avec du blanc dominant" "A"
create_crit "CRIT-03" "Un maillot avec du rouge dominant" "A"
create_crit "CRIT-04" "Un maillot avec du noir ou du gris foncé" "A"
create_crit "CRIT-05" "Un maillot avec une couleur flashy (jaune, orange, rose ou vert fluo)" "A"
create_crit "CRIT-06" "Un maillot avec des rayures verticales (type Juventus, Argentine, Milan AC...)" "A"
create_crit "CRIT-07" "Un maillot avec des motifs géométriques discrets dans le tissu" "A"
create_crit "CRIT-08" "Un maillot avec un col à boutons (type polo)" "A"
create_crit "CRIT-09" "Un maillot avec des manches d'une couleur différente du torse" "A"

# --- Catégorie B ---
create_crit "CRIT-10" "Un maillot avec le logo Adidas" "B"
create_crit "CRIT-11" "Un maillot avec le logo Nike" "B"
create_crit "CRIT-12" "Un maillot avec un autre équipementier que Nike ou Adidas" "B"
create_crit "CRIT-13" "Un maillot avec un écusson rond" "B"
create_crit "CRIT-14" "Un maillot avec un animal sur l'écusson" "B"
create_crit "CRIT-15" "Un maillot avec au moins une étoile dans l'écusson" "B"
create_crit "CRIT-16" "Un maillot avec un drapeau national visible" "B"
create_crit "CRIT-17" "Un maillot de club français" "B"
create_crit "CRIT-18" "Un maillot de club étranger" "B"
create_crit "CRIT-19" "Un maillot d'une sélection nationale" "B"

# --- Catégorie C ---
create_crit "CRIT-20" "Un maillot floqué avec le numéro 10" "C"
create_crit "CRIT-21" "Un maillot floqué avec le numéro 7 ou 9" "C"
create_crit "CRIT-22" "Un maillot avec un numéro à deux chiffres" "C"
create_crit "CRIT-23" "Un maillot avec un numéro impair" "C"
create_crit "CRIT-24" "Un maillot avec un nom de joueur commençant par M, C ou R" "C"
create_crit "CRIT-25" "Un maillot avec le nom d'un joueur qui ne joue plus aujourd'hui" "C"
create_crit "CRIT-26" "Un maillot sans aucun flocage dans le dos" "C"
create_crit "CRIT-27" "Un maillot dont le numéro est écrit en blanc ou en doré" "C"

# --- Catégorie D ---
create_crit "CRIT-28" "Un maillot avec un sponsor écrit en gros caractères blancs" "D"
create_crit "CRIT-29" "Un maillot avec une compagnie aérienne en sponsor" "D"
create_crit "CRIT-30" "Un maillot avec une marque de bière ou de paris en ligne" "D"
create_crit "CRIT-31" "Un maillot avec un sponsor automobile ou pneus" "D"
create_crit "CRIT-32" "Un maillot avec des bandes ou liserés au bout des manches" "D"
create_crit "CRIT-33" "Un maillot avec une phrase ou une date à l'intérieur du col" "D"
create_crit "CRIT-34" "Un maillot à manches longues ou porté avec sous-maillot" "D"
create_crit "CRIT-35" "Un maillot Édition spéciale ou Collector" "D"

echo "🎉 Import des critères terminé !"

