# Changelog

Todo o registro de mudanças notáveis deste projeto. O formato segue
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/) e o projeto usa Versionamento Semântico.

## [2.5.0] - 2026-10-23

### Removido
- **A home parou de repetir o que já tem página dedicada.** Saíram da página inicial os
  blocos de benefícios, segmentos, kits/planos, "como funciona" e FAQ — cada um agora é um
  cartão de atalho na seção *Explore o site* (`.atalhos`), apontando para a própria página.
- As mesmas duplicações foram desfeitas nas páginas filhas: `/planos` e `/como-funciona`
  não repetem mais o FAQ (bridge "Ver dúvidas frequentes →") e `/solucoes` não repete os
  passos do `/como-funciona`.
- `PageController` deixou de consultar FAQ/depoimentos para páginas que não exibem mais
  esse conteúdo.

## [2.6.0] - 2026-10-23

### Adicionado
- **Site ultra leve: página pública não visita o banco.** Cada leitura de conteúdo
  (`settings`, páginas do CMS/menu, categorias, depoimentos, FAQs) passou a
  `Cache::rememberForever`, invalidada por evento de gravação do modelo.
  - Trait `App\Models\Concerns\CachesContent`: o modelo declara `protected array $cacheKeys`
    e `saved`/`deleted` esquecem as chaves dele. Usado em `Page`, `Category`, `Testimonial`
    e `Faq`.
  - `Page::publicadasParaSite()` alimenta **dois** acessos de uma só consulta: o menu
    (`menuParaSite()`) e a página do CMS (`paraSite($slug)`); `scopeMenu()` foi removido por
    desnecessário.
  - `Setting::$memoria` é o espelho da rodada em memória estática (`esquecerMemoria()` para
    scripts que escrevem em massa), e `AppServiceProvider::dadosDoSite()` monta a árvore
    `$golden` **uma vez por requisição** — antes o `View::composer` de `site.*` rodava a cada
    *include* de parcial (≈5× por visita) refazendo ~30 consultas.
  - `database/migrations/2026_10_23_000001_add_leitura_indexes.php`: índices dos caminhos de
    leitura (`products(ativo,ordem)`, `products(category_id,ativo)`, `categories(ativa,ordem)`,
    `pages(publicada,exibir_menu,ordem_menu)`, `testimonials(aprovado,ordem)`,
    `faqs(ativa,ordem)`, `orders(status,created_at)`, `stock_movements(product_id,created_at)`,
    `leads(campaign_id,created_at)`).
  - `tests/Feature/LevezaDasPaginasTest.php` trava a promessa: com cache frio cada página
    pública fica abaixo de 14 consultas e, com cache quente, ** zero** — `GET /` = 4 → 0.
    Também cobre que gravar no painel invalida o cache da página.
  - `composer deploy` (`migrate --force` + `config:cache` + `route:cache` + `view:cache`).

### Alterado
- `.env.example`/`.env`: `SESSION_DRIVER=file` e `CACHE_STORE=file` (com `database`, cada
  visita pagava SELECT/INSERT em sessão e cache), `QUEUE_CONNECTION=sync` e o motivo escrito
  no próprio arquivo.
- `<link rel="preconnect">` para `cdnjs.cloudflare.com` no `<head>` do site, junto dos
  *preconnect* já existentes das fontes.

## [2.4.0] - 2026-10-22

### Adicionado
- **Depoimentos da home viraram carrossel arrastável** (padrão do sistema de referência):
  **um depoimento por vez** em telas menores e **dois a partir de 768px**, com
  **segurar-e-arrastar** — dedo no celular e mouse no computador (`grabCursor` +
  `simulateTouch`, `threshold: 5`, `touchRatio: 1.2`), setas anterior/próximo, *bullets*
  clicáveis, teclado `←`/`→` e auto-rotação de 5,5s que pausa no hover (`pauseOnMouseEnter`)
  e volta ao começo no último card (`rewind`) em vez de parar.
  - `initDepoimentos()` em `public/js/golden-site.js` monta o carrossel a partir de
    `.js-depoimentos`, com `slidesPerView`/`spaceBetween` por breakpoint (1/16 → 2/24) e
    `watchOverflow`, que desliga a navegação quando todos os cards cabem na tela.
  - Markup em `resources/views/site/home.blade.php`: `.depo > .swiper > .swiper-wrapper >
    .swiper-slide > .depo-card`, mais a linha de controles `.depo-ctl` (seta, *bullets*,
    seta) fora da área de arraste.
  - Estados com `prefers-reduced-motion: reduce`: auto-rotação desligada e deslocamento
    instantâneo (`speed: 0`), sem quebrar o arraste.
  - `@stack('styles')` no `<head>` do layout do site, para a home carregar o CSS/JS do
    carrossel sem empilhar peso nas outras páginas.

