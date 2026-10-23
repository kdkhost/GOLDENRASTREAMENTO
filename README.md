# Golden Rastreamento — CRM + CMS + ERP integrados

Sistema web da **Golden Rastreamento** (rastreamento veicular e gestão de frotas): um site
institucional **multipágina** com o padrão visual aprovado (dourado + teal sobre fundo
escuro), um **painel administrativo de leads** para acompanhar contatos e conversões das
campanhas de tráfego pago, e **CRM, CMS e ERP funcionando no mesmo sistema** — inclusive
uma **loja virtual de rastreadores e chips M2M** que pode ser **desativada a qualquer
momento pelo painel**.

Construído em **Laravel 13** seguindo a estrutura e os padrões do repositório de referência
(`foco7`): AdminLTE 4 no painel, autenticação própria com perfis, migrations
*driver-agnostic*, CMS de conteúdo e captura de leads com atribuição de campanha. A
*aparência* do painel ainda é o tema padrão do AdminLTE com ajustes dourados — portar o
`admin-foco7.css` e o layout completo do modelo está pendente.

| | |
|---|---|
| **Pacote** | `kdkhost/golden-rastreamento` |
| **Sistema** | Golden Rastreamento |
| **Desenvolvimento** | Marcelo (KDKHOST) — <https://kdkhost.com.br> · contato@kdkhost.com.br |

---

## 1. Requisitos

- PHP 8.3+ (extensões `pdo_sqlite` e `pdo_mysql`)
- Composer 2
- Node 18+ (apenas para assets, opcional — o tema usa CSS/JS puro servido de `public/`)

## 2. Rodar local (SQLite / offline)

A **raiz deste repositório é o próprio projeto** Laravel — não existe subpasta do sistema.

```bash
composer install
cp .env.example .env
php artisan key:generate

# banco SQLite local
touch database/database.sqlite
php artisan migrate:fresh --seed

# imagens de produtos (loja/ERP)
php artisan storage:link

php artisan serve
```

Acesse:
- Site: <http://127.0.0.1:8000>
- Loja virtual: <http://127.0.0.1:8000/loja>
- Painel: <http://127.0.0.1:8000/login>

**Credenciais de demonstração**

| Perfil        | E-mail                   | Senha       |
|---------------|--------------------------|-------------|
| Administrador | `admin@golden.com.br`    | `golden123` |
| Vendedor      | `vendedor@golden.com.br` | `golden123` |

> O `DatabaseSeeder` cria 42 leads em 3 campanhas e nos 5 status do funil e, em seguida,
> roda os seeders de módulo: **CatalogSeeder** (4 categorias, 10 produtos, 4 fornecedores),
> **CmsSeeder** (depoimentos, FAQs e páginas institucionais reais), **CrmSeeder** (converte
> os leads marcados como *vendido* em clientes) e **OrdersSeeder** (6 pedidos de exemplo).
> Tudo é idempotente e pode ser apagado sem afetar o código.

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

SESSION_DRIVER=file
CACHE_STORE=file

GOLDEN_WHATSAPP=5519997101483
GOLDEN_EMAIL=comercial@goldenrastreamento.com.br
GOLDEN_TRACKING_ENABLED=false

