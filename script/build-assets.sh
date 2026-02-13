#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ASSETS_DIR="$ROOT_DIR/assets"

if ! command -v perl >/dev/null 2>&1; then
  echo "Errore: perl non trovato. Impossibile minificare gli asset."
  exit 1
fi

if [ ! -d "$ASSETS_DIR" ]; then
  echo "Errore: cartella assets non trovata: $ASSETS_DIR"
  exit 1
fi

shopt -s nullglob

css_files=("$ASSETS_DIR"/*.css)
js_files=("$ASSETS_DIR"/*.js)

for input in "${css_files[@]}"; do
  if [[ "$input" == *.min.css ]]; then
    continue
  fi

  output="${input%.css}.min.css"
  echo "Minifying CSS: $(basename "$input") -> $(basename "$output")"
  perl -0777 -pe '
    s:/\*.*?\*/::gs;
    s/[\r\n\t ]+/ /g;
    s/\s*([{}:;,])\s*/$1/g;
    s/;}/}/g;
    s/^\s+|\s+$//g;
  ' "$input" > "$output"
done

for input in "${js_files[@]}"; do
  if [[ "$input" == *.min.js ]]; then
    continue
  fi

  output="${input%.js}.min.js"
  echo "Minifying JS:  $(basename "$input") -> $(basename "$output")"
  perl -0777 -pe '
    s:/\*.*?\*/::gs;
    s://[^\n\r]*::g;
    s/[\r\n\t]+/ /g;
    s/\s{2,}/ /g;
    s/\s*([{}();,:])\s*/$1/g;
    s/;}/}/g;
    s/^\s+|\s+$//g;
  ' "$input" > "$output"
done

echo "Build completata."