### Alterado
- **Swiper 11.2.10 versionado no repositório** (`public/vendor/swiper/swiper-bundle.min.{css,js}`)
  em vez de CDN: sem dependência externa em tempo de execução, sem *hash* SRI para manter,
  e com a mesma versão fixa para dev e produção. Carregado só na home.
- CSS dos depoimentos em `public/css/golden-site.css`: `.depo` deixou de ser `display:grid`
  de duas colunas e passou a ser o palco do carrossel — cards em `flex` de altura
  equivalente (o autor fica ancorado embaixo com `margin-top:auto`), cursor de **mão**
  (`grab`/`:active` → `grabbing`), `user-select:none` para o arraste não selecionar texto,
  *bullets* na cor da marca com o ativo alongado (9px → 26px) e transição usando a mesma
  `cubic-bezier(.22,.61,.36,1)` do menu, da gaveta e da central de WhatsApp.
- `.depo` saiu das listas de `grid-template-columns` dos breakpoints 960px/600px, e as setas
  somem abaixo de 700px (ali o gesto é o caminho).
- Sem JavaScript (ou sem o bundle) o bloco não trava: enquanto o carrossel não inicializa,
  `.swiper:not(.swiper-initialized)` quebra os cards em lista e nada fica escondido dentro
  de um `overflow:hidden`.
- README: a rota `/` passou a descrever "depoimentos em carrossel" e perdeu o "contato" que
  saiu da home na 2.2.0.

## [2.3.0] - 2026-10-22

### Adicionado
- **Botão flutuante do WhatsApp no padrão do sistema de referência (`foco7`)**: ele deixou
  de ser um `<a>` solto para o `wa.me` e passou a abrir uma **central de canais**.
  - Parcial `site/partials/whatsapp-box.blade.php` com o botão (`#waFloat`) e o box
    multicanal (`#waPopup`): cabeçalho com selo **Online agora**, título/subtítulo, botão
    fechar e um **card por canal** (ícone + nome + descrição + seta), cada um apontando
    para o `wa.me` **já com a mensagem pré-preenchida** (`?text=`).
  - Quatro canais de fábrica em `config('golden.whatsapp_box.canais')`: **Emergência &
    Central 24h**, **Comercial & Novos Planos**, **Suporte Técnico & Aplicativo** e
    **Faturamento & Mensalidade** — cada um com a frase de abertura própria.
  - Helper global **`wa_url($msg, $numero)`** (`app/Support/helpers.php`): limpa o número
    (só dígitos), usa o `contato_whatsapp` do painel por padrão e monta o `?text=` com
    `urlencode`. Todos os links do widget saem dele.
  - **Editável no painel** (Admin → Configurações → "Botão do WhatsApp no site", rota
    `POST /admin/configuracoes/whatsapp`): liga/desliga a caixa, troca título e subtítulo,
    desliga canais individualmente e aponta cada canal para um **número próprio**
    (ex.: suporte na segunda linha `(19) 97139-5129`) — vazio herda o número principal.
  - Estados com `aria-expanded`/`aria-controls`/`aria-label`, `role="dialog"` e
    `data-track="whatsapp_click"` + `data-cta="canal_<id>"` em cada card, para separar por
    canal na medição de campanhas.

### Alterado
- CSS do widget portado na paleta dourada (`.wa-float` com **badge dourado de online** e
  **anel pulsante** `wa-ring`, `.wa-popup`, `.wa-head`, `.wa-channel`): mesma curva
  `cubic-bezier(.22,.61,.36,1)` do resto do site, entrada com escala + deslocamento e
  saída simétrica. O botão fica dourado com o anel parado enquanto o box está aberto.
