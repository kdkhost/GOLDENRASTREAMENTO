#!/usr/bin/env bash
#
# Instalador do Golden Rastreamento (Laravel) — sirve para qualquer domínio ou
# servidor (cPanel, VPS, container) que tenha PHP >= 8.3.
#
#   ./deploy/instalador-golden.sh --url=https://seudominio.com.br
#   ./deploy/instalador-golden.sh --help
#
# Nenhuma credencial fica guardada neste arquivo: o instalador pergunta na tela
# ou lê das variáveis GOLDEN_DB_PASSWORD / GOLDEN_ADMIN_PASSWORD e grava só no
# .env, que está fora do git.
#
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"

VERSAO="2.25.4"

URL=""
PHP_BIN="${PHP_BIN:-}"
COMPOSER_BIN="${COMPOSER_BIN:-}"
BANCO="${BANCO:-mysql}"
HOST_BANCO="${DB_HOST:-127.0.0.1}"
PORTA_BANCO="${DB_PORT:-3306}"
NOME_BANCO="${DB_DATABASE:-}"
USUARIO_BANCO="${DB_USERNAME:-}"
SENHA_BANCO="${GOLDEN_DB_PASSWORD:-}"
CRIAR_BANCO=0
EMAIL_ADMIN="${GOLDEN_ADMIN_EMAIL:-admin@golden.com.br}"
SENHA_ADMIN="${GOLDEN_ADMIN_PASSWORD:-}"
NOME_ADMIN="Administrador Golden"
COM_DEMO=1
LIMPAR_BANCO=""
AMBIENTE=production
DEBUG=false
HTACCESS=1
# Arquivos que já estão na raiz do domínio e precisam continuar acessíveis pela
# mesma URL (política de privacidade citada num app, por exemplo). Repetível.
ESTATICOS=()
SEM_CACHE=0
BACKUP=1
PHP_WEB="${GOLDEN_PHP_WEB:-}"

if [ -t 1 ]; then
  C_INICIO=$'\033[1m'; C_OK=$'\033[32m'; C_AVISO=$'\033[33m'; C_ERRO=$'\033[31m'; C_FIM=$'\033[0m'
else
  C_INICIO=''; C_OK=''; C_AVISO=''; C_ERRO=''; C_FIM=''
fi

passo() { printf '\n%s==>%s %s\n' "$C_INICIO" "$C_FIM" "$*"; }
ok()    { printf '     %s✔%s %s\n' "$C_OK" "$C_FIM" "$*"; }
aviso() { printf '     %s!%s %s\n' "$C_AVISO" "$C_FIM" "$*"; }
erro()  { printf '\n%sERRO:%s %s\n\n' "$C_ERRO" "$C_FIM" "$*" >&2; exit 1; }

# Todo o texto dos comandos vai para um log; a tela mostra só o resultado. Se um
# comando falhar, a última parte do log aparece na hora — sem precisar caçar.
LOG_INSTALACAO="storage/logs/instalador-$(date +%Y%m%d-%H%M%S).log"
rodar() {
  mkdir -p "$(dirname "$LOG_INSTALACAO")"
  printf '\n$ %s\n' "$*" >> "$LOG_INSTALACAO"
  if ! "$@" >> "$LOG_INSTALACAO" 2>&1; then
    printf '\n%sERRO no comando:%s %s\n' "$C_ERRO" "$C_FIM" "$*" >&2
    printf '     Últimas linhas de %s:\n' "$LOG_INSTALACAO" >&2
    tail -25 "$LOG_INSTALACAO" >&2
    exit 1
  fi
}

