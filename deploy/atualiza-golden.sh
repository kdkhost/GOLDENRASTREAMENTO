#!/usr/bin/env bash
#
# Atualização do Golden Rastreamento num servidor já instalado:
# puxa o código do repositório, atualiza dependências, aplica migrations e
# reativa os caches. Use depois do primeiro deploy/instalador-golden.sh.
#
#   bash deploy/atualiza-golden.sh
#   bash deploy/atualiza-golden.sh --ramo=main --php=/usr/bin/ea-php83
#
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"

RAMO="${RAMO:-main}"
PHP_BIN="${PHP_BIN:-}"
COMPOSER_BIN="${COMPOSER_BIN:-}"
SEM_GIT=0

while [ $# -gt 0 ]; do
  case "$1" in
    --ramo=*) RAMO="${1#*=}"; shift ;;
    --php=*) PHP_BIN="${1#*=}"; shift ;;
    --composer=*) COMPOSER_BIN="${1#*=}"; shift ;;
    --sem-git) SEM_GIT=1; shift ;;
    --help|-h) echo "Uso: bash deploy/atualiza-golden.sh [--ramo=main] [--php=/caminho] [--composer=/caminho] [--sem-git]"; exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; exit 1 ;;
  esac
done

if [ -z "$PHP_BIN" ]; then
  for candidato in php ea-php83 ea-php84 php8.3 /opt/alt/php83/usr/bin/php; do
    if command -v "$candidato" >/dev/null 2>&1; then PHP_BIN="$(command -v "$candidato")"; break; fi
  done
fi
[ -n "$PHP_BIN" ] || { echo "PHP não encontrado (use --php=/caminho)."; exit 1; }

if [ -z "$COMPOSER_BIN" ]; then
  if command -v composer >/dev/null 2>&1; then COMPOSER_BIN="composer"
  elif [ -f "$HOME/bin/composer.phar" ]; then COMPOSER_BIN="$PHP_BIN $HOME/bin/composer.phar"
  else echo "Composer não encontrado."; exit 1; fi
fi

echo
echo "==> Atualizando o código (ramo $RAMO)"
if [ "$SEM_GIT" -eq 0 ] && [ -d .git ]; then
  git pull --ff-only origin "$RAMO"
else
  echo "    sem git: usando os arquivos já publicados nesta pasta"
fi

echo "==> Dependências"
$COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "==> Migrations"
"$PHP_BIN" artisan config:clear >/dev/null
"$PHP_BIN" artisan migrate --force

echo "==> Storage"
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true

echo "==> Caches"
"$PHP_BIN" artisan cache:clear >/dev/null
"$PHP_BIN" artisan config:cache >/dev/null
"$PHP_BIN" artisan route:cache >/dev/null
"$PHP_BIN" artisan view:cache >/dev/null

echo
echo "Atualização concluída:"
"$PHP_BIN" artisan about --only=environment 2>/dev/null || true
echo