# SMTP (o código de e-mail já está pronto; só falta a credencial do servidor)
MAIL_MAILER=smtp
MAIL_HOST=smtp.seudominio.com.br
MAIL_PORT=587
MAIL_USERNAME=noreply@seudominio.com.br
MAIL_PASSWORD=***
MAIL_ENCRYPTION=tls
```

Depois:

```bash
composer install --no-dev --optimize-autoloader
composer deploy                      # migrate --force + config:cache + route:cache + view:cache
php artisan storage:link             # serve as imagens de produtos
```

Deixe `SESSION_DRIVER=file` e `CACHE_STORE=file` (ou `redis`) em produção: com driver
`database` cada visita pagava consulta de sessão e de cache. Se optar por fila
(`QUEUE_CONNECTION=database`), mantenha `php artisan queue:work --sleep=3 --tries=3` sob
supervisor — é por ela que os e-mails de pedido saem sem atrasar a resposta ao cliente.

As **mesmas migrations** rodam em SQLite e MySQL — não há código específico de driver.
Em produção o HTTPS é forçado automaticamente (`AppServiceProvider`). Nunca rode os
seeders de demonstração em produção (`--seed`); eles criam a senha `golden123`.

---

## 4. O que foi entregue

### Site público (multipágina)
| Rota | Página |
|------|--------|
| `/` | Home (hero, faixa de confiança, vitrine da plataforma, atalhos por página, depoimentos em carrossel, CTA final) |
| `/solucoes` | Funcionalidades da plataforma |
| `/para-quem` | Segmentos atendidos |
| `/planos` | Kits e perguntas frequentes |
| `/como-funciona` | Passos + regra de instalação |
| `/faq` | Dúvidas frequentes |
| `/contato` | Formulário de lead + canais |
| `/privacidade` | Política de Privacidade (LGPD) |
| `/loja` | **Loja virtual** (vitrine de rastreadores, chips M2M e acessórios; filtro por categoria) |
| `/loja/{slug}` | Página do produto + compra direta |
| `/loja/carrinho` | Sacola (quantidades, subtotal, regra de frete) |
| `/loja/checkout` | Checkout em 4 etapas: comprador, entrega, pagamento, consentimento |
| `/loja/pedido/{numero}/{token}` | Pedido do cliente: linha do tempo, endereço, totais |
| `/p/{slug}` | Páginas institucionais criadas no **CMS** |

Conteúdo de marketing (benefícios, segmentos, kits, passos, FAQ) fica centralizado em
`config/golden.php` — fácil de editar sem tocar nas views. Depoimentos, FAQs e páginas
também podem vir do banco (painel), com *fallback* para o config.

**Regra de conteúdo:** o que tem página dedicada não se repete na home nem em outra página.
A home mostra um cartão de atalho (`.atalhos`) para Soluções, Planos, Para quem é, Como
funciona, FAQ e Contato; `/planos` e `/como-funciona` levam ao `/faq` em vez de copiar o FAQ,
e `/solucoes` leva ao `/como-funciona` em vez de repetir os passos.

**Leveza:** a página pública não consulta o banco. Menu, configurações, categorias,
depoimentos, FAQs, páginas do CMS e **o catálogo inteiro da loja** (blob `golden.loja_catalogo`,
com preço, estoque e categoria de cada item ativo) passam por `Cache::rememberForever` e são
invalidados por evento de gravação do modelo (trait `App\Models\Concerns\CachesContent`);
`Setting` guarda a rodada em memória e `AppServiceProvider::dadosDoSite()` monta `$golden` uma
única vez por requisição. A sacola vive na sessão e resolve os itens contra esse catálogo —
vitrine, página de produto, carrinho e checkout não fazem nenhuma consulta; preço e estoque só
são relidos no `DB::transaction` que grava o pedido. Por isso sessão e cache ficam em arquivo
(`SESSION_DRIVER=file`, `CACHE_STORE=file`) — nada de SELECT/INSERT por visita, e nenhum
pico de banco no meio de uma campanha.
`tests/Feature/LevezaDasPaginasTest.php` mede isso: cache frio = poucas consultas por página
(`/` faz 4), cache quente = **zero**.
Índices dos caminhos de leitura em `2026_10_23_000001_add_leitura_indexes.php`. Ao publicar,
use `composer deploy` (migrations + `config:cache` + `route:cache` + `view:cache`); depois de
mudar conteúdo no painel, `php artisan optimize:clear` se quiser reaquecer o cache na hora.

Em telas até 960px o menu vira uma **gaveta deslizante da esquerda** (abertura/fechamento
suaves com *stagger* nos itens, backdrop, fecha por fora/`Esc`/link). O menu tem **dois
níveis**: a árvore mora em `config('golden.nav')` (Soluções, Planos e kits, Loja,
Institucional, Contato) e `AppServiceProvider::montarMenu()` completa os grupos dinâmicos —
categorias do catálogo em **Loja** e páginas publicadas em **Institucional** — resolvendo
também o item ativo (rota + slug/categoria da página). O render fica em
`resources/views/site/partials/nav-links.blade.php` e é compartilhado pelo header (dropdown
no hover) e pela gaveta (acordeão), com `white-space:nowrap` + reticências para nenhum
rótulo quebrar em duas linhas. Ao adicionar uma página no CMS, ela entra nos dois
automaticamente; desligar a loja remove o grupo inteiro da árvore.

O **botão flutuante do WhatsApp** segue o padrão do sistema de referência: em vez de ir
direto ao `wa.me`, ele abre uma **central de canais** (`site/partials/whatsapp-box.blade.php`)
com um card por atendimento — Emergência 24h, Comercial & Novos Planos, Suporte Técnico &
Aplicativo e Faturamento & Mensalidade — e cada canal já leva a **mensagem pré-preenchida**
(`wa_url($msg, $numero)`). Canais, textos e número de cada canal são editáveis em
**Admin → Configurações → Botão do WhatsApp no site**; desligando a caixa, o botão volta a
ser link direto.

**Movimento e abertura.** Cada bloco da página entra com `pageIn` (340ms,
`cubic-bezier(.22,.61,.36,1)` — a mesma curva do menu, da gaveta e da central de WhatsApp) e
*stagger* nos primeiros blocos. O botão **voltar ao topo** (`#toTop`) tem um **anel que enche
com a rolagem e esvazia ao subir**: o `stroke-dashoffset` de um `<circle pathLength="100">` é
atualizado pelo scroll (limitado a ~60Hz, sem forçar *layout* no caminho quente). É um
**componente compartilhado** — `partials/to-top.blade.php` + `public/css/golden-to-top.css` +
`public/js/golden-to-top.js` — incluído no site (à esquerda da bolha do WhatsApp) e no **painel
administrativo** (canto inferior direito); cada tema só ajusta a posição e a cor por variáveis.
No primeiro gesto sobre um link interno o navegador já faz
`<link rel="prefetch">` da página de destino (`initPrefetch`), o que corta o tempo de abertura
da próxima página; nada disso roda com `save-data` ou 2g, e `prefers-reduced-motion: reduce`
desliga a animação de entrada, o *smooth scroll* e as transições.

