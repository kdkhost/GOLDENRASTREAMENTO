# Changelog

Todo o registro de mudanças notáveis deste projeto. O formato segue
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/) e o projeto usa Versionamento Semântico.

## [2.16.0] - 2026-10-23

### Adicionado
- **Registro de imagens em `config/golden.php` — a única fonte das fotos do site** — 19 slots
  (`marca_logo`, `favicon`, `site_compartilhar`, as quatro fotos da home, os cinco segmentos e
  o cartaz de cada uma das sete páginas fixas), cada um com `pagina`, `titulo`, `padrao`, `alt`,
  `dimensao` e `proporcao`. Nenhuma view cita arquivo de imagem direto; quem precisa de uma foto
  pede pelo nome.
- **`config/golden.php` → `paginas_site`** — a copy de abertura das sete páginas fixas (rótulo do
  caminho, linha de destaque, título e subtítulo) passou a ter padrão registrado, editável
  do painel. A home fica de fora de propósito: a abertura dela é o layout aprovado, com `<em>`
  e quebras de linha, e o que se troca nela são as fotos.
- **`app/Support/ConteudoSite.php`** — resolve o que está no ar: o valor salvo em `settings`
  vence, o arquivo do pacote fica por baixo, e em branco significa "volta ao original". Também
  faz o atalho `hero_<pagina>` entre o nome da página e o slot do cartaz.
- **`site_img()`, `site_img_alt()`, `site_img_enviada()` e `site_pagina()`** em
  `app/Support/helpers.php` — as views leram tudo por essas quatro funções.
- **`Admin\SiteContentController` com duas telas novas** — *Conteúdo → Imagens do site*
  (`/admin/conteudo/imagens`) e *Conteúdo → Textos das páginas* (`/admin/conteudo/textos`).
  Na de imagens cada slot é um cartão com a foto que está no ar, o selo
  `foto do pacote` / `enviada pelo painel` / `sem foto no ar`, a dimensão pedida, o campo de
  arrastar-e-soltar e o texto alternativo; marcar **Voltar à foto original** apaga o envio e o
  arquivo do disco. Nenhuma chave é aceita do formulário — a lista vem do config.
- **Campo `site_imagem` em `config/envios.php`** — uma única regra de envio para os 19 slots
  (formatos, 6 MB e a pasta `site`); a dimensão recomendada, que muda de slot para slot, é
  mostrada pela tela, então a regra nunca diverge do que o servidor aceita.
- **`<x-site.img>`** — component de imagem do site: não renderiza nada se o slot não tem
  arquivo, e resolve `largura`/`altura` a partir do registro (`true` = valores do registro,
  número = valor explícito, `false` = omite).
- **`resources/views/site/partials/page-hero.blade.php`** — as sete páginas fixas tinham sete
  heróis escritos à mão; agora é um partial só, com o cartaz atrás do título.
- **Capa própria em cada página do CMS** — migration
  `2026_10_23_000005_da_capa_as_paginas_do_cms` cria `pages.imagem` e `pages.imagem_alt`;
  o formulário de página ganhou o campo de arrastar-e-soltar, a capa aparece no herói de
  `/p/{slug}` e vira o `og:image` daquela página.
- **`Setting::forget()`** — apaga a chave e derruba o cache e a memória estática; sem isso o
  "voltar ao original" só valia no próximo request.
- **`tests/Feature/ImagensDoSitePeloCmsTest.php`** — onze travas: nenhuma view cita
  `asset('img/`, todo slot tem o que a tela mostra, o envio vence o pacote (foto e alt), o
  cartaz só existe depois do envio, trocar apaga o arquivo anterior, voltar ao padrão devolve a
  foto **e** o texto do pacote, caminho de outro campo é recusado, os textos são editáveis e
  revertíveis, e a capa da página do CMS funciona de ponta a ponta.

### Corrigido
- **O título das sete páginas fixas estava invisível** — `h1,h2,h3,h4` pinta `color:var(--ink)`,
  que é quase preto, e `.page-hero` só definia a cor no `<section>`; como regra direta vence
  herança, "Escolha o tamanho da sua frota" e os outros seis títulos saíam escuros sobre o
  fundo escuro. O defeito era anterior a esta versão — a unificação dos heróis em um partial só
  foi o que o expôs. Agora `.page-hero h1{color:#fff}` resolve para as duas origens do herói.
- **O cartaz saía no fluxo em vez de ficar atrás do título** — a regra
  `.page-hero.com-foto>.foto` vale só para filho direto da `<section>`, e a `<img>` estava
  dentro de `.wrap`; na tela isso aparecia como um bloco de foto empurrando a página inteira
  para baixo. As duas chamadas do herói passaram a pôr a foto como filho direto, e o teste
  agora verifica essa posição.
- **Voltar à foto original deixava para trás o texto do envio** — o alt pertence à foto: ao
  reverter, a descrição digitada vai junto e volta o `alt` registrado para o pacote.
- **Callout das duas telas novas abria buracos no texto** — `.callout` é
  `display:flex; justify-content:space-between`, então a prosa solta virava vários itens de
  flex; agora o texto vai em um `<span>`, como no callout do dashboard.