ajuda() {
  cat <<'TEXTO'
Instalador do Golden Rastreamento

  bash deploy/instalador-golden.sh --url=URL [opções]

Endereço
  --url=URL                 endereço público do site, com https:// (obrigatório).
  --banco=mysql|sqlite      motor do banco (padrão: mysql).

Banco
  --host=H --porta=P        padrão 127.0.0.1:3306.
  --nome=N                  nome do banco (padrão: golden; no sqlite, o arquivo).
  --usuario=U               usuário do MySQL.
  --senha=S                 senha do MySQL. Prefira a variável de ambiente
                            GOLDEN_DB_PASSWORD, que não fica no histórico do shell;
                            sem ela o instalador pede na tela, sem mostrar.
  --criar-banco             cria o banco se não existir (precisa de privilégio
                            CREATE no usuário informado).

Painel
  --admin-email=E           login do administrador (padrão: admin@golden.com.br).
  --admin-nome=N            nome exibido no painel.
  --admin-senha=S           senha do login; prefira GOLDEN_ADMIN_PASSWORD. Sem
                            ela, o instalador pede duas vezes na tela.

Comportamento
  --sem-demo                não grava os dados de demonstração (leads, pedidos,
                            páginas e catálogo de exemplo).
  --limpar-banco            apaga TODAS as tabelas do banco antes de criar as
                            tabelas do Golden (migrate:fresh). Sem a flag, o
                            instalador pergunta antes de mexer em qualquer dado.
  --dev                     APP_ENV=local e APP_DEBUG=true (só para testes).
  --sem-htaccess            não escreve o .htaccess de proteção na raiz.
  --estatico=ARQUIVO        deixa um arquivo que já está na raiz do domínio
                            acessível pela mesma URL, sem passar pelo Laravel.
                            Use ao substituir um site antigo cuja URL ainda é
                            citada em algum lugar (política de privacidade de um
                            app, por exemplo). Repita a opção para cada arquivo;
                            o arquivo precisa existir na raiz.
  --sem-cache               não gera cache de config/rotas/views.
  --sem-backup              não tirar cópia do banco antes de limpar as tabelas.
  --php-web=ea-php83        grava no .htaccess a versão de PHP que serve a web.
                            Em hospedagem CloudLinux/LiteSpeed o seletor da conta
                            costuma ficar atrás do PHP exigido pelo Laravel; esta
                            linha escolhe o handler .php do servidor. Use o nome
                            exato da versão instalada (ea-php83, ea-php84...).
  --php=/caminho            força o binário do PHP (ex.: /usr/bin/ea-php83).
  --composer=/caminho       força o binário do Composer.
  --help                    esta tela.

Exemplos
  # cPanel reaproveitando um banco que já existe no plano
  GOLDEN_DB_PASSWORD='...' GOLDEN_ADMIN_PASSWORD='...' \
    bash deploy/instalador-golden.sh \
      --url=https://seudominio.com.br \
      --php=/usr/bin/ea-php83 --php-web=ea-php83 \
      --nome=usuario_do_banco --usuario=usuario_do_banco --limpar-banco

  # VPS limpa, criando o banco
  bash deploy/instalador-golden.sh --url=https://painel.exemplo.com.br \
      --nome=golden --usuario=golden --criar-banco

  # Teste local, sem MySQL
  bash deploy/instalador-golden.sh --url=http://localhost:8000 --banco=sqlite --dev
TEXTO
}

while [ $# -gt 0 ]; do
  case "$1" in
    --url) URL="${2:-}"; shift 2 ;;
    --url=*) URL="${1#*=}"; shift ;;
    --banco) BANCO="${2:-}"; shift 2 ;;
    --banco=*) BANCO="${1#*=}"; shift ;;
    --host) HOST_BANCO="${2:-}"; shift 2 ;;
    --host=*) HOST_BANCO="${1#*=}"; shift ;;
    --porta) PORTA_BANCO="${2:-}"; shift 2 ;;
    --porta=*) PORTA_BANCO="${1#*=}"; shift ;;
    --nome) NOME_BANCO="${2:-}"; shift 2 ;;
    --nome=*) NOME_BANCO="${1#*=}"; shift ;;
    --usuario) USUARIO_BANCO="${2:-}"; shift 2 ;;
    --usuario=*) USUARIO_BANCO="${1#*=}"; shift ;;
    --senha) SENHA_BANCO="${2:-}"; shift 2 ;;
    --senha=*) SENHA_BANCO="${1#*=}"; shift ;;
    --criar-banco) CRIAR_BANCO=1; shift ;;
    --admin-email) EMAIL_ADMIN="${2:-}"; shift 2 ;;
    --admin-email=*) EMAIL_ADMIN="${1#*=}"; shift ;;
    --admin-nome) NOME_ADMIN="${2:-}"; shift 2 ;;
    --admin-nome=*) NOME_ADMIN="${1#*=}"; shift ;;
    --admin-senha) SENHA_ADMIN="${2:-}"; shift 2 ;;
    --admin-senha=*) SENHA_ADMIN="${1#*=}"; shift ;;
    --sem-demo) COM_DEMO=0; shift ;;
    --limpar-banco) LIMPAR_BANCO=1; shift ;;
    --dev) AMBIENTE=local; DEBUG=true; shift ;;
    --sem-htaccess) HTACCESS=0; shift ;;
    --estatico) ESTATICOS+=("${2:-}"); shift 2 ;;
    --estatico=*) ESTATICOS+=("${1#*=}"); shift ;;
    --sem-cache) SEM_CACHE=1; shift ;;
    --sem-backup) BACKUP=0; shift ;;
    --php=*) PHP_BIN="${1#*=}"; shift ;;
    --php-web=*) PHP_WEB="${1#*=}"; shift ;;
    --composer=*) COMPOSER_BIN="${1#*=}"; shift ;;
    --help|-h) ajuda; exit 0 ;;
    *) erro "Opção desconhecida: $1 (veja --help)" ;;
  esac
