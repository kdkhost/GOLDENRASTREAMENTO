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
  # O `php` do PATH em hospedagem compartilhada é a versão do seletor da conta, que
  # costuma ser mais velha que a exigida aqui; por isso os nomes versionados vêm
  # primeiro e o escolhido precisa ter ao menos 8.3.
  for candidato in ea-php83 ea-php84 php8.3 /usr/local/bin/ea-php83 /usr/bin/ea-php83 \
                   /opt/cpanel/ea-php83/root/usr/bin/php /opt/alt/php83/usr/bin/php php; do
    if command -v "$candidato" >/dev/null 2>&1 && \
       "$(command -v "$candidato")" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' 2>/dev/null; then
      PHP_BIN="$(command -v "$candidato")"
      break
    fi
  done
fi
[ -n "$PHP_BIN" ] || { echo "PHP não encontrado (use --php=/caminho)."; exit 1; }

if ! "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' 2>/dev/null; then
  VERSAO_ESCOLHIDA="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
  echo "O PHP escolhido ($PHP_BIN) é $VERSAO_ESCOLHIDA e o sistema exige 8.3+."
  echo "Passe --php=/caminho/para/o/php8.3 ou mude a versão na hospedagem (seção 2 de docs/INSTALACAO.md)."
  exit 1
fi

# Composer sempre pelo mesmo PHP escolhido: o binário `composer` do servidor roda no
# `php` do PATH, que pode ser outro, e aí o install falha por causa da versão.
if [ -z "$COMPOSER_BIN" ]; then
  if [ -f "$HOME/bin/composer.phar" ]; then COMPOSER_BIN="$PHP_BIN $HOME/bin/composer.phar"
  elif [ -f composer.phar ]; then COMPOSER_BIN="$PHP_BIN $RAIZ/composer.phar"
  elif command -v composer >/dev/null 2>&1; then COMPOSER_BIN="$PHP_BIN $(command -v composer)"
  else echo "Composer não encontrado."; exit 1; fi
elif [ "${COMPOSER_BIN##*.}" = "phar" ]; then
  COMPOSER_BIN="$PHP_BIN $COMPOSER_BIN"
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