- Fechamento por todos os caminhos: botão X do cabeçalho, clique fora, `Esc` e ao escolher
  um canal; a gaveta mobile esconde botão e box (`body.nav-open`) para não competirem.
- Nomes e descrições dos canais com `white-space:nowrap` + reticências (mesma regra
  antiquebra aplicada ao menu) e corpo do box com rolagem própria (`max-height:min(42vh,
  380px)`), além de teto de altura no popup (`calc(100vh - 124px)`) para nunca estourar o
  topo da tela em janelas baixas.
- Em telas pequenas o botão encolhe (52px) e o rótulo de ajuda (`Falar no WhatsApp`) some.
- `$golden['whatsapp_url']` agora é gerado pelo `wa_url()`, então o mesmo número tratado
  no painel vale para o widget, a gaveta e o rodapé.

## [2.2.0] - 2026-10-22

### Adicionado
- **Menu do site em dois níveis (submenus)**, montado a partir de `config('golden.nav')` e
  resolvido por `AppServiceProvider::montarMenu()`, que compartilha a mesma árvore com o
  header e com a gaveta mobile:
  - **Soluções** ▸ Funcionalidades da plataforma · Para quem é · Como funciona
  - **Planos e kits**
  - **Loja** ▸ Todos os produtos + uma entrada por categoria ativa do catálogo
    (`/loja?categoria=…`)
  - **Institucional** ▸ páginas publicadas com "exibir no menu" no CMS · Dúvidas
    frequentes · Política de privacidade
  - **Contato**
  Com 10+ links soltos virando 5 itens, o menu do header deixa de apertar/espremer as
  opções em telas menores.
- **Dropdown no desktop** (painel centralizado sob o item, 236px mínimos, 260ms na mesma
  curva `cubic-bezier(.22,.61,.36,1)` da gaveta, com ponte invisível de 16px para o mouse
  não perder o hover) e **acordeão na gaveta** (o submenu expande via `max-height`, com a
  setinha do botão girando 180° e o submenu filho marcado com barra dourada à esquerda).
- Botão de acionamento dedicado por grupo (`.nav-caret`, alvo de toque de 30px) e
  `aria-expanded`/`aria-controls` por submenu; no mobile a setinha inline do rótulo some
  (`.nav-drawer .nav-arrow{display:none}`) para não aparecer seta duplicada.

### Alterado
- **Nenhum rótulo quebra em duas linhas**: `white-space:nowrap` +
  `overflow:hidden;text-overflow:ellipsis` em `.nav-link` e nos itens de submenu (com
  `max-width:220px`), então títulos longos do CMS ("Sobre a Golden Rastreamento",
  "Funcionalidades da plataforma") cortam com reticências em vez de quebrar o layout.
- Itens do menu agora são `.nav-item > .nav-link` (+ `.nav-sub`) em vez de `<a>` soltos;
  a cascata de entrada da gaveta passou a ser por `.nav-item` (5 itens, 40ms entre eles) e
  as linhas ficaram todas com a mesma altura (52px) — o botão de grupo não herdava o
  `line-height` dos links, o que aparecia como uma linha mais baixa no meio do menu.
- Item ativo calculado de verdade: página do CMS bate pelo **slug**, categoria da loja bate
  pelo **filtro da query string** e o pai acende por prefixo de rota (ex.: em
  `/loja/rastreador-j16-4g` só "Loja" fica destacado). Antes, todos os filhos do grupo
  acendiam juntos.
- Grupo **Loja** sai completamente da árvore quando a loja é desativada no painel
  (`->reject()`), em vez de sumir só no HTML.
- Submenu sempre abre fechado: `abrir()`/`fechar()` da gaveta resetam os itens abertos, então
  reabrir a gaveta nunca vem com um acordeão pendurado; `Esc` fecha o submenu no desktop e
  a gaveta no mobile, sem deixar estado para trás.
- Removido o `$golden['menu']` (lista flat) do *view composer* — só existia para montar o
  menu; agora é `$golden['nav']`.