done

printf '\n%sGolden Rastreamento — instalador v%s%s\n' "$C_INICIO" "$VERSAO" "$C_FIM"
printf 'Pasta da aplicação: %s\n' "$RAIZ"

[ -f artisan ] || erro "Este diretório não tem o artisan do Laravel. Rode o instalador a partir da raiz do projeto."
[ -n "$URL" ] || erro "Falta --url=https://seu-dominio (o endereço público do site)."
URL="${URL%/}"

case "$BANCO" in
  mysql|sqlite) ;;
  *) erro "Banco '$BANCO' não suportado. Use mysql ou sqlite." ;;
esac

# O nome do --estatico vira regex dentro do .htaccess: só letra, número, ponto,
# travessão e barra de pastas — e o arquivo precisa existir na raiz, senão a
# regra ficaria apontando para o nada.
for estatico in ${ESTATICOS[@]+"${ESTATICOS[@]}"}; do
  printf '%s' "$estatico" | grep -Eq '^[A-Za-z0-9][A-Za-z0-9._/-]*$' \
    || erro "--estatico inválido ('$estatico'). Informe o caminho a partir da raiz, ex.: privacidade.html."
  [ -f "$estatico" ] || erro "--estatico: não há arquivo '$estatico' na raiz do projeto."
done

# ---------------------------------------------------------------------------
# 1. Requisitos
# ---------------------------------------------------------------------------
passo "Checando os requisitos"

if [ -z "$PHP_BIN" ]; then
  for candidato in php ea-php83 ea-php84 php8.3 php8.4 /opt/alt/php83/usr/bin/php /usr/bin/php8.3; do
    if command -v "$candidato" >/dev/null 2>&1; then PHP_BIN="$(command -v "$candidato")"; break; fi
  done
fi
[ -n "$PHP_BIN" ] || erro "PHP não encontrado. Instale o PHP 8.3 ou superior; se ele estiver fora do PATH, passe --php=/caminho/do/php."

VERSAO_PHP="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
  || erro "PHP $VERSAO_PHP encontrado, mas o sistema exige 8.3 ou superior. Escolha a versão no seletor de PHP da hospedagem (no cPanel: MultiPHP Manager / PHP Selector) e rode de novo; se o servidor tiver mais de um binário, passe --php=/usr/bin/ea-php83. Para o PHP que serve a WEB, use --php-web=ea-php83."
ok "PHP $VERSAO_PHP ($PHP_BIN)"

if [ -n "$PHP_WEB" ]; then
  printf '%s' "$PHP_WEB" | grep -Eq '^[A-Za-z0-9._-]+$' \
    || erro "--php-web inválido ('$PHP_WEB'). Use só o nome do handler, ex.: ea-php83."
fi

FALTANDO=""
for ext in pdo mbstring openssl tokenizer xml fileinfo curl json; do
  "$PHP_BIN" -r "exit(extension_loaded('$ext') ? 0 : 1);" || FALTANDO="$FALTANDO $ext"
done
[ -z "$FALTANDO" ] || erro "Faltam extensões do PHP:$FALTANDO"

