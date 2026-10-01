#!/usr/bin/env bash
set -euo pipefail
cd ~/public_html
git fetch origin && git reset --hard origin/main
php install/migrate.php
touch ~/.lsphp_restart.txt
for u in /login /pre-cotacao /cliente/dashboard; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "https://guinchafacil.com.br$u")
  echo "$u -> $code"
  [[ "$code" =~ ^(200|302|401)$ ]] || { echo "FALHOU no endpoint $u"; exit 1; }
done
echo "DEPLOY OK $(git rev-parse --short HEAD)"