- **Miniatura do slot recortava logo e favicon** — `object-fit:cover` num cartão largo cortava
  o miolo das artes quadradas; passou a `contain`, que mostra a peça inteira.

### Alterado
- **Todo o site público passou a tirar as fotos do CMS** — `layouts/site`, `home`, as sete
  páginas fixas, `site/pagina`, o login e o painel (logo e favicon) leem `site_img()`; os
  `asset('img/...')` que sobraram são pega por teste.
- `public/css/golden-site.css` recebeu o `.page-hero`, o `.page-cms` e o `.cms-body` que
  estavam escritos dentro de `@push('styles')` nas views.

## [2.15.0] - 2026-10-23

### Adicionado
- **`public/css/golden-theme.css` — a paleta única dos dois temas** — todos os tokens de cor
  do painel moram aqui: a marca (`--brand-*`), os pares `--t-<tom>-bg` / `--t-<tom>-fg` de
  cada tom semântico, as cores do gráfico (`--chart-*`) e as superfícies do login
  (`--login-*`). O bloco `[data-bs-theme="dark"]` redefine **cada** par para o fundo escuro,
  então nenhum chip, ícone ou barra fica com cor de tema errado. `admin-golden.css` e o novo
  `golden-login.css` não têm mais nenhum hexadecimal próprio.
- **`public/css/golden-login.css`** — a tela de acesso saiu do `<style>` de 23 linhas e passou
  a consumir os mesmos tokens; ela lê `golden_admin_theme` do navegador e abre no tema que o
  usuário deixou no painel.
- **Favicon própria do produto** — `public/img/favicon.png` (64×64) e um
  `public/favicon.ico` de três tamanhos (16/32/48) gerados a partir de `logo-golden.png`; o
  `.ico` que existia tinha zero bytes. O partial `resources/views/partials/favicon.blade.php`
  é a única fonte e está nos três documentos HTML (site, painel e login), então a mesma
  imagem aparece nos dois lugares.
- **`tests/Feature/TemasEIconesDoPainelTest.php`** — cinco travas: os três documentos usam o
  partial da favicon (e nenhum escreve `rel="icon"` próprio), os arquivos de ícone existem e
  não estão vazios, nenhuma view do painel escreve hexadecimal nem usa `bg-success` /
  `bg-secondary` / `btn-outline-dark`, os CSS do painel só usam tokens, e todo tom do tema
  claro tem par no escuro.
- **`title` / `icon` / `descricao` e slot `actions` em `<x-admin.table>`** — o cabeçalho do
  cartão da tabela passou a ser o mesmo do `<x-admin.card>`, sem precisar encaixar um cartão
  dentro do outro.

### Alterado
- **`/admin/dashboard` reformulada em seis zonas** — (1) os quatro KPIs de leads, (2) os
  quatro módulos integrados com o aviso da loja, (3) o gráfico de 30 dias ao lado do cartão
  "Para fazer agora" (novos, pedidos em aberto e estoque abaixo do mínimo, cada linha levando
  à fila correspondente), (4) o funil, (5) a tabela de campanhas e (6) a tabela de leads
  recentes. As listas soltas de antes viraram tabelas com o mesmo cabeçalho do resto do
  painel, os rótulos que quebravam em duas linhas foram encurtados e cada bloco passou a ter
  descrição do que mede.
- **Cores do gráfico vêm dos tokens** — o Chart.js não conhece mais `#c9862a`: ele lê
  `--chart-line`, `--chart-area`, `--chart-grid` e `--chart-tick` do CSS e se repinta no
  evento `golden:theme`, que o botão de tema do layout agora dispara. Trocar de tema com o
  dashboard aberto colore linha, área, eixos e rótulos sem recarregar.
- **Chips de situação, funil e menu do lado escuro** — `.st-*`, `.icon-*` (agora `tone-*`),
  as bolhas de Leads/Pedidos da sidebar e o selo de papel saíram dos `bg-warning` /
  `bg-info text-dark` do Bootstrap (que ficam com texto escuro sobre fundo claro no tema
  escuro) para os pares de tom. As barras do funil ganharam cor de marca sólida com texto
  legível, e a largura chega por `--w` em vez de estilo de apresentação.
- **`<h6>` solto e `alert` do Bootstrap na dashboard** — viraram `.zone-title` e `.callout`
  (`ok` / `warn`), que acompanham o tema.