### Removido
- **Fim da duplicidade do formulário de lead.** Ele existia em três páginas ao mesmo tempo
  (home, `/para-quem` e `/contato`), sempre pelo mesmo parcial `site/partials/lead-form`.
  Agora vive **somente na página dedicada** (`/contato`):
  - Na **home**, a seção "Fale com a Golden" com o formulário saiu; a página termina em
    FAQ → CTA final, que já levava para `/contato` e WhatsApp.
  - Em **`/para-quem`**, o painel de duas colunas com o formulário virou um bloco central
    com três caminhos: "Solicitar minha proposta →" (`/contato`), "Conhecer os kits →"
    (`/planos`) e WhatsApp.
  - Os botões de kit continuam mandando para `/contato?kit=…`, e o *select* "Kit de
    interesse" da página de contato já vem preenchido com o kit escolhido — a remoção do
    formulário inline não perdeu a pré-seleção.

## [2.1.0] - 2026-10-22

### Adicionado
- **Identidade do projeto no `composer.json`**: pacote renomeado de `laravel/laravel` para
  `kdkhost/golden-rastreamento`, com `description` e `keywords` próprios (Golden
  Rastreamento, CRM/CMS/ERP, loja virtual), `license: proprietary` e bloco `authors`
  creditando **Marcelo (KDKHOST)** — contato@kdkhost.com.br / kdkhost.com.br — como
  desenvolvedor do sistema. O `composer.lock` teve o *content-hash* atualizado.
- Mesma identidade refletida no `package.json` (nome, versão e descrição dos assets).
- **Menu deslizante do site (gaveta da esquerda)** em telas até 960px: painel fixo de
  `min(84vw, 330px)` que entra com `translateX(-102%) → 0` em **380ms** na curva
  `cubic-bezier(.22,.61,.36,1)`, com os links surgindo em cascata (60 ms entre itens),
  cabeçalho próprio (logo + botão fechar que gira 90°), CTA "Falar com especialista" e
  rodapé com WhatsApp/telefone/e-mail.
- **Backdrop** com desfoque que escurece a página, e o ícone do botão troca de hambúrguer
  para X com rotação — o mesmo tempo e a mesma curva nas duas direções, então abrir e
  fechar são coerentes entre si.
- Parcial `site/partials/nav-links.blade.php`: a lista de links (incluindo Loja e as
  páginas do CMS) agora é **fonte única** consumida pelo menu do header e pela gaveta —
  nada de marcar item desatualizado em um dos dois.

### Alterado
- A gaveta saiu de dentro do `<header>`: o `backdrop-filter` do cabeçalho criava um
  *containing block* e a gaveta ficava presa nos 74px do header, sem cobrir a tela toda.
- Fechar por todos os caminhos: X do botão, botão fechar da gaveta, clique no fundo,
  `Esc` e ao escolher um link. Trava o scroll da página (`body.nav-open`), esconde o botão
  flutuante do WhatsApp enquanto aberta, devolve o foco ao botão e mantém
  `aria-expanded`/`aria-label` corretos.
- `prefers-reduced-motion` respeitado (transições reduzidas a ~0 ms).
- Em desktop o menu continua exatamente como era (fileira no header); a gaveta é
  `display:none` acima de 960px.

## [2.0.0] - 2026-10-22

### Adicionado
- **Sistema integrado CRM + CMS + ERP** no mesmo painel, com dados reais coletados do site oficial goldenrastreamento.com.br.
- **CRM** — `customers` + `interactions`: ficha de cliente (CPF/CNPJ, endereço, frota, valor anual, responsável), linha do tempo de interações (ligação, WhatsApp, e-mail, reunião, nota), próxima ação e conversão de **lead → cliente** com um clique (no funil ou automaticamente ao marcar "Vendido").
- **ERP** — `categories`, `products`, `stock_movements`, `suppliers`, `orders`, `order_items`: catálogo com preços, promoção, mensalidade, estoque com mínimo, movimentações (entrada/saída/ajuste), fornecedores (Vivo, Claro, Algar, TIM) e pedidos com status e formas de pagamento.
- **CMS** — `pages`, `testimonials`, `faqs`: páginas institucionais editáveis (com HTML sanitizado contra XSS), depoimentos e FAQs gerenciados pelo painel; conteúdo do site agora vem do banco com *fallback* para `config/golden.php`. Páginas do CMS entram automaticamente no menu do site.
- **Loja virtual pública** (`/loja`, `/loja/{slug}`, checkout e confirmação de pedido) para vender rastreadores J16, chips M2M e acessórios — **com interruptor ON/OFF no painel** (botão rápido na sidebar + seção em Configurações). Quando desligada, as rotas respondem 404 e o item some do menu.
- Checkout com validação, *honeypot*, *rate limit*, transação de banco, baixa automática de estoque e registro de auditoria.
- Helpers globais (`brl`, `loja_ativa`, `slugify_pt`) e middleware `LojaAtiva` (alias de rota `loja`).
- Seeders `CatalogSeeder`, `CmsSeeder`, `CrmSeeder`, `OrdersSeeder` com produtos, depoimentos reais (Douglas, Jonatha, Marcelle), FAQs, páginas e pedidos de demonstração.
- Dados oficiais da empresa aplicados no site (endereço, telefones, parceiros M2M, formas de pagamento) e novo bloco "Módulos integrados" no dashboard.