Os **depoimentos** da home são um **carrossel arrastável** no mesmo padrão: **um depoimentopor vez** em telas menores, dois a partir de 768px, com **segurar-e-arrastar** (dedo no
celular e mouse no computador), setas, *bullets* clicáveis, teclado (`←`/`→`) e
auto-rotação de 5,5s que pausa no hover e volta ao início no fim (`rewind`). A biblioteca é
o **Swiper 11**, servida localmente em `public/vendor/swiper/` e carregada só na home via
`@push('styles')` / `@push('scripts')`; sem JavaScript o bloco continua legível como lista.
Com `prefers-reduced-motion: reduce` a auto-rotação desliga e o deslocamento fica instantâneo.

### CRM (`/admin/clientes`)
- **Clientes** com dados de empresa (documento, tipo física/jurídica, endereço, cidade/UF,
  segmento, tamanho da frota, valor anual) e status `prospecto` / `ativo` / `inativo`.
- **Conversão de lead em cliente** com um clique na ficha do lead
  (`POST /admin/leads/{lead}/converter`) ou **automática ao mover no funil para
  "Vendido"** — o lead recebe `customer_id` e a trilha de atendimento passa a fazer parte
  do cadastro.
- **Interações** (ligação, WhatsApp, e-mail, reunião, visita, nota) com responsável,
  resultado, próxima ação e linha do tempo por cliente.
- Listagem com busca, filtro por status, contagem de pedidos e interações.

### ERP (`/admin/produtos`, `/admin/pedidos`)
- **Catálogo**: categorias, produtos (preço, preço promocional, mensalidade, imagem,
  destaque, ativo) e **fornecedores**.
- **Estoque real**: cada produto tem `estoque` e `estoque_minimo`; todo movimento é
  registrado no razão `stock_movements` (entrada / saída / ajuste) com saldo resultante,
  usuário, motivo e pedido relacionado. Ajustes feitos pelo painel geram lançamento.
- **Pedidos**: numeração `GR26-00007` (derivada do id dentro da transação, sem corrida de
  `MAX()+1`), itens com snapshot de nome/SKU/preço, subtotal, frete, desconto e total em
  centavos; pagamento `cartao` / `boleto` / `pix` / `retirada` / `não`; entrega
  (`entrega` / `retira`); funil `pendente → aguardando pagamento → pago → em separação →
  enviado → entregue` (ou `cancelado`), com datas automáticas de `pago_em` / `enviado_em`.
  Cada transição grava um marco em `order_histories` (status, recado, autor), que vira a
  linha do tempo do cliente e o histórico do painel.
- **Faturamento** da loja no dashboard e alerta de **estoque baixo**.

### CMS (`/admin/paginas`)
- **Páginas** com título, slug único, eyebrow, introdução, corpo em HTML rico, meta
  título/descrição, ordem no menu e *switches* "exibir no menu" / "publicada".
- O HTML do corpo é **sanitizado** na gravação (remove `<script>`, `<iframe>`, `<form>`,
  manipuladores `on*` e URLs `javascript:`) contra XSS armazenado.