- **Idioma do painel** — `APP_LOCALE` do `.env` local estava em `en` enquanto
  `.env.example` já pedia `pt_BR`; as datas relativas da dashboard saíam em inglês ("18 hours
  ago") ao lado do resto da interface em português.

### Corrigido
- **Rótulo do canal nas campanhas da dashboard** usava o chip azul de "novo"; agora é o tom
  neutro, que não confunde situação com origem.

## [2.14.0] - 2026-10-23

### Adicionado
- **Home reconstruída sobre a referência visual aprovada, com fotografia real** — nove
  imagens próprias em `public/img/home/` (central de monitoramento do herói, plataforma no
  desktop, rastreador J16, estrada ao pôr do sol e uma foto por segmento), todas em JPEG
  comprimido e com `width`/`height` + `loading="lazy"` (só o herói carrega primeiro).
  `public/img/` tinha apenas o logo: sem as imagens a home entregue não chegava perto da
  referência.
- **Onze seções na ordem da referência** — herói com foto de fundo, faixa de confiança e a
  marca no canto; "Por que escolher a Golden" (seis benefícios ao lado do título);
  aplicativo e plataforma sobre fundo escuro; "Para quem é" em cinco cartões com foto e
  texto sobreposto; kits com o card do meio destacado; equipamento J16 com a lista de
  verificações; como funciona; depoimentos; dúvidas frequentes em duas colunas; e o CTA
  final sobre a estrada com a marca no canto.
- **Faixa do equipamento J16** — `config('golden.equipamento_info')` guarda título, texto,
  imagem e as quatro verificações (homologação Anatel, 4G/2G multioperadora, kit com
  rastreador/chip/relé/chicote, variedade de modelos). É a única fonte: `/` e `/solucoes`
  leem do mesmo lugar.
- **`tests/Feature/HomeDaReferenciaTest.php`** — trava a entrega que falhou antes: percorre
  a home e exige os marcadores das seções da referência, que toda imagem referenciada no
  HTML exista em `public/` e que os cinco segmentos tenham fotografia própria.
- **Utilitários de layout em `golden-site.css`** — `.split` / `.split-larga` / `.split-txt`
  (título de um lado, conteúdo do outro), `.hero-foto` + `.hero-marca`, `.seg-foto`
  (cartão com foto, brilho no hover e gradiente para leitura), `.ben-grid`, `.checks`,
  `.destaque` e `.cta-final.cta-foto`. Nada de estilo inline: as faixas escuras empilham em
  uma coluna abaixo de 960px e o `prefers-reduced-motion` desliga o zoom das fotos.

### Alterado
- **Faixa de confiança vive dentro do herói** — os quatro selos agora ficam sob o texto,
  com o ícone acima da legenda, como na referência; o bloco `.trust` saiu do fundo cinza
  próprio para a faixa do herói.
- **Home sem a grade de atalhos** — cada seção da referência já leva à sua página dedicada
  (plataforma → `/solucoes`, segmentos → `/para-quem`, kits → `/planos`, J16 → loja,
  passos → `/como-funciona`, dúvidas → `/faq`), então a grade repetitiva saiu junto com o
  CSS dos mockups de tela.
- **`PageController::home()` entrega o conteúdo das seções** — benefícios, segmentos, kits,
  passos, equipamento e as cinco primeiras dúvidas. Tudo de `config()` ou do cache de
  conteúdo: o contrato de leveza continua em pé (14 consultas no máximo com cache frio,
  zero com cache quente).
- **Cartões de kit mostram o número sozinho** — `config('golden.kits')` ganhou a chave
  `numero`, porque `/planos` montava `{{ $kit['titulo'] }} <small>veículos</small>` e
  renderizava "5 veículos veículos". Agora é "5 / veículos" como na referência.

## [2.13.1] - 2026-10-23

### Adicionado
- **`php artisan app:define-senha`** — recupera o acesso ao painel pela SSH, sem depender
  de memória nem do próprio painel: `app:define-senha admin@golden.com.br 'nova-senha'`,
  com a senha digitada no prompt (sem eco) se o argumento for omitido e `--reativar` para
  religar uma conta desligada. E-mail errado não derruba nada: o comando lista os e-mails
  cadastrados. Senha menor que 8 caracteres é recusada antes de gravar, e a troca fica na
  trilha de auditoria (`senha_redefinida`). Testes em `RecuperarSenhaDoPainelTest`.

### Alterado
- **A documentação diz onde a senha do servidor está e como trocar** — `README.md` e
  `docs/INSTALACAO.md` deixam claro que `admin@golden.com.br` / `golden123` vale **só no
  ambiente local**; em produção o instalador grava a senha forte em
  `~/backups-golden/acesso-painel.txt` (600, fora do docroot) e a tela de `/login`, desde a
  2.12.1, não mostra mais nenhuma credencial — por isso a tabela de diagnóstico do guia
  ganhou a linha "a senha do painel 'não funciona' no servidor".

## [2.13.0] - 2026-10-23

### Adicionado
- **Componentes de tela do painel** (`resources/views/components/admin/`): `field`, `switch`,
  `card`, `table`, `filters` + `filter`, `stat` e `delete`. São a única fonte do padrão visual
  de formulário do painel — todo `<input>` sai com `.form-label` acima, `id="f-<nome>"`,
  `invalid-feedback` do Bootstrap e o mesmo `maxlength` da regra de validação do controller.
- **Uma página de cadastro por módulo**: `campanhas/form`, `clientes/form`, `categories/form`,
  `suppliers/form`, `faqs/form`, `testimonials/form`, `users/form` e `products/estoque`
  (movimentação de estoque com saldo, mínimo e histórico). As rotas `create`/`edit` e
  `admin.clientes.edit` / `admin.campanhas.edit` existem agora de verdade; nenhum outro lugar
  repete o formulário — quem precisa cadastrar um cliente a partir de um pedido recebe um link
  com os dados já preenchidos pela query string.
- **`PaginasDoPainelTest`**: percorre toda rota `admin.*` de GET, loga como administrador e
  exige 200 em cada uma (36+ páginas), confere os filtros das listas e o recorte do vendedor
  (5 páginas liberadas, 5 proibidas). É o teste que pega nome de rota errado em view, prop
  desconhecida em componente ou variável que esquecemos no controller — nada disso aparece no
  `view:cache`.

### Corrigido
- **Exportar leads (`/admin/leads/exportar`) dava 500** — o middleware
  `IdentificadorVisita` gravava o cookie de atribuição chamando `$response->cookie()`, método
  que só existe na resposta do Laravel; a resposta do `streamDownload` (CSV) é uma
  `StreamedResponse` do Symfony e não tem esse método. O cookie agora entra por
  `Cookie::queue()`, que é anexado ao download por estar num middleware externo — qualquer
  arquivo baixado (CSV, planilha, nota) passa a sobreviver à barreira de consentimento.
- **Campos do checkout fora do padrão da plataforma** — `/loja/checkout` tinha `<label>`
  embrulhando o `<input>`, sem `for`/`id`, sem `type`/`inputmode`, sem limite de caracteres e
  com estilos em linha (`h1 style="font-size:1.7rem"`). Agora cada campo é um `.field` com
  rótulo próprio (`coNome`, `coTelefone`, `coDocumento`, `coEntrega-*`…), `type` e `inputmode`
  certos (telefone, e-mail, CEP numérico), `maxlength` espelhando a validação, mensagem de
  erro sob o campo e o formulário inteiro sem `style=""` — o dourado dos ícones, o aviso de
  não-cobrança e a dica de frete grátis saíram do atributo e viraram regras CSS
  (`.retira-note i`, `.pix-key i`, `.sum-hint i`, `.checkout-aviso`), e `golden-site.css`
  ganhou `.field-title`, `.form-err` e o dimensionamento de rádio/checkbox dentro de `.field`.

### Alterado
- **As 27 telas do painel migraram para os componentes**: nada de
  `style="border-radius:16px"`, card montado à mão, `<table>` cru ou modal por linha. As listas
  usam `x-admin.table` (com linha vazia automática e cabeçalho `center:`/`end:`), os filtros
  usam `x-admin.filters` num formulário GET com botão limpar, e os indicadores usam
  `x-admin.stat` com link. As colunas do funil ganharam rótulos e cores próprias
  (`.funnel-label`, `.fb-novo`…`.fb-perdido`) em `admin-golden.css`.
- **Menu reorganizado por área de trabalho**: Visão geral (Dashboard) · Comercial (Leads,
  Clientes, Campanhas) · Vendas (Pedidos, Produtos e estoque, Categorias, Fornecedores, link
  "Loja no site") · Conteúdo do site (Páginas, Depoimentos, FAQs) · Sistema (Configurações,
  Usuários, Auditoria). O interruptor ON/OFF da loja, que estava duplicado na sidebar e em
  Configurações, ficou só em Configurações; os contadores de pendência saíram de dentro do
  layout (dois `count()` + `Setting::all_values()` a cada requisição) para um composer de view
  com memoização por requisição (`$pendencias`), e o rodapé do painel mostra a versão do PHP e
  o driver do banco em vez de texto fixo.
- **Consultas fora das views**: `Lead::where(...)->count()` e afins deram lugar a variáveis do
  controller; os rótulos de canal de campanha viraram uma única fonte em
  `Campaign::CANAIS` / `canalLabel()`; o rótulo de status de lead já vinha de
  `Lead::STATUS_LABELS`.
- **`x-admin.table` aceita atributos** (`$attributes->merge`), então `class="mt-3"` e
  `mb-3` funcionam; ícones do FontAwesome Pro que desenhavam caixa vazia
  (`fa-truck-field`, `fa-external-link-alt`) foram trocados pelos equivalentes livres.

## [2.12.1] - 2026-10-23

### Corrigido
- **`deploy/atualiza-golden.sh` parava em "requires php ^8.3" mesmo com o 8.3 no
  servidor** — o script escolhia o primeiro `php` que houvesse, e em hospedagem
  compartilhada esse é a versão do seletor da conta (aqui, 8.2); o `composer` do plano
  roda no mesmo `php` do PATH, então o `composer install` barrava antes das migrations.
  A procura agora começa pelos binários versionados (`ea-php83`, `ea-php84`, `php8.3`,
  caminhos do CloudLinux/cPanel) e aceita só quem tem 8.3 ou mais; o Composer é sempre
  chamado **com o PHP escolhido** (`$PHP_BIN composer.phar`), e um `--php=` antigo demais
  derruba o script com a mensagem dizendo qual versão foi encontrada e onde mudar.
- **A tela de login não entrega mais a credencial de demonstração em produção** — o aviso
  "Ambiente de demonstração / admin@golden.com.br · senha: golden123" era impresso para
  qualquer visitante do `https://dominio/login`, mesmo no servidor de produção, onde o
  instalador já trocou a senha por uma forte. Além de entregar o e-mail do administrador,
  o texto dava a entender que a senha padrão ainda vale. Agora ele só aparece com
  `APP_ENV=local`, que é quando o seed de demonstração está de fato em uso.

## [2.12.0] - 2026-10-23

### Corrigido
- **Arquivo enviado pelo painel devolvia 403** — o `.htaccess` de proteção gerado na
  instalação bloqueava o caminho `storage` junto com as pastas de código-fonte, e é por
  esse caminho que o link `public/storage` serve imagem, vídeo e documento. A regra de
  bloqueio agora alcança só `app`, `bootstrap`, `config`, `database`, `resources`,
  `routes` e `tests`; o que precisa ficar fechado continua fechado, porque todo pedido é
  reescrito para dentro de `public/` (o vínculo público aponta para `storage/app/public`,
  então `storage/logs`, `storage/framework` e `storage/app/private` não têm como ser
  alcançados) e a regra de `.log` segue valendo. Conferido em produção: upload pelo
  painel → URL `https://…/storage/produtos/…png` respondeu `200` com `image/png`, e
  `.env`, `app/Kernel.php` e `storage/logs/laravel.log` seguiram em `403`.

### Adicionado
- **`--php-web=ea-php83` no instalador** — resolve o caso do PHP da web ser outro que o do
  SSH (o erro 500 `Your Composer dependencies require a PHP version ">= 8.3.0"`), que em
  hospedagem CloudLinux/LiteSpeed não se corrigia pelo `.htaccess` até aqui. A opção grava
  `AddType application/x-httpd-<versão> .php` no `.htaccess` da raiz, escolhe o handler
  `.php` do servidor e assume sobre o seletor da conta. Foi assim que a instalação em
  `public_html` saiu de PHP 8.1 para **8.3.35 (litespeed)** sem depender do painel: o
  seletor da conta parava no 8.2 (`cloudlinux-selector get --interpreter php
  --get-supported-versions`), mas o `ea-php83` estava instalado e o handler funcionou.
- **Checagem final do instalador** — depois de tudo, ele abre o próprio `--url` com
  `curl`, e se a resposta for o erro de plataforma avisa na tela que o PHP da web é outro e
  mostra a linha `--php-web=` que resolve; resposta 200 vira ✔ e qualquer outra coisa
  aponta o log do Laravel.
- **Documentação do PHP da web em `docs/INSTALACAO.md`** — a seção 2 explica os dois PHPs
  do servidor, receita o handler do `.htaccess` com o teste do arquivo temporário, e traz o
  comando `cloudlinux-selector set … --extensions '{"fileinfo":"enabled",…}'` para acender
  `fileinfo`/`intl` (o estado é `enabled`/`disabled`, e a lista substitui a anterior —
  informe tudo de uma vez). As seções 4.7 e 12 explicam por que `storage` não vai para a
  lista de bloqueio e como testar o arquivo enviado.

### Alterado
- A mensagem de erro do instalador para PHP antigo não diz mais "mudar o `.htaccess` não
  resolve"; `README.md` traz o `--php-web=` no exemplo de instalação, e os nomes de conta
  fictícios voltaram aos exemplos.

## [2.11.0] - 2026-10-23

### Adicionado
- **`deploy/instalador-golden.sh`** — a instalação do sistema em qualquer domínio
  (cPanel, VPS ou container) passa a ser um comando só. Ele confere PHP 8.3+ e as
  extensões, instala o Composer se faltar, gera o `.env` de produção (com `chmod 600`
  e `key:generate`), roda `composer install --no-dev`, prepara o banco, grava o login
  do painel, liga `public/storage`, escreve o `.htaccess` de proteção da raiz e ativa os
  caches de config/rotas/views. Nada de credencial fica no script: senha entra por
  variável de ambiente (`GOLDEN_DB_PASSWORD`, `GOLDEN_ADMIN_PASSWORD`) ou é pedida na
  tela sem aparecer. `--help` traz as opções: `--url`, `--banco=mysql|sqlite`, `--host`,
  `--porta`, `--nome`, `--usuario`, `--senha`, `--criar-banco`, `--admin-email`,
  `--admin-nome`, `--admin-senha`, `--sem-demo`, `--limpar-banco`, `--sem-backup`,
  `--dev`, `--sem-htaccess`, `--sem-cache`, `--php=`, `--composer=`.
  - Antes de apagar tabelas de um banco que já tem outro sistema, o instalador tira
    `mysqldump` `.gz` para a pasta **acima** do projeto — fora do que o navegador serve —
    com permissão `600`, e só então roda `migrate:fresh`. Sem terminal ele exige a flag
    explícita `--limpar-banco`; com terminal, pede para digitar `SIM`.
  - Cada comando é gravado em `storage/logs/instalador-<data>.log`. Quando algo falha, o
    instalador para, mostra o comando e as últimas 25 linhas do log em vez de morrer em
    silêncio — foi assim que o bug de status abaixo apareceu na primeira corrida real.
- **`deploy/atualiza-golden.sh`** versionado — `git pull --ff-only`, `composer install
  --no-dev`, `migrate --force`, `storage:link` e os caches, com `--ramo`, `--php=` e
  `--sem-git` para o caso de publicar por upload.
- **`docs/INSTALACAO.md`** — documentação completa em português: requisitos, o PHP 8.3 no
  MultiPHP do cPanel (e por que `.htaccess` não resolve), instalação pelo script,
  instalação manual passo a passo, criação de banco no painel, `.htaccess` de entrega,
  referência de todas as chaves do `.env`, loja e campos de envio, backup e restauração,
  tabela de problemas comuns, segurança e checklist de saída.
- **`docs/Golden-Rastreamento-Instalacao.pdf`** — o mesmo conteúdo numa PDF de identidade
  do projeto (capa escura e dourada, tabelas e blocos de código no tema), gerado por
  **`docs/gerar-pdf.py`**, que imprime o Markdown com Edge/Chrome em modo sem interface.
  Fonte única: mexeu no `docs/INSTALACAO.md`, roda o script e o PDF acompanha.

### Corrigido
- **Status do pedido maior que a coluna quebrava o MySQL.** `orders.status` e
  `order_histories.status` paravam em `varchar(16)` e `aguardando_pagamento` tem 20
  caracteres: no SQLite nada acontece, no MySQL o seed morria em `Data too long for
  column` e o checkout morreria no mesmo ponto. A migration
  `2026_10_23_000004_alarga_status_dos_pedidos.php` leva as duas colunas para 32, e
  `test_todo_status_do_pedido_sobrevive_ida_e_volta_no_banco` grava e lê cada status do
  fluxo (85 testes no total).

### Alterado
- `README.md` — a seção de servidor passa a começar pelo instalador, com o guia
  `docs/INSTALACAO.md` por trás; a lista de estrutura cita `deploy/` e `docs/`.
- Exemplos de instalação deixaram de citar conta de hospedagem, banco e domínio reais: o
  repositório é público e dado de servidor não entra nele.

## [2.10.0] - 2026-10-23

### Adicionado
- **Campos de envio com arrastar-e-soltar em todo o painel** — imagem do produto, vídeo do
  produto e documentos do pedido. Cada campo aceita arrastar arquivos para dentro dele, mostra
  o preview do que subiu, o progresso real do envio com o tempo decorrido, uma animação curta
  de processamento e a regra do campo escrita dentro do próprio campo (formatos, tamanho
  máximo e dimensão ideal).
  - `config/envios.php` — a regra única de cada campo: tipo, pasta de destino, formatos,
    limite em MB, dimensão e proporção ideais. É a mesma fonte que imprime a dica na tela e
    que o servidor valida; nada de cópia à mão.
  - `app/Support/Envio.php` — monta a dica legível ("JPG / JPEG / PNG / WEBP · até 4 MB ·
    ideal 1200 × 1200 px (1:1)"), grava o arquivo, descreve o arquivo para o preview
    (bytes, tamanho formatado, largura × altura) e confere caminho: só aceita valor que
    comece na pasta do próprio campo, sem travessia, e que exista no disco público.
  - `app/Http/Controllers/Admin/UploadController.php` — destino único (`POST` /
    `DELETE` `/admin/uploads`, `throttle:40,1`). O navegador manda o arquivo e a **chave** do
    campo; a pasta nunca vem do pedido, então não há como escolher onde escrever.
  - `resources/views/partials/upload.blade.php` + `public/css/golden-upload.css` +
    `public/js/golden-upload.js` — o componente: zona de arraste (com elevação quando o
    arquivo está em cima), miniatura/anexos, barra de progresso com *shimmer*, chips dos
    arquivos múltiplos e o rodapé "Carregado em 151 ms · 6 KB · 1280 × 720 px".
  - `database/migrations/2026_10_23_000003_envios_de_midia.php` — colunas que dão realidade a
    "vídeos" e "documentos": `products.video` e `orders.documentos` (JSON).
  - Testes: `tests/Feature/CamposDeEnvioTest.php` (16), `tests/Unit/ExtensaoDoArquivoEnviadoTest.php` (3),
    `tests/Feature/EstoqueDoProdutoTest.php` (3) e `tests/Feature/NomesDeRotaTest.php`, que varre o
    projeto atrás de `route('nome')` inexistente.

### Corrigido
- **Salvar um produto com ajuste de estoque dava 404.** `StockMovement::registrar()` recebia um
  *clone* com `setRawAttributes(['estoque' => …], true)` para representar o saldo anterior — o
  que apaga a chave primária — e o `refresh()` de dentro explodia em
  `ModelNotFoundException`. Agora o saldo anterior entra como parâmetro explícito.
- **Criar ou editar uma página do CMS morria no redirecionamento** (`Route [admin.paginas.index]
  not defined`); o nome certo é `admin.conteudo.paginas.index`. O mesmo tipo de erro existia em
  `ProductController` (`admin.produtos.index` → `admin.catalogo.produtos.index`).
- Gravação de arquivo: a extensão no disco é a **do conteúdo** do arquivo, e o envio é recusado
  se ela não estiver entre os formatos do campo — o nome que o navegador manda não decide nada,
  porque um script PHP batizado de `capa.jpg` acabaria gravado como `.php` dentro de
  `public/storage`, executável. A barreira fica em `Envio::guardar()` (a gravação em si), e o
  endpoint devolve a recusa no mesmo formato JSON que o campo já mostra na tela.
- O nome gravado em disco voltou a ser legível (`rastreador-j16-original-H4k2p9Qz.png`): o
  caminho é o que sobra no pedido meses depois, e hash puro não diz o que é o arquivo.
- Toda tela do painel agora mostra um painel de erros de validação no topo — antes, uma
  recusa do servidor podia não aparecer em lugar nenhum do formulário.

### Alterado
- A vitrine continua com **zero consultas**: o vídeo do produto entrou no blob de catálogo
  (`golden.loja_catalogo`), e a leitura é `$produto['video'] ?? null` para uma cache antiga não
  quebrar a página.
- O formulário de produto deixou de ser `multipart`: o arquivo sobe sozinho, no seu ritmo, e o
  formulário guarda só o caminho devolvido pelo endpoint.
- `resources/views/admin/layouts/app.blade.php` carrega `golden-upload.css`/`.js` junto do
  componente ao topo, e a curva de movimento compartilhada (`cubic-bezier(.22,.61,.36,1)`) +
  `prefers-reduced-motion` valem para as animações de upload também.

## [2.9.0] - 2026-10-23

### Adicionado
- **Botão "voltar ao topo" no painel administrativo**, com o mesmo anel que preenche ao descer e
  esvazia ao subir. O painel rola pela própria janela, então o comportamento é idêntico ao do
  site: aparece depois de 320px, o clique sobe suave (instantâneo com
  `prefers-reduced-motion`), e o anel fica no canto inferior direito (`right:22px`), acima da
  sidebar (`z-index:1050`).

### Alterado
- O botão deixou de ser código do site e virou **componente compartilhado**, para os dois lados
  partirem da mesma implementação — sem segunda cópia da curva e da matemática do rolagem:
  - `resources/views/partials/to-top.blade.php` — o markup, incluído por `layouts.site` e
    `admin.layouts.app`.
  - `public/css/golden-to-top.css` — geometria, anel, hover, foco, mobile e
    `prefers-reduced-motion`, parametrizados por variáveis (`--to-top-size`,
    `--to-top-accent`, `--to-top-track`, `--to-top-face`). Cada tema só ajusta a posição: o site
    mantém `.to-top{right:92px}` para liberar a bolha do WhatsApp, o painel usa o padrão.
  - `public/js/golden-to-top.js` — o `initToTop` que estava dentro de `golden-site.js`, agora
    autoinicializado a partir do `#toTop` presente na página.
  - `golden-site.css` perdeu as 13 linhas de geometria duplicadas e `golden-site.js`, a função.

## [2.8.1] - 2026-10-23

### Removido
- **Numeração de passos** — o pedido explícito é não exibir isso em lugar nenhum do sistema.
  - `/como-funciona`: o contador dourado `01 / 02 / 03 / 04` acima de cada ícone saiu da view,
    as chaves `'n'` saíram de `config('golden.passos')` e a regra `.step .n` saiu do CSS. O
    passo continua com ícone, título e texto.
  - Checkout da loja: os `legend` eram `1. Quem compra`, `2. Entrega`, `3. Pagamento`,
    `4. Observações`; agora são só os nomes, sem o número na frente.

## [2.8.0] - 2026-10-23

### Adicionado
- **Loja com fluxo comercial completo**: vitrine → sacola → checkout → pedido numerado com
  link público → linha do tempo → gestão no painel.
  - `app/Support/Carrinho.php`: sacola na sessão do visitante, resolvida contra o catálogo em
    cache — adicionar, trocar quantidade e ver o total não custam consulta ao banco. A
    quantidade é limitada à prateleira publicada (999 quando o produto não tem controle de
    estoque) e item que sai da vitrine some da sessão.
  - **Checkout em quatro etapas**: quem compra (pessoa física/jurídica com CPF 11 / CNPJ 14
    dígitos), entrega (endereço obrigatório quando a modalidade é entrega), pagamento (Pix,
    cartão, boleto, pagamento na retirada) e observações. Inclui consentimento LGPD gravado
    (`consent_given`/`consent_em`), campo-mel (`website`) e *throttle* próprio por ação.
  - `order_histories` + `App\Models\OrderHistory`: toda mudança de status vira um marco com
    autor e recado do painel. Alimenta a "Linha do tempo" do cliente (`Order::jornada()`) e o
    histórico da tela do pedido.
  - **Página pública do pedido** `/loja/pedido/{numero}/{token}`: token de 32 caracteres no
    caminho, gerado na criação — sem o link recebido, o pedido não é enumerável. `token` e
    `ip_hash` ficaram em `Order::$hidden`.
  - **Confirmação por e-mail** (`App\Mail\PedidoRecebido` com botão "Acompanhar meu pedido" e
    `PedidoNovoEquipe` para a equipe quando `loja_email_equipe` estiver preenchido), enviados
    em `queue()` dentro de `try/catch`: falha de SMTP nunca anula a venda.
  - **Regras da loja no painel** (Configurações → Loja): `loja_livre` (frete grátis / combinar
    entrega), frete fixo, frete grátis acima de um valor, chave Pix, retirada na loja e e-mail
    da equipe — rota `admin.settings.loja-regras`, separada da chave de ligar/desligar.
  - Numeração de pedido derivada do próprio id (`GR26-00007`) dentro da transação, com valor
    provisório no `creating` — evita o `MAX()+1` que duplica sob pico de campanha.
  - Migração `2026_10_23_000002_aprimora_fluxo_da_loja`: colunas de comprador/entrega em
    `orders`, `order_items.sku`, tabela `order_histories`, e reescreve o funil antigo
    (`em_andamento` → `separando`) com a linha do tempo de volta.

### Alterado
- **Vitrine, página de produto, sacola e checkout não consultam o banco**: o catálogo ativo
  inteiro passou a morar num blob `golden.loja_catalogo` (`Product::catalogoParaSite()`),
  invalidado pelos eventos de `Product`/`Category`. Preço e estoque só são relidos dentro do
  `DB::transaction` que grava o pedido — é ele que barra a venda quando a prateleira esvaziou
  por fora do painel. `LevezaDasPaginasTest` agora cobre `/loja`, `/loja/{slug}`,
  `/loja/carrinho` e o checkout com sacola cheia.
- Topo do site ganhou a sacola com contador (`carrinho_itens` no pacote `$golden`) com
  animação de *pop*, e o `main` passou a renderizar o *flash* de sucesso/erro.
- Funil de pedido virou estado de verdade: `Order::STATUS`/`JORNADA`
  (pendente → aguardando pagamento → pago → em separação → enviado → entregue → cancelado) e
  `Order::ABERTOS`, usados pelo painel e pelos contadores do *dashboard*.
- Produto de plano (sem valor à vista) mostra só a mensalidade; antes aparecia "R$ 0,00".

### Corrigido
- `GET /loja/checkout` com a sacola vazia dava **500**: o método era tipado `: View` e o
  caminho de carrinho vazio devolvia um `RedirectResponse` (`View|RedirectResponse` agora, com
  teste próprio).
- **Todos os botões "Excluir" do painel gravavam como POST.** `@csrf@method('DELETE')` cola a
  diretiva na palavra anterior e o Blade não a compila: o texto `@method('DELETE')` saía na
  tela e a rota `DELETE` respondia 405. Corrigido nas nove listagens (produtos, categorias,
  fornecedores, FAQs, páginas, depoimentos, campanhas, usuários e pedidos) e no `Nota@else`
  da linha do tempo de lead; `tests/Unit/DiretivasBladeTest.php` varre as 41 telas para o
  padrão não voltar.
- `Carrinho::gravar()` tinha o teto de estoque calculado duas vezes, com ramos que podiam
  divergir.

### Testado
- `tests/Feature/FluxoDaLojaTest` (9 cenários / 88 asserções): vitrine com filtros, jornada
  completa até o pedido numerado, estoque baixando com razão no `stock_movements`, cliente
  entrando no CRM (e reaproveitando cadastro existente), barreiras de documento/consentimento/
  campo-mel, confereção de estoque real na transação, regras de frete vindas do painel,
  mudança de status no painel alimentando a linha do tempo pública e loja desligada virando
  404.

## [2.7.0] - 2026-10-23

### Adicionado
- **Abertura animada de página**: cada `main > section` entra com `pageIn` (fade + 14px de
  subida) em 340ms na mesma `cubic-bezier(.22,.61,.36,1)` do menu, da gaveta e da central de
  WhatsApp, com *stagger* de 50/100/140ms entre os primeiros blocos. O restante da página já
  entra pronto, sem tela em branco.
- **Botão "voltar ao topo" com anel de progresso**: `#toTop` no rodapé do layout, com um
  `<circle pathLength="100">` cujo `stroke-dashoffset` é escrito pelo scroll — a borda dourada
  **preenche conforme a página desce e esvazia no mesmo ritmo ao subir** (reverso natural, sem
  estado separado). Aparece depois de 320px com fade+scale, e o clique sobe suavemente
  (`behavior:'smooth'`).
  - `initToTop()` em `public/js/golden-site.js`: listener `passive`, atualização limitada a
    ~60Hz por marcação de tempo (sem *flag* de rAF, que trava em aba de fundo) e
    `scrollHeight` lido só em carga, *resize* e após 500ms parado — o caminho quente não força
    *layout*.
- **Páginas seguintes abrem mais rápido**: `initPrefetch()` insere
  `<link rel="prefetch">` no primeiro gesto (*pointerover*/*focusin*) sobre um link do mesmo
  *origin* — uma vez só por URL, ignorando `_blank`, `download`, âncora, hash e as rotas
  `/admin|/login|/logout`. Desligado com `save-data` ou conexão 2g.

### Alterado
- `.to-top` fica à **esquerda** do botão flutuante do WhatsApp (`right:92px` / 80px no mobile)
  para nunca cobrir a central de canais; a barra de cookies ganhou `right:158px` para não passar
  por baixo dos dois.
- Bloco `prefers-reduced-motion` inclui `main>section{animation:none}` e os novos
  `.to-top`/`.to-top-face`; com a preferência ligada o anel continua preenchendo (é
  consequência do scroll), só sem transição, e a subida ao topo é instantânea.
- `semMovimento` virou constante única do arquivo (usado pelo carrossel de depoimentos, pelo
  anel e pelo *scroll* suave), em vez de cada *init* consultar `matchMedia`.

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
