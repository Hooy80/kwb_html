#!/usr/bin/env bash
# Interactive local cleanup helper
# Lists development/test files and offers to delete them locally.

set -euo pipefail
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

echo "Project root: $ROOT_DIR"

# Patterns to consider for local cleanup (adjust as needed)
patterns=(
  "**/__tests__/**"
  "**/*.test.*"
  "**/*.spec.*"
  "**/test_*.js"
  "**/test_console.js"
  "**/*_build*.zip"
  "**/*.zip"
  "_notused"
  "raak_build*.zip"
)

candidates=()
while IFS= read -r -d $'\0' f; do
  candidates+=("$f")
done < <(find . -type f -print0)

# Filter candidates by patterns
to_delete=()
for f in "${candidates[@]}"; do
  for pat in "${patterns[@]}"; do
    if [[ "$f" == $pat ]]; then
      to_delete+=("$f")
    fi
  done
done

# Additional heuristics: explicit names
explicit=("test_console.js" "raak_build.zip" "raak_build_new.zip" "raak_build_final.zip")
for name in "${explicit[@]}"; do
  if [ -f "$name" ]; then
    to_delete+=("$name")
  fi
done

# Unique and show
if [ ${#to_delete[@]} -eq 0 ]; then
  echo "No obvious local cleanup candidates found."
  exit 0
fi

printf "Found %d local candidate(s) for cleanup:\n" ${#to_delete[@]}
printf "%s\n" "${to_delete[@]}"

read -p "Delete these files locally? (type 'DELETE' to confirm) " ans
if [ "$ans" = "DELETE" ]; then
  for f in "${to_delete[@]}"; do
    echo "Removing: $f"
    rm -f -- "$f" || true
  done
  echo "Deleted ${#to_delete[@]} files."
else
  echo "Aborting. No files deleted."
fi
