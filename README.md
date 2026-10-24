# Golden Rastreamento — CRM + CMS + ERP integrados

Sistema web da **Golden Rastreamento** (rastreamento veicular e gestão de frotas): um site
institucional **multipágina** com o padrão visual aprovado (dourado + teal sobre fundo
escuro), um **painel administrativo de leads** para acompanhar contatos e conversões das
campanhas de tráfego pago, e **CRM, CMS e ERP funcionando no mesmo sistema** — inclusive
uma **loja virtual de rastreadores e chips M2M** que pode ser **desativada a qualquer
momento pelo painel** e um **portal do cliente** (`/portal`) onde ele vê os próprios
pedidos, abre chamados e lê o contrato assinado.

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

> **Estas credenciais valem só na sua máquina** (`APP_ENV=local`). No servidor instalado, a
> senha do administrador é forte e fica guardada em `~/backups-golden/acesso-painel.txt`
> (fora do git e do docroot). Sem o painel, pela SSH:
> `php artisan app:define-senha admin@golden.com.br 'nova-senha'` — use `--reativar` se a
> conta estiver desligada.

> O `DatabaseSeeder` cria 42 leads em 3 campanhas e nos 5 status do funil e, em seguida,
> roda os seeders de módulo: **CatalogSeeder** (4 categorias, 10 produtos, 4 fornecedores),
> **CmsSeeder** (depoimentos, FAQs e páginas institucionais reais), **CrmSeeder** (converte
> os leads marcados como *vendido* em clientes) e **OrdersSeeder** (6 pedidos de exemplo).
> Tudo é idempotente e pode ser apagado sem afetar o código.

## 3. Subir para o servidor (MySQL)

O caminho recomendado é o instalador: ele confere PHP 8.3+/extensões/Composer, gera o
`.env` (com permissão `600`), instala as dependências sem as de desenvolvimento, copia o
banco antigo antes de encostar nele, aplica migrations e seeds, define o login do painel,
liga `public/storage`, escreve o `.htaccess` de proteção da raiz e ativa os caches.

```bash
GOLDEN_DB_PASSWORD='senha-do-banco' GOLDEN_ADMIN_PASSWORD='senha-do-painel' \
  bash deploy/instalador-golden.sh \
    --url=https://seudominio.com.br \
    --php-web=ea-php83 \
    --nome=nome-do-banco --usuario=nome-do-usuario

# atualização, depois da primeira instalação
bash deploy/atualiza-golden.sh
```

Nenhuma credencial fica no script nem no repositório: as senhas entram por variável de
ambiente (ou são pedidas na tela, sem aparecer) e vão só para o `.env`. A opção
`--sem-demo` pula os dados de demonstração; `--help` traz a lista completa.

`--php-web=` resolve o problema mais comum em hospedagem compartilhada: o `php` do SSH é
8.3 e o PHP que serve o navegador é outro, e o site fica em erro 500. A linha grava o
handler no `.htaccess` (`AddType application/x-httpd-ea-php83 .php`), que é o que manda em
servidores CloudLinux/LiteSpeed onde o seletor da conta para no 8.2.

O guia **`docs/INSTALACAO.md`** (e o PDF `docs/Golden-Rastreamento-Instalacao.pdf`, gerado
por `python docs/gerar-pdf.py`) cobre o passo a passo manual, o PHP 8.3 no MultiPHP do
cPanel, a referência de todas as chaves do `.env`, backup/restore e a tabela de problemas
comuns.

O que o instalador escreve, em resumo, no `.env` de produção:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seudominio.com.br

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=seuusuario_cms
DB_USERNAME=seuusuario_cms
DB_PASSWORD=***

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=database

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