### Atualizado
- **Estrutura do repositório**: o projeto Laravel agora ocupa a **raiz** do repositório — as
  migrações, controllers, views e o restante do sistema foram movidos de `golden/` para a raiz
  (histórico preservado como *rename*). O `.gitignore` da raiz unificou as regras do framework
  com a lista de documentos internos, que continuam fora do versionamento.
- Sidebar do painel reorganizada em Comercial (CRM) / Vendas (ERP) / Site (CMS) / Administração + atalho de liga/desliga da loja.
- Rodapé e cabeçalho do site com endereço, telefones, cobertura de chips e pagamento reais.
- `config/golden.php`, `.env.example` e `AppServiceProvider` com os dados oficiais da Golden.
- `.env.example` ganhou o bloco completo de **SMTP/e-mail** (`MAIL_*`) documentado: em desenvolvimento o driver é `log` (nada é enviado, só registrado em `storage/logs`), e em produção basta preencher a credencial do servidor — o código de e-mail faz parte do sistema e não é removido.
- Fechar uma venda no funil (**status "Vendido"**) agora **converte o lead em cliente do CRM automaticamente** e abre a ficha do cliente, com lançamento na auditoria.
- `README.md` reescrito para cobrir os módulos CRM/ERP/CMS, a loja virtual com interruptor, as rotas novas, os seeders de demonstração e o passo `php artisan storage:link`.

## [1.0.0] - 2026-10-22

### Adicionado
- Projeto **Laravel 13** "Golden Rastreamento" criado seguindo o padrão do repositório de referência `foco7` (AdminLTE 4, auth próprio, migrations *driver-agnostic*).
- **Site multipágina** com o padrão visual aprovado (dourado + teal sobre fundo escuro): Home, Soluções, Para quem é, Planos, Como funciona, FAQ, Contato e Privacidade.
- **Painel administrativo de leads** (prioridade do briefing):
  - Dashboard com KPIs (total, novos, taxa de conversão, receita), funil visual, gráfico de 30 dias e conversão por campanha.
  - CRUD/consulta de leads com filtros (status, campanha, período, busca, responsável), ficha com mover no funil, notas e linha do tempo.
  - Exportação CSV respeitando filtros.
  - Gestão de campanhas (cadastro + criação automática por UTM) com métricas.
  - Trilha de auditoria das ações administrativas.
  - Usuários e permissões (perfis `admin`, `vendedor`, `visualizador`).
  - Configurações de contato e de medição (GA4/Pixel/GTM) com *switch* de consentimento.
- **Captura de leads** (`POST /leads`) com validação, *rate limit*, *honeypot*, preservação de UTMs/`gclid`/`fbclid`, ID de visita anônimo, IP pseudonimizado e registro de consentimento LGPD.
- Instrumentação de eventos (`page_view`, `view_plans`, `select_plan`, `contact`, `whatsapp_click`, `generate_lead`, `lead_success`).
- Migrations: `users` (role/status/telefone/avatar), `campaigns`, `leads`, `lead_status_histories`, `activities`, `settings`.
- `DatabaseSeeder` com admin, vendedor, 3 campanhas e 42 leads de demonstração.
- Configuração dupla de banco: **SQLite** para desenvolvimento offline e **MySQL** pronto para produção (mesmas migrations).
- Documentação: `README.md` (instalação dev/prod, estrutura, pendências do briefing) e este `CHANGELOG.md`.
