#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "== LibreTT Refactor Audit =="
echo "Root: $ROOT_DIR"
echo

echo "-- File counts --"
echo "Total tracked files:"
rg --files | wc -l | tr -d ' '
echo "PHP files:"
find . -name '*.php' -type f | wc -l | tr -d ' '
echo

echo "-- Largest PHP files (top 20) --"
find . -name '*.php' -type f -print0 | xargs -0 wc -l | sort -nr | head -n 20
echo

echo "-- Largest directories by PHP LOC (top 20) --"
find . -name '*.php' -type f -print0 \
  | xargs -0 wc -l \
  | awk 'NR>1 {print $1, $2}' \
  | awk '{
      path=$2;
      sub("^\\./","",path);
      split(path, parts, "/");
      dir=(parts[1] == "" ? "." : parts[1]);
      sum[dir] += $1;
    }
    END {
      for (d in sum) print sum[d], d;
    }' \
  | sort -nr | head -n 20
echo

echo "-- Monolith watch --"
if [ -f "includes/class-opentt-unified-core.php" ]; then
  wc -l "includes/class-opentt-unified-core.php"
fi
if [ -f "includes/modules/trait-opentt-unified-shortcodes.php" ]; then
  wc -l "includes/modules/trait-opentt-unified-shortcodes.php"
fi
if [ -f "src/WordPress/UserPortalManager.php" ]; then
  wc -l "src/WordPress/UserPortalManager.php"
fi
echo

echo "-- Namespace sanity sample --"
sed -n '1,20p' src/WordPress/Shortcodes/MatchesListShortcode.php | sed -n '1,6p'
echo

echo "Audit complete."

