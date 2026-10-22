# Golden Rastreamento — Site multipágina + Painel de Leads

Sistema web da **Golden Rastreamento** (rastreamento e gestão veicular): um site
institucional **multipágina** com o padrão visual aprovado (dourado + teal sobre fundo
escuro) e um **painel administrativo de leads** para acompanhar contatos e conversões das
campanhas de tráfego pago.

Construído em **Laravel 13** seguindo a mesma estrutura/padrão do repositório de referência
(`foco7`): AdminLTE 4 no painel, autenticação própria com perfis, migrations
*driver-agnostic*, CMS de conteúdo e captura de leads com atribuição de campanha.

---

## 1. Requisitos

- PHP 8.3+ (extensões `pdo_sqlite` e `pdo_mysql`)
- Composer 2
- Node 18+ (apenas para assets, opcional — o tema usa CSS/JS puro servido de `public/`)

## 2. Rodar local (SQLite / offline)

```bash
cd golden
composer install
cp .env.example .env
php artisan key:generate

# banco SQLite local
touch database/database.sqlite
php artisan migrate --seed

php artisan serve
```

Acesse:
- Site: <http://127.0.0.1:8000>
- Painel: <http://127.0.0.1:8000/login>

**Credenciais de demonstração**

| Perfil        | E-mail                   | Senha       |
|---------------|--------------------------|-------------|
| Administrador | `admin@golden.com.br`    | `golden123` |
| Vendedor      | `vendedor@golden.com.br` | `golden123` |

> O seeder cria 42 leads distribuídos em 3 campanhas e nos 5 status do funil, para o
> dashboard já nascer populado.

## 3. Subir para o servidor (MySQL)

No `.env` de produção:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seudominio.com.br

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=golden_sistema
DB_USERNAME=golden_user
DB_PASSWORD=***

GOLDEN_WHATSAPP=55DDDNÚMERO
GOLDEN_EMAIL=comercial@seudominio.com.br
GOLDEN_TRACKING_ENABLED=false
```

Depois:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force        # cria as tabelas no MySQL
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

As **mesmas migrations** rodam em SQLite e MySQL — não há código específico de driver.
Em produção o HTTPS é forçado automaticamente (`AppServiceProvider`).

---

## 4. O que foi entregue

### Site público (multipágina)
| Rota | Página |
|------|--------|
| `/` | Home (hero, benefícios, plataforma, segmentos, kits, como funciona, depoimentos, FAQ, contato) |
| `/solucoes` | Funcionalidades da plataforma |
| `/para-quem` | Segmentos atendidos |
| `/planos` | Kits e perguntas frequentes |
| `/como-funciona` | Passos + regra de instalação |
| `/faq` | Dúvidas frequentes |
| `/contato` | Formulário de lead + canais |
| `/privacidade` | Política de Privacidade (LGPD) |

Conteúdo de marketing (benefícios, segmentos, kits, passos, FAQ) fica centralizado em
`config/golden.php` — fácil de editar sem tocar nas views.

### Painel administrativo de leads (`/admin`) — prioridade do briefing
- **Dashboard**: KPIs (total, novos, taxa de conversão, receita fechada), funil visual,
  gráfico de leads nos últimos 30 dias, **conversão por campanha** e leads recentes.
- **Leads**: listagem com **filtros** (status, campanha, período, busca, responsável),
  ficha completa com **mover no funil** (novo → contatado → proposta → vendido / perdido),
  notas de atendimento, edição do cadastro, atribuição de campanha e **linha do tempo**.
- **Exportação CSV** respeitando os filtros ativos (com trilha de auditoria).
- **Campanhas**: cadastro manual + criação automática por UTM, com métricas por campanha.
- **Auditoria**: trilha de ações administrativas (login, mudança de funil, exportação,
  configuração…), apenas para admin.
- **Usuários & permissões**: perfis `admin`, `vendedor`, `visualizador` com middleware
  de papel (`role:`).
- **Configurações**: WhatsApp oficial, e-mail, e chaves de medição (GA4/Pixel/GTM) com
  *switch* para ativar a coleta.

### Captura de leads (`POST /leads`)
- Validação no servidor + **rate limit** (`throttle:8,1`) + **honeypot** anti-spam.
- Preserva **UTMs, `gclid`/`fbclid`**, página de origem e um **ID de visita** anônimo
  (cookie `golden_visitor`, 90 dias).
- IP **pseudonimizado** (hash) e registro de **consentimento LGPD**.
- Nunca expõe dados pessoais no front; o painel lê exclusivamente do backend.

### Medição
Eventos já instrumentados em `public/js/golden-site.js`: `page_view`, `view_plans`,
`select_plan`, `contact`, `whatsapp_click`, `generate_lead` (após sucesso HTTP) e
`lead_success`. Enquanto `tracking_enabled=false`, os eventos são apenas logados no console.

---

## 5. Estrutura principal

```
app/
  Http/Controllers/{Site,Auth,Admin}/   # páginas, captura de lead, login, painel
  Http/Middleware/ControleAcesso.php     # role:admin,vendedor,...
  Http/Middleware/IdentificadorVisita.php
  Models/ (Lead, Campaign, LeadStatusHistory, Activity, Setting, User)
  Services/LeadService.php               # atribuição + criação + funil
config/golden.php                        # conteúdo do site + contatos
database/migrations/                     # users, campaigns, leads, histories, activities, settings
resources/views/{layouts,site,auth,admin}
public/{css,js,img}                      # tema do site e do painel
```

## 6. Pendências para produção (do briefing)

- Substituir o logotipo conceitual (`public/img/logo-golden.png`) pelo **original em
  SVG/PNG transparente**.
- Trocar o *mock* da plataforma por **capturas reais** e fotos do rastreador J16.
- Confirmar **preços** de kits/mensalidade e o **número oficial de WhatsApp**
  (`GOLDEN_WHATSAPP` / painel de Configurações).
- Revisar a **Política de Privacidade** com o jurídico.
- Só então ligar **GTM/GA4/Ads/Meta Pixel** e habilitar `tracking_enabled`.
- Vincular vendas confirmadas ao CRM/financeiro para conversão real (integração futura).