OPCIONAIS=""
for ext in gd intl zip exif; do
  "$PHP_BIN" -r "exit(extension_loaded('$ext') ? 0 : 1);" || OPCIONAIS="$OPCIONAIS $ext"
done
if [ -n "$OPCIONAIS" ]; then
  aviso "Sem as extensões$OPCIONAIS — envio de imagens/vídeos e alguns pacotes podem falhar."
else
  ok "Extensões do PHP completas"
fi

if [ -z "$COMPOSER_BIN" ]; then
  if command -v composer >/dev/null 2>&1; then
    COMPOSER_BIN="composer"
  elif [ -f "$HOME/bin/composer.phar" ]; then
    COMPOSER_BIN="$PHP_BIN $HOME/bin/composer.phar"
  elif [ -f composer.phar ]; then
    COMPOSER_BIN="$PHP_BIN composer.phar"
  else
    passo "Composer não encontrado — baixando o executável oficial"
    mkdir -p "$HOME/bin"
    # O download vai pelo curl quando ele existe: em conta de hospedagem com
    # allow_url_fopen desligado (padrão no EasyApache) o copy() do PHP falha em
    # silêncio e o instalador continuaria apontando para um phar que não está lá.
    if command -v curl >/dev/null 2>&1; then
      rodar curl -sSfL https://getcomposer.org/composer-stable.phar -o "$HOME/bin/composer.phar"
    else
      rodar "$PHP_BIN" -r "copy('https://getcomposer.org/composer-stable.phar', getenv('HOME').'/bin/composer.phar');"
    fi
    COMPOSER_BIN="$PHP_BIN $HOME/bin/composer.phar"
  fi
fi
ok "Composer: $COMPOSER_BIN"

# ---------------------------------------------------------------------------
# 2. O que falta perguntar
# ---------------------------------------------------------------------------
if [ "$BANCO" = "mysql" ]; then
  NOME_BANCO="${NOME_BANCO:-golden}"
  if [ -z "$USUARIO_BANCO" ]; then
    read -r -p "     Usuário do MySQL: " USUARIO_BANCO
    USUARIO_BANCO="${USUARIO_BANCO:-$NOME_BANCO}"
  fi
  if [ -z "$SENHA_BANCO" ]; then
    [ -t 0 ] || erro "Falta a senha do banco: use GOLDEN_DB_PASSWORD='...' ou rode num terminal interativo."
    read -r -s -p "     Senha do MySQL (não aparece na tela): " SENHA_BANCO; echo
  fi
else
  NOME_BANCO="${NOME_BANCO:-database/database.sqlite}"
  touch "$NOME_BANCO"
fi

# ---------------------------------------------------------------------------
# 3. .env
# ---------------------------------------------------------------------------
passo "Gerando o arquivo .env"
[ -f .env.example ] || erro "Falta .env.example no repositório."
if [ -f .env ]; then
  ARQUIVO_ENV_ANTERIOR=".env.bak-$(date +%Y%m%d-%H%M%S)"
  cp .env "$ARQUIVO_ENV_ANTERIOR"
  aviso "O .env anterior foi preservado em $ARQUIVO_ENV_ANTERIOR"
fi
cp .env.example .env

# define CHAVE VALOR — troca a linha no .env (mesmo comentada) sem quebrar os
# comentários do arquivo; valor com espaço, # ou | ganha aspas.
define() {
  ENV_CHAVE="$1" ENV_VALOR="$2" "$PHP_BIN" -r '
    $chave = getenv("ENV_CHAVE");
    $valor = getenv("ENV_VALOR");
    if (preg_match("/[\\s#|\"\\x27]/", $valor) && ! preg_match("/^\".*\"$/", $valor)) {
      $valor = "\"".str_replace("\"", "\\\"", $valor)."\"";
    }
    $arquivo = getcwd()."/.env";
    $linhas = file($arquivo);
    $achou = false;
    foreach ($linhas as $i => $linha) {
        if (preg_match("/^[#\\s]*".preg_quote($chave, "/")."=/", $linha)) {
            $linhas[$i] = $chave."=".$valor."\n";
            $achou = true;
            break;
        }
    }
    if (! $achou) { $linhas[] = $chave."=".$valor."\n"; }
    file_put_contents($arquivo, implode("", $linhas));
  '
}