- **Depoimentos** e **FAQs** editáveis, publicados no site sem tocar em código.

### Loja virtual com *switch* no painel
Fluxo de ponta a ponta: **vitrine → sacola → checkout → pedido numerado → linha do tempo →
gestão no painel**, com e-mail de confirmação nas duas pontas.

- **Menu Comercial / Vendas / Site / Administração** na sidebar, com um
  **botão liga/desliga a loja** direto na barra lateral (e o mesmo controle na tela de
  Configurações).
- Desativar a loja faz o middleware `loja` (`LojaAtiva`) responder **404** em todas as
  rotas `/loja*` e esconder os links do menu e do rodapé — o restante do site e o painel
  seguem funcionando, nada é apagado.
- **Vitrine** `/loja` com filtros por categoria (contagem por grupo), preço/promoção/
  mensalidade, estoque publicado e *CTA* de WhatsApp quando o item está sem estoque.
- **Sacola** em sessão (`App\Support\Carrinho`): ajuste de quantidades, remoção por linha,
  subtotal, frete e quanto falta para o frete grátis — tudo resolvido contra o catálogo em
  cache, sem tocar no banco. O contador do topo (`carrinho_itens`) vem do mesmo pacote.
- **Checkout** `/loja/checkout` em quatro etapas: comprador (pessoa física/jurídica, CPF 11
  ou CNPJ 14 dígitos), entrega (endereço completo obrigatório quando a modalidade é entrega;
  retirada na loja aparece só quando o painel liga), pagamento (Pix com a chave configurada,
  cartão, boleto ou pagamento na retirada) e observações, com **consentimento LGPD** gravado,
  campo-mel anti-spam, máscaras de telefone/CEP/CPF-CNPJ e *autofill* de endereço pelo ViaCEP.
- **Pedido transacional**: `POST /loja/pedido` (`throttle:8,1`) reabre o catálogo no
  `DB::transaction`, confere preço e estoque reais (barra a venda se a prateleira esvaziou por
  fora do painel), cria pedido + itens, lança a baixa em `stock_movements`, leva o comprador
  ao CRM (reaproveitando o cadastro pelo documento ou e-mail), registra o primeiro marco da
  linha do tempo, pseudonimiza o IP e esvazia a sacola.
