#!/usr/bin/env bash

set -euo pipefail

set -a
source .env
set +a

API_BASE="https://firestore.googleapis.com/v1/projects/${FIREBASE_PROJECT_ID}/databases/mercato/documents/users"

urlencode() {
  python3 - "$1" <<'PY'
import sys
from urllib.parse import quote
print(quote(sys.argv[1], safe=''))
PY
}

create_user() {
  local NAME="$1"
  local DOC_ID
  DOC_ID="$(urlencode "$NAME")"

  if curl -sS -f -X PATCH \
    -H "Content-Type: application/json" \
    -d "{
      \"fields\": {
        \"subId\": { \"stringValue\": \"$NAME\" },
        \"pseudo\": { \"stringValue\": \"\" },
        \"score\": { \"integerValue\": \"0\" },
        \"isActive\": { \"booleanValue\": true }
      }
    }" \
    "${API_BASE}/${DOC_ID}?key=${FIREBASE_API_KEY}" >/dev/null; then
    echo "✔ User créé / mis à jour : $NAME"
  else
    echo "✖ Erreur lors de l'import : $NAME" >&2
    return 1
  fi
}

# Liste des users
USERS=(
"Adam"
"Adrien Thomas"
"Agathe R"
"Alexandre T"
"Anne Couapel"
"Antoni De Jesus"
"Antoine Wischlen"
"Arno Larode"
"Audrey"
"Cedric Kouakam"
"Celya"
"Charles Lefeuvre"
"Chris B"
"Clémence"
"Dominique"
"Clara Grousset"
"Eléna"
"Elisa"
"Elo"
"Emma Frachet"
"Emma Charrade"
"Enzo V"
"Florent JIN"
"Grégoire"
"Guillaume K"
"Hugo Illoul"
"Hugo Calio"
"Hugo Chirossel"
"Idris"
"Julien Gillet"
"Jules"
"Julie Felicia"
"Julie La Parisienne"
"Alex Becquet"
"Léa Becquet"
"Loïc Laurent"
"Lucile Ferrand"
"Margot"
"Marie Warolin"
"Marin Come"
"Mathilde Cornu Becquet"
"Mathis Rattier"
"Maxence Trognon"
"Médéric"
"Armelle Servoles"
"Augustin Carré"
"Nassim Khelifi"
"Nicolas Dupasquier"
"Nicolas Leseurre"
"Danovan"
"Philippe"
"Pierre Moureaux"
"Quentin Jacku"
"Rayan Zegadi"
"Rhayan Belayachi"
"Remi"
"Sabine BLIN"
"Seb Udriste"
"Shams"
"Téo Chabot"
"Quentin Mas"
"Tom"
"François"
"Vincent"
"Yann Trillat"
"Ivana"
"Lucas Nobile"
"Arbitre 1"
"Arbitre 2"
)

# Import
for USER in "${USERS[@]}"; do
  create_user "$USER"
done

echo "🎉 Import terminé !"