define APP_NAME 'Golden Rastreamento'
define APP_ENV "$AMBIENTE"
define APP_DEBUG "$DEBUG"
define APP_URL "$URL"
define APP_TIMEZONE America/Sao_Paulo
define APP_LOCALE pt_BR
define APP_FALLBACK_LOCALE pt_BR
define DB_CONNECTION "$BANCO"

if [ "$BANCO" = "mysql" ]; then
  define DB_HOST "$HOST_BANCO"
  define DB_PORT "$PORTA_BANCO"
  define DB_DATABASE "$NOME_BANCO"
  define DB_USERNAME "$USUARIO_BANCO"
  define DB_PASSWORD "$SENHA_BANCO"
else
  define DB_DATABASE "$NOME_BANCO"
fi

define SESSION_DRIVER file
define CACHE_STORE file
define QUEUE_CONNECTION database
define FILESYSTEM_DISK public
define LOG_LEVEL "$([ "$AMBIENTE" = production ] && echo warning || echo debug)"

# O cookie de sessão é a chave da porta: se ele viaja em texto claro, quem escuta a
# rede entra sem senha nenhuma. Por isso ele só é marcado como seguro quando o próprio
# endereço do site é HTTPS — num bench local em http:// o painel continuaria abrindo.
case "$URL" in
  https://*)
    define SESSION_SECURE_COOKIE true
    ok "Cookie de sessão marcado como seguro (endereço HTTPS)"
    ;;
  *)
    aviso "SESSION_SECURE_COOKIE fica desligado: $URL não é HTTPS. Num servidor de verdade, o cookie viaja solto."
    ;;
esac
define SESSION_HTTP_ONLY true
define SESSION_SAME_SITE lax

chmod 600 .env
ok ".env escrito com permissão 600 (banco: $BANCO, ambiente: $AMBIENTE)"

# As dependências vêm antes da chave: sem o vendor/ o artisan nem começa, e num
# clone limpo do repositório (o caso de um servidor onde o sistema nunca esteve)
# o instalador pararia na primeira linha do passo seguinte.
passo "Instalando as dependências (sem as de desenvolvimento)"
if [ -f vendor/autoload.php ]; then
  aviso "O vendor/ já existe — pulando o composer install (é o que o deploy usa no dia a dia)."
else
  rodar $COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction
  ok "Dependências instaladas"
fi

passo "Gerando a chave da aplicação"
rodar "$PHP_BIN" artisan key:generate --force --quiet
ok "APP_KEY definida"

# ---------------------------------------------------------------------------
# 5. Banco
# ---------------------------------------------------------------------------
passo "Preparando o banco de dados"

if [ "$BANCO" = "mysql" ] && [ "$CRIAR_BANCO" -eq 1 ]; then
  if command -v mysqladmin >/dev/null 2>&1; then
    if mysqladmin --host="$HOST_BANCO" --port="$PORTA_BANCO" --user="$USUARIO_BANCO" --password="$SENHA_BANCO" create "$NOME_BANCO" >/dev/null 2>&1; then
      ok "Banco '$NOME_BANCO' criado"
    else
      aviso "O usuário informado não tem privilégio para criar '$NOME_BANCO'. Crie-o no cPanel (MySQL Databases), ligando este mesmo usuário, e rode o instalador de novo."
    fi
  else
    aviso "Sem o mysqladmin no PATH: crie o banco pelo painel antes de continuar."
  fi
fi