- **Confirmação por e-mail**: `PedidoRecebido` ao cliente (com o botão "Acompanhar meu
  pedido") e `PedidoNovoEquipe` para a equipe quando `loja_email_equipe` estiver preenchido.
  O envio é em `queue()` dentro de `try/catch` — SMTP sem credencial nunca anula a venda.
- **Acompanhamento público** `/loja/pedido/{numero}/{token}`: o token de 32 caracteres no
  caminho substitui o login; sem o link recebido por e-mail o pedido não é enumerável.
- **Regras da loja no painel** (Configurações → Loja): frete grátis / combinar entrega
  (`loja_livre`), valor do frete fixo, frete grátis acima de um valor, chave Pix, retirada na
  loja e e-mail da equipe — em formulário próprio (`admin.settings.loja-regras`), separado da
  chave que liga/desliga a loja.
- **Gestão no painel** (`/admin/pedidos`): mudar status, forma de pagamento e notas, com um
  "recado para a linha do tempo" opcional que aparece para o cliente; link público do pedido
  pronto para copiar e atalho para criar o cliente no CRM.

### Painel administrativo de leads (`/admin`) — prioridade do briefing
- **Dashboard**: KPIs (total, novos, taxa de conversão, receita fechada), funil visual,
  gráfico de leads nos últimos 30 dias, **conversão por campanha**, leads recentes e uma
  linha **"Módulos integrados"** (clientes, produtos, pedidos/faturamento, páginas) com o
  estado da loja.
- **Leads**: listagem com **filtros** (status, campanha, período, busca, responsável),
  ficha completa com **mover no funil** (novo → contatado → proposta → vendido / perdido),
  notas de atendimento, edição do cadastro, atribuição de campanha e **linha do tempo**.
- **Exportação CSV** respeitando os filtros ativos (com trilha de auditoria).
- **Campanhas**: cadastro manual + criação automática por UTM, com métricas por campanha.
- **Auditoria**: trilha de ações administrativas (login, mudança de funil, exportação,
  configuração, cadastros de produto/página/pedido…), apenas para admin.
- **Usuários & permissões**: perfis `admin`, `vendedor`, `visualizador` com middleware
  de papel (`role:`). Catálogo, CMS e configurações são restritos a `admin`.
- **Configurações**: WhatsApp e telefones oficiais, endereço da empresa, chaves de
  medição (GA4/Pixel/GTM) com *switch* de consentimento e a loja virtual.

### Captura de leads (`POST /leads`)
- **Um único formulário no site**, na página dedicada `/contato`
  (`resources/views/site/partials/lead-form.blade.php`). As demais páginas convertem por
  CTA — os botões de kit apontam para `/contato?kit=…` e o *select* de interesse já vem
  preenchido; não há formulário duplicado embutido em home nem em `/para-quem`.
- Validação no servidor + **rate limit** (`throttle:8,1`) + **honeypot** anti-spam.
- Preserva **UTMs, `gclid`/`fbclid`**, página de origem e um **ID de visita** anônimo
  (cookie `golden_visitor`, 90 dias).
- IP **pseudonimizado** (hash) e registro de **consentimento LGPD**.
- Nunca expõe dados pessoais no front; o painel lê exclusivamente do backend.

### Medição
Eventos já instrumentados em `public/js/golden-site.js`: `page_view`, `view_plans`,
`select_plan`, `contact`, `whatsapp_click`, `generate_lead` (após sucesso HTTP) e
`lead_success`. Enquanto `tracking_enabled=false`, os eventos são apenas logados no console.

### Convenções de dados
- **Dinheiro sempre em centavos** (`unsignedInteger`), exibido pelo helper global
  `brl()` — evita erro de ponto flutuante e funciona igual nos dois bancos.
- Helpers globais em `app/Support/helpers.php` (`brl`, `loja_ativa`, `slugify_pt`),
  carregados por `autoload.files` do Composer.
- Migrations *driver-agnostic*: nada de `enum` nativo, `COMMENT` ou SQL específico de
  MySQL/SQLite; os valores fixos são validados no model (`const STATUS`, `TIPOS`).

---

## 5. Estrutura principal

```
app/
  Http/Controllers/{Site,Auth,Admin}/   # páginas, loja, captura de lead, login, painel
  Http/Middleware/ControleAcesso.php     # role:admin,vendedor,...
  Http/Middleware/LojaAtiva.php          # alias `loja` -> 404 quando a loja está off
  Http/Middleware/IdentificadorVisita.php
  Models/  Lead, Campaign, LeadStatusHistory, Activity, Setting, User,
           Customer, Interaction, Product, Category, Supplier,
           StockMovement, Order, OrderItem, OrderHistory, Page, Testimonial, Faq
  Mail/    PedidoRecebido, PedidoNovoEquipe
  Services/LeadService.php               # atribuição + criação + funil
  Support/Carrinho.php                   # sacola em sessão, sobre o catálogo em cache
  Support/helpers.php                    # brl(), loja_ativa(), slugify_pt()
config/golden.php                        # conteúdo do site + contatos reais
database/migrations/                     # leads/campanhas + CRM + ERP + CMS
database/seeders/                        # Database, Catalog, Cms, Crm, Orders
resources/views/{layouts,site,site/store,mail,auth,admin}
public/{css,js,img}                      # tema do site e do painel
tests/Feature/FluxoDaLojaTest.php        # vitrine -> sacola -> checkout -> pedido -> painel
tests/Feature/LevezaDasPaginasTest.php   # zero consultas com cache quente
tests/Unit/DiretivasBladeTest.php        # diretivas Blade compiladas em todas as telas
```

`php artisan test` roda tudo contra SQLite em memória (58 testes).

## 6. Pendências para produção (do briefing)

- Substituir o logotipo conceitual (`public/img/logo-golden.png`) pelo **original em
  SVG/PNG transparente**.
- Trocar o *mock* da plataforma por **capturas reais** e fotos do rastreador J16.
- Confirmar os **preços** da loja (o seeder usa valores de referência editáveis no painel:
  o site oficial não publica tabela de preços) e as **regras de mensalidade/contrato**.
- Definir a **forma de pagamento on-line** da loja (gateway de cartão/pix ou boleto
  bancário real). Hoje o pedido registra a intenção (`cartao`/`boleto`/`pix`) e o status é
  atualizado manualmente pelo painel — sem captura de dados de cartão no sistema.
- Revisar a **Política de Privacidade** com o jurídico.
- Só então ligar **GTM/GA4/Ads/Meta Pixel** e habilitar `tracking_enabled`.