Feito à mão, os mesmos passos são:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force --seed   # ou migrate:fresh --seed num banco zerado
php artisan storage:link
composer deploy                      # config:cache + route:cache + view:cache
```

Deixe `SESSION_DRIVER=file` e `CACHE_STORE=file` (ou `redis`) em produção: com driver
`database` cada visita pagava consulta de sessão e de cache. Se optar por fila
(`QUEUE_CONNECTION=database`), mantenha `php artisan queue:work --sleep=3 --tries=3` sob
supervisor — é por ela que os e-mails de pedido saem sem atrasar a resposta ao cliente.

As **mesmas migrations** rodam em SQLite e MySQL — não há código específico de driver.
Em produção o HTTPS é forçado automaticamente (`AppServiceProvider`). Os seeders criam o
login com a senha `golden123`: o instalador troca por uma senha forte logo depois (o valor
fica em `~/backups-golden/acesso-painel.txt`); na mão, troque no painel ou rode
`php artisan app:define-senha` antes de divulgar o site.

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
também podem vir do banco (painel), com *fallback* para o config. **As fotos do site e a
abertura das páginas fixas seguem a mesma regra**: `config/golden.php` registra os 19 slots de
imagem e o texto padrão de cada página, o painel guarda a sobrescrita em `settings`, e o que
não foi enviado continua vindo do arquivo do pacote (`App\Support\ConteudoSite`).

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
- **Catálogo**: categorias, produtos (preço, preço promocional, mensalidade, imagem, **vídeo**,
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
  linha do tempo do cliente e o histórico do painel. Comprovantes e notas do pedido entram
  pelo campo de documentos (até 12 arquivos por pedido).
- **Faturamento** da loja no dashboard e alerta de **estoque baixo**.

### CMS (`/admin/conteudo`)
- **Páginas** (`/admin/conteudo/paginas`) com título, slug único, eyebrow, introdução, corpo em
  HTML rico, **foto de capa** com texto alternativo, meta título/descrição, ordem no menu e
  *switches* "exibir no menu" / "publicada". A capa entra no herói de `/p/{slug}` e no
  `og:image` daquela página.
- O HTML do corpo é **sanitizado** na gravação (remove `<script>`, `<iframe>`, `<form>`,
  manipuladores `on*` e URLs `javascript:`) contra XSS armazenado.
- **Imagens do site** (`/admin/conteudo/imagens`) — as 19 fotografias expostas no site,
  agrupadas pela página onde aparecem. Cada slot mostra a imagem que está no ar, o selo
  `foto do pacote` / `enviada pelo painel` / `sem foto no ar`, a dimensão e a proporção
  pedidas, o campo de arrastar-e-soltar e o texto alternativo. Enviar guarda o arquivo em
  `storage/site/`; marcar **Voltar à foto original** apaga o envio, apaga o arquivo e devolve
  a foto **e** a descrição do pacote.
- **Textos das páginas** (`/admin/conteudo/textos`) — a abertura das sete páginas fixas
  (rótulo do caminho, linha de destaque, título e subtítulo). A home fica de fora de propósito:
  a abertura dela é o layout aprovado, e nela o que se troca pelo painel são as fotos.
- As duas telas seguem a mesma precedência: o valor em `settings` vence e o padrão registrado
  em `config/golden.php` fica por baixo, então **campo em branco = texto/foto original de
  volta**, sem republicar nada. Nenhuma chave é aceita do formulário — a lista vem do config,
  o que barra escrita arbitrária em `settings`.
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

### Campos de envio (imagens, vídeos, documentos)
Todo campo de arquivo do painel é o mesmo componente de **arrastar-e-soltar**: soltar o
arquivo dentro do campo, ver o preview ali mesmo, acompanhar o progresso real do envio com o
tempo decorrido, e ler **dentro do campo** a regra que ele aceita — formatos, tamanho máximo e
a dimensão ideal.

- `config/envios.php` é a **regra única**: por campo (`produto_imagem`, `produto_video`,
  `pedido_documento`) ficam o tipo, a pasta de destino, os formatos, o limite em MB e a
  dimensão/proporção ideais. A linha que o operador lê e o que o servidor valida saem da
  mesma fonte — não há segunda cópia escrita à mão para desandear.
- **Um endpoint para tudo** (`POST` / `DELETE` `/admin/uploads`, `throttle:40,1`): o navegador
  manda o arquivo e a **chave** do campo; a pasta nunca vem do pedido, então não existe como
  escolher onde o sistema escreve. A resposta é JSON porque o progresso, o tempo e as dimensões
  do arquivo só existem numa troca assíncrona.
- **Caminho é texto no formulário, e texto não é confiável**: ao salvar, o valor precisa começar
  na pasta daquele campo, não pode ter travessia e o arquivo tem que estar no disco público
  (`Envio::caminhoConfere`). Documento de pedido apontando para a pasta de imagem é recusado.
- **A extensão gravada vem do conteúdo**, nunca do nome enviado, e precisa estar entre os
  formatos do campo — um script PHP chamado `foto.jpg` é recusado em vez de virar `.php`
  executável dentro de `public/storage`.
- **Nome legível em disco**: `rastreador-j16-original-H4k2p9Qz.png`. O caminho é o que aparece
  no pedido meses depois; hash puro não diz o que é o arquivo.
- Remover um arquivo recém-enviado apaga na hora; trocar a imagem de um produto que já está no
  ar só apaga a antiga quando o formulário é salvo — cancelar a edição não quebra a vitrine.
- Onde está: imagem e **vídeo** do produto (`/admin/catalogo/produtos`, vídeo também toca na
  página da loja) e **documentos** do pedido (comprovantes/notas, até 12 por pedido, em cartão
  próprio na ficha do pedido).

### Painel administrativo de leads (`/admin`) — prioridade do briefing
- **Menu em árvore**: a sidebar vem de `config/golden.php` → `menu_painel` e é montada por
  `App\Support\MenuPainel`. São quatro grupos (**Comercial**, **Vendas**, **Conteúdo do site**,
  **Sistema**) com as páginas dentro; o clique abre e fecha o grupo, e o *accordion* do AdminLTE
  mantém **um só aberto por vez**. Quem fica marcado como ativo é a página em si — `/admin/leads/12`
  acende *Leads* pelo prefixo da rota e já chega com **Comercial** aberto (o grupo herda o dourado,
  sem a barra, para não haver duas marcas na mesma coluna). `admin:true` esconde o que é só do
  administrador: os dois grupos restritos inteiros e o catálogo dentro de Vendas.
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
- Cada cadastro sai **atribuído**: canal, rede, se era pago, primeiro e último toque,
  landing page e o `visit_id` da jornada (ver *Atribuição de tráfego*). Sem visita prévia
  (API, aba anônima, formulário sem cookie), a classificação usa as marcações que o próprio
  POST carrega — o lead nunca fica sem origem.
- IP **pseudonimizado** (hash) e registro de **consentimento LGPD**.
- Nunca expõe dados pessoais no front; o painel lê exclusivamente do backend.

### Atribuição de tráfego (`/admin/atribuicao`) — "de onde veio cada clique"
- **Toda a jornada fica registrada**: `visits` (uma linha por visita, com a origem decidida)
  e `visit_events` (cada tela vista e cada clique medido). O visitante é anônimo
  (cookie `golden_visitor`, 90 dias) e o IP só entra como hash.
- **Classificador** em `App\Services\Atribuidor`, na ordem da prova mais forte:
  identificador de clique → `utm_medium` → `utm_source` → referer → direto.
  Todo o vocabulário mora em `config/golden.php` (`canais`, `meios_*`, `ids_clique`,
  `redes_sociais`, `buscadores`, `ia_humanos`, `ia_usuarios`, `indexadores`) — nenhuma
  regra de origem escrita solta em controller.
- **Canais reconhecidos**: `anuncio` (por rede: google, facebook, tiktok, bing, linkedin,
  yahoo — pelo `gclid`/`fbclid`/`ttclid`/`msclkid`/`li_fat_id`/`yclid`), `rede_social`,
  `busca_organica`, **`busca_ia`** (ChatGPT, Perplexity, Gemini, Copilot, Claude, Grok,
  Mistral…), `indicacao`, `email`, `parceiro`, `direto` e `indexador` (robôs, **fora dos
  números**).
- **Robô ≠ pessoa**: `GPTBot`, `ClaudeBot`, `Meta-ExternalAgent`, o crawler do Google etc.
  entram no registro marcados como `bot` e ficam fora de toda KPI; já o **navegador
  embutido** do WhatsApp/Instagram/Facebook/TikTok — onde o clique humano realmente
  aconteceu — é tratado como gente.
- **Dispositivo, navegador e sistema** vêm do user-agent (`celular`/`tablet`/`computador`,
  `instagram`, `samsung-internet`, `ios`/`android`…), então a ficha diz se o clique veio do
  app do Instagram ou do navegador.
- **Primeiro e último toque** no lead: `canal`/`rede` (fechou) e `canal_primeiro`/
  `rede_primeiro` (conheceu), com `toques` e `primeiro_toque_em`. Rede social apresentou +
  anúncio fechou é exatamente o que a ficha mostra.
- **Farol próprio, sem Google**: `POST /rastreio/evento` (`throttle:120,1`) recebe os cliques
  do `public/js/golden-site.js` (CTA, telefone, WhatsApp, e-mail, link externo, início de
  formulário, kit, sacola, checkout, pedido) com `fetch(..., credentials:'same-origin')`.
  Nada de terceiro na frente do cadastro.
- **Retenção**: `php artisan rastreio:podar` (agendado semanalmente em
  `bootstrap/app.php`) apaga o que passou de `golden.rastreio.dias_retencao`, junto com os
  eventos — sem depender do *cascade* do banco.
- **Trava de tamanho**: toda coluna recebe `Str::limit` no serviço, porque o SQLite ignora
  comprimento de `varchar` e o MySQL devolve *Data too long* no tráfego real.
- Testes: `tests/Feature/AtribuicaoDeOrigemTest.php` (20).

### Portal do cliente (`/portal`) — a casa do cliente sem pedir nada à equipe
- **Cinco telas**: *Início* (resumo com pedidos, investido, chamados abertos e contratos),
  *Pedidos* (linha do tempo da jornada e os documentos do pedido), *Chamados*, *Contratos*
  com o PDF e *Meus dados*. Folha própria (`public/css/golden-portal.css`) sobre o tema do
  site, sem cor nova e com o mesmo favicon.
- **Auto-cadastro que não duplica ficha**: `App\Support\PortalDoCliente` procura em
  `customers` primeiro pelo documento (normalizado, com ou sem máscara) e depois pelo
  e-mail; achou, entrega a senha à ficha existente — entre candidatas, a que tem mais
  pedidos ganha. Não achou, cria a ficha já ativa e `vincularPedidosSemFicha()` puxa os
  pedidos de convidado pelo e-mail. A conta são quatro colunas em `customers`
  (`email_portal` única em 190, `password`, `remember_token`, `ultimo_acesso_em`), não uma
  segunda tabela de usuário.
- **Duas sessões que não se atravessam**: guarda `cliente` sobre `customers` com chave
  `login_cliente`, contra a guarda padrão sobre `users`. Portal aberto não abre rota do
  painel, e vice-versa — mesmo com as duas no mesmo navegador.
- **Chamados** (`tickets` + `ticket_messages`): o cliente abre do portal, a equipe atende em
  *Admin → Atendimento → Chamados*. A conversa é uma fila só, então não existe "última
  resposta" para sincronizar entre as duas telas; a autoria sai de `user_id`/`customer_id`.
- **Contratos** (`contracts`): a equipe cadastra e anexa o PDF pelo dropzone (campo
  `contrato_documento`), e o status controla a leitura — **rascunho é invisível para o
  cliente**, inclusive no download, que passa por `/portal/contratos/{id}/arquivo` em vez de
  URL pública de `/storage`.
- **Recuperação sem broker**: link assinado (`URL::temporarySignedRoute`, 60 minutos), sem
  tabela de tokens, e a mesma frase para e-mail com ou sem conta.
- 404 (não 403) para recurso de outro cliente, honeypot, throttle por rota e consentimento
  LGPD no cadastro. `php artisan test`: `tests/Feature/PortalDoClienteTest.php` (18).

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
  Http/Controllers/{Site,Auth,Admin,Portal}/  # páginas, loja, captura de lead, login, painel, portal
  Http/Middleware/ControleAcesso.php     # role:admin,vendedor,...
  Http/Middleware/AutenticarCliente.php  # alias `cliente` -> guarda do portal
  Http/Middleware/LojaAtiva.php          # alias `loja` -> 404 quando a loja está off
  Http/Middleware/IdentificadorVisita.php
  Http/Middleware/RegistrarRastreio.php  # toda tela do site vira visita/evento
  Models/  Lead, Campaign, LeadStatusHistory, Activity, Setting, User,
           Customer, Interaction, Product, Category, Supplier,
           StockMovement, Order, OrderItem, OrderHistory, Page, Testimonial, Faq,
           Visit, VisitEvent, Ticket, TicketMessage, Contract
  Mail/    PedidoRecebido, PedidoNovoEquipe, ChamadoRecebido, ChamadoEquipe,
           ChamadoResposta, RedefinirSenhaPortal
  Services/LeadService.php               # atribuição + criação + funil
  Services/Atribuidor.php                # decide a origem (canal/rede/pago/motivo)
  Services/Rastreio.php                  # grava visita, evento e a atribuição do lead
  Console/Commands/PodarRastreio.php     # rastreio:podar, a retenção do registro
  Support/Carrinho.php                   # sacola em sessão, sobre o catálogo em cache
  Support/Envio.php                      # regras e gravação dos campos de arrastar-e-soltar
  Support/PortalDoCliente.php            # auto-cadastro: acha a ficha pelo documento/e-mail
  Support/helpers.php                    # brl(), loja_ativa(), slugify_pt()
config/golden.php                        # conteúdo do site + contatos reais
config/envios.php                        # formatos, limite, pasta e dimensão de cada campo de envio
database/migrations/                     # leads/campanhas + CRM + ERP + CMS + portal (chamados, contratos)
database/seeders/                        # Database, Catalog, Cms, Crm, Orders
deploy/instalador-golden.sh              # instalação em qualquer domínio (--help)
deploy/atualiza-golden.sh                # git pull + composer + migrate + caches
docs/INSTALACAO.md                       # guia completo em português (PDF: docs/gerar-pdf.py)
resources/views/{layouts,site,site/store,mail,auth,admin,portal}
public/{css,js,img}                      # tema do site e do painel; golden-upload.* e golden-to-top.*
tests/Feature/AtribuicaoDeOrigemTest.php # origem de cada clique, jornada, farol e poda
tests/Feature/FluxoDaLojaTest.php        # vitrine -> sacola -> checkout -> pedido -> painel
tests/Feature/PortalDoClienteTest.php    # auto-cadastro, duas sessões, chamado, contrato, recuperação
tests/Feature/CamposDeEnvioTest.php      # regras de upload, preview gravado, caminhos forjados
tests/Feature/LevezaDasPaginasTest.php   # zero consultas com cache quente
tests/Unit/DiretivasBladeTest.php        # diretivas Blade compiladas em todas as telas
tests/Unit/ExtensaoDoArquivoEnviadoTest.php  # a extensão vem do conteúdo, não do nome
```

`php artisan test` roda tudo contra SQLite em memória (220 testes, 1.576 asserções). Os
comprimentos de coluna, porém, só o MySQL fiscaliza — por isso toda migration nova é
conferida no servidor antes de virar entrega.

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