if [ "$BANCO" = "mysql" ]; then
  TABELAS=$(DBH="$HOST_BANCO" DBP="$PORTA_BANCO" DBN="$NOME_BANCO" DBU="$USUARIO_BANCO" DBS="$SENHA_BANCO" \
    "$PHP_BIN" -r '
      try {
        $pdo = new PDO(sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4", getenv("DBH"), getenv("DBP"), getenv("DBN")), getenv("DBU"), getenv("DBS"));
        echo count($pdo->query("show tables")->fetchAll(PDO::FETCH_NUM));
      } catch (Throwable $e) {
        echo "FALHA:".$e->getMessage();
      }
    ' || echo "FALHA: conexão recusada")
  case "$TABELAS" in
    FALHA*) erro "Não consegui falar com o MySQL em $HOST_BANCO:$PORTA_BANCO como '$USUARIO_BANCO'. Confira nome do banco, usuário e senha. Detalhe: ${TABELAS#FALHA:}" ;;
  esac
  ok "$TABELAS tabela(s) encontradas em '$NOME_BANCO'"
else
  TABELAS=0
fi

if [ "${TABELAS:-0}" -gt 0 ]; then
  if [ -z "$LIMPAR_BANCO" ]; then
    if [ -t 0 ]; then
      echo "     O banco já tem tabelas que não são do Golden."
      read -r -p "     Apagar tudo e recriar as tabelas do Golden? (digite SIM): " RESPOSTA
      [ "${RESPOSTA:-}" = "SIM" ] || erro "Nada foi apagado. Rode de novo com --limpar-banco quando tiver certeza."
      LIMPAR_BANCO=1
    else
      erro "O banco não está vazio e a limpeza precisa de confirmação. Rode de novo com --limpar-banco."
    fi
  fi
  # Cópia de segurança antes de apagar: vai para a pasta ACIMA do projeto, que
  # não é servida pelo navegador, e fica legível só para o dono da conta.
  if [ "$BANCO" = mysql ] && [ "${BACKUP:-1}" -eq 1 ] && command -v mysqldump >/dev/null 2>&1; then
    ARQUIVO_BACKUP="$(dirname "$RAIZ")/backup-banco-${NOME_BANCO}-$(date +%Y%m%d-%H%M).sql.gz"
    if MYSQL_PWD="$SENHA_BANCO" mysqldump --single-transaction --skip-lock-tables \
        --host="$HOST_BANCO" --port="$PORTA_BANCO" --user="$USUARIO_BANCO" "$NOME_BANCO" 2>>"$LOG_INSTALACAO" | gzip > "$ARQUIVO_BACKUP"; then
      chmod 600 "$ARQUIVO_BACKUP"
      ok "Cópia de segurança do banco em $ARQUIVO_BACKUP"
    else
      rm -f "$ARQUIVO_BACKUP"
      aviso "Não foi possível copiar o banco (mysqldump indisponível ou sem privilégio)."
    fi
  fi
  rodar "$PHP_BIN" artisan migrate:fresh --force
  ok "Tabelas antigas removidas e recriadas pelo Golden"
fi

if [ "$COM_DEMO" -eq 1 ]; then
  rodar "$PHP_BIN" artisan migrate --force --seed
  ok "Migrations e dados de demonstração aplicados"
else
  rodar "$PHP_BIN" artisan migrate --force
  ok "Migrations aplicadas (sem dados de demonstração)"
fi

# O treinamento da assistente não é dado de demonstração: é o que ela sabe da empresa, e sem ele
# toda conversa da bolha escalaria por falta de fonte. O comando é idempotente — acrescenta o que
# falta e regrava só o catálogo com os preços vigentes; o texto editado no painel fica como está,
# e a personalidade já nasce com a ficha (por isso `--personalidade` não roda aqui).
passo "Treinando o assistente virtual"
# Não é `rodar`: ali a falha derruba a instalação, e assistente sem base é tela que se resolve
# depois no painel — não motivo para deixar o site sem instalar.
printf '\n$ %s artisan assistente:ensinar\n' "$PHP_BIN" >> "$LOG_INSTALACAO"
if "$PHP_BIN" artisan assistente:ensinar >> "$LOG_INSTALACAO" 2>&1; then
  ok "Base de conhecimento e catálogo aplicados ao assistente"
else
  aviso "O treinamento não rodou; ensine a base em Atendimento → O que o assistente sabe."
fi

# ---------------------------------------------------------------------------
# 6. Administrador do painel
# ---------------------------------------------------------------------------
passo "Configurando o acesso ao painel"
if [ -z "$SENHA_ADMIN" ]; then
  if [ -t 0 ]; then
    read -r -s -p "     Senha do login '$EMAIL_ADMIN' (não aparece na tela): " SENHA_ADMIN; echo
    read -r -s -p "     Repita a senha: " CONFIRMA; echo
    [ "$SENHA_ADMIN" = "$CONFIRMA" ] || erro "As senhas não conferem."
  else
    aviso "Sem senha informada: mantendo a senha padrão do seed. Troque-a no painel antes de divulgar o site."
  fi
fi

if [ -n "$SENHA_ADMIN" ]; then
  rodar env ADMIN_EMAIL="$EMAIL_ADMIN" ADMIN_SENHA="$SENHA_ADMIN" ADMIN_NOME="$NOME_ADMIN" "$PHP_BIN" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $u = App\Models\User::where("email", getenv("ADMIN_EMAIL"))->orderBy("id")->first() ?? new App\Models\User();
    $u->name = getenv("ADMIN_NOME") ?: "Administrador Golden";
    $u->email = getenv("ADMIN_EMAIL");
    $u->password = Illuminate\Support\Facades\Hash::make(getenv("ADMIN_SENHA"));
    $u->role = "admin";
    $u->status = "ativo";
    $u->email_verified_at = now();
    $u->save();
  '
  ok "Administrador '$EMAIL_ADMIN' ativo"
fi

# ---------------------------------------------------------------------------
# 7. Storage, .htaccess e caches
# ---------------------------------------------------------------------------
passo "Disponibilizando public/storage"
rodar "$PHP_BIN" artisan storage:link || [ -e public/storage ] || ln -s ../storage public/storage
ok "public/storage pronto"

# O que o painel recebe por upload sai por public/storage, que é um link simbólico para
# storage/app/public. A folha que manda naquele diretório vem no repositório; ela só é
# reescrita aqui quando está faltando — uma instalação feita sem git (cópia manual,
# restauração de backup) não pode deixar aberto justamente o buraco do único diretório
# que o público alcança.
if [ ! -f storage/app/public/.htaccess ]; then
  cat > storage/app/public/.htaccess <<'EXECUCAO'
# Nada nesta pasta é programa: é arquivo que alguém subiu pelo painel.
Options -Indexes -ExecCGI
RemoveHandler .php .php3 .php4 .php5 .php7 .php8 .phtml .phar .pl .py .cgi .rb
RemoveType .php .phtml .phar
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<FilesMatch "\.(php|php3|php4|php5|php7|php8|phtml|phar|pl|py|cgi|rb|htaccess)$">
    Require all denied
</FilesMatch>
EXECUCAO
  ok "storage/app/public/.htaccess escrito (arquivo não estava na instalação)"
else
  ok "storage/app/public/.htaccess presente: arquivo subido não vira programa"
fi

if [ "$HTACCESS" -eq 1 ]; then
  passo "Escrevendo o .htaccess de proteção da raiz"
  # Vale quando o docroot do domínio é a própria pasta do projeto (cPanel:
  # public_html). Num VPS com docroot em public/, este arquivo é ignorado.
  cat > .htaccess <<'PROTECAO'
# Gerado pelo instalador do Golden Rastreamento.
# Serve para o caso de o docroot apontar para a raiz do projeto (public_html no
# cPanel): todo pedido entra pela pasta public e nenhum arquivo de código ou de
# segredo fica listável/baixável.

Options -Indexes -MultiViews

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /

  # Arquivos de projeto que jamais devem ser servidos
  RewriteRule ^(\.env|\.env\.*|\.git|\.gitattributes|\.npmrc|artisan|composer\.(json|lock)|phpunit\.xml|package\.json|vite\.config\.js) - [F,L]
  RewriteRule (^|/)(error_log|.*\.log)$ - [F,L]

  # Pastas de código-fonte. O storage não entra aqui: o que o público alcança é o
  # link public/storage, e todo pedido já é reescrito para dentro de public/.
  RewriteRule ^(app|bootstrap|config|database|resources|routes|tests)(/|$) - [F,L]
PROTECAO

  if [ ${#ESTATICOS[@]} -gt 0 ]; then
    {
      printf '\n  # Arquivos que o domínio já tinha e precisam continuar na mesma URL.\n'
      printf '  # Vêm antes da reescrita para public/ e são servidos como estão.\n'
      for estatico in "${ESTATICOS[@]}"; do
        printf '  RewriteRule ^%s$ - [L]\n' "${estatico//./\\.}"
      done
    } >> .htaccess
    ok ".htaccess: $(IFS=,; echo "${ESTATICOS[*]}") mantidos na URL de origem"
  fi

  cat >> .htaccess <<'ENTRADA'

  # Toda requisição entra pela pasta public, que tem o front controller
  RewriteCond %{REQUEST_URI} !^/public/
  RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
ENTRADA
  ok ".htaccess: -Indexes, bloqueio de .env/.git/logs e reescrita para public/"

  if [ -n "$PHP_WEB" ]; then
    cat >> .htaccess <<PHPDAWEB

# Versão do PHP que serve este site. Em hospedagem com CloudLinux/LiteSpeed o
# seletor da conta decide a versão e costuma ficar atrás do PHP que o Laravel
# exige; esta linha escolhe o handler dos arquivos .php diretamente.
AddType application/x-httpd-$PHP_WEB .php
PHPDAWEB
    ok ".htaccess: PHP da web fixado em $PHP_WEB"
  fi
fi

if [ "$SEM_CACHE" -eq 0 ]; then
  passo "Otimizando para produção"
  rodar "$PHP_BIN" artisan config:clear
  rodar "$PHP_BIN" artisan migrate --force
  rodar "$PHP_BIN" artisan config:cache
  rodar "$PHP_BIN" artisan route:cache
  rodar "$PHP_BIN" artisan view:cache
  ok "Cache de configuração, rotas e views gerado"
fi

# ---------------------------------------------------------------------------
# Conferência: o PHP que serve a web pode não ser o mesmo do comando php
# ---------------------------------------------------------------------------
if command -v curl >/dev/null 2>&1; then
  passo "Conferindo o site pelo endereço público"
  ARQUIVO_CHECAGEM="$RAIZ/storage/logs/checagem-http.$$.html"
  CODIGO="$(curl -ksL -m 25 -o "$ARQUIVO_CHECAGEM" -w '%{http_code}' "$URL/" 2>/dev/null || echo 000)"
  if [ "$CODIGO" = "200" ]; then
    ok "$URL respondeu 200"
  elif grep -qi "detected issues in your platform" "$ARQUIVO_CHECAGEM" 2>/dev/null; then
    aviso "$URL respondeu $CODIGO: o PHP da WEB é mais antigo que o do comando php ($VERSAO_PHP)."
    aviso "Se a hospedagem usa CloudLinux/LiteSpeed, instale a linha do PHP da web:"
    aviso "  --php-web=ea-php83   (ou acrescente ao .htaccess: AddType application/x-httpd-ea-php83 .php)"
    aviso "Se não resolver, mude a versão no seletor de PHP do painel da hospedagem."
  else
    aviso "$URL respondeu $CODIGO. Veja as últimas linhas do log do Laravel em storage/logs/laravel.log."
  fi
  rm -f "$ARQUIVO_CHECAGEM"
fi

# ---------------------------------------------------------------------------
# Resumo
# ---------------------------------------------------------------------------
printf '\n%sInstalação concluída%s\n' "$C_INICIO" "$C_FIM"
cat <<RESUMO

  Site ......... $URL
  Painel ....... $URL/admin   (login: $EMAIL_ADMIN)
  Pasta ........ $RAIZ
  Banco ........ $([ "$BANCO" = mysql ] && echo "mysql $HOST_BANCO:$PORTA_BANCO/$NOME_BANCO como $USUARIO_BANCO" || echo "sqlite $NOME_BANCO")
  Ambiente ..... $AMBIENTE, depuração $DEBUG
  PHP .......... $VERSAO_PHP
  Log .......... $LOG_INSTALACAO   (texto completo dos comandos rodados)

Depois de instalar
  1. Abra $URL e confira a home, /loja, uma página de produto e o painel /admin.
  2. No painel, ajuste Configurações: WhatsApp, telefone, e-mail e endereço.
  3. Para os e-mails saírem de verdade, preencha o SMTP no .env (MAIL_MAILER=smtp).
     O código de e-mail já existe no projeto — falta só a credencial do servidor.
  4. Se quiser e-mails e tarefas em fila: php artisan queue:work (com supervisor).
  5. Para atualizar o código no futuro: bash deploy/atualiza-golden.sh

RESUMO
