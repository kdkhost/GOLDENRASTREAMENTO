# Changelog

Todo o registro de mudanças notáveis deste projeto. O formato segue
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/) e o projeto usa Versionamento Semântico.

## [2.55.0] - 2026-10-29

### Adicionado
- **A Ana ganhou rosto.** A foto da assistente entrou na ficha (`avatar` + `avatar_alt`) pelo envio de
  sempre (`Envio::guardar`, jpg/png/webp, 2 MB, 1:1) e aparece no balão do site, no funil e nos
  chamados. A imagem é retrato de estúdio com fone de ouvido, fundo escuro e luz dourada, entregue em
  512×512 JPEG.
- **Ficha pronta para trabalhar.** Nome **Ana**, cargo **Consultora de rastreamento veicular**, tom
  descolado, todos os textos de conversa preenchidos (saudação, nome, e-mail, confirmação de cadastro,
  avaliação, despedida), dica **"Falar com a Ana"**, status **"Online agora"**, placeholder
  **"Escreva sua mensagem…"**, saída humana **"Prefiro falar com uma pessoa →"** e ícone
  `fa-headset`. Tudo com o mesmo valor padrão em `config/assistente.php` e editável em
  `/admin/assistente`.
- **Treinamento ampliado: 13 assuntos novos, 30 no total.** Entraram planos e valores (J16 4G/2G,
  moto, kit chicote, relé, pânico, chip M2M, mensalidade e Plano Frota), o que é preciso para
  funcionar, app e plataforma, cercas virtuais e alertas, frota/locadora/rural, formas de pagamento,
  endereço e canais, pós-compra, privacidade e dados, segunda via, compatibilidade de veículos,
  manutenção do rastreador e perguntas frequentes. Os itens usam os placeholders do painel
  (`:whatsapp`, `:email`, `:telefones`, `:portal`, `:cidade`, `:servidor_1/2`, `:financeiro`), então
  telefone, e-mail e endereço saem sempre do cadastro da empresa, nunca escritos à mão.
- **Regras da persona mais firmes.** A Ana não promete instalação, bloqueio ou garantia em nome da
  empresa (a instalação é contratada e paga direto ao profissional do cliente), orienta boletim de
  ocorrência e canal de emergência em roubo/furto, não pede CPF no chat e não inventa endereço,
  telefone ou condição.

### Corrigido
- **Cumprimento junto com o nome escalava para a equipe.** "Boa noite, meu nome é Marcelo" era
  reconhecido como nome, mas `Nome::soONome()` recusava a saudação e a conversa caía em `sem_fonte`.
  As saudações entraram em `APRESENTACOES`: agora a pessoa recebe a confirmação da ficha e a conversa
  segue normalmente.
- **"Prefero" no balão.** O padrão e o placeholder do painel passaram a escrever **"Prefiro falar com
  uma pessoa →"**.

### Operação
- **Gemini atendendo; GPT sem créditos.** A chave da OpenAI é válida, mas a conta responde 429
  `insufficient_quota` ("no credits remaining"); a conversa, a visão, o documento e a transcrição seguem
  em `gemini/gemini-3.1-flash-lite`, escolhido no painel. Quando houver saldo, trocar a rodada em
  Configurações → Assistente não exige deploy.

### Testes
- `NomeNaConversaTest` ganhou o caso do cumprimento com o nome (confirmação da ficha, sem escalação);
  `BolhaDoAssistenteTest` acompanhou o texto novo do balão.

## [2.54.1] - 2026-10-29

### Operação
- **Chave do Google Gemini trocada no painel.** A chave anterior foi excluída por engano no AI Studio;
  a nova ("Golden Atendimento Ana") foi gravada cifrada em Configurações → Chaves
  (`assistente_gemini_key`, origem banco) e provada com uma geração real antes de valer.
- **Conversa, visão, documento e transcrição passaram para `gemini/gemini-3.1-flash-lite`.** O
  `gemini-3-flash-preview` na cota gratuita permite 20 pedidos por dia e estourou durante os testes
  (HTTP 429), o que fazia a Ana escalar por `provedor_com_erro`. A escolha fica no banco
  (`assistente_rodada_*`) e pode ser trocada na mesma tela quando o faturamento do projeto Google for
  ativado.
- **Assistente religado no site e no funil.** A ficha estava com `ativo` desligado desde as 19:09;
  voltou ligada e o atendimento foi provado de ponta a ponta em produção.
- **OpenAI sem créditos.** A chave `assistente_openai_key` responde 429 "no credits remaining"; o
  atendimento segue só no Gemini até haver saldo ou outro provedor.

## [2.54.0] - 2026-10-29

### Adicionado
- **A ficha do usuário virou um perfil completo.** `cargo`, `setor`, `bio` e `admissao` entraram na
  tabela `users` e no formulário; o painel de Configurações abre as suas abas de perfil, mas a ficha da
  pessoa não existia como retrato — agora existe. A busca da lista de usuários também acha por cargo e
  setor, e a lista mostra `cargo · setor` embaixo do e-mail. A migration
  `2026_10_29_200001_da_um_perfil_completo_ao_usuario` cria as colunas.
- **Foto do usuário.** O campo `usuario_avatar` (jpg/png/webp, 2 MB, 1:1) usa o dropzone de sempre; sem
  foto, o painel desenha as iniciais do nome. A foto aparece na prévia da ficha, na lista da equipe e no
  topo do painel, e trocar ou excluir o usuário apaga a imagem antiga do disco. O valor que chega do
  formulário é conferido por `Envio::caminhoConfere()`, então um caminho forjado não vira avatar.
- **Último acesso de verdade.** Toda abertura de sessão grava `ultimo_acesso_em` e `ultimo_acesso_ip`
  (por `marcarAcesso()`), e quem entrou antes da coluna existir continua vendo a data pela auditoria —
  `edit()` cai no último `login` registrado em `activities`.
- **Cadastro em abas.** A ficha passou a ter as abas Acesso, Perfil, Segurança e Atividade (estas duas
  só em ficha existente). Segurança mostra segundo fator, e-mail verificado, último acesso com IP e as
  janelas abertas em nome da pessoa; Atividade mostra tempo de casa, leads sob cuidado, última
  atualização e os 12 movimentos mais recentes, com atalho para a auditoria filtrada. A barra de salvar
  fica no pé, diz o que é gravação e o que é leitura, e o envio continua em segundo plano
  (`golden-salvar.js`), com a aba de erro aberta automaticamente.

### Testes
- `FichaDoUsuarioTest` cobre as abas, os campos novos, o avatar (envio, troca com limpeza, recusa de
  caminho de outra pasta, exclusão junto do usuário), as iniciais sem foto, o registro do último acesso
  no login e o conteúdo das abas de Segurança e Atividade.

## [2.53.0] - 2026-10-29

### Adicionado
- **O e-mail dito no meio da conversa agora localiza o cadastro.** Antes ele era só anotado no fio; agora
  `Identificacao::vincular()` normaliza o endereço, procura a ficha em `Customer::porEmail`, amarra a
  conversa ao cliente e completa nome e contato que estiverem em branco. Quem é reconhecido ouve isso na
  hora — a frase nova `confirmacao_cadastro` ("Achei o seu cadastro, :nome…"), editável no painel como
  todas as outras —, e a equipe vê no fio a linha de sistema com o número e o nome da ficha. O e-mail é
  lido **em qualquer ponto** da conversa, inclusive com a equipe já no comando ou a máquina desligada: a
  identificação não depende de a IA estar respondendo.
- **A conversa pode ser encerrada pela pessoa.** Encerrar pede a nota primeiro (a pesquisa não pode morrer
  com o fio), grava a linha de sistema, fecha a ação em andamento e marca a conversa como `fechada`. A
  partir daí o campo de envio sai de cena, o anexo perde o pulso e a bolha mostra o convite a começar outra
  — reabrir sozinha seria a tela contrariando o fim que a pessoa pediu. As estrelas continuam valendo para
  quem fechou sem dar nota.
- **Anexar arquivo virou campo de verdade, e o documento passou a valer.** O clipe manda a peça pelo
  endpoint `/assistente/arquivo`, com o limite de tamanho lido de `Midia::bytesDe()` (a mesma regra que o
  aceita), e cada tipo segue por onde sabe ser lido: imagem e documento vão ao provedor de visão/leitura
  com a legenda como pergunta, áudio é transcrito primeiro e a transcrição entra como se tivesse sido
  digitada. O envio de documento passou a vir **ligado** (`assistente.permissoes.documento`), e o
  interruptor do painel continua mandando; arquivo recusado volta com a frase da regra quebrada no toastr,
  sem fechar a bolha.

### Alterado
- **A Ana fala como gente.** Os textos padrão da ficha foram reescritos (saudação, nome, e-mail, pedido de
  nota, agradecimento, despedida) e as regras ganharam pé: respostas de duas a quatro frases, sem
  repetir a saudação a cada volta, sem repetir a pergunta já respondida, uma pergunta por vez e nada de
  preço, prazo ou condição sem lastro. A migration `2026_10_29_100001_a_ficha_reconhece_o_cadastro` troca
  só os textos que ninguém editou — quem escreveu a própria frase no painel continua com ela. Ela reconhece
  as duas impressões digitais dos padrões antigos (o de 2.44 a 2.52 e o de 2.38 a 2.43), porque uma
  instalação que ficou parada numa versão antiga ainda carrega o texto dela.
- **A conversa deixou de esquecer o assunto na segunda pergunta.** O fio entregue ao modelo passou a 16
  falas e a ordem da conversa diz explicitamente que o atendimento está em andamento; pergunta curta
  ("e a instalação?") herda o assunto da anterior em vez de virar uma pergunta solta sem contexto.
- **O campo de envio é uma cápsula só.** Texto, clipe e enviar dividem a mesma moldura com anel dourado no
  foco; o mesmo componente serve para o site e o funil. A conversa rola sozinha para a última fala a cada
  resposta, a cada anexo e ao abrir — ninguém precisa puxar o scroll para acompanhar.
- **Arquivo recusado fala a língua do cliente.** As mensagens de `Midia::recusa()` deixaram de citar o
  painel e dizem "O envio de documento não está disponível neste atendimento", com o rótulo certo por tipo
  (áudio, imagem, documento).

### Corrigido
- **Quem se apresenta e dá o e-mail na mesma frase é reconhecido, não escalado.** "Oi, sou a Maria. Meu
  e-mail é maria@exemplo.com" é uma apresentação inteira: `Identificacao::eApresentacao()` desconta o
  endereço, as palavras de apresentação e o nome que a pessoa acabou de dizer, e, se não sobra assunto, a
  máquina confirma o cadastro com a fala fixa em vez de mandar a frase ao provedor — que sem fonte
  respondia "já chamei a equipe" para quem só tinha chegado. Perguntas de verdade com e-mail no meio
  ("meu email é ..., quero a segunda via") continuam seguindo o caminho normal.
- **O nome não engole mais a frase seguinte.** O ponto final passou a encerrar a captura dos padrões de
  nome: em "sou a Maria. Meu e-mail é ..." o nome é "Maria", e não "Maria. Meu e-mail é teste" — que
  estourava o teto de palavras e deixava a pessoa anônima na ficha.
- **A leitura de documento não devolve mais o rascunho do modelo.** O Gemini 3 pensa antes de responder e
  o pensamento é cobrado no mesmo teto de tokens da fala; num documento grande o orçamento acabava no
  meio do raciocínio, e o cliente recebia um começo de frase em inglês. O pedido passou a usar
  `thinkingConfig.thinkingLevel = minimal`, as partes marcadas como pensamento são descartadas na
  leitura e uma resposta cortada (`finishReason` diferente de `STOP`) vira falha — a conversa escala com
  a frase da casa em vez de mostrar frase pela metade.
- **O arquivo enviado virou a única fonte da resposta sobre ele.** A ordem da chamada de documento e de
  imagem passou a dizer que a resposta sai do que o arquivo mostra e que cláusula, valor, prazo ou
  condição que não estejam nele não podem ser inventados; o que faltar, a Ana oferece à equipe em vez
  de completar com conhecimento geral.
- **Documento de texto passou a ser lido de verdade.** O Gemini não lê `text/plain` nem `text/csv` em
  `inlineData`: recebia o anexo e respondia como se nada tivesse sido enviado — e completava o contrato
  com cláusulas que ninguém escreveu. `.txt`, `.csv` e `.docx` (cujo texto mora num XML dentro do
  pacote) agora são lidos pelo sistema e entregues ao modelo como texto, com o nome do arquivo na
  frente; PDF continua indo como anexo, que é o formato que ele lê. Quanto do arquivo se aproveita é um
  teto novo no painel (`assistente_documento_caracteres`), com 30 mil caracteres na casa — o excedente é
  omitido com um aviso para a resposta não inventar o que ficou de fora.
- **Resposta de erro não fecha mais a bolha.** O JavaScript tratava qualquer retorno sem `ativo` como
  "máquina desligada" e desaparecia com o widget; uma recusa de arquivo (422) ou um CSRF vencido (419) agora
  mostram a frase da regra e a conversa continua na tela.
- **Começar outra conversa não apaga mais o fio antes da hora.** A tela só limpava depois do `/nova`
  responder; quando o pedido falhava, a pessoa ficava com a bolha vazia e a conversa antiga perdida.

### Testado
- `BolhaDoAssistenteTest` cobre o documento lido com a legenda como pergunta e com o arquivo como fonte
  única, o `.txt` indo ao modelo como texto (sem `text/plain` em anexo) e o `.docx` lido de dentro do
  pacote, a imagem indo ao provedor de visão, o áudio transcrito seguindo a conversa e a pergunta curta
  herdando o assunto e o histórico; `IdentificacaoEAvaliacaoPeloAssistenteTest` prova o vínculo pelo
  e-mail, a apresentação com e-mail confirmada sem chamar o provedor, a confirmação de cadastro, a nota
  das estrelas e o encerramento com a pesquisa já respondida; `SegundaViaPeloAssistenteTest` acompanha o
  fio pelo e-mail até a segunda via; `RespostaDoGeminiTest` garante o pedido de pensamento curto, o
  descarte do rascunho e a falha da resposta cortada. Suite completa: 731 testes, 11.966 asserções.

## [2.52.0] - 2026-10-29

### Corrigido
- **As chaves estavam certas e o robô não respondia porque os modelos tinham sido aposentados.** Toda
  capacidade estava presa na geração Gemini 2.5 (`gemini-2.5-flash`, `gemini-2.5-pro`,
  `gemini-2.5-flash-preview-tts`), e o Google passou a responder **404 "não está mais disponível para novas
  contas"** para esses nomes — o `Motor` convertia o 404 na frase de escalada, e o cliente lia "já chamei
  alguém da equipe" logo depois de a chave ter sido salva. O log de chamadas provava: desde a gravação das
  chaves não houve **uma única** chamada à IA. A lista do painel passou aos modelos vivos (conversa, visão e
  documento no `gemini-3-flash-preview`, voz no `gemini-3.8-flash-tts`, com as alternativas testadas uma a
  uma com a chave da casa) e o padrão da casa acompanhou.
- **Modelo que sai do catálogo não cala mais o atendimento.** `Fabrica::rodada()` confere a escolha gravada
  no painel contra a lista daquela capacidade e, quando o provedor aposentou o nome, cai no padrão da casa
  em vez de deixar a chamada morrer — a tela de chaves avisa que a escolha ficou no passado e mostra em que
  modelo o atendimento está até alguém escolher outro. A troca de geração acontece sem aviso; o sistema
  agora se cura sozinho.
- **A pergunta comprida deixou de esconder a fonte.** A cobertura da busca era dividida pelo número de
  palavras da pergunta, e "quero testar a plataforma tem como?" ficava em `0,3333` contra um piso de `0,34`
  mesmo com um item cujo título é "plataforma" — o cliente ouvia a frase de escalada. A palavra que o
  administrador pôs no **título ou nos assuntos** agora ancora o item e vence o piso; a palavra achada só no
  corpo continua devendo a cobertura mínima, porque no corpo toda palavra aparece.
- **Cortesia não vai mais para a fila da equipe.** "Oi, tudo bem?" era mandado ao provedor sem fonte e o
  piso de fonte obrigatória transformava a saudação em "chamei a equipe". `Busca::eCortesia()` reconhece as
  falas feitas só de saudação (ao menos uma palavra de cortesia e nenhuma de conteúdo), o `Motor` as isenta
  da fonte obrigatória e a ordem da conversa diz ao modelo que ali é só cumprimentar — sem inventar preço,
  prazo ou condição. "Tem como?", feita só de palavras vazias, **não** é cortesia e continua devendo fonte.
- **"Boa tarde" deixou de ser tratado como despedida.** O padrão de despedida da avaliação aceitava um
  cumprimento solto, e quem abria a conversa com "boa tarde" ou "boa noite" recebia o pedido de nota e o
  atendimento fechava antes de começar — pior ainda em "boa tarde, quero saber o preço", que é pergunta. O
  cumprimento só conta como adeus quando a fala inteira é cortesia, e a primeira fala do cliente nunca
  encerra o atendimento.

### Testado
- `BuscaDoAssistenteTest` prova a âncora do título, o piso ainda valendo para o corpo e a fronteira entre
  cortesia e pergunta de conteúdo; `BolhaDoAssistenteTest` fecha uma saudação de ponta a ponta (provedor
  chamado uma vez, conversa sem escalada e a ordem de saudação no pedido); `ChavesDeIntegracaoTest` grava um
  modelo aposentado e confere que a chamada cai no padrão e que a tela avisa. Suite completa: 714 testes.

## [2.51.1] - 2026-10-29

### Corrigido
- **A lista "Equipe e segunda chave" dizia "desligada" para quem tinha a chave acesa.** A consulta da tela
  de Segurança pedia `two_factor_confirmed_at` mas não pedia `two_factor_secret`, e `User::doisFatoresAtivo()`
  exige as duas colunas: sem a primeira carregada, o model respondia `false` para todo mundo. O card do topo
  (que lê a conta da própria sessão) dizia *Acesa* enquanto a linha do colega dizia *desligada* — e o botão de
  apagar a chave de quem perdeu o celular, que só aparece numa linha acesa, nunca era desenhado. A tela
  mentia justamente para a única pessoa com poder de consertar.

### Testado
- `AutenticacaoDoisFatoresTest::test_a_lista_da_equipe_distingue_quem_tem_a_chave_acesa` acende a chave de um
  colega pela camada de dentro e confere, na tela pedida como administrador, as duas etiquetas e o enlace do
  botão de apagar na conta certa. Suite completa: 707 testes.

## [2.51.0] - 2026-10-29

### Adicionado
- **A porta do painel ganhou fechadura dupla, e ela inteira se configura na tela.** `config/seguranca.php`
  traz os números (`tentativas`, `janela_minutos`, `bloqueio_minutos`, `dois_fatores`, `sessao_minutos`,
  `sessoes_simultaneas`, mais a tolerância do TOTP e o prazo do desafio), os seis campos novos em
  **Sistema → Configurações → Chaves de integração → Segurança do acesso** mandam neles primeiro
  (grupo `seguranca` do `ChavesDeIntegracao`, gravado no banco e aplicado no começo de cada pedido), e o
  `.env` fica como plano de fundo de quem ainda não preencheu nada — é o que faz uma instalação recém-clonada
  já nascer blindada, sem depender de alguém lembrar de configurar antes da primeira senha errada.
- **Força-bruta tem porteiro próprio**: `App\Services\Seguranca\VigiaDeAcesso` conta em dois baldes
  separados, **por e-mail e por endereço de rede** (um escritório inteiro atrás do mesmo roteador não trava
  a conta de um colega; quem troca de rede não zera o próprio contador), fecha conta e endereço pelo prazo
  da casa com piso de um minuto, e na batida que tranca avisa a dona da conta por **e-mail**
  (`App\Mail\TentativasDeLogin`: endereço, navegador, hora e o prazo) **e** pelo sino
  (`acesso_suspeito`, uma vez por hora, sem encher a caixa de entrada de quem está sendo testado). Cada
  batida, o trava e o destrava deixam linha na Auditoria.
- **Dois fatores de verdade (TOTP)**: `App\Services\Seguranca\DoisFatores` + `DoisFatoresController` e as
  telas `admin/dois-fatores/*` e `auth/dois-fatores`. O QR é **desenhado pela própria página em SVG**
  (`bacon/bacon-qr-code`) — um PNG em `storage/app/public` seria a chave secreta de alguém publicada em
  URL, segurada pelo cache do navegador depois da saída. O segredo **só entra no banco depois que um código
  real bate**, cifrado pelo cast `encrypted` do model, e a pessoa recebe **oito códigos de recuperação**
  (`XXXXX-XXXXX`, `encrypted:array`) mostrados uma vez só, que abrem a porta **uma** vez e se apagam. A
  senha certa sozinha para na **meia-porta** (`/login/dois-fatores`, com prazo de dez minutos, guardando só
  a identidade e sem o "lembrar de mim", que é uma porta que se abre sozinha); o campo do código é contado
  pelo mesmo porteiro, porque seis dígitos sem limite são um milhão de combinações; e a política da casa
  (*ninguém* / *só administradores* / *toda a equipe*) obriga por `GarantirDoisFatores` (`dois_fatores`),
  levando quem ainda não tem chave à **ativação**, não à barra na porta — por isso a tela de acendimento
  mora fora do prefixo `/admin`, para o caminho não virar laço.
- **Roubo de sessão deixou de ser cego**: a migration `2026_10_29_000001_armam_a_porta_do_painel` cria
  `sessoes_do_painel` (usuário, carimbo do aparelho, rede, navegador, último sinal) e as três colunas do
  dois fatores em `users`. O driver de sessão é arquivo e a hospedagem não entrega o conteúdo das sessões
  alheias — sem esta tabela, "encerrar o notebook do escritório" não teria o que encerrar.
  `App\Http\Middleware\AtestarSessaoDoPainel` consulta a lista em **todo** pedido da equipe: janela
  encerrada está encerrada de verdade, a aba esquecida fecha com o tempo de inatividade e o teto de
  aparelhos derruba a mais antiga. Somado a `$middleware->authenticateSessions()`, **trocar a senha fecha
  todas as janelas daquela conta**, inclusive a que alguém abriu com a cookie roubada antes da troca.
- **Tela nova: Sistema → Segurança do acesso** (`Admin\SegurancaController`, `admin/seguranca/index`),
  aberta a todo perfil do painel — as janelas e a chave são de quem está logado. Quatro números no topo
  (janelas abertas, tentativas na janela, segunda chave, sessão sem uso), "Minha segunda chave" (acender,
  renovar, ver a folha, desligar com código), "Janelas abertas nesta conta" com **Encerrar esta janela** e
  **Sair das outras janelas**, e para o administrador: "Os limites da casa", "Equipe e segunda chave" com
  o botão de apagar a chave de quem perdeu o celular, "Destravar uma conta" e os movimentos da última
  semana. O que é da casa leva `role:admin` na rota **e** conferência no controller — esconder o botão na
  view não é autorização.
- **Toda resposta da casa sai carimbada**: `App\Http\Middleware\CabecalhosDeSeguranca`, anexado ao grupo
  global (não só `web`), porque os pontos de entrada anônimos — login, portal, captura de lead — são
  justamente onde um navegador é induzido a confiar numa página que não é nossa. CSP com `default-src
  'self'`, `frame-ancestors 'self'`, `object-src 'none'`, `base-uri 'self'` e **`form-action 'self'`** (o
  que impede um campo de texto com HTML colado de mandar o `csrf_token` do administrador para um endereço
  de fora no próximo "Salvar"), `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: strict-origin-when-cross-origin`, `Cross-Origin-Opener-Policy: same-origin`,
  `Permissions-Policy` desligando câmera/geolocalização/`interest-cohort` e `Strict-Transport-Security` só
  quando a conexão é cifrada.
- **O upload não vira programa**: `storage/app/public/.htaccess` (`RemoveHandler`, `Require all denied`,
  `-ExecCGI`, `-Indexes`) no único diretório da instalação que o público alcança, com a entrada no
  `.gitignore` da pasta para o arquivo sobreviver ao `composer install`.
- **A ajuda acompanha o que foi criado**: sete gavetas novas em `config/guia_configuracao.php` (o grupo +
  os seis campos, cada uma com a frase do que é, a ordem para configurar e o atalho para a tela onde o
  efeito se vê) e a seção **"Segurança do acesso"** em `config/guia_painel.php`, com o prefixo
  `admin.seguranca.` no mapa de rotas — sem isso a tela nova existiria sem quem explicasse.
- Dependências: `pragmarx/google2fa` (^8.0) e `bacon/bacon-qr-code` (^3.0). O instalador passou a escrever
  `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true` e `SESSION_SAME_SITE=lax` quando a URL começa com
  `https://` (em http:// ele avisa em vez de marcar o cookie que o navegador descartaria).

### Corrigido
- **Editar o próprio perfil voltou a salvar.** `<x-admin.field>` com `:travado` desenhava `disabled`, e
  campo desabilitado não viaja no pedido: `role` e `status` simplesmente não chegavam ao controller, a
  validação os exigia e o "Salvar" do administrador na própria ficha não gravava nada — nem nome, nem
  telefone, nem avatar, nem senha. O campo travado agora mostra o valor **dentro** do controle
  (`readonly` + `aria-readonly`) e envia por baixo um escondido com o valor que já estava no banco, então a
  pessoa guarda o que é seu sem conseguir escrever o que é da casa.
- **`two_factor_recovery_codes` saía como texto e voltava como lista.** O cast era `encrypted`, que chama
  `openssl_encrypt` com o array e morria em `TypeError`. Passou a `encrypted:array`, que entra como lista e
  sai como lista, cifrada.
- **A tela de Segurança quebrou no primeiro `diffForHumans()`.** `MAX(created_at)` é consulta bruta: o banco
  devolve texto, nunca Carbon, e a "última entrada" da equipe derrubava a página em produção. O corte agora
  acontece uma vez, no controller, para a view receber data de verdade.
- **As telas do dois fatores obedecem ao guarda do painel** (`auth:web`). Com o `auth` solto, uma sessão de
  portal — outra tabela, `Customer`, sem segredo nenhum — batia em `/dois-fatores/configurar` e devolvia
  500 em `Customer::doisFatoresAtivo()`; a política da casa não alcança a vitrine, nem por engano.

### Testado
- Quatro suítes novas, **45 testes**: `ForcaBrutaDoLoginTest` (11 — os dois baldes, o prazo, o piso de um
  minuto, o aviso por e-mail e no sino, o contador que envelhece), `AutenticacaoDoisFatoresTest`
  (14 — a senha que não abre sozinha, o segredo gravado só com prova, a cifra no banco lida na coluna crua,
  o código de recuperação que vale uma vez, o limite do campo do código, a meia-porta que expira, a
  política por perfil, a folha que aparece uma vez e a renovação que queima a anterior, o admin que desliga
  e o colega que recebe 403, e o portal fora do alcance), `BlindagemDaSessaoTest` (15 — a cookie
  `HttpOnly`/`SameSite`/`Secure`, o identificador trocado no login, os carimbos de cada resposta, a CSP
  conferida contra os endereços que os próprios layouts carregam, HSTS só sob HTTPS, a janela registrada com
  aparelho e rede, encerrar/sair-das-outras/aba esquecida/teto de aparelhos, a troca de senha que fecha as
  janelas e o arquivo enviado que não vira código) e `PerfilTravadoTest` (5).
- `tests/TestCase.php`: `be()` agora limpa o carimbo de janela e as cifras de senha da sessão quando o teste
  troca de personagem. Sem isto, `authenticateSessions()` derrubava o pedido seguinte de quem nunca abriu
  janela — 29 erros que eram o teste atravessando duas janelas com um corpo só, não o sistema.
- **Suite completa verde: 706 testes, 11 781 asserções.**

### Documentado
- `docs/INSTALACAO.md`: a seção 21 virou 21.1–21.5 (a porta e seus três números, a segunda chave com as oito
  regras que a fazem ser de verdade, o que cada carimbo de cabeçalho impede, e os testes que travam cada
  item); as linhas `SEGURANCA_*` entraram na referência do `.env` (seção 7); quatro linhas novas no quadro
  de problemas (porta travada, código do aplicativo que não bate, "sessão encerrada por segurança" e o
  upload no `/storage`); e sete itens no checklist depois de instalar, todos conferíveis por `curl` sem
  abrir o DevTools. O PDF de instalação foi regenerado da mesma fonte.

## [2.50.1] - 2026-10-29

### Corrigido
- **A criptografia do SMTP que estava no banco chegava crua ao transporte.** `ChavesDeIntegracao::aplicar()`
  jogava o valor gravado direto em `mail.mailers.smtp.scheme` sem passar por `esquema()` — só o `.env`
  era traduzido. Um `ssl` guardado por uma versão anterior da tela, ou um `tls` colado no campo,
  chegava ao Symfony como nome que ele não conhece: `The "ssl" scheme is not supported`, e cada
  disparo do sistema caía, do link de senha do portal à segunda via da fatura, sem que ninguém
  tivesse tocado em código. A `mail_scheme` ganhou `cast` próprio: o valor sai traduzido para o
  transporte **e** entra traduzido no banco, então o que a pessoa vê no campo amanhã é o nome que o
  transporte vai usar. Palavra sem tradução nenhuma devolve `null`, que é o estado em que a porta
  decide.

### Configurado em produção
- **A caixa de disparos do domínio novo voltou a autenticar.** `disparos@goldenrastreamento.com.br`
  existia na conta, mas a senha gravada no painel era a da instalação antiga (`535 Incorrect
  authentication data`). Definida senha nova pela própria hospedagem (`uapi Email passwd_pop`), a
  gravação subiu pelo caminho da tela — `mail_mailer`, `mail_host`, `mail_port`, `mail_scheme`,
  `mail_username`, `mail_password`, `mail_from_address`, `mail_from_name` **as oito** com
  `origem=banco` — e o `.env` ficou como plano de fundo coerente (465 + `smtps`). A prova de disparo
  respondeu **O disparo está no ar**, sem devolução na caixa, e o aviso que estava preso em
  `failed_jobs` (`ConversaEscalada`) saiu com `queue:retry all`; a tabela voltou a zero.

### Testado
- `ChavesDeIntegracaoTest` ganhou o caso da linha antiga do banco: `ssl` gravado antes desta regra
  sai `smtps` do `config()`, ` TLS ` digitado na tela é guardado como `smtp`, e palavra
  irreconhecível deixa o transporte deduzir pela porta. Suite completa verde: `656 testes`, 11 031 asserções.

### Documentado
- `docs/INSTALACAO.md`, seção 7.1: **preencha as oito chaves, não cinco** — a tela diz de onde veio
  cada valor, e deixar usuário e remetente só no `.env` é o que faz o painel parecer
  meio-configurado; o par que a hospedagem atende hoje (465 com `smtps`, 587 com STARTTLS); e duas
  linhas novas no quadro de problemas (`535 Incorrect authentication data`,
  `The "ssl" scheme is not supported`). O PDF de instalação foi regenerado da mesma fonte.

## [2.50.0] - 2026-10-29

### Adicionado
- **Gravar não leva a tela embora.** Todo `<form>` de escrita do painel e do portal saiu do
  navegador e passou por `public/js/golden-salvar.js`: o mesmo POST, respondido em JSON por
  `App\Http\Middleware\ResponderEscritaAjax` (folha nova, última do grupo `web`). A pessoa clica, o
  botão mostra o trabalho, o *toastr* sai na hora e a tela continua onde estava — o *scroll*, a aba
  aberta, o hash do endereço e o que foi digitado. São **71 formulários cobertos por uma camada só**:
  nenhum controller foi tocado, porque eles continuam devolvendo o redirect de sempre e é esta folha
  que o traduz para o pedido assíncrono.
- **O que precisa de valor novo volta do servidor no lugar.** O formulário declara a região em
  `data-atualiza` (`.app-content` ou `.app-content-header`) e ela é trocada por um GET discreto — o
  selo de status, a linha do tempo do chamado, o contagem e a paginação da listagem, o resultado da
  sonda. Um GET por gravação, um aborte se a pessoa gravar de novo antes da volta: nada de
  empilhar recarga nem de congestionar a hospedagem compartilhada.
- **Quem cria é levado para a listagem, sem piscar.** `POST /campanhas` grava e devolve
  `/campanhas`, que é o endereço do próprio POST: comparar o destino com a requisição diria "fica onde
  está" e a pessoa continuaria diante do formulário vazio. O navegador manda a tela em que está no
  cabeçalho `X-Golden-Tela`, e é contra ela que o destino é medido — a ficha nova aparece na listagem,
  o título e o menu acompanham, e nenhuma navegação acontece.
- **Erro de validação vermelha no lugar, sem apagar nada.** O motivo sai separado por campo
  (`->withErrors()` do controller e o 422 do `validate()`), a aba onde o campo está é aberta, o foco
  volta para ele e a rolagem o centraliza. Os textos de 400 caracteres de *Textos do site* ficaram
  exatamente como foram digitados.
- **Ato destrutivo continua perguntando, e o "sim" grava em segundo plano.** O SweetAlert2 do
  `data-confirmar` pede licença, o POST sai depois da resposta, e a linha excluída some da listagem
  com a contagem do cabeçalho corrigida — tudo sem recarga.
- **O portal obedece à mesma folha.** Abrir chamado, responder na ficha e gravar os dados do cliente
  passam pelo mesmo caminho: a conversa nova entra na linha do tempo, o campo volta vazio do servidor,
  e o cliente não vê a tela saltar.

### Alterado
- **`components/admin/delete` já nasce com a região declarada** (`:atualiza="'.app-content'"`), então
  toda exclusão do sistema — campanha, usuário, bloco, contrato — devolve a listagem refeita.
- **Um formulário que não declara região não pede nada.** É o caso dos campos cujo valor gravado é o
  que a pessoa acabou de escrever: a tela já diz a verdade, e um GET a mais por nada seria
  exatamente o congestionamento que se quis evitar.
- **O que precisa do navegador de verdade ficou de fora, por decisão escrita.** Download de CSV e de
  contrato, o OAuth do Google Ads, entrar e sair, e as telas do Livewire saem com `data-sem-ajax` (ou
  não são de escrita). E se a tela de destino exige um script que esta página não tem — o painel de
  métricas do Google Ads é o caso — o navegador vai buscá-la de fato: trocar por cima deixaria a
  página muda, e tela quebrada pisca muito mais que tela carregada.
- **A recarga de região remonta o que vive na tela.** Depois da troca sai `golden:trocou`, e as abas
  do `admin-abas.js` renascem no grupo recém-chegado — a aba continua a mesma, sem a escuta dobrada.

### Testado
- `tests/Feature/SalvarEmSegundoPlanoTest.php` (12 testes, 182 asserções): o JSON que o navegador
  recebe, o redirect que continua chegando para o navegador comum, o recado que não aparece duas
  vezes, o motivo por campo, o destino de quem muda de tela, quem cria e não continua no formulário,
  a chave de sessão que a tela lê depois (`sonda`), as duas regiões que o painel sabe trocar e cada
  contrato de `data-atualiza` / `data-sem-ajax` nas views. **655 testes, 11 028 asserções, todos
  verdes.**
- Bancada real (Chrome de cabeça para baixo, `storage/app/bancada-salvar.mjs`, fora do
  repositório): oito gravações de verdade — medição, pausar, excluir, criar, erro de validação,
  ficha do lead com a tela rolada, abrir chamado e responder no chamado — e em **nenhuma** houve
  navegação de documento; o *scroll* de 300 px continuou em 300 px e o valor gravado voltou lido do
  banco.

## [2.49.0] - 2026-10-28

### Adicionado
- **A ajuda de cada item, dentro de um ícone.** Cada campo, cartão e *switch* das sete abas de
  *Configurações* e dos oito grupos de chaves de integração ganhou o círculo de interrogação miúdo ao
  lado do rótulo — 102 itens documentados, dos três credenciais do WHMCS a cada um dos interruptores
  de veiculação e a cada capacidade da Ana. A gaveta do item diz o que aquilo é, a ordem em que se
  faz, e **onde a credencial nasce**: 82 botões de endereço levando à API do Google Ads, ao AI Studio
  e à OpenAI, ao cPanel de e-mail, ao Google Business Profile, à tela de API do próprio WHMCS e à
  conversa de teste no número. Passo a passo nenhum aparece na tela antes de ser pedido.
- **Onde o item não tem nome de campo, ele se anuncia.** `x-admin.field`, `x-admin.switch` e
  `x-admin.campo-chave` procuram a ajuda pelo próprio nome, então a view de tela nenhuma precisa
  lembrar de chamá-la; `x-admin.card` recebe `ajuda="chave"` para cartão e grupo. Item sem entrada
  escrita em `config/guia_configuracao.php` não mostra ícone — é assim que o mecanismo vale para todas
  as telas sem obrigar nenhuma delas a falar de ajuda.
- **Endereço que não dá para montar some da gaveta.** `rota` vira o endereço real daquela rota e
  `{host}`, `{whmcs}`, `{whatsapp}` saem do que o painel já gravou; faltando a informação, o botão sai
  junto com o marcador. É melhor a pessoa não ver o botão do que ver um botão que não leva a lugar
  nenhum — e nada de endereço pela metade chega à tela.
- **A ajuda não pesa em quem não pede.** Cada corpo já vem na página dentro de um `<template>` que o
  navegador não desenha; `public/js/golden-ajuda.js` copia o escolhido para o corpo da gaveta e abre a
  *offcanvas* à direita, sobre a tela, sem rede e sem meia tela carregando. O formulário fica onde
  estava, e todo ícone é `type="button"` — dentro de `<form>`, um botão sem tipo enviaria tudo no
  lugar de abrir a ajuda.

### Alterado
- **O cabeçalho do painel para de falar.** A linha de texto "Como usar esta tela" virou o mesmo ícone
  discreto ao lado do título, abrindo a seção inteira do guia na gaveta; o `aria-label` continua
  dizendo *Como usar esta tela: \<nome da seção\>*, para quem lê com leitor de tela.
- **A descrição que estourava o título da tela de Configurações saiu por inteiro.** O parágrafo
  explicando as sete abas foi apagado da view: a mesma informação agora mora no ícone da tela e no
  ícone de cada aba, e o cabeçalho volta a ser título e botões.
- **Um bloco novo no guia: `links`.** `App\Support\GuiaConfiguracao` monta `texto → passos → links →
  aviso` a partir do config, e o partial compartilhado `admin/guia/partials/blocos` desenha os botões
  dourados — guia e gaveta com os mesmos sete tipos de bloco, a mesma folha e o mesmo texto.
- **A leitura da tela, nos testes, é a parte desenhada.** `Tests\TestCase::visivel()` tira os
  `<template>` antes de conferir o que uma tela oferece. Sem isso, o guia — que cita o botão
  **Ver a conta do cliente** e o **Revincular ao financeiro** pelo nome — faria um teste de
  veiculação passar com o botão escondido. `PortalSupervisaoTest` e `RevinculoWhmcsTest` passaram a
  medir o HTML visível nos dois lados, e `GuiaDoPainelTest` cobra o cartão de abertura na parte
  desenhada e o corpo da ajuda guardado.

### Testes
- `tests/Feature/AjudaDeConfiguracaoTest.php` (9 novos) — cada grupo e cada campo de integração com
  gaveta escrita; cada segredo com o endereço de onde nasce; cada rota citada ainda existindo e cada
  `url` em https ou marcador da casa; passo sem número; botão que não envia formulário; texto em
  português sem marca crua; o link dependente de informação ausente; e a tela de Configurações
  oferecendo a ajuda sem despejá-la na frente de ninguém. Suite: 643 testes, 10 841 afirmações.

### Documentação
- README (*Guia de uso*) e `docs/INSTALACAO.md` (seção 14) reescritos para a ajuda por item: o ícone
  do cabeçalho, a gaveta, `config/guia_configuracao.php` com `App\Support\GuiaConfiguracao`, o bloco
  `links` e os marcadores que se enchem sozinhos. O PDF da instalação é gerado da mesma fonte.

## [2.48.1] - 2026-10-28

### Corrigido
- **O histórico volta três horas.** O 2.48.0 consertou o fuso a partir dele; o que já estava no
  banco continuou com o instante errado, porque a hospedagem acorda em UTC e o MySQL vive em UTC−3.
  A fala da Ana gravada às 22:30 de Brasília estava registrada como `01:30`, e é ela que a fila, a
  ficha do lead e o relatório do portal mostram. `App\Support\FusoHistorico` atrasa o que o próprio
  sistema carimbou, na conta que a parede do banco devolve.
- **A seleção não é um `UPDATE` cego: são duas metades provadas.** Valor **abaixo da virada**
  (`2026-10-27 22:46:45`, a hora em que o 2.48.0 assumiu a produção) é prova basta — linha do regime
  novo não pode valer menos que o instante em que ela nasceu. Entre a virada e a virada + 3h o valor
  sozinho não diz nada (o velho já adiantado cai exatamente na hora do novo); ali quem decide é a
  **sequência de ids**, que é a ordem real de gravação: a maior volta de pelo menos uma hora dentro
  da faixa é o momento em que o código novo assumiu, e o corte vem impresso no relatório. Sem volta
  clara, nada é mexido na faixa — a linha é contada e fica para um humano, porque chute aqui custa
  dado certo.
- **O que não é relógio adiantado fica onde está.** `contracts.celebrado_em` e `vigencia_ate`,
  `customers.nascimento`, `interactions.proxima_acao_em`, `funnel_blocks.limite_em`,
  `notificacoes.expira_em`, `conversas.acao_pendente_em`: data digitada por gente ou calculada para a
  frente nunca saiu do fuso da casa. Carimbo de fila (`jobs.created_at` é inteiro) e tabelas de
  framework (`cache`, `sessions`, `migrations`, `failed_jobs`) também ficaram de fora; a checagem de
  tipo pelo `Schema::getColumns()` é o que impede a conta de transformar `1730000000` em 1969.
- **Escreve pelo query builder, não pelo modelo**, coluna por coluna, com o mesmo formato com que a
  leitura veio — salvar em Eloquent recarimbaria `updated_at` e faria o histórico de outubro parecer
  atividade de hoje.

### Adicionado
- **Um passe, uma vez só, com volta.** A migration
  `2026_10_28_000001_atrasam_o_historico_para_a_hora_do_cliente` roda com o deploy, diz em voz alta
  quantas linhas atrasou, quantas resolveu pelo corte de ids e quantas sobraram para um humano, e
  grava a marca `fuso_correcao_do_registro_em` na tabela de configurações — sem ela, um `migrate`
  desavisado jogaria o histórico seis horas para trás. O `down()` desfaz o passe inteiro.

### Na produção
- O passe atrasou **8.597 células** de uma só vez: 8.309 com valor anterior à virada (prova basta) e
  288 na faixa duvidosa, resolvidas pelo corte de ids — os cortes caíram exatamente sobre a hora do
  deploy (`visits` em 925, `conversas_mensagens` em 45, `cron_execucoes` em 2244), conferido contra o
  log de acesso do Apache, que estampa a hora real do servidor em UTC−3: a fala 43 passou a ler
  `22:30:43` e o log marca `POST /assistente/fala` às `27/Oct/2026:22:30:43 -0300`.
- **As 7 células sem volta clara no meio da sequência foram provadas uma a uma pelo mesmo log** e
  atrasadas à mão, porque a prova é externa ao banco: `activities` 130/131/132 correspondem a
  `POST /login` 22:24:32 e ao abre-e-fecha da supervisão do portal às 22:25:46 e 22:25:57 — todas
  gravadas como 01:2x, portanto do regime velho; e o `lost_at` do lead 41 é `criado_em + 5 dias`
  exatos, com o `criado_em` da mesma linha já provado como anterior à virada.

### Travado com testes
- `tests/Feature/CorrecaoDoHorarioDoRegistroTest`, dez travas: o atraso do que a hospedagem gravou
  adiantado (carimbos da conversa, da última fala e de cada fala andando juntos), o que é anterior à
  virada sem faixa a discutir, a intactabilidade do que já nasceu na hora da casa, a faixa duvidosa
  resolvida pela volta do relógio na sequência de ids, a faixa sem volta deixando tudo para o humano,
  a lista de alvos vigiada contra as colunas intactáveis e as de framework, o `updated_at` que não
  ganha data de hoje, o passe único protegido pela marca no banco, o desfazer que devolve as três
  horas e a faixa de três horas no fuso de Brasília.

## [2.48.0] - 2026-10-27

### Corrigido
- **O relógio da Ana volta a marcar a hora do cliente.** Na bolha, a hora ao lado de cada fala saía
  três horas adiantada para quem escreve de São Paulo, e a mesma hora errada aparecia nas datas do
  painel, do portal e dos e-mails. A causa era uma linha: `config/app.php` escrevia
  `'timezone' => 'UTC'` **fixo**, então o `APP_TIMEZONE=America/Sao_Paulo` que o instalador grava no
  `.env` da hospedagem nunca chegou a valer nada — e o `now()` do framework obedece ao fuso da
  aplicação, não ao do visitante. Medido no servidor: o MySQL da hospedagem já vive em UTC−3
  (`NOW()` 22:40 contra `UTC_TIMESTAMP()` 01:40), de modo que a casa gravava a hora de Brasília como
  se fosse UTC e o instante armazenado saía três horas para frente. Com o fuso da casa certo, a
  escrita, a leitura e a tela passam a concordar.
- **Uma escolha, não um efeito colateral do servidor.** `App\Support\Fuso` é a nova casa da regra: o
  fuso vigente é o que o painel gravou, senão o do `.env`, senão o da casa (`America/Sao_Paulo`,
  primeiro item da lista fechada em `config/golden.php`). Aplicar não é só escrever em `config()` —
  o framework define o fuso do PHP no bootstrap, antes de qualquer provider, então `aplicar()` avisa
  `date_default_timezone_set()` também, que é o que `now()` e cada `format()` perguntam. Um código
  gravado à mão no banco fora da lista não desregula nada: volta ao padrão.

### Adicionado
- **Fuso horário na tela de Configurações.** Campo novo em **Sistema → Configurações → Contato e
  endereço**, ao lado do endereço e do CEP — é a localidade da casa, não uma chave de integração.
  Seletor com a lista fechada dos fusos do Brasil (Brasília, Amazonas, Mato Grosso, Acre) e UTC para
  quem precisa conferir registro no fuso do servidor; em branco significa "manda o do ambiente", e a
  própria tela diz qual está valendo hoje. Validação na mesma lista, sem regra escrita em dois
  lugares, e o guia do painel conta o campo.

### Travado com testes
- `tests/Feature/FusoHorarioDoSistemaTest`, sete travas: o fuso da casa sem nada escolhido, a hora
  que sai no JSON da bolha para um instante UTC congelado (21:00 UTC → `18:00` na tela), o seletor com
  a lista fechada na tela, a escolha do painel mandando sobre o ambiente, o fuso inventado barrado no
  POST, o valor escrito à mão no banco perdendo para o padrão, e nenhuma outra folha do sistema
  chamando `date_default_timezone_set()` por conta própria — duas casas para a mesma regra é exatamente
  como o relógio volta a divergir.

## [2.47.3] - 2026-10-27

### Corrigido
- **A lista do select passou a ser desenhada pela própria página.** A 2.47.2 deu fundo e texto às
  opções, mas no Chrome de algumas máquinas a lista ainda abria como uma mancha só, sem linhas e
  sem letra, nos dois temas — porque o pop-up nativo é montado fora da página, e ali entram o tema
  do Windows, o modo escuro forçado do navegador e a aceleração de vídeo, que a CSS nenhuma alcança.
  Onde o navegador entende `appearance: base-select` (Chrome e Edge 123+), o `<select>` e o seu
  `::picker(select)` agora usam esse modo: a lista virou DOM, pintado com os tokens da paleta, e o
  que restou de nativo — o ícone do seletor, escondido porque o Bootstrap já desenha a seta — não
  interfere. Quem não tem o suporte fica com o pop-up do sistema e o par de cores da 2.47.2.
- **Armadilha da especificidade, registrada em teste:** o `appearance: none` da classe
  `.form-select`, do Bootstrap, ganha da etiqueta `select`, então o seletor nomeia as duas — sem
  isso o campo continuaria com o pop-up do sistema e a correção não chegaria a ninguém.
- **Três travas novas** em `TemasEIconesDoPainelTest`: o bloco `@supports (appearance:base-select)`
  existe, nomeia `.form-select`, e a caixa da lista lê `--select-surface` da paleta.

## [2.47.2] - 2026-10-27

### Corrigido
- **A lista do select voltou a ser legível.** Em algumas máquinas, abrir um campo de escolha do
  painel no tema escuro mostrava a lista **vazia**: as opções estavam lá, mas em texto claro sobre
  o fundo branco que o próprio Windows pintou. O pop-up do `<select>` não é desenhado pela página —
  é desenhado pelo sistema, que pega o fundo nas cores do sistema e só o texto vem do CSS. A paleta
  única declara agora as duas cores da lista (`--select-surface`, `--select-text`, `--select-marcado`,
  cada uma com par opaco no escuro) em `option` e `optgroup`, e cada tema declara o **esquema nativo**
  (`color-scheme: light` / `dark`), que é o que faz navegador, barra de rolagem e calendário pintarem
  o resto na mesma metade. Fundo opaco é obrigatório ali: transparência, nesse pop-up, é invisível.
- **Duas travas novas** em `TemasEIconesDoPainelTest`: a lista do select tem fundo e texto declarados
  nos dois temas (e os três tokens são cor sólida, não `rgba`), e nenhuma outra folha do projeto escreve
  cor de `option` — a regra vive num lugar só, como todo o resto da paleta.

## [2.47.1] - 2026-10-27

### Adicionado
- **A despedida virou texto da ficha.** Coluna nova, `assistente.despedida`, com a frase editável em
  **Atendimento → Assistente virtual** (com `:nome`) e o padrão em `config/assistente.php`. É a sétima
  fala fixa do caminho da identificação e do encerramento: pergunta e confirmação do nome, pergunta e
  confirmação do e-mail, pedido de avaliação, agradecimento pela nota e agora a despedida.
  `2026_10_27_000008_da_a_fala_de_despedida_a_ficha_do_assistente` é aditiva, tem `down()` e foi
  exercitada no MySQL 8.4 da hospedagem.

### Corrigido
- **Despedida não chama mais a equipe.** Na caminhada real da bolha, o cliente que escreveu
  "obrigado, era isso" depois de receber a fatura ouviu *isso eu não sei responder com certeza, já
  chamei alguém da equipe* — a fala de encerramento estava indo ao provedor como se fosse pergunta, e a
  conversa fechava em `aguardando_equipe` com a pesquisa aberta na tela esperando nota. Quem se despede
  não perguntou nada: `App\Services\Assistente\Atendedor` reconhece o encerramento **por regra, antes de
  a máquina abrir a boca** — com a pesquisa em aberto a fala vira o pedido de nota, com a nota dada sai a
  frase de despedida, e em nenhum dos dois caminhos existe chamada de IA, custo de token ou escala para
  gente. A conversa continua `aberta` e `respondido_por_equipe_em` continua nulo.
- **Duas travas novas** em `IdentificacaoEAvaliacaoPeloAssistenteTest`: a despedida pede a nota e não
  chama a equipe, e a despedida depois da nota se despede pelo nome sem repetir a pesquisa uma segunda
  vez na mesma conversa.

## [2.47.0] - 2026-10-27

### Adicionado
- **A porta do atendimento personalizado.** Nada que olhe a conta da pessoa abre antes de a casa
  saber de quem é a conta. `App\Services\Assistente\Identificacao` é a fechadura: o pedido de segunda
  via — ou qualquer ação sobre a ficha — só desce para a máquina quando a conversa tem **nome** e
  **e-mail do cadastro**. O assunto geral continua sendo respondido na hora, e o pedido fica na fila
  em `conversas.acao_pendente` para **retomar sozinho** quando a identificação fecha, ninguém
  precisa repetir o que já pediu. Vencido o prazo da conferência
  (`assistente.verificacao.minutos`, padrão 20) o pedido pendente vence junto: retomar uma segunda via
  dita uma hora atrás, noutro assunto, seria a máquina agindo por conta própria.
- **O e-mail dito no chat.** Aproveitado de qualquer frase ("meu email é maria@exemplo.com, quero a
  via"), com o espaço que o celular coloca em volta do `@` descolado antes de procurar, e descartado
  quando é o endereço da própria casa — quem cola o contato do site não está se identificando, está
  repetindo o que leu. Quando a fala é **só o endereço**, a máquina confirma com a frase fixa da ficha
  e nenhuma chamada de IA acontece, pelo mesmo motivo da apresentação pelo nome. O endereço serve para
  *achar* a ficha e nunca para enviar: o destino continua sendo a coluna do banco.
- **A nota do atendimento da Ana no fim da conversa.** `App\Services\Assistente\Avaliacao` colhe a nota
  de 1 a 5 nos dois únicos fins que existem: a ação que se encerrou (a fatura saiu) e a despedida
  escrita no chat ("obrigado, era isso", "pode encerrar"). Uma só vez por conversa
  (`avaliacao_pedida_em` é a prova), com piso de falas da máquina antes de incomodar
  (`assistente.avaliacao.minimo_de_falas`, padrão 3), e **nunca quando um humano assumiu o fio** — a
  pessoa avaliaria o atendente errado.
- **Dois caminhos de resposta, uma coluna.** O toque nas estrelas da bolha entra pela rota nova
  `POST /assistente/avaliar`; o número escrito no chat é lido por regra (`4`, `nota 4`, `4/5`,
  `quatro`, `5 estrelas`, e o resto da frase vira `avaliacao_texto`), e uma fala só é lida como nota
  depois de a máquina ter perguntado — `4` no meio de uma conferência é dado de cadastro. Os dois
  gravam `avaliacao_nota` + `avaliada_em` e deixam a fala de sistema
  `Avaliação do atendimento: 4/5` no histórico.
- **Quatro falas novas na ficha, editáveis no painel**, com `:nome` onde a casa chama a pessoa:
  *Pergunta pelo e-mail*, *Resposta a quem disse o e-mail*, *Pedido de avaliação* e
  *Agradecimento pela nota*. Em branco, vale o padrão de `config/assistente.php`.
- **Cinco interruptores na tela de chaves** (`Sistema → Configurações → Chaves de integração →
  Assistente`): *Perguntar o e-mail do cadastro*, *Vezes que o e-mail é pedido*, **Exigir nome e e-mail
  antes de mexer na conta da pessoa**, *Pedir a nota do atendimento no fim* e *Falas da máquina antes
  da pesquisa*. Nada de ajuste por arquivo: o que a casa decide é gravado no banco, e o `.env` só vale
  enquanto o banco está vazio.
- **Motivo de escala novo na fila**: `sem_identificacao` — esgotados os tetos de pergunta, a conversa
  vai para a equipe com a frase dita ao cliente, em vez de virar formulário infinito.
- **Três migrations** (`2026_10_27_000005`, `000006`, `000007`): as colunas de e-mail e avaliação na
  conversa, as quatro falas na ficha do assistente e a fila da identificação. Todas aditivas, com
  `down()`, comprimento de `varchar` conferido no MySQL 8.4 da hospedagem.
- **Treze travas em `IdentificacaoEAvaliacaoPeloAssistenteTest`**: a ação não abre sem e-mail, o pedido
  fica na fila e retoma sozinho, o pedido vence com a janela da conferência, o teto derruba para a
  equipe, o e-mail colado no chat nunca é destino, o endereço isolado não gasta IA, a nota escrita e a
  estrela tocada gravam as mesmas colunas, a pesquisa não abre em conversa assumida por gente, e os
  quatro endereços da bolha não dividem o mesmo teto de requisições.
- **O deploy treina a Ana sozinho.** `deploy/instalador-golden.sh` roda `assistente:ensinar` logo depois
  das migrations, e `deploy/atualiza-golden.sh` repete o passo a cada atualização — sem
  `--personalidade`, a única opção capaz de passar por cima do tom escolhido no painel. Instalação nova
  que nasce sem base é instalação em que toda conversa da bolha escala por falta de fonte, e isso seria
  a primeira coisa que o cliente veria. O passo não derruba a instalação se falhar: base manca se
  resolve no painel, site sem instalar não se resolve.

### Corrigido
- **Cada endereço da bolha tem seu próprio teto.** Os contadores de requisição do site
  (`/assistente/abrir`, `/fala`, `/falas`, `/avaliar`, `/leads` e `/rastreio/evento`) estavam escritos
  sem prefixo no `throttle`, e sem prefixo a chave é assinada só pelo IP e pelo navegador: a varredura
  de `falas`, que roda a cada cinco segundos com o balão aberto, consumia o mesmo teto da fala. Na
  bancada isso apareceu como um `429` mudo no meio do atendimento — o visitante não conseguia escrever
  nem tocar nas estrelas, justamente quando a pesquisa estava na tela. Cada rota passou a ter prefixo
  próprio, e a trava nova derruba o teste se alguém tirar um deles.

### Alterado
- **A ordem do atendimento** passou a ter a identificação no meio: ação em curso manda, nome e e-mail
  são aproveitados de qualquer fala, a ação nova só abre identificada, o resto é conversa — e o fim de
  atendimento virou hora de pesquisa. A ordem de sistema também avisa o modelo quando o e-mail já foi
  dito (*não o peça de novo, não o escreva na resposta*) e quando ele falta (*nada da conta pode ser
  enviado, alterado ou localizado — não diga que fez nada disso*).
- **A ficha da conversa no painel** passa a mostrar *E-mail do cadastro* e
  *Avaliação do atendimento* (`4/5`, `pedida, sem nota` ou `não pedida`).
- **A bolha ganhou estrelas**: acendem quando a pesquisa foi pedida e ainda não tem nota, e o toque
  responde sem apagar o fio.
- **Guia do painel** (`assistente` e `assistente-conversas`) e **`docs/INSTALACAO.md`** documentam a
  porta da identificação, a colheita da nota e a quarta rota JSON — o PDF da instalação é regerado da
  mesma fonte.

## [2.46.0] - 2026-10-27

### Alterado
- **O vínculo com o financeiro não se desfaz mais no clique que foi só perguntar.** O botão
  **Revincular ao financeiro** fazia as duas coisas de uma vez: consultava o WHMCS e, se ele não
  confirmasse conta nenhuma, apagava o número gravado na ficha. A intenção estava certa — ficha
  ligada a uma conta que o próprio e-mail não confirma é a porta de entrada da fatura alheia — mas
  a resposta *"não achei conta para este e-mail"* chega igual de quem apagou a conta e de quem
  digitou o e-mail errado, e um clique de conferência não pode apagar nada. Agora a verificação
  para na tela: o número velho continua de pé, e desfazer é decisão à parte.

### Adicionado
- **A pendência do desfazer.** Quando o financeiro não confirma conta para um e-mail que tinha
  vínculo, `CustomerController::revincular` grava em sessão `revinculo_pendente`
  (`cliente`, `antes`, `email`) e devolve o aviso *"o vínculo com a conta #X continua de pé: nada
  foi desfeito sem a sua confirmação"*. Na ficha do cliente aparece então o botão
  **Desfazer o vínculo com a conta #X**, com confirmação própria — é o segundo clique, com
  `desfazer=1` na mesma rota, que chama `desfazerVinculo()` e escreve `whmcs_id = null`.
- **Dois guardas no segundo clique**, porque entre uma chamada e outra a ficha pode ter mudado de
  mãos: a pendência precisa ser *desta* ficha, e precisa apontar para o número que *ainda* está
  gravado. Sem pendência, pendência de outra ficha ou número já trocado, nada se desfaz e a tela
  diz o que refazer. Refazer a verificação também derruba a pendência anterior — ela é a resposta
  de uma chamada superada.
- **Seis travas novas em `RevinculoWhmcsTest`**: o vínculo fica de pé depois do "não confirmou", a
  confirmação desfaz exatamente o que a verificação apontou, desfazer sem verificação prévia não
  toca na ficha nem gasta chamada (`Http::assertNothingSent()`), a pendência de uma ficha não
  desfaz o vínculo de outra, a pendência vira botão na tela, e a verificação nova deixa a pendência
  velha sem valor.

### Corrigido
- **A bancada sabe dizer "não tem conta".** O WHMCS falso de `storage/app/demo-whmcs.php` devolvia
  lista vazia para e-mail desconhecido, e o painel tem razão em tratar lista vazia como falha de
  conversa — resultado é que o `sem_conta`, justamente o caminho que abre a pendência, nunca
  chegava a ser exercitado na tela. Agora um e-mail que não está na bancada devolve o `SUCCESS`
  com `clientid` vazio que o WHMCS real manda quando a credencial é boa e a conta não existe. O
  parser da camada não foi tocado.

## [2.45.1] - 2026-10-27

### Corrigido
- **Toda tela volta a falar português, sem exceção.** O pedido veio como regra permanente —
  *"nunca ponha textos em outro idioma na tela de desenvolvimento e outras"* — e a varredura nas
  views, nos controladores, nas sondas e na assistente mostrou que o sistema já falava português
  quase inteiro: `Total` é palavra nossa, os `Click` do Google Ads são nomes de método da API, e os
  rótulos de `paginas_site` já estavam todos traduzidos. Sobrou uma palavra, e ela estava em três
  lugares que se escrevem uns pelos outros: o nome do grupo de fotografias da página inicial em
  Sistema → Conteúdo → Imagens do site, o prefixo que o controlador usa para decidir em qual aba
  cada slot aparece (`Str::before($rotulo, ' ·') === 'Home'`) e o atalho que escolhe o destino de
  uma campanha. `Home` virou **Página inicial** nos três, com o teste que trava o vocabulário do
  grupo acompanhando (`ImagensDoSitePeloCmsTest`) — porque a regra só vale se o contrato que
  depende da palavra for junto.

### Adicionado
- **A regra de idioma está gravada na memória do projeto.** Não como nota solta: toda string de
  tela, toast, confirmação, guia, e-mail e mensagem de erro sai em português brasileiro, inclusive
  nas telas de desenvolvimento e nas sondas técnicas. Código de resposta e nome de variável podem
  aparecer entre parênteses como prova do que o servidor disse; a frase que explica vem sempre em
  português.

## [2.45.0] - 2026-10-27

### Adicionado
- **A ficha pode mandar refazer o vínculo com o financeiro.** A pergunta que abriu este trabalho
  foi a mais honesta do mundo: *"toda vez que o cliente entrar com o mesmo e-mail do WHMCS, vai
  carregar os serviços dele?"* — e a resposta tinha um porém que ninguém tinha dito em voz alta. O
  vínculo nasce na primeira visita, pelo e-mail de acesso, e **depois disso é o número gravado que
  manda**: nenhuma visita seguinte volta a conferir e-mail. É o que deixa o portal rápido, e é o
  que deixa a tela mentindo quando a conta é apagada e recriada no WHMCS, ou quando o e-mail muda
  numa das pontas. Faltava caminho de volta, e o caminho que existia era mexer na coluna pelo
  banco.
- **`POST /admin/clientes/{cliente}/revincular`** (`CustomerController::revincular`) refaz a
  descoberta pelo e-mail de acesso de agora e obedece a uma linha só: **escreve na ficha apenas o
  que o financeiro confirmou.** O `ok` grava o id devolvido — e se for o mesmo que já estava, diz
  que nada mudou em vez de fingir trabalho. O `sem_conta` **desfaz** o ponteiro velho, porque
  ficha ligada a uma conta que o próprio e-mail da pessoa não confirma é a porta de entrada da
  fatura alheia; sem vínculo anterior, a ficha fica como está e o recado pede para conferir o
  cadastro das duas pontas. Timeout, HTTP errado e credencial recusada não mudam nada — vínculo
  nenhum se desfaz porque o servidor ficou mudo por doze segundos. E o id confirmado pertencer a
  outra ficha bloqueia a gravação com o nome da outra ficha na frase: a coluna é única de
  propósito, uma conta no financeiro e uma ficha aqui.
- **O botão na ficha, com a pergunta antes do estrago.** Dentro do bloco *No financeiro (WHMCS)*,
  `data-confirmar` nomeia o e-mail que vai ser perguntado e avisa, quando existe vínculo, que ele
  será desfeito se o financeiro não confirmar nada — a confirmação sai em SweetAlert2 e o retorno
  em toastr, como no resto do painel. O botão não aparece sem as três credenciais nem sem e-mail na
  ficha: painel nenhum oferece porta que a rota fecha. Administrador e vendedor podem apertar — o
  resultado é o e-mail que está na ficha que eles já editam, e condicionar a correção a um
  administrador disponível deixaria o cliente esperando.

### Testado
- `RevinculoWhmcsTest` (10) roda a rota inteira com o WHMCS fingido e cobre os três desfechos, a
  colisão de id com outra ficha, a ficha sem e-mail (que não gasta chamada nenhuma, travada com
  `Http::assertNothingSent()`), o caso sem credencial — onde a rota não chama **e** a ficha não
  oferece o botão —, e o papel: vendedor passa, visualizador leva 403. A régua que nenhum deles
  solta é a que importa aqui: em cada falha de conversa, o teste lê a ficha de novo e exige que o
  número antigo continue no lugar.
- Suíte inteira em **592 testes e 6.733 asserções**.

## [2.44.0] - 2026-10-27

### Adicionado
- **O e-mail do servidor passou a ser decidido na tela, com prova.** *"Reconfigure o SMTP por
  dentro do painel"* começa num detalhe que derrubava todo disparo sem ninguém tocar no código: o
  Symfony só conhece `smtp` (STARTTLS) e `smtps` (TLS desde o abraço), e o mundo inteiro escreve
  `tls` e `ssl` nos `.env`. A tradução mora numa linha só — `ChavesDeIntegracao::esquema()` — e a
  aba de e-mail passou a oferecer os dois mundos com as palavras de sempre. O nome da variável é
  agora `MAIL_SCHEME`, com `MAIL_ENCRYPTION` ainda lida como plano de fundo; `mail.host` perdeu o
  `127.0.0.1` de fábrica (uma configuração incompleta não pode disfarçar conexão legítima) e a
  porta padrão virou a 587 de hospedagem. O remetente segue três passos — painel, `.env`, caixa
  autenticada — porque num servidor compartilhado um `from` inventado volta como devolução.
- **A sonda de disparo.** O WHMCS tinha a dela; o correio não tinha nenhuma, e o "não saiu" demorava
  dias para chegar — pelo cliente que esperou o link de senha e nunca o recebeu.
  `App\Services\Mail\Sonda` manda **uma mensagem de verdade** (`App\Mail\TesteDeDisparo`) pelo
  mesmo caminho do link de senha e da segunda via: envelope, remetente global, template Blade,
  transporte escolhido na tela. Não é `connection test`: abrir a porta e deixar passar o template
  quebrado é justamente o erro que sobra. A resposta vira frase de painel em quatro estados
  (`ok`, `sem_disparo`, `sem_resposta`, `recusada`, `falhou`), com o detalhe técnico sempre junto,
  porque quem administra servidor pensa em código de resposta, não em prosa. Síncrona de propósito
  — fila consumida pelo cron diria "entrou", nunca "chegou". O destino é o que a pessoa digita,
  nunca a caixa de um cliente.
- **O assistente agora faz, não só fala.** `App\Services\Assistente\Acoes` é o registro das ações, e
  cada uma é uma classe sobre o contrato `Contratos\Acao` (gatilho, interruptor, passos). A
  escolha é deliberada: **nada aqui é ferramenta de IA**. Quem decide se a fala é um pedido de
  segunda via é uma regex da casa; quem confere é o banco; quem envia é o código. O estado da ação
  mora na conversa (`conversas.acao`, `acao_passo`, `acao_tentativas`, `acao_dados_json`,
  `acao_em`), e a tela de conversas mostra a linha inteira — ação, passo, erros gastos e a hora em
  que a conferência vence — em `Acoes::descrever()`. Desligado o interruptor no meio de uma
  conversa, a ação morre na hora com o estado apagado: interruptor que vale só para a próxima
  conversa não é interruptor.
- **A segunda via da fatura pelo chat, atrás de conferência.** `SegundaViaDaFatura` atravessa dois
  passos: localizar a ficha pelo e-mail informado e conferir os dados que **já estão gravados
  nela**, um por vez, até `assistente.verificacao.minimo` bater. `Verificacao::CAMPOS` é a régua
  única — CPF/CNPJ e CEP por dígitos, nascimento com a barra desmontada antes de qualquer parser
  (deixá-la nas mãos do `Carbon::parse` trocaria mês por dia nas datas que passam de 12), placa
  normalizada contra a lista da ficha, telefone pelos últimos oito dígitos. Três coisas não mudam
  em lugar nenhum: **a resposta errada nunca é devolvida** (conferência que corrige é consulta que
  vaza), **o e-mail digitado no chat nunca é destino** — sai sempre para `Customer::emailDeAcesso()`,
  que é coluna do banco — e **estouradas as tentativas a conversa inteira vai para a equipe** com o
  motivo, nunca abandona quem pediu. O envio é síncrono e fica registrado como
  `segunda_via_enviada`.
- **O que a ficha pode provar.** `customers.nascimento` e `customers.placas` (separadas por vírgula)
  entraram por migration, com campo na ficha, e a tela ganhou *Verificação no chat*: quantos dos
  dados escolhidos aquela conta tem, quais são, e o piso exigido. Ficha sem nada conferível não é
  atalho — é a máquina chamando gente.
- **O nome antes do assunto.** A primeira resposta da bolha agora é a saudação seguida do pedido de
  nome (`assistente.pergunta_nome`, editável na ficha do assistente), e o `App\Services\Assistente\Nome`
  aproveita o nome dito em qualquer fala — "me chamo Ana", "sou a Maria", "quem fala é o Carlos" —
  com teto de `assistente.nome.perguntas_max` (3) gravado em `conversas.nome_pedidos`, para que
  quem não quer se identificar não fique travado. O nome entra na ordem de sistema da conversa e no
  vocativo das frases fixas da conferência: a formalidade não pode depender de a IA ter lido a
  frase em que a pessoa se apresentou.
- **Quem disse o nome recebe resposta da casa, não do provedor.** `assistente.confirmacao_nome`
  (editável em *Assistente → Persona*, com `:nome` no lugar do nome dito) devolve
  "Prazer, Maria! Pode dizer — o que eu resolvo por aqui hoje?" na hora. `Nome::soONome()` é o que
  separa a fala que é **só o nome** da que traz assunto pela frente: "Maria Aparecida" é
  apresentação e fica com a frase fixa; "Maria Aparecida, quero a segunda via da fatura" continua
  descendo para a ação. Custa zero chamada de IA e trava o custo de um detalhe que parecia
  educação: mandar o nome puro ao provedor fazia a máquina responder "não entendi" e chamar a
  equipe para atender quem acabou de se apresentar.
- **Quatro interruptores novos na tela de chaves**: *Deixar o assistente enviar a segunda via pelo
  chat* (aba Financeiro), *Perguntar o nome antes de conversar*, *Vezes que o nome é pedido*, e os
  quatro números da conferência — campos, acertos exigidos, erros aceitos e vida da janela
  (aba Assistente). Como sempre, **o painel grava no banco e o banco manda**; o `.env` só conduz o
  que o banco não tem.

### Corrigido
- **O chat estava prestes a reencobrar quem já pagou.** A escolha da fatura usava o saldo e, sem
  saldo, caía em `$faturas[0]` — e o `whmcs.veiculacao.faturas_pagas` padrão traz a quitada na
  lista. Segunda via de fatura paga é cobrança repetida, e é o cliente que sente o erro. A regra
  "ainda há o que pagar?" passou a morar numa fonte só, `Veiculacao::faturaEmAberto()` (estado
  `Unpaid`/`Payment Pending`/`Collections`, ou saldo maior que zero sem estado), que agora é a
  mesma do aviso de "R$ X em aberto" do portal — dois lados que não podem discordar.
- **O e-mail da pessoa virava o nome dela.** "Como você se chama?" responde-se, no chat, com o
  endereço; `maria@exemplo.com.br` deixaria um "Maria" na ficha de alguém. E-mail é dado de acesso,
  não vocativo, e é aparado antes de qualquer tentativa. O mesmo vale para "sou cliente da Golden":
  as palavras de resposta (`sou`, `estou`, `somos`, `tenho`, `acho`, `penso`) estão na lista do que
  nunca é nome.
- **O WHMCS respondia `SUCCESS`, e o sistema lia como falha.** A comparação do campo `result` era
  com a palavra minúscula, e a API devolve `SUCCESS`/`FAILURE` em caixa alta: toda chamada
  bem-sucedida caía em `recusada`. Na tela, o cliente veria *"Área financeira em preparação"* com o
  painel mostrando a conexão verde, e a segunda via do chat pararia em "o financeiro não respondeu
  agora". Agora é `Str::lower()` antes de comparar — um bug que só aparece com o provedor de
  verdade, porque todo falso da bancada escrevia do jeito que o código esperava.
- **Cinco caminhos de disparo, quatro destinos diferentes.** O link de senha lia só
  `email_portal`; o aviso de chamado e de pedido liam `email_portal ?: email` duplicado em três
  controladores; a segunda via não existia. Todos passaram por `Customer::emailDeAcesso()`, a única
  fonte da resposta — que é também o que garante que nada digitado num chat decide para onde vai
  uma correspondência.
- **A escala levava dado de cadastro no corpo.** `ConversaEscalada` recebia a última
  `ConversaMensagem`; agora recebe uma frase de prova escrita pelo código que parou, porque a
  máquina também escala quando uma *ação* parou — e aí não houve fala de cliente, houve resultado
  de conferência.

### Testado
- `ProvaDeDisparoTest` (8) cobre a régua do que falta, os transportes que não saem da máquina, a
  tradução dos nomes de criptografia, e os três modos de falha em frase de painel.
- `SegundaViaPeloAssistenteTest` (15) roda a conversa sem nenhuma chave de IA ligada — prova de
  que atendeu o código, não o modelo — e trava o gatilho sem acento, a ficha inexistente, a
  resposta errada que não devolve o valor certo, o teto de tentativas que escala, a janela vencida
  que recomeça com a mesa de trabalho vazia, o envio sempre para o e-mail cadastrado e a
  inexistência de fatura em aberto.
- `NomeNaConversaTest` (9) cobre a primeira fala que pede o nome, o nome aproveitado na mesma fala,
  o que nunca é nome (e-mail, dígitos, saudação, "sou cliente"), o teto de perguntas e a casa
  desligando o pedido pela tela de chaves — e os dois lados do reconhecimento: a fala que é só nome
  recebe a frase da casa e **não** entra na fila da equipe, e a fala que traz assunto pela frente não
  é trocada por ele. O `tearDown` desta classe é lição aprendida: a cópia em memória de `Setting` é
  estática e sobrevive ao rollback, então o `aplicar()` do boot do teste seguinte lia o config de um
  banco que já não tinha aquela chave.
- `WhmcsDoPortalTest` ganhou o teste que finge a resposta do WHMCS em `SUCCESS` (caixa alta, como a
  API real escreve) e exige que *Meus serviços* liste o plano em vez de "Área financeira em
  preparação".
- `VeiculacaoDoWhmcsTest` ganhou o par que amarra portal e chat na mesma regra de fatura em aberto.
- Suíte inteira em **582 testes e 6.688 asserções**. As quatro migrations novas
  (`nascimento`/`placas`, as colunas de ação em `conversas`, `assistente.pergunta_nome`,
  `assistente.confirmacao_nome`) são exercitadas no MySQL 8.4 da hospedagem antes de qualquer clique
  de verificação, porque o SQLite dos testes não mede comprimento de coluna.

## [2.43.0] - 2026-10-27

### Adicionado
- **Ver a conta do cliente, em modo somente leitura.** A pergunta que abriu este trabalho era
  honesta: *"onde estão os dados sincronizados do WHMCS?"* — e a resposta é **em lugar nenhum**.
  *Meus serviços* e *Minhas faturas* leem a API na hora, a cada visita, e o único registro que fica
  na ficha é o `customers.whmcs_id` descoberto pelo e-mail. Sem espelho no banco não havia o que
  abrir numa tela de "dados sincronizados", e inventar uma segunda tela de leitura seria inventar
  uma segunda verdade: filtros que podem divergir, um `whmcs_id` que pode envelhecer, um HTML que
  o cliente nunca viu. A supervisão então não copia o portal — ela **entra** nele.
  O botão **Ver a conta do cliente**, no topo da ficha (*Admin → Clientes*), autentica a ficha no
  guard `cliente` dentro da mesma sessão do painel (`web` continua aberto), e a pessoa passa a
  ver exatamente as telas que o cliente vê, com os mesmos interruptores de `Veiculacao` e o mesmo
  id no financeiro. Travado por `tests/Feature/PortalSupervisaoTest.php` (13 testes, 97 asserções),
  cujo último par compara o HTML do supervisor com o do cliente na mesma tela, linha a linha.
- **A janela não escreve.** `App\Http\Middleware\AcessoSupervisionado` foi montado sobre o grupo
  autenticado do portal (`['cliente', 'supervisao']`, nesta ordem: o cadeado só pergunta depois de
  a framework saber que há uma sessão de portal) e recusa todo verbo que não seja `GET`/`HEAD`,
  menos as duas saídas — `portal.supervisao.sair` e `portal.sair`. A barreira mora no middleware,
  não nas views, de propósito: esconder botão por botão seriam cinco promessas para manter, e a
  sexta — a que alguém esquece no HTML de uma tela futura — é a que grava. Recusada a escrita, a
  volta é um *toast* dizendo que nada foi gravado, e a tentativa fica na auditoria como
  `portal_supervisao_bloqueado` com a rota e o verbo. Teste cobre as três telas que escrevem
  (cadastro, chamado e senha) e confere depois que o nome, o telefone, a senha e a contagem de
  chamados do cliente estão intactos.
- **Barra de supervisão e porta de saída.** Um `alert` âmbar em fluxo normal, logo acima do
  cabeçalho da página (`resources/views/portal/partials/supervisao.blade.php`, incluído antes do
  `app-content-header` para não tocar no layout fixo do AdminLTE), diz em qual conta você está,
  quem abriu, a que hora, e traz os dois botões: **Sair da conta do cliente** (devolve o
  administrador à ficha, com o painel inteiro vivo) e **Ir à ficha no painel**. Nenhuma linha de
  CSS nova, nenhum posicionamento fixo, nenhuma cor fora dos tokens — o pedido era explícito em
  não danificar o layout, e a tela não ganhou nem um seletor próprio.
- **A janela vence sozinha.** `App\Support\Supervisao` guarda o marcador em
  `golden.supervisao` (id da ficha, id e nome de quem abriu, hora) e só o devolve quando as quatro
  condições batem ao mesmo tempo: marcador com forma, guard `cliente` na ficha marcada, guard
  `web` no administrador que abriu, veiculação ligada e prazo em vigor — 45 minutos, por padrão.
  Vencido, o middleware derruba a sessão do portal em vez de deixar a navegação virar conta
  normal, e avisa que quem estava ali não era o dono. `session()->regenerate()` foi
  deliberadamente **não** chamado na saída: derrubaria o token CSRF das outras abas do painel
  abertas no mesmo navegador.
- **Dois interruptores novos na aba do financeiro** (*Sistema → Configurações → Financeiro
  (WHMCS)*): *Abrir a conta do cliente no painel, em modo leitura* e *A janela de supervisão fica
  aberta (minutos)*, com a casa de sempre — **o painel grava no banco e o banco passa a mandar**;
  o `.env` deste servidor (`WHMCS_VEICULA_SUPERVISAO`, `WHMCS_VEICULA_SUPERVISAO_MINUTOS`) conduz
  apenas o que o banco não tem. Desligada a supervisão, o botão some da ficha, a rota responde 403
  e uma janela já aberta se fecha na requisição seguinte; quem não é `admin` não entra por
  nenhum caminho.

### Corrigido
- **A ficha do cliente dizia quanto do financeiro ela guardava, e não dizia nada.** Havia `whmcs_id`
  na tabela e nenhuma linha na tela, o que deixava a pergunta "onde estão os dados sincronizados?"
  sem resposta visível. A ficha ganhou o par *No financeiro (WHMCS)* — `conta #77` com a nota
  "o único registro guardado aqui; serviços e faturas são lidos na hora, a cada visita" — ou
  `não localizado` quando a conexão ainda não achou aquela pessoa.
- **Um login de verdade não herda a janela.** `PortalAuthController` limpa o marcador ao
  autenticar, ao cadastrar e ao sair. Sem isso, a pessoa que digitasse a própria senha no portal
  herdaria a barra de "está na conta de outra pessoa" e o cadeado — travando a escrita dela na
  conta dela. O mesmo teste que prova a barreira prova a volta: o cliente real continua gravando.
- **`$request->user()` não é o administrador depois do login do cliente.** Uma vez autenticada a
  ficha, a framework passa a responder o guard do portal, e a janela registraria no próprio cliente
  quem entrou nela. Quem abre a janela é lido por `Auth::guard('web')->user()`, antes do `login()`.

## [2.42.2] - 2026-10-27

### Corrigido
- **O agendador voltou a fechar o histórico e a avisar quando um cron quebra.** Desde a 2.33.0,
  cada tarefa registrada na tela *Sistema → Crons* terminava em
  `Unable to resolve dependency [Parameter #0 [ <required> $saida ]] in class App\Support\Agendador`
  — 901 linhas assim no log de produção em um dia. O motivo é contrato da framework: o `Event`
  entrega a saída capturada ao callback de `onSuccess`/`onFailure` **pelo nome do parâmetro**
  (`closureParameterTypes()['output'] === Stringable::class`); com o parâmetro chamado `$saida`,
  o container tenta resolvê-lo sozinho, não acha, e lança antes de qualquer linha do nosso código
  rodar. O comando em si executava — o que se perdia era o desfecho: a execução ficava aberta em
  `executando`, a ficha da tarefa nunca mostrava a falha, o sino de `cron_falhou` nunca tocava e a
  tela de histórico continuava dizendo que estava tudo bem. Os dois callbacks agora pedem
  `Stringable $output`, e a volta inteira está travada por dois testes novos em
  `tests/Feature/CentralDeCronsTest.php` (17): um roda o evento de verdade pelo `Event::run()` e
  cobra a linha fechada com `Application cache cleared` dentro da saída, outro aponta a tarefa
  para um comando inexistente e cobra `falha`, `fim` preenchido e a notificação
  `cron_falhou:{id}:{data}`. Trocar o nome de volta por `$saida` faz os dois caírem com a mesma
  exceção de produção — é isso que impede a regressão silenciosa.

- **A hospedagem voltou a servir o site em PHP 8.3, e a documentação agora diz como sem root.**
  O domínio amanheceu em branco com `Your Composer dependencies require a PHP version ">= 8.3.0".
  You are running 5.6.40.` O `AddType application/x-httpd-ea-php83 .php` do `.htaccess` estava lá,
  mas o cPanel escreve um bloco próprio **depois** dele (`# php -- BEGIN cPanel-generated handler`),
  e `AddHandler` de versão antiga no fim do arquivo vence `AddType` no meio. A correção não é
  editar o bloco — o cPanel reescreve na próxima salva — e sim mandar o cPanel reescrevê-lo certo,
  pela API da própria conta: `uapi LangPHP php_set_vhost_versions vhost=dominio version=ea-php83`
  (o argumento é `vhost`, não `domain`, e os nomes de função são `php_get_vhost_versions` /
  `php_set_vhost_versions`, não o `get_version`/`set_version` de versões antigas do painel). A
  seção 2 de `docs/INSTALACAO.md` ganhou o procedimento, o motivo de o `php` da linha de comando
  ser um **terceiro** PHP que nada tem a ver com o do navegador, e a regra da casa que já protege
  isso: cron e deploy chamam `/opt/cpanel/ea-php83/root/usr/bin/php` pelo caminho cheio, nunca
  `php` sozinho.

## [2.42.0] - 2026-10-26

### Adicionado
- **`App\Services\Whmcs\Veiculacao`: a conexão com o financeiro passou a ter o que veicular.**
  Até aqui, preencher as três credenciais abria a porta e tudo que cabia nela saía para o
  cliente — as duas telas, os serviços encerrados, as faturas pagas, o link que leva para fora
  do portal e as duas escritas no `UpdateClient`. Não havia onde decidir o contrário. Agora cada
  uma dessas é um interruptor, e a regra vive numa classe só, lida pelo controller, pela sidebar,
  pelo `Cliente` e pela tela de Configurações: `abre('servicos')`, `abre('faturas')`,
  `mostraServico($status)`, `mostraFatura($status)`, `mostraGrupo($grupo)`, `mostraLink()` e
  `espelha('dados'|'senha')`. Sete chaves `Sim/Não` mais a lista `whmcs_veicula_grupos` ficam no
  grupo `whmcs-veiculacao` de `ChavesDeIntegracao`, gravam no banco e valem na hora; em branco,
  o `.env` (`WHMCS_VEICULA_*`) conduz, como em toda integração da casa.
- **Desligar deixou de ser esconder botão: é não gastar a chamada.** `PortalFinanceiroController`
  consulta o interruptor antes de instanciar qualquer HTTP — fechada a tela, o cliente lê "Esta
  área do portal está fechada." com o caminho de volta, e o financeiro não recebe uma requisição
  sequer (travado por `Http::assertNothingSent()`). O menu do portal acompanha: o item perde a
  cor e ganha o aviso *desligada*, para a pessoa saber que a área existe sem cair numa porta
  trancada. Desligar o espelho é a mesma lógica do outro lado — o portal grava só em si, e como
  desligado não é falha, nenhum *toast* de "o financeiro não respondeu" aparece.
- **Filtro de vitrine: estado, grupo e saída para fora.** Serviços `Terminated`, `Cancelled` e
  `Fraud` ficam de fora por padrão (o passado do contrato não é oferta), `Suspended` continua
  dentro porque suspenso ainda é contrato, e fatura paga só aparece para quem quer o histórico.
  `whmcs_veicula_grupos` mostra apenas os grupos listados — comparação sem caixa e sem
  espaço, lista vazia é "todos", que é como a integração nasceu. Desligado o `link_externo`, a
  coluna de pagamento passa a dizer *Pelo atendimento* e nenhum `clientarea.php` sai no HTML.
- **Aba Financeiro (WHMCS) em Configurações, com o estado da conexão e a sonda.** Um cartão diz
  se as credenciais estão completas ou lista o que falta pelo nome do campo e a variável do
  `.env`, com ligação direta para a aba interna das chaves; outro mostra o endereço exato que
  será consultado (com o `/includes/api.php` que o painel completa), o tempo de espera, o limite
  de itens e os grupos autorizados; o terceiro reúne os interruptores. A **sonda** pergunta ao
  WHMCS por um e-mail e devolve em português o que ele respondeu — `ok` com o id da conta,
  `sem_conta` ("Conexão ok — conta, não."), `recusada` citando a mensagem dele, `http_erro` com
  o código, `sem_resposta` com o detalhe da rede, ou `sem_credencial` sem tocar na rede. Cada
  estado veste o selo pela tabela `config/golden.tons_status.sonda_whmcs`, e o segredo nunca
  volta para o HTML.
- `Cliente::chamarCrua()` e `Cliente::sonda()`: a chamada crua, que preserva estado, status HTTP
  e mensagem original. `chamar()` achata todo erro em `null` porque a página do cliente não tem o
  que fazer com a diferença entre credencial revogada e servidor mudo — quem está ligando a
  integração tem, e é para isso que os dois métodos novos existem. `endpointParaExibir()` mostra
  o endereço realmente consultado, e `faltando()` responde "o que falta" em nomes de tela.
- **Os interruptores têm aba própria, não mais um cartão na lista de chaves.** O grupo
  `whmcs-veiculacao` declara `aba_propria`, e `ChavesDeIntegracao::gruposDeChaves()` o deixa de
  fora da lista de abas internas de *Chaves de integração* — assim nenhum campo aparece em duas
  telas. O salvamento é o endpoint genérico (`POST /configuracoes/chaves`), sem uma linha de
  gravação duplicada, e `test_salvar_os_interruptores_no_painel_grava_no_banco_e_manda_na_frente_do_env`
  prova que o que a tela gravou é o que `Veiculacao` lê.
- **17 testes em `tests/Feature/VeiculacaoDoWhmcsTest.php`**, mais o mapa de abas e o teste da aba
  própria em `AbasDasConfiguracoesTest.php`. O arquivo fixa também uma armadilha do Laravel:
  `Http::fake()` **acumula** stubs e o primeiro que casa vence, então cada teste instala um único
  fake roteado pela `action` recebida — dois fakes no mesmo teste deixariam o segundo mudo, e um
  teste mudo passa por acidente.

## [2.42.1] - 2026-10-26

### Corrigido
- **A sonda lê o WHMCS que existe, não o que a documentação descreve.** Na primeira prova em
  produção, um e-mail sem ficha respondeu `result: error` com `Client Not Found` e HTTP 200 —
  credencial boa, conta ausente. Como todo `result` diferente de `success` era tratado como
  recusa, a tela diria "credencial revogada" justamente para quem configurou tudo certo. Agora
  essa resposta cai em `sem_conta` ("Conexão ok — conta, não."), o mesmo estado do `success` com
  `clientid` vazio; a recusa de verdade (`Invalid credentials`) continua citando o motivo.

## [2.41.0] - 2026-10-26

### Adicionado
- **`App\Support\Csv`: a escrita de CSV da plataforma numa fonte só.** Antes, cada exportação
  chamava o próprio `fputcsv` com vírgula, na ordem em que o programador tinha escrito as
  colunas. O Excel em português não reconhece vírgula como separador, então cada linha inteira
  entrava numa célula só e o arquivo abria como uma bagunça ilegível. Agora quem exporta declara
  `Rótulo => fn (registro) => valor` e recebe o download pronto, com as quatro promessas do
  padrão: **colunas em ordem alfabética pelo rótulo** (o acento não prega lugar — `Última
  atividade` fica no "u" que lhe cabe, antes de `UTM campanha`, e não no fim do arquivo, como
  jogava a comparação de bytes), separador **ponto e vírgula**, **BOM UTF-8** e linha em CRLF
  para o Excel abrir com as colunas abertas e o acento certo, e **toda célula entre aspas**.
- **O arquivo não executa nada e não desalinha a planilha.** Texto é aparado e tem suas quebras
  de linha viradas em espaço (uma célula multilinha quebrava o alinhamento de todo o resto);
  número sai com vírgula decimal e data no `dd/mm/aaaa hh:mm`, como o Brasil escreve; valor em
  centavos sai como moeda; e o que começa com `=`, `+`, `@`, `-`, tab ou CR entra com apóstrofo
  na frente, para um telefone ou @ de cliente virar dado e não fórmula.
- **Testes do padrão** (`tests/Feature/ArquivosCsvTest.php`): sete testes que conferem a ordem
  alfabética, o BOM/separador/aspas, a célula multilinha, a neutralização de fórmula, os formatos
  brasileiros e, tela por tela, o cabeçalho exato e uma linha por registro nos dois arquivos.

### Alterado
- **As duas exportações do painel passaram pela fonte única.** `Admin → Leads → Exportar CSV`
  (também no painel inicial) e `Admin → Atribuição → Exportar` agora só declaram suas colunas; a
  escrita é do `Csv`. Os leads ganham cabeçalho estável — `Campanha`, `Criado em`, `E-mail`,
  `Frota`, `ID`, `Kit`, `Nome`, `Origem`, `Perfil`, `Responsável`, `Status`, `UTM campanha`,
  `Valor (R$)`, `WhatsApp` — e a atribuição teve seus rótulos técnicos traduzidos: `utm_source`,
  `utm_medium`, `utm_campaign` e `Views` viraram **UTM fonte**, **UTM meio**, **UTM campanha** e
  **Visualizações**, iguais aos da tabela na tela.

### Documentação
- README (seção de exportação) e guia do painel (`Leads`, `Atribuição`) explicam o padrão do
  arquivo: uma coluna por campo, em ordem alfabética, ponto e vírgula e BOM — abre no Excel já
  organizado.

## [2.40.0] - 2026-10-26

### Alterado
- **`/admin/conteudo/imagens` virou uma galeria por área do site.** A tela tinha 20 cards
  enfileirados na ordem do arquivo de configuração, sem hierarquia: quem abria não sabia por
  onde começar nem qual foto pertencia a quê. Agora a tela tem as mesmas abas do resto do
  painel — **Identidade** (3), **Home** (9) e **Cartazes das páginas** (8), cada uma com a
  contagem no rótulo — e acima delas quatro contadores dizem o estado do site inteiro:
  quantas fotografias existem, quantas vêm do painel, quantas ainda são a foto do pacote e
  quantas estão sem imagem no ar (estas últimas com o selo âmbar de pendência).
- **Cada ficha diz onde a foto entra.** Os oito cartazes têm o mesmo título — "Cartaz do topo
  da página" —, então o nome da página (Soluções, Planos e kits, Dúvidas frequentes…) passou a
  aparecer em cima do card; na Home, quem identifica a ficha é a seção. Um clique na moldura
  abre a foto em tamanho grande, com o link direto do arquivo e o da página onde ela está no ar.
- **As molduras deixaram de cortar e de ampliar as fotos.** A altura da prévia é a mesma em
  todos os cards; a foto quadrada e o favicon aparecem inteiros e no tamanho real do arquivo
  (um PNG de 64 px deixaria de ser nítido ampliado), e só as faixas largas — 16:9 e 20:9 — são
  recortadas, que é exatamente como o site as usa. Corrigido também o grid que fazia a moldura
  estourar a altura: a moldura passou a flexível, onde o `max-height` da foto resolve contra a
  altura certa do card.

### Corrigido
- **Duas abas abertas ao mesmo tempo.** A tela nova não marcava o botão da primeira aba como
  ativo, e o Bootstrap, sem saber qual painel fechar, deixava os dois visíveis um embaixo do
  outro. O botão da primeira aba passou a nascer `active`, como na tela de Configurações, e um
  teste novo confere que cada slot cai na aba da sua área.

### Documentação
- Guia do painel (`Guia de uso → Imagens e textos do site`) reescrito para as três abas, os
  quatro contadores, o clique na foto e a regra de recorte.

## [2.39.0] - 2026-10-26

### Adicionado
- **Configurações em abas: um tipo de configuração por aba.** A tela `/admin/configuracoes`
  deixou de ser uma lista corrida e passou a ter seis abas — **Contato e endereço**, **Acessos do
  site**, **Loja virtual**, **WhatsApp**, **Medição** e **Chaves de integração** —, cada uma com o
  seu próprio conteúdo, o seu botão de salvar no pé e nada de outra aba misturado no meio.
- **Chaves de integração com uma aba interna por grupo.** Os oito grupos (Google Ads, Avaliações
  do Google, E-mail de disparo, WHMCS e os quatro do assistente) viraram pílulas dentro da aba de
  chaves, em grade no computador e em fila com rolagem lateral no celular.
- **Componente de abas do painel** (`public/js/admin-abas.js`), sem nome de tela dentro: devolve a
  pessoa à última aba usada, obedece a link com hash (`#chaves-google-ads` abre Chaves → Google
  Ads, o mesmo endereço que os atalhos do painel já usavam), abre a aba do primeiro campo com erro
  — senão o motivo da recusa ficava escondido — e rola a barra até a aba aberta quando ela sai do
  campo de visão no celular.
- **Cada aba salva só o que mandou.** O formulário leva um `_aba` dizendo de onde veio; o
  controlador grava apenas os campos presentes no POST. Salvar a aba de acessos não apaga nome do
  site, WhatsApp nem o interruptor de medição — e o contato continua obrigatório na própria aba.

### Alterado
- **A aba aberta é dourada nos dois níveis**, com o mesmo par de tokens da marca: a barra de cima
  é um segmentado com ícone por tipo, e as pílulas internas usam o tom de ouro sobre o fundo do
  cartão. No tema escuro e no claro a leitura é a mesma, porque nenhuma cor foi escrita à mão.
- **O botão de salvar mora no pé da aba**, separado do conteúdo por uma linha discreta, em vez de
  ficar solto depois do último cartão.

## [2.38.0] - 2026-10-26

### Adicionado
- **A Ana tem personalidade própria e fala como gente.** A persona de fábrica virou natural,
  humanizada, atenciosa, dinâmica e bem-humorada: português do Brasil, frases curtas, tom
  descolado sem clichê de robô. Nasce com o tom **Descolado e bem-humorado** (novo na lista de
  tons, ao lado de Amigável, Formal, Direto e técnico e Comercial) e com regras que acolhem
  primeiro quem está em emergência (roubo, bloqueio, veículo sumido), vendem mostrando o caminho
  sem empurrar produto, usam somente as fontes da base, nunca pedem documento ou senha no chat e
  chamam a equipe quando o assunto foge.
- **A base de conhecimento da Golden, pronta para ensinar em um clique.** `config/conhecimento.php`
  traz **16 assuntos** escritos a partir do que o site já publica: quem é a Golden, o que a Ana
  resolve, o rastreador J16 4G/2G e a versão para moto, instalação, bloqueio remoto, cobertura,
  planos e mensalidade, como acessar a plataforma, o portal do cliente, faturas e financeiro, como
  comprar, rastreador sem sinal, roubo ou furto, suporte e emergência, garantia e contrato. O
  **catálogo** é montado na hora com os produtos ativos da loja, para o preço do robô não envelhecer.
- **Números e endereços não ficam escritos no texto.** Marcadores (`:whatsapp`, `:email`,
  `:telefones`, `:servidor_1`, `:servidor_2`, `:financeiro`, `:portal`, `:cidade`) são preenchidos
  na semeadura com o valor vigente do painel — trocar o WhatsApp ou o endereço de um servidor e
  rodar de novo mantém a base em dia.
- **Dois caminhos para treinar, com a mesma régua.** O comando `php artisan assistente:ensinar`
  (com `--atualizar` para regravar os textos fixos e `--personalidade` para aplicar a persona) e o
  botão **Ensinar o básico da Golden**, no topo de *Atendimento → Assistente virtual → O que o
  assistente sabe*. Nenhum dos dois apaga o que a equipe editou no painel.
- **Conexão direta com o WHMCS, configurada pelo painel.** O grupo *WHMCS — financeiro e serviços*
  em *Sistema → Configurações → Chaves de integração* guarda endereço, identificador e segredo
  (cifrado no banco, nunca devolvido à tela), além do tempo máximo de espera e do limite por
  consulta. Sem as três peças a integração fica desligada e o portal explica — nenhuma tela cai
  por falta de credencial.
- **Duas telas novas no portal, com o grupo Financeiro no menu**: *Meus serviços*
  (`/portal/servicos`) mostra os produtos contratados, o ciclo, o valor, o próximo vencimento e a
  situação, com o link que abre o serviço no financeiro; *Minhas faturas* (`/portal/faturas`)
  mostra emissão, vencimento, situação, total e saldo em aberto, com o botão **Pagar** que leva à
  fatura. O resumo da fatura em aberto aparece no topo.
- **A tela distingue falha de vazio.** Sem credencial, conta não encontrada, financeiro fora do ar
  e lista vazia têm recados diferentes e ações próprias — falha de API nunca vira "você não tem
  faturas", e o cliente nunca lê o nome de uma variável de ambiente.
- **A mesma senha e os mesmos dados nos dois lugares.** Trocar a senha no portal grava a mesma
  senha no WHMCS (`password2`); corrigir nome, telefone, endereço, cidade, UF e CEP espelha no
  `UpdateClient`, sem o e-mail (é o login do financeiro). Se o espelho falhar, um *toast* avisa e
  a gravação local continua valendo.
- `customers.whmcs_id` (`int unsigned`, nulo, único) guarda o id do cliente no financeiro,
  descoberto pelo e-mail de acesso na primeira visita. O id nunca vem da URL: sai sempre da ficha
  autenticada.
- `config/whmcs.php` reúne os mapas de situação de fatura, situação de serviço e ciclo de cobrança
  traduzidos uma vez só; nenhuma view escreve "Unpaid" nem escolhe cor por conta própria.

### Alterado
- As tabelas de serviços e faturas do portal foram compactadas para o celular: o ciclo desceu para
  baixo do valor, a emissão virou a linha de apoio do número da fatura, o saldo ficou sob o total e
  o link de cada serviço virou um ícone com rótulo de acessibilidade. As cinco colunas cabem na
  tela sem esconder o botão de pagar.
- A ficha *Meus dados* ganhou a linha **Financeiro** (ligado, aguardando configuração ou com o
  caminho das faturas) e o aviso de que a mesma senha passa a valer no financeiro.
- O portal passou de seis para oito telas; o rodapé do guia e a documentação acompanham.

### Documentado
- `docs/INSTALACAO.md` (10 e 13.1) descreve as credenciais do WHMCS, as permissões
  `GetClientsDetails`, `GetClientsProducts`, `GetInvoices` e `UpdateClient`, as quatro respostas da
  tela, as regras de espelho, a personalidade de fábrica, os 16 assuntos e o comando de
  treinamento. A versão do documento subiu para 2.38.0 e o PDF foi regerado.
- O guia do painel ganhou a seção de treinamento e o tom padrão na ficha do assistente.

### Testado
- `TreinamentoDoAssistenteTest` (5) cobre a base inteira com o catálogo, a persona aplicada, a
  segunda rodada que não apaga o texto editado, o `--atualizar`, os marcadores do painel e o botão
  da tela.
- `WhmcsDoPortalTest` (10) cobre os quatro estados, as duas listas, a descoberta do id pelo e-mail,
  o espelho de dados e de senha, a falha do espelho e o segredo cifrado.
- Suíte inteira em **496 testes e 5.813 asserções**; a migration `2026_10_26_200001_liga_a_ficha_ao_whmcs`
  foi exercitada no MySQL 8.4 antes da entrega.

## [2.37.0] - 2026-10-26

### Adicionado
- **A caixa do WhatsApp inteira agora se edita item por item no painel.** Em
  *Sistema → Configurações → Botão do WhatsApp no site*, cada item que o visitante lê ganhou o
  seu campo: título, subtítulo, selo ao lado do ponto verde, dica do botão do canto, rótulo do
  rodapé e a mensagem que já vai escrita na conversa do rodapé. Em cada canal humano entram
  nome, descrição, ícone (lista fechada), número próprio, mensagem pronta e o interruptor — o
  antes/agora do atendente deixa de depender de `config/golden.php`.
- `config/golden.php` ganhou `whatsapp_box.online`, `whatsapp_box.flutuante`,
  `whatsapp_box.rodape` e a lista fechada `whatsapp_box.icones`, que a validação e o select do
  painel compartilham.
- `CaixaDoWhatsappTest` cobre o campo de cada item no painel, os textos gravados chegando ao
  site, o canal com número e mensagem próprios, o retorno ao padrão quando o campo fica em
  branco e a recusa de ícone fora da lista.

### Alterado
- `AppServiceProvider::montarCanais()` lê ícone e mensagem pronta do banco (antes só nome,
  descrição, número e interruptor), com qualquer ícone fora da lista voltando ao padrão do canal.
- O bloco de canais da tela de Configurações virou um cartão por atendente, com todos os
  campos à vista; o assistente virtual continua apontando para a ficha dele em Atendimento.

## [2.36.1] - 2026-10-26

### Alterado
- **O assistente virou um dos atendentes da caixa do WhatsApp.** O botão flutuante próprio saiu
  do canto do site e da página de funil: o robô agora é a primeira linha da caixa multicanal,
  com o rosto (ou o ícone), o nome e o cargo da ficha. Escolher a linha fecha a caixa e abre a
  conversa no mesmo lugar, acima do botão do WhatsApp — e abrir a caixa com a conversa aberta
  fecha a conversa, para os dois painéis não se cobrirem. Os canais humanos continuam embaixo,
  cada um indo para o `wa.me` com a mensagem pronta.
- **Sem canal humano, o mesmo botão vira a porta do assistente.** Se todas as linhas de
  WhatsApp forem desligadas, o botão do canto passa a abrir a conversa, na cor da casa e com o
  rosto da ficha. Com a caixa desligada em Configurações, o canto volta ao link direto do
  WhatsApp e o assistente não é carregado — nem folha, nem script.
- **O nome e o cargo da ficha são os rótulos do atendente na caixa.** O cartão **Bolha de
  atendimento** em *Atendimento → Assistente virtual* continua editando a dica (o rótulo de
  acessibilidade da linha e a dica do botão quando ele é o único atendente), o ícone sem rosto,
  o selo de status, o campo de mensagem e o link para falar com uma pessoa.
- O aviso dourado de resposta da equipe, antes no botão do robô, agora aparece no botão do
  WhatsApp: é por ele que o cliente volta à conversa.

### Documentado
- `docs/INSTALACAO.md` (13.1), o guia do painel e o cartão da ficha descrevem o assistente
  dentro da caixa, não mais acima dela.

### Testado
- `BolhaDoAssistenteTest` cobre a linha do assistente como primeiro atendente, o botão único
  quando não há canal humano, a caixa desligada e a ficha desligada. Suíte inteira em 473
  testes e 5.613 asserções.

## [2.36.0] - 2026-10-26

### Alterado
- **A bolha do assistente passou para cima do WhatsApp.** No canto do site e da página de
  funil, o botão do robô deixou o andar de baixo e ocupa agora a posição logo acima do
  WhatsApp; o WhatsApp continua ancorado no pé da tela. O balão abre a partir da bolha e a
  caixa multicanal do WhatsApp sobe acima dela quando aberta, para um não cobrir o outro. No
  celular os dois encolhem juntos e mantêm a mesma ordem.
- **Os textos da bolha saíram do HTML e foram para a ficha do assistente.** *Atendimento →
  Assistente virtual* ganhou o cartão **Bolha de atendimento**: dica ao passar o mouse (aceita
  `:nome`), ícone de quem ainda não tem rosto, selo de status, texto do campo de mensagem e o
  link para falar com uma pessoa. O que for gravado ali vale na hora; em branco, o padrão de
  `config/assistente.php` conduz.
- **Todo rótulo do caminho até a plataforma virou campo de Configurações.** O cartão **Acessos
  externos do site** ganhou o nome e o ícone de cada servidor, e um cartão novo, **Textos dos
  acessos**, reúne o rótulo do botão dourado, o nome do ícone de conta, o título da área do
  cliente, as duas portas do submenu, o título e a descrição do modal e a frase de ajuda com o
  texto do link. Campo em branco devolve aquele rótulo ao padrão da casa, sem apagar o resto.

### Adicionado
- A tabela `assistente` ganhou `dica`, `status`, `placeholder`, `humano` e `icone`, todos
  opcionais: a ficha que não os preencheu continua exatamente como era.
- `config/assistente.php` ganhou o bloco `bolha` e a lista fechada `icones_bolha`; e
  `config/golden.php`, o bloco `acessos.textos` e a lista `acessos.icones`, que a validação e
  os selects do painel compartilham.
- `BolhaDoAssistenteTest` cobre os textos padrão da bolha e a ficha editada no painel;
  `PortalDoClienteTest` cobre a troca de todos os textos dos acessos e o retorno ao padrão
  quando o campo fica em branco.

## [2.35.1] - 2026-10-26

### Alterado
- **O modal dos servidores mostra o nome, não o endereço.** As linhas com
  `app1.goldenrastreamento.com.br` e `134.209.82.248:8082` saíram dos cartões de **Servidor 1**
  e **Servidor 2**: ficou só o nome de cada um, e o endereço continua na própria ligação, em
  nova aba. A frase de apoio passou a convidar a escolher o servidor em vez de "abrir o
  endereço".
- **O botão dourado do cabeçalho agora diz Rastreamento.** **Falar com especialista** deu
  lugar a **Rastreamento**, no topo e na gaveta; a aparência dourada e o modal que ele abre
  não mudaram. O caminho de contato continua dentro do modal.

## [2.35.0] - 2026-10-26

### Alterado
- **O cabeçalho do site virou a porta de entrada de quem já é cliente.** O texto **Área do
  cliente**, que ficava solto entre os links, deu lugar a um **ícone de login** ao lado do
  carrinho: passar o mouse (ou focar pelo teclado) abre um submenu com **Painel do cliente**
  — a rota interna do portal, que muda de destino sozinha para quem já entrou — e
  **Financeiro antigo**, que abre o sistema antigo em outra aba. No celular, onde não há
  mouse, o ícone alterna o mesmo submenu com um toque, e a gaveta continua mostrando o item
  em acordeão com as duas portas.
- **O botão dourado continua botão — e agora abre os servidores.** **Falar com especialista**
  manteve fonte, gradiente e tamanho, mas deixou de ser link para `/contato`: o clique abre
  um modal com **Servidor 1** (`app1.goldenrastreamento.com.br`) e **Servidor 2**
  (`134.209.82.248:8082`), cada um em nova aba, com o endereço à vista para não confundir as
  duas plataformas. O caminho de contato não se perdeu: o próprio modal oferece **Fale com um
  especialista**, e o botão da gaveta abre o mesmo modal em vez de duplicar a conversa.
- **Os três endereços externos se trocam no painel.** *Sistema → Configurações* ganhou o
  cartão **Acessos externos do site** com financeiro e os dois servidores; o que a tela grava
  fica no banco e vence `config/golden.acessos`, que só conduz enquanto ninguém salvou. O
  portal não aparece na lista porque é rota interna e acompanha a sessão do cliente.

### Adicionado
- **Submenu e modal sem uma requisição a mais.** Ambos são markup, CSS e JS da casa (nada de
  biblioteca nova): o submenu anima na mesma curva dos demais menus, o modal tranca o corpo,
  prende o foco, devolve o foco ao botão que o abriu, fecha com `Esc` e com o véu, e os dois
  respeitam `prefers-reduced-motion`.
- `PortalDoClienteTest` ganhou o contrato do ícone com as duas portas, do modal com os dois
  servidores e da troca de endereços pelo painel — inclusive que o texto antigo saiu do topo
  e que o botão guardou a classe dourada.

## [2.34.0] - 2026-10-26

### Alterado
- **O guia de uso abre em outra aba e vira um painel de uma seção.** O item **Guia de uso** da
  barra lateral, o link **Como usar esta tela** do cabeçalho de cada tela e o botão **Guia do
  painel** do aviso de crons passaram a abrir `/admin/guia` em `target="_blank"`: consultar a
  documentação não custa mais a tela que estava aberta. A aparência é a mesma — a busca e o
  índice continuam na coluna da esquerda, presa à tela como uma barra lateral —, mas a direita
  deixou de ser a lista das 30 seções empilhadas: clicar num item do índice troca o cartão da
  leitura por aquela seção, com o título do grupo no alto, e **não rola a página**. O corpo do
  cartão ganha rolagem própria (altura medida do topo do painel ao rodapé) e o endereço passa a
  carregar `#secao` por `history.replaceState`, que troca o endereço sem pedir ao navegador que
  role até a âncora. O `#secao` continua valendo como link direto: o atalho de cada tela abre o
  guia já na seção certa, e recarregar mantém a seção aberta.
- **A busca escolhe o que abrir, em vez de despejar tudo.** Digitar filtra o índice como antes —
  sem acento, "contrato" acha "Contratos" — e abre a primeira seção que casa; sem resultado, o
  índice avisa e a leitura fica onde estava. Endereço com `?busca=…` chega já filtrado.
- **No celular o guia continua navegável.** Como não existe "à direita" numa tela estreita, o
  índice ganhou altura própria (rola por dentro) e a seção escolhida é trazida para a tela
  depois da troca. A transição de cartão respeita `prefers-reduced-motion`.
- `GuiaDoPainelTest` (16) ganhou a aba própria do guia e o contrato do painel que troca de seção
  sem rolar a página; os contratos de markup que já existiam (ids únicos, sem numeração,
  `guia-chamada` em Leads e ausente no guia, `#google-ads` no HTML do Google Ads) seguem iguais.

## [2.33.0] - 2026-10-26

### Adicionado
- **A central de crons (`Sistema → Crons`).** A agenda do sistema deixou de ser código e virou
  tela: criar, editar, pausar, excluir e rodar agora, com histórico por execução. A hospedagem
  continua com **uma** linha no cron, que não muda (`* * * * * … artisan schedule:run`); o que
  roda depois dela é decidido no painel. A tela abre com quatro tarefas semeadas pela migration —
  drenar a fila, sincronizar o Google Ads, podar o rastreio e podar as notificações — e mostra no
  topo a batida do agendador em palavras ("vivo há 40 segundos"): a diferença entre uma tarefa
  lenta e a crontab arrancada.
- **A agenda é a fonte, não o espelho.** `Agendador` lê a tabela `cron_tarefas` a cada
  `schedule:run` e registra no Laravel exatamente as tarefas ativas; pausar tira a tarefa da
  agenda na hora, sem cache intermediário. Cada execução abre e fecha uma linha em
  `cron_execucoes` (origem, duração, saída e erro), e a ficha da tarefa mostra as últimas 20. O
  botão **Rodar agora** grava o mesmo par abrir/fechar, mudando só a origem — e recusa os
  comandos da fila, que prenderiam a tela por cinquenta segundos para fazer o que o minuto
  seguinte faria.
- **O comando é validado como uma linha só.** O select oferece os comandos registrados no
  `artisan` e as flags ficam num campo separado; juntos passam pela mesma régua
  (`CronTarefa::erroDoComando()`): nada de `;`, `&&`, pipe, redirecionamento ou crase, e fora da
  agenda os comandos que apagam dados ou pedem senha (`tinker`, `migrate*`, `db:wipe`,
  `key:generate`…). As quatro tarefas do sistema podem ser pausadas e reagendadas, mas não
  apagadas.
- **A central de notificações.** Um aviso dentro do painel por destinatário — sino no topo com
  contador, página dedicada (`/admin/notificacoes`), ficha dinâmica para cada aviso
  (`/admin/notificacoes/{id}`) e a rota `admin.notificacoes.sino` para o sino se atualizar
  sozinho, com `no-store` e sem consulta com a aba escondida. Abrir a ficha é o gesto de ler: o
  aviso sai do sino de quem abriu e continua no histórico; o de outra pessoa não abre (404) e não
  é apagado por ninguém.
- **O catálogo de tipos em `config/notificacoes.php`** — dez tipos (lead, pedido, chamado aberto
  e respondido, depoimento, conversa escalada, conta do portal, Google Ads desconectado, cron
  falhou e agenda sem batimento), cada um com rótulo, ícone, tom, audiência (`equipe` ou
  `admin`) e prazo próprio. A ficha não conhece tipo nenhum: lê o catálogo e o contexto
  estruturado, então um tipo novo entra sem migration e sem view.
- **Preferências por pessoa e prazos por administrador.** A tela
  `/admin/notificacoes/preferencias` desliga tipo por tipo (a linha só existe para o que foi
  desligado; o padrão é receber) e, para o administrador, grava no banco a retenção das lidas e
  o prazo de uma notificação não lida — os mesmos números que `notificacoes:apodrecer` usa na
  poda.
- **Um canal a mais, não um canal no lugar do outro.** `Notifica::disparar()` é a única porta de
  entrada: deduplica por chave (`user_id` + `chave`), respeita a preferência de cada um e nunca
  derruba o fluxo que a chamou. Os pontos que já avisavam a equipe — lead, pedido, chamado,
  conversa, conta do portal, Google Ads — passaram a avisar também aqui, e o e-mail continua
  exatamente onde sempre esteve.
- **`notificacoes:apodrecer`** faz a limpeza das duas centrais: aposenta notificação lida depois
  da retenção e não lida que venceu, apara o histórico de execuções no teto por tarefa e acusa em
  uma linha por dia a tarefa ativa sem batimento (pulando o aviso quando o agendador inteiro
  está em falta, para não acusar quatro tarefas por causa de uma crontab arrancada).
- **As duas telas entraram no guia**: seções `crons` e `notificacoes` em Sistema, com o mapa
  `rotas` apontando `admin.crons.*` e `admin.notificacoes.*` para elas; a linha "Como usar esta
  tela" chega nelas como em todas as outras.

### Alterado
- **`/admin/google-ads` refeita na régua do painel.** A tela abria com dois cartões empilhados e,
  logo abaixo, o texto inteiro do guia despejado — quatro passos, listas e duas tabelas no meio
  da métrica. Agora ela tem um cartão de abertura só ("Ligar o Google Ads"): o que falta dito em
  duas linhas, os nomes das chaves no painel lateral e o botão que leva à tela certa. Com a conta
  conectada, a tela é a de sempre — conta, filtros, seis números e a tabela. O passo a passo
  continua a um clique, no "Como usar esta tela" do cabeçalho; o cartão dobrável e a folha que o
  desenhava saíram do projeto, porque a seção do guia já tem tela própria.

### Corrigido
- **A contagem do disparo só conta o que entrou.** `Notifica::disparar()` devolvia 1 também para
  o aviso que já existia na chave; agora usa `wasRecentlyCreated`, e quem chama sabe se notificou
  de novo ou só confirmou o que estava lá.
- **As credenciais das avaliações do Google deixaram de morar no `.env`.** Conta do Business
  Profile, local e token já eram graváveis na tela de Chaves de integração — mas a importação
  recusava com "configure no `.env`", e quem administra o site não tem terminal. Agora a
  mensagem do comando, do botão "Atualizar do Google" e do aviso da tela de Depoimentos diz
  **qual campo falta** e leva à tela certa (`Sistema → Configurações → Chaves de integração →
  Avaliações do Google`, com âncora própria). O `.env` continua como plano de fundo de quem
  ainda não passou pelo painel.
- **O grupo "Avaliações do Google" ganhou os dois ajustes que só existiam no `.env`**:
  quantas avaliações ler por rodada (`50`) e a nota mínima que vira depoimento (`4`) — mesmo
  validador, mesmo caminho de gravação, nenhuma linha de view nova.
- **`DepoimentosDoGoogle::configurado()` passou a exigir o token.** Antes ele dizia "configurado"
  só com conta e local, e o botão caía no erro da API em vez de explicar que faltava a
  credencial; a conferência agora é a mesma do que a leitura precisa.

## [2.32.1] - 2026-10-26

### Corrigido
- **O cron do Google Ads parou de gritar num log que ninguém lê.** `google-ads:sync` sem
  credenciais devolvia falha também quando era o agendamento quem chamava, e o `schedule:run`
  gravava um `production.ERROR` a cada quinze minutos — numa instalação que ainda não conectou
  conta nenhuma, que é o estado normal. Chamado à mão continua falhando alto, com a lista do que
  falta e o endereço da tela; pelo cron (`--agendamento`) é aviso e saída limpa, porque log cheio
  de falso erro é o jeito certo de fazer a equipe parar de ler o log.

## [2.32.0] - 2026-10-26

### Adicionado
- **A bolha de atendimento no site e no funil.** O robô que existia só dentro do painel agora
  atende o visitante anônimo: botão flutuante no canto, balão que abre, saudação escrita por ele
  mesmo, campo de fala e a mesma conversa que a equipe lê em `/admin/assistente/conversas`.
  A folha (`public/css/golden-assistente.css`) e o script (`public/js/golden-assistente.js`) só
  entram na página quando o interruptor correspondente está ligado — desligado, não sobra nem um
  byte de robô no HTML do visitante.
- **Três endereços JSON** (`POST /assistente/abrir`, `POST /assistente/fala`, `GET /assistente/falas`)
  com throttle próprio — 20 falas por minuto, porque cada uma pode virar uma chamada de IA que
  cobra por token. O `canal` (`site` ou `funil`) e a página vêm em toda chamada e ficam gravados
  na conversa: quando a conversa anônima vira lead, a origem já está registrada.
- **O `window.GOLDEN_IA`**, impresso pelo partial da bolha, é o contrato entre as duas pontas: o
  script não sabe nada de IA, só pinta e pergunta; quem decide responder, escalar ou calar é o
  servidor. Nada do que o visitante digita vira HTML na tela — `textContent`, sempre.
- **`Assistente::paraABolha()`** — a leitura em cache da ficha (`golden.assistente_bolha`), do
  mesmo jeito que `Page::paraSite()` serve o rodapé. Nenhuma página pública emite `SELECT` para
  montar o robô, e desligá-lo no painel vale na hora.
- **O cadastro amarra a conversa ao lead.** `LeadService::capturar()` avisa o `Atendedor`, que
  entrega à equipe o resumo do que o visitante já contou — atendimento e venda ficam na mesma
  ficha, e ninguém reescreve a própria dor.
- **Sem formulário na página, a bolha leva ao `/contato` da casa** com o resumo da conversa já
  escrito no campo de mensagem (`?message=`); havendo formulário, ela rola até ele. É a regra da
  casa: um formulário por assunto, o resto são links.

### Corrigido
- **O assistente parava de responder depois da saudação.** `Conversa::mensagens()` já chega
  ordenada do mais antigo (é assim que o histórico se pinta), e um `latest('id')` por cima não
  desfaz o primeiro critério — só empata. A "última fala" voltava sendo a saudação, o `atender()`
  desistia em silêncio e a conversa morria sem motivo aparente. Agora é `reorder('id', 'desc')`,
  nos dois lugares que leram a janela de falas (`Conversa::ultimaMensagem()` e
  `Atendedor::historico()`), com um teste que viaja no tempo entre as falas para provar que o
  bug morderia sem o conserto.
- **O robô nascia mudo numa instalação limpa.** `firstOrCreate` devolve o modelo com os campos que
  ele mesmo escreveu; os interruptores de atendimento (`ativo`, `no_site`, `no_funil`,
  `no_chamado`) vêm dos padrões da tabela e não estavam entre eles. Sem o `refresh()` depois de
  criar, a primeira página os lê como nulos e a bolha não subia em lugar nenhum.
- **A varredura não martela servidor de hospedagem compartilhada.** Com o balão aberto a resposta
  chega em cinco segundos; fechado, só quem foi passado para a equipe é varrido, e a cada trinta —
  e nunca com a aba em segundo plano.

## [2.31.0] - 2026-10-26

### Adicionado
- **As três telas do assistente no painel** (`/admin/assistente`). A ficha do robô, o que ele
  sabe e o que ele conversa — cada uma no grupo **Atendimento** do menu, cada uma com o seu
  "Como usar esta tela" no cabeçalho.
- **`/admin/assistente` — a persona.** Nome, cargo, saudação, tom de fala, regras próprias, a voz
  com que ele responde e o rosto dele, desenhado pelo campo de arrastar-e-soltar da casa
  (`assistente_avatar` em `config/envios.php`: 96 × 96 px, até 2 MB). Quatro interruptores dizem
  onde ele atende — site, página de funil e chat do chamado — e um cartão ao lado responde a única
  pergunta que importa antes de ligá-lo: *ele consegue responder?*, listando a capacidade que
  ainda não tem chave e levando para a tela de chaves em vez de repetir o formulário ali.
- **`/admin/assistente/conhecimento` — a memória do robô.** Um assunto por linha, com resposta,
  assuntos (tags), origem e endereço da fonte. É daqui que a `Busca` tira a única resposta que o
  assistente tem autorização de dar: o que não está nesta lista, ele não inventa. A listagem filtra
  por busca, origem e situação; a ficha mostra a procedência completa — de onde veio, quando foi
  lido e quem cadastrou — porque resposta sem procedência é a mesma coisa que chute.
- **`/admin/assistente/conversas` — a caixa de entrada do atendimento.** Três contadores (abertas,
  aguardando a equipe, resolvidas), filtro por canal, e a última fala de cada conversa prévia na
  linha. Dentro dela, o histórico com a fala do cliente, do assistente, da equipe e do sistema
  lado a lado, cada uma assinada por quem é; o selo do provedor que respondeu, as fontes que a
  máquina usou (para a equipe conferir, não para crer de cabeça baixa), o arquivo anexado, a caixa
  de resposta da equipe e o seletor de status.
- **Bolha de pendência no menu**: o item "Conversas do site" leva o número de conversas
  `aguardando_equipe`, contado em `AppServiceProvider::pendenciasDoPainel()` — a mesma fonte que
  alimenta as outras bolhas do painel.
- **Camada multi-provedor de IA** (`App\Services\Assistente`), que é o chão destas telas: `Motor`
  é a porta única de toda chamada, `Fabrica` escolhe provedor e modelo *por capacidade* (banco →
  `.env` → padrão da casa) e nenhum modelo vira HTTP sem estar na lista que a própria tela oferece.
  Gemini e OpenAI por enquanto; acrescentar um terceiro é uma entrada no config mais uma classe.

### Corrigido
- **A foto do depoimento nunca chegava ao banco.** O dropzone do avatar vivia na coluna da
  direita, fora do `<form>` — a tela aceitava o arraste, o upload respondia ok, e o `avatar`
  simplesmente não estava no POST. O formulário agora abraça a linha inteira, e o mesmo desenho
  foi conferido em todas as outras nove telas que usam o campo de arrastar-e-soltar.
- **`Assistente::instrucoes()` mandava o texto com `\n` escrito.** As regras do atendimento
  chegavam ao provedor como uma linha única com a barra e o nê visíveis, porque as aspas do
  config eram simples. Agora são aspas duplas de verdade e o teste recusa qualquer barra-n literal.
- Um POST rejeitado na persona **criava a ficha antes de validar**, deixando uma linha órfã com
  nome vazio quando o administrador errava o tom ou a voz. A ficha só nasce depois que o `validar()`
  passa.

### Testado
- `AssistenteDoPainelTest` (15 testes) trava o que a view não dá para conferir olhando: a ficha
  nasce dos padrões e é linha única; tom e voz fora da lista são recusados sem gravar nada; o
  avatar só vale se veio do campo certo e está no disco; campo ausente no POST preserva o rosto e
  rosto novo apaga o arquivo velho; a ficha lista as capacidades sem chave; referência duplicada de
  conhecimento é recusada; assumir a conversa pela equipe desliga a máquina; o selo de cada status
  vem de `config('golden.tons_status')`; e a persona só oferece as vozes que a `Fabrica` sabe usar.
- `CamposDeEnvioTest` ganhou a trava estrutural que faltava: **todo dropzone vive dentro do
  próprio `<form>`**, lido na fonte de todas as views. A regra foi validada nos dois sentidos — com
  a tela consertada o teste passa, e com o `</form>` devolvido para antes da coluna da direita ele
  falha apontando o arquivo.
- `PaginasDoPainelTest` abre as cinco rotas novas e confere que o vendedor vê as conversas e não
  vê a persona nem o conhecimento. Suite completa: 405 testes, 5038 afirmações.

## [2.30.0] - 2026-10-26

### Mudado
- **A tela do guia voltou a ser uma tela do painel.** `/admin/guia` era a única tela do sistema
  desenhada por fora do padrão: texto solto sobre a fundo, sem moldura, com um campo de busca
  escrito à mão na view e classes utilitárias decidindo peso e espaçamento bloco a bloco. Agora cada
  grupo é um cartão `x-admin.card` — sete cartões, Começando a Sistema, cada um com o seu ícone — e o
  índice do lado esquerdo é o cartão "Procurar no guia" como qualquer outro da casa.
- **O campo de busca é o `x-admin.filter` compartilhado**, com o `id` opcional que o componente
  ganhou para o script continuar achando o campo. Ganho de tabela: um endereço com `?busca=telefone`
  chega filtrado, porque o valor vem da URL como em toda listagem do painel.
- **Um seletor só decide a medida da leitura** (`70ch` para parágrafo, lista, passo, aviso e
  fechamento). Tabela e bloco de código usam a largura inteira do cartão, que é onde as colunas
  cabem; `68ch` espalhado em cinco regras virou uma.
- O peso e o espaço de cada bloco saíram da view e moram na folha: `.guia-secao-titulo` e
  `.guia-passo-descricao` substituíram `fw-bold text-body` e `small text-secondary`, e
  `mb-0`/`list-unstyled`/`align-middle` sumiram dos dois templates.
- **O índice fixo cabe na tela.** Em `col-lg-3` ele era mais alto que o viewport e as últimas seções
  ficavam fora de alcance para sempre: o cartão passou a ter `max-height:calc(100vh - 2rem)` e a
  lista rola sozinha (`overflow-y:auto` + `overscroll-behavior:contain`), sem arrastar a página.
- No navegador, o grupo some **como cartão** quando a busca não deixa seção nenhuma dentro dele, e o
  rótulo do grupo no índice some junto — antes ficava o título órfão sobre a lista vazia.

### Corrigido
- **A primeira coluna das tabelas do guia** estava presa em `white-space:nowrap`: a célula mais
  estreita possible virava a mais larga, e a coluna de texto — que é a que tem frase — era espremida
  até estourar o cartão em tela estreita. Saiu o `nowrap`, entrou `vertical-align:middle` na folha.
- O texto de abertura dizia "A barra da esquerda traz cinco grupos". Lido dentro do guia, isso
  descrevia a tela errada — o índice do guia tem sete grupos, e quem procurava "Portal do cliente"
  na frase não achava. Diz "O menu lateral", que é do que a frase fala.
- A seção do Google Ads mostrava `*se*` e `*o que*` com o asterisco na tela: ênfase de um asterisco
  só não é nada do que `GuiaPainel::inline()` desenha. Virou `**se**` / `**o que**`, o negrito que o
  resto do guia já usa.

### Testado
- `GuiaDoPainelTest` (14 testes, 761 afirmações) ganhou duas travas sobre a arrumação:
  *a tela é montada com as peças do painel* — o campo vem de `x-admin.filter`, nenhum `<input>` na
  view, nenhum dos utilitários que saíram voltou, um cartão por grupo do config e ícone no cabeçalho
  de cada um; *a folha e o script deixam tudo caber na tela* — sem `nowrap` na tabela, altura máxima
  e rolagem própria no índice, a medida de leitura escrita uma única vez, nenhum hexadecimal na
  folha, a busca sem acento intacta no script e os dois escondimentos estruturais (`[data-grupo]` e
  `[data-nav-grupo]`). O teste da marca crua passou a ler o config seção por seção: nenhum texto que
  passa pelo `inline()` pode guardar ênfase de um asterisco, e o `* * * * *` do cron continua vivo
  onde é comando — dentro do bloco de código, que não formata nada.

## [2.29.0] - 2026-10-25

### Adicionado
- **Chaves de integração no painel** (`/admin/configuracoes#chaves`). A regra da casa passou a valer
  para as credenciais: o que se configura, se configura pela tela, e grava no banco. Três grupos na
  mesma página — **Google Ads** (token de desenvolvedor, ID e segredo OAuth, endereço de volta, MCC,
  vida do cache e período padrão), **Avaliações do Google** (conta, ficha, token e os dois limites de
  leitura) e **E-mail de disparo** (mailer, host, porta, criptografia, usuário, senha, remetente e
  nome). Uma linha nova em `config/golden.php` e a tela aparece: `App\Support\ChavesDeIntegracao` é
  a fonte única dos campos, e nenhuma view decide cor nem rotulagem.
- **O banco passa na frente do `.env`, e não o contrário.** `AppServiceProvider::boot()` chama
  `ChavesDeIntegracao::aplicar()`, que sobrepõe o valor gravado ao `config()` antes de qualquer
  leitura. Os ~15 leitores do sistema — o serviço do Google Ads, o driver Socialite, o Livewire do
  painel, os comandos e a importação de depoimentos — continuaram perguntando `config(...)`: salvar
  na tela vale na hora, sem `config:clear`, sem `artisan` e sem reiniciar servidor. Banco vazio não
  é erro, é o estado em que o `.env` conduz, e a tabela que ainda não existe é apenas um `catch`.
- **Segredo é segredo.** Token, segredo OAuth, senha de e-mail e Bearer do Business Profile saem
  cifrados do banco (`Crypt`) e **não voltam a aparecer na tela** — o formulário mostra *se* estão
  gravados, nunca *o que*. Apagar um segredo é uma chave explícita ao lado dele ("apagar e voltar ao
  `.env`"), porque uma caixa de senha vazia não pode querer dizer "apague a minha senha" a cada
  salvamento do resto do formulário. Campo comum em branco apaga a linha e devolve aquele valor ao
  `.env`: é o mesmo "voltar à foto original" das imagens do site — o painel passa na frente do
  ambiente, não escreve por cima dele.
- `x-admin.campo-chave`, o componente que desenha cada linha: o campo, o selo de onde o valor vem
  (`gravada no banco` / `vem do .env` / `sem valor`, com cor vinda de `tom_por_status`) e a variável
  de plano de fundo ao lado, para quem administra o servidor reconhecer a chave na hora.
- **Um toque no card da galeria abre o vídeo e já o põe rodando** (`/videos`). Antes eram dois
  toques — um no card, outro no vidro. O comando de sincronização e o cartão de espera do Google Ads
  passaram a nomear a tela onde se preenche cada falta, em vez de nomear o arquivo.

### Corrigido
- O guia do painel ensinava a edição do `.env` e mandava rodar `php artisan config:clear` — o que a
  regra nova tornou desnecessário. As seções *Google Ads*, *Configurações*, *Depoimentos* e
  *Ambiente* agora apontam para **Sistema → Configurações → Chaves de integração**, com a tabela do
  nome que a tela usa ao lado da variável que ainda existe como plano de fundo.
- O guia citava `GOOGLE_PLACES_API_KEY`, uma chave que nunca existiu em lugar nenhum: a importação
  lê conta, ficha e token do Business Profile.
- Os cartões de "não configurado" (tela de Depoimentos e `php artisan depoimentos:google`) diziam
  "falta no `.env`"; hoje dizem o que falta e apontam a tela.
- **Uma chave por linha.** Os três cartões de chaves ficam lado a lado em `col-xl-4`, e o componente
  desenhava cada campo em meia coluna: a dica e o nome longo da variável (`GOOGLE_ADS_LOGIN_CUSTOMER_ID`)
  atravessavam a borda e batiam no cartão vizinho, o que tornava a tela ilegível no desktop. Cada campo
  passou a ocupar a linha inteira do próprio cartão, os `'col'` que sobravam em
  `ChavesDeIntegracao::GRUPOS` saíram de cena, e `admin-golden.css` deixou o `<code>` do nome quebrar
  dentro da linha em vez de empurrar o vizinho.

### Mudado
- **Celular e tablet deitam o vídeo.** O toque que dá play — no play da barra ou no card da galeria
  — pede tela cheia no palco do vídeo e trava a orientação na horizontal até onde o navegador
  deixa; onde o pedido é recusado (iOS), a classe `giro` gira o próprio quadro em CSS. Sair pelo
  sistema (o «voltar» do Android, o Esc) levanta o quadro de volta, porque a tela cheia pode acabar
  sem passar pela casa.
- `loadVideoById` entrou no player **só** no ramo do toque que pediu play; `cueVideoById` continua
  sendo o caminho de quem só abriu o quadro. A guarda de autoplay passou a contar os `load`s em vez
  de proibir a palavra.

### Testado
- `ChavesDeIntegracaoTest` (13 testes) trava a regra por todos os lados em que ela pode quebrar:
  banco vence `.env`, `.env` conduz o que o banco não tem, campo apagado devolve o valor ao
  ambiente, toda chave aponta para um `config` que existe de verdade, segredo cifrado no banco e
  ausente do HTML, a tela mostra nome e origem de cada campo, cada campo ocupa a linha inteira do
  cartão, POST do grupo `email` muda `config('mail.mailers.smtp.*')`, segredo em branco sem pedir
  apagar **não** apaga nada, os números do Google Ads voltam inteiros, grupo desconhecido não
  escreve, e o vendedor lê mas não edita.
- `GuiaDoPainelTest` trocou a exigência: o guia ainda nomeia as quatro credenciais que a tela
  reclama, mas agora **proíbe** `config:clear` na seção do Google Ads. `PaginaDeFunilTest` ganhou
  o deitar em tela cheia (pedido nativo, trava de orientação, `giro` e a escuta de saída), e
  `GaleriaDeVideosTest`, o abrir-e-tocar de um toque com o quadro visível antes do pedido.

### Documentado
- `docs/INSTALACAO.md`: a seção 7 abre declarando que **o `.env` é plano de fundo**, não o painel de
  controle; a seção 9.1 do Google Ads perdeu o bloco de variáveis e ganhou a tabela "nome na tela ↔
  variável"; a caixa de disparo se preenche na tela; as chaves do Business Profile idem. O README
  diz onde a credencial de SMTP se escreve hoje. O PDF de instalação foi regenerado da mesma fonte.

## [2.28.0] - 2026-10-25

### Adicionado
- **Guia de uso dentro do painel** (`/admin/guia`): 25 seções em 7 grupos — Começando, Comercial,
  Vendas, Atendimento, Conteúdo do site, Portal do cliente e Sistema — cobrindo cada área do
  sistema do primeiro login ao diagnóstico. A coluna da esquerda lista as seções por grupo, a busca
  filtra em português (sem se enganar com acento: "contrato" acha "Contratos") e a seção por onde a
  rolagem passa fica marcada, sozinha. Quem opera o painel abre o guia pelo mesmo menu que o
  administrador, com o mesmo texto: não há versão "só para admin" da documentação.
- **O texto mora num lugar só.** `config/guia_painel.php` é a fonte; `App\Support\GuiaPainel` lê. A
  tela do guia, o cartão dobrável da tela de Google Ads e o link do cabeçalho consomem os mesmos
  blocos — o que está escrito no guia é literalmente o que a tela diz, e corrigir um lugar corrige
  todos. Seis tipos de bloco (`texto`, `passos`, `lista`, `codigo`, `tabela`, `aviso`), `**negrito**`
  e `` `código` `` inline, nenhum HTML cru no config, e passo nenhum com número: a ordem já está na
  lista (regra da 2.12.x valendo para a documentação também).
- **A instrução aparece onde a dúvida acontece.** No cabeçalho de cada tela do painel, uma linha
  discreta — "Como usar esta tela" — leva direto à âncora da seção que documenta aquela rota
  (`admin.leads.*` → `#leads`, `admin.google-ads.*` → `#google-ads`). São 20 telas cobertas por um
  `@include` no layout, sem editar uma linha de cada view; a rota que não tem seção não oferece
  link, então o guia não promete o que ainda não escreveu.
- **Google Ads: os quatro passos do Google na própria tela.** O cartão "Como configurar — os quatro
  passos no Google, o .env e o comando" vive em `admin/google-ads` e no preview de criativo, fechado
  por padrão, **nascendo aberto quando falta credencial** — quem está no passo que ele explica não
  precisa procurá-lo. Dentro: conta gerente (MCC), token de desenvolvedor, tela de consentimento com
  a URI `https://SEU_DOMINIO/auth/google-ads/callback`, ativar a API, as cinco linhas do `.env`,
  `config:clear`, `google-ads:sync` e a tabela do que fazer quando a tela reclama.

### Testado
- `GuiaDoPainelTest` (12 testes, 525 asserções) trava a documentação: toda seção tem o que desenhar,
  **toda rota e prefixo que o guia cita ainda existem** (guia apontando para rota renomeada é guia
  desmentido pelo sistema), cada item do menu tem seção, cada tom de aviso tem par no dark e no
  light, nenhum passo voltou a ser numerado, o link do cabeçalho cai na âncora certa, o cartão abre
  com credencial faltando e fecha com ela presente, e a tela carrega folha e script próprios. A
  seção do Google Ads nomeia as quatro credenciais que `GoogleAdsService::credenciaisFaltantes()`
  reclama — o guia não pode ensinar a preencher outra coisa.
- `ConexaoGoogleAdsTest`: a régua do cartão de espera passou a ser a frase própria dele ("sincronizada
  em segundo plano") em vez da palavra "Carregando", que o guia agora cita de propósito para a pessoa
  reconhecer o sintoma. 359 testes, 4.298 asserções verdes.

### Documentado
- `docs/INSTALACAO.md` ganhou a seção *Guia de uso dentro do painel* (era a 14; as seguintes
  desceram uma casa) e um apontamento no passo a passo do Google Ads. O README lista as fontes
  novas na estrutura.

## [2.27.1] - 2026-10-25

### Corrigido
- **Os seis cartões de resumo do Google Ads respiram.** Com a linha dividida em seis colunas,
  cada cartão ficava com ~120 px em tela de 1440 px e o rótulo em caixa alta — que por decisão da
  2.26.2 pode quebrar linha — se partia no meio ("49.1 00 / IMPRESS ÕES", "R$ 3.98 3,60 / CUSTO").
  A grade passou a `col-12 col-sm-6 col-lg-4`: três por linha no desktop, dois no tablet, um no
  celular, com o número e o rótulo inteiros. O remédio é a largura, não a tipografia: o rótulo
  continua grande e em caixa alta, e nenhuma palavra volta a ser cortada ao meio.

## [2.27.0] - 2026-10-25

### Adicionado
- **Google Ads dentro do painel** (admin → Comercial → Google Ads). Cada pessoa que entra no
  painel conecta a própria conta de anunciante pelo OAuth do Google e passa a ver impressões,
  cliques, custo, conversões, CTR médio e ROAS das campanhas — em português, com o dinheiro
  formatado pela casa. A tela é o componente Livewire `GoogleAdsDashboard`; a conta fica em
  `google_ads_accounts` com o token de renovação **cifrado no banco** (`cast encrypted`), e cada
  chamada devolvida pelo Google fica registrada em `google_ads_api_logs`.
- **A leitura nunca sai do cache.** O painel pede a janela ao `GoogleAdsCache`
  (`google_ads:metrics:{user}:{customer}:{dias}`, meia hora de vida) e, se ela não existe,
  enfileira `SyncGoogleAdsMetrics` e mostra **"Carregando…" com `wire:poll` de 3 s** — a tela se
  refaz sozinha quando os números chegam. Uma recarga de painel, portanto, não custa uma chamada
  de rede nem estoura a cota da API. O agendamento (`google-ads:sync`, a cada 15 min) faz o mesmo
  para todas as contas ativas; o job tem 3 tentativas com espera de 30 s, 2 min e 5 min, e quando
  o Google recusa a credencial ele **desconecta a conta e avisa o dono por e-mail** em vez de
  tentar de novo — token revogado não se conserta em fila.
- **Camada de serviço própria, com a biblioteca oficial.** `GoogleAdsService`
  (`app/Services/GoogleAds`) monta o cliente, roda GAQL, divide `cost_micros`/`average_cpc` por
  milhão para chegar em reais e traduz os enums (`ENABLED` → Ativa, `PAUSED` → Pausada,
  `REMOVED` → Removida). Ela é registrada por um `GoogleAdsServiceProvider` que também cadastra o
  driver `google-ads` do Socialite com `access_type=offline` e `prompt=consent` — sem esses dois
  o Google não devolve token de renovação e a conta "conectaria" uma única vez. Nada de
  wrappers da API descontinuada: `googleads/google-ads-php` V26, `API Escrow` e transporte REST.
- **Pausar e ativar campanha, com a prova antes da escrita.** Os botões da tabela são formulário
  POST comum (herdam a confirmação em SweetAlert2 e o retorno em toastr do painel) e o controller
  chama **duas** vezes: primeiro `validarOperacao` (`validate_only => true`, que percorre
  permissão, status e FieldMask sem tocar na campanha) e só depois `mutateCampaigns`. A
  campanha é conferida contra a lista sincronizada **desta conta** — um `campaign_id` digitado à
  mão ou de outra conta conectada não escreve em lugar nenhum.
- **Prévia do anúncio responsivo** (`RsaPreview`, admin → Comercial → Criativo do anúncio): até
  15 títulos de 30 caracteres e 4 descrições de 90, com contador por campo que fica verde, âmbar
  ou vermelho conforme a trava do Google, URL final com as duas rotas de display, e o mock do
  resultado da busca em três combinações de títulos/descrições, alternando desktop e celular. Não
  existe API oficial de prévia — o mock em HTML/CSS é o padrão do mercado, e ele é desenhado com
  as cores da busca, não as do painel.
- **Folha `public/css/golden-ads.css`** com os contadores, o cartão da conta e o mock da busca,
  todos por token (`--anuncio-*`, criados em `golden-theme.css` com par claro e escuro).
- **Testes** (`tests/Feature/ConexaoGoogleAdsTest.php`, 12 casos): o pedido de acesso carrega
  offline + escopo + `redirect_uri` verdadeiros; o callback guarda a conta e o banco não devolve
  o token em texto; reconectar **atualiza a mesma fila** em vez de duplicar; a tela lê da cache e
  enfileira em vez de chamar a API; ninguém escreve na conta de outro; pausar passa pela operação
  simulada antes da verdadeira; e o job que recebe credencial recusada desconecta, avisa e escreve
  o erro na janela.

### Configurado
- `.env.example` ganha o bloco `GOOGLE_ADS_*` comentado (token de desenvolvedor, app OAuth,
  `LOGIN_CUSTOMER_ID` da MCC sem hífen, `GOOGLE_ADS_TRANSPORT`, `GOOGLE_ADS_CACHE_STORE`,
  `GOOGLE_ADS_CACHE_MINUTOS`, `GOOGLE_ADS_DIAS`) e `config/services.php` o grupo `google_ads`.
  Faltando qualquer um, a tela diz **qual** variável falta em vez de quebrar.

### Decidido
- **Transporte REST por padrão.** A hospedagem em produção é compartilhada: sem root e sem pecl,
  a extensão `grpc` do PHP não entra. O mesmo `GOOGLE_ADS_TRANSPORT=grpc` ativa o transporte
  rápido numa máquina própria, sem mudar uma linha de código.
- **A cache pode cair no store de arquivos.** A hospedagem tem a extensão Redis do PHP mas não o
  servidor Redis, então `GOOGLE_ADS_CACHE_STORE` fica vazio e a métrica vive onde o app já guarda
  cache; com um servidor Redis, `redis` liga tudo sem outra mudança.

## [2.26.2] - 2026-10-25

### Corrigido
- **O site para de sacudir quando se arrasta a tela para o lado no celular.** A assinatura do
  rodapé foi publicada quebrando linha proibida (`white-space:nowrap`) e ainda com a calha dos
  flutuantes somada à própria largura: numa tela de 390 px ela passava a página inteira em
  87 px, o navegador entendia que havia um lado direito para mostrar e habilitava a rolagem
  horizontal. A medida foi feita em tela, com o documento real, e não por suposição — e ela
  achou dois transbordos antigos que a assinatura só tornou visível: a linha de botões
  "Detalhes" + "Adicionar" do cartão de produto (15 px no celular, 34 px no tablet) e o e-mail
  `comercial@goldenrastreamento.com.br`, uma palavra maior que a coluna do rodapé, que alargava
  a grade em qualquer desktop.
- **A regra, agora única:** nenhuma coluna de grade cresce por causa do próprio conteúdo
  (`minmax(0,…)` em toda `grid-template-columns` do site), o que não cabe quebra linha em vez de
  esticar (a assinatura e a linha de ações dos cartões), e uma palavra maior que a caixa quebra
  no meio em vez de vazar tinta para fora da tela (`overflow-wrap` no `body`). A coluna de
  contato do rodapé ganhou o espaço que faltava para o e-mail continuar inteiro em desktop.
  Medido em `/`, `/loja`, `/videos` e `/c/frota-j16` em dez larguras, de 320 px a 1440 px:
  zero de rolagem horizontal.

### Adicionado
- **Teste que vigia a largura da página** (`test_nada_na_pagina_publica_e_mais_largo_que_a_tela`):
  nenhuma `grid-template-columns` pode voltar a declarar coluna `1fr` seca, a assinatura não pode
  voltar a cravar linha única, a calha dos flutuantes não pode passar de 150 px e a linha de
  ações do cartão de produto tem de poder quebrar.

## [2.26.1] - 2026-10-25

### Alterado
- **A agência assinada no rodapé é a cliente, não o autor do código.** Onde estava
  "KDKHOST" entra **Eth! Estratégias** — <https://ethestrategias.com.br>. O nome do
  desenvolvedor continua apontando para o endereço dele, e o teste que vigiava a
  correspondência entre tela e `composer.json` passou a comparar só o desenvolvedor:
  a agência não tem nada a ver com o autor do pacote.

## [2.26.0] - 2026-10-25

### Adicionado
- **A assinatura de quem fez o sistema**, no canto direito do rodapé: o nome do desenvolvedor
  e a agência, cada um com o próprio link. Vive em `config('golden.credito')` — a mesma fonte
  dos autores do `composer.json`, que um teste compara com a tela para o site não desmentir o
  pacote. O rodapé do site e o da página de campanha incluem o mesmo parcial
  (`partials.desenvolvedor`); nenhum dos dois escreve URL. Ela desvia da calha dos botões
  flutuantes (`--flutuante-largura`), que paira exatamente sobre o fim da página — sem a
  calha, o nome ficava atrás dos dois botões em toda tela estreita.
- **Uma régua só para o canto direito.** A distância do pé da tela virou o token
  `--flutuante-bottom` (em `golden-to-top.css`, carregado por site, funil, portal e painel), e
  WhatsApp flutuante, caixa multicanal e "voltar ao topo" passam a obedecê-lo.

### Corrigido
- **Os flutuantes não ficam mais atrás do selo do reCAPTCHA.** O selo é fixo, senta no pé da
  tela com 60 px de altura e tem precedência sobre qualquer `z-index` nosso, então o botão do
  WhatsApp e o do topo desapareciam atrás dele numa página com captcha. Com
  `body:has(.grecaptcha-badge)`, a régua do canto ganha os 74 px do selo e o stack inteiro
  sobe junto, animado; sem captcha, nada muda e o canto continua encostado na borda. O selo
  não é escondido — as regras do serviço exigem que ele apareça.

## [2.25.4] - 2026-10-25

### Corrigido
- **O instalador funciona num servidor limpo.** Ele gerava a `APP_KEY` antes de instalar as
  dependências; num clone onde o sistema nunca esteve, o `artisan` nem começa sem o `vendor/`,
  e a instalação morria no terceiro passo com um erro mudo. Agora o `composer install` vem
  primeiro (e é pulado quando o `vendor/` já existe, que é o caso do deploy do dia a dia).
- **A queda do Composer.** O download usava `copy()` do PHP, que depende de `allow_url_fopen` —
  desligado por padrão no EasyApache. Numa conta de cPanel o arquivo não vinha e o instalador
  seguia adiante apontando para um `.phar` inexistente. Passa a baixar com `curl`, com o `copy()`
  como plano B.
- **A versão impressa na tela** (`instalador v2.12.0`) estava presa a uma entrega antiga; agora
  acompanha a versão do sistema.

### Adicionado
- **`--estatico=ARQUIVO`**, repetível, no instalador. Ao substituir um site que já estava no ar,
  alguma URL antiga costuma continuar citada em algum lugar — a política de privacidade de um app
  publicado na Play Store é o caso real desta entrega. A opção escreve, no `.htaccess` gerado, uma
  regra que serve o arquivo da raiz na URL de origem, antes da reescrita para dentro de `public/`.
  O instalador recusa nome com caractere de regex e exige que o arquivo exista na raiz.

## [2.25.3] - 2026-10-25

### Adicionado
- **A máscara de topo.** `.video-mascara`, no partial do player, é uma faixa de 96 px com o
  preto da casa que desce sobre o quadro no instante em que o vídeo começa a tocar e sobe
  sozinha depois. É a resposta ao cartão de título — avatar, nome do vídeo e nome do canal —
  que o YouTube pinta nos primeiros segundos de **toda** reprodução, inclusive na retomada do
  meio do vídeo. Ela não recebe clique (`pointer-events:none`) e não esconde a imagem: é
  gradiente, não cortina.
- **As duas esquinas da barra.** `.video-controles::before` e `::after` fecham os cantos
  inferiores de baixo com o preto da barra. Ali o iframe desenha o botão de link/compartilhar
  à esquerda e a marca "YouTube" à direita, e não existe `playerVar` que os tire. Cobrimos só
  as pontas: o meio da faixa continua livre, porque estes tutoriais trazem a legenda queimada
  na própria imagem, e um corte que fechasse a barra inteira comeria a legenda junto.
- **O selo da casa por cima da bolha dele.** No mesmo instante do tocar, o selo dourado do
  vidro entra maior (`.video-vidro.pulso`, escala 1,55) exatamente sobre o centro do quadro,
  onde o YouTube desenha a própria bolha cinza de play/pause, e sai junto com a máscara. O
  ícone do selo é o mesmo da barra — play parado, pause tocando —, de modo que o único
  play/pause visível na tela é sempre o da casa.
- **Uma janela medida, não suposta.** `JANELA_DAS_MARCAS` é o tempo que a cobertura fica no ar,
  e ele veio de medição no player real: o cartão de título some por volta de 7 s e a bolha do
  centro ainda está inteira aos 5 s, já tendo ido aos 9. A janela é 9 s, e um temporizador só
  governa as duas coberturas — as duas janelas antigas (6 s e 2,8 s) deixavam a bolha exposta.
- **Trava em teste.** `test_a_casa_cobre_o_que_o_youtube_pinta_durante_a_reproducao()` responde
  pela máscara, pelas esquinas, pelo pulso do selo, pela janela única e pelo fato de o `marca`
  cair no `PLAYING` e subir no `PAUSED`.

### Corrigido
- **Os controles da casa pintavam debaixo da própria cobertura.** As esquinas novas são
  pseudo-elementos posicionados, e pseudo-elemento posicionado pinta depois de irmão não
  posicionado: na primeira versão o `::before` tapava o play e o mudo da barra. Levaram
  `position:relative` e voltaram para a frente.

### Limitação conhecida — encerrada
- A "Limitação conhecida" da 2.25.2, sobre a interface do YouTube aparecer enquanto o vídeo
  toca, não existe mais. O vídeo continua vindo do YouTube, mas nada da interface dele fica na
  tela: os controles estão desligados, o vidro opaco cobre o quadro em todos os outros
  momentos, e o que o player desenha por cima da imagem durante a reprodução — cartão de
  título, bolha central, botão de link e marca — passa a ser coberto pela casa.

## [2.25.2] - 2026-10-25

### Adicionado
- **Um player só para a casa inteira.** `resources/views/site/partials/video-player.blade.php`
  é a única peça de vídeo que existe: a faixa da campanha e o modal da galeria incluem o
  mesmo partial e ganham o mesmo quadro, o mesmo vidro e os mesmos controles. Antes havia
  duas máquinas escritas soltas — o `public/js/golden-funil.js` da faixa e o iframe que o
  `initVideos()` da galeria montava na hora — e as duas já tinham divergido em comportamento.
  O `golden-funil.js` foi excluído; o vídeo mora só em `golden-site.js`, e o `<iframe>` não
  é mais escrito em lugar nenhum: quem monta o player é a API, sobre o `.video-quadro`.
- **O quadro abre coberto e calado.** O vidro opaco (`.video-vidro.cobre`, `#05070a`) fica
  sobre o player enquanto o vídeo não está tocando de fato, com o selo dourado de play por
  cima. É o que não deixa a barra de título, o logo e a tela final do YouTube aparecerem
  para quem só abriu o quadro e ainda não apertou play.
- **O iframe passa a ter nome da casa.** O `title` que o visitante e o leitor de tela leem
  é o rótulo do bloco ou o título do tutorial, nunca "YouTube video player". Como a API
  reescreve o atributo depois que recebe os metadados do vídeo, um `MutationObserver`
  preso ao `title` do iframe (`inst.getIframe()`) costura o rótulo de volta toda vez.

### Removido
- **Autoplay, em qualquer superfície.** O parâmetro que a casa pede é `autoplay: 0`, o
  `embed/…?autoplay=1` do modal antigo sumiu junto com `Video::embed()`, e abrir um quadro
  nunca chama `playVideo()`: abrir é só abrir. Trocar de vídeo no modal usa
  `cueVideoById` (deixa pronto, não toca) em vez de `loadVideoById` (que toca). O único
  caminho para o som começar é um toque em play nosso — inclusive o toque dado antes de a
  API chegar, que é guardado e aplicado no `onReady`, porque foi o visitante que pediu.
- **Todo convite ao YouTube que a página fazia.** O link vermelho "Assistir no YouTube" do
  modal e o `.video-modal-yt` saíram; o `<link rel="preconnect">` do YouTube saiu do layout
  do funil e agora só é empurrado pela página que realmente tem vídeo. Nenhuma marca, botão
  ou texto do YouTube é escrito por nós em lugar nenhum.

### Limitação conhecida
- Enquanto o vídeo **está tocando**, o player do YouTube desenha a interface dele por cima
  da imagem — em janela estreita é o logo "Shorts", o volume, os três pontos, o joinha e o
  compartilhamento no canto direito, mais o avatar e o `@canal` embaixo à esquerda. Não há
  parâmetro da API que desligue isso (`controls: 0` e `modestbranding: 1` são o que existe
  oficial), e a casa cobre o quadro com o vidro em todos os outros momentos. A única forma
  de o vídeo tocar sem nenhuma marca de fora é servir o arquivo no próprio site.

## [2.25.1] - 2026-10-25

### Removido
- **O quadro que flutuava sobre a página.** Na 2.25.0 o vídeo da campanha se soltava da
  faixa ao rolar e ficava fixo no centro da tela, voltava ao lugar de origem pausado quando
  a rolagem devolvia a faixa, e ganhava um botão de fechar. Não ficou bom: o retângulo
  grande no meio da tela tapa a chamada da campanha justamente na hora em que o visitante
  está lendo a oferta, e o "voltar e pausar" confundia mais do que ajudava. O vídeo agora
  **fica onde a faixa o pôs** — rolar não o solta, não pausa e não retoma nada.
  Foram junto o `IntersectionObserver`, a classe `.flutuante`, o `.f-video-fechar` e as
  regras de CSS do quadro fixo.
- O que a 2.25.0 trouxe de vídeo **continua valendo**: tocar na própria página, sem modal,
  sem a marca e sem a barra do YouTube (`youtube-nocookie` + `controls: 0` + o vidro que
  fica com o clique e com o botão direito), os controles da casa (tocar/pausar, mudo e a
  régua de volume) e o retorno ao cartaz quando o vídeo termina. A correção do
  `animation-fill-mode` (`both` → `backwards` em `main>section`) fica: reter `transform`
  na seção é o tipo de armadilha que engole qualquer `position: fixed` dentro dela, e há
  teste travando a regra.

## [2.25.0] - 2026-10-25

### Adicionado
- **O vídeo da campanha toca na própria página.** O bloco "Vídeo de vendas" do funil
  abria o modal da galeria, que é da superfície do site: na página de campanha ele
  devolvia a marca e as informações do YouTube por cima do conteúdo. Agora o quadro
  nasce na própria faixa, com a API montando o iframe em `.f-video-quadro` dentro de
  `youtube-nocookie.com` com `controls: 0`, `disablekb: 1`, `fs: 0`, `rel: 0`,
  `modestbranding: 1` e `playsinline: 1`, coberto por um **vidro** nosso que fica com
  o clique e com o **botão direito**. Os controles que existem são os da casa — tocar/
  pausar, mudo e a régua de volume — mais o fechar, que só aparece com o quadro solto.
  A API do YouTube só é pedida no primeiro toque em assistir.
- **O quadro flutua com a rolagem, sempre pelo centro.** Passou do vídeo ao rolar para
  baixo, ele se desprende da faixa e fica fixo **no centro da tela** (nunca no canto);
  a rolagem devolveu a faixa à vista, ele volta ao lugar de origem **e pausa** — pausa
  do percurso, não do visitante — e rolar para baixo outra vez o solta **retomando do
  ponto em que parou**. Terminou, ou o visitante fechou, volta ao cartaz e para no zero.
- **Carrossel compartilhado do site.** `initDepoimentos` virou `initCarrosseis` em
  `public/js/golden-site.js`: um `div.carrossel` + `.swiper.js-carrossel` é agora o
  único jeito de fazer fileira deslizante, no site e no funil. O ritmo, o rótulo para o
  leitor de tela e os breakpoints vêm dos atributos `data-avanco`, `data-rotulo`,
  `data-sentido` e `data-passos` do próprio elemento; a regra de deslizar fica em um
  lugar só. Avanço automático **para ao segurar** no celular e **ao passar o mouse** no
  desktop, e a fila de controles some quando não há para onde ir
  (`.carrossel.travado .carro-ctl{display:none}`).
- **A prova social do funil é o carrossel com avatar.** `FunnelBlock::depoimentos()`
  coloca na frente os **depoimentos aprovados da plataforma** — com cidade ou papel,
  nota em estrelas, foto quando existe (senão as iniciais) e o selo do Google para os
  importados — e soma, sem repetir ninguém, o que for digitado no próprio bloco. O campo
  **Foto do depoimento** entrou nas colunas do tipo no painel.

### Alterado
- **Benefícios: linha única no notebook, um por vez no celular.** Os quatro cards que
  quebravam 3 + 1 viraram a mesma fileira deslizante: até **4 por vez** a partir de
  1100 px, dois a partir de 640 px, **um por vez** abaixo disso, com arraste para
  qualquer lado, **sentido reverso** e 4,2 s de avanço. Cards iguais em altura, ícone,
  título e texto, e o hover que levanta o card voltou.
- **Campanha centralizada na tela larga.** A partir de 721 px os títulos, os resumos, a
  contagem regressiva e as chamadas de **Benefícios**, **Prova social** e **Urgência**
  ficam no centro — a contagem da urgência não anda mais solta à esquerda com vão morto
  ao lado. No celular tudo continua alinhado à esquerda.
- **O Swiper só é chamado por quem desliza.** `FunnelPage::temCarrossel()` decide, na
  view, se a folha e o script (154 KB de JS) entram nos `@stack` da carcaça do funil.
  Campanha sem faixa deslizante continua sem um byte de Swiper, e o carrossel é
  self-hosted em `public/vendor/swiper/` — nada de CDN no caminho.
- **`rewind`, não `loop`.** Com poucos slides o `loop` do Swiper duplica o miolo da
  lista e reclama no console; o `rewind` volta ao começo sem inventar depoimento e
  funciona com qualquer número de cards por vez.

### Corrigido
- **A animação de abertura prendia o vídeo fora da tela.** `main>section` entrava com
  `animation: pageIn … both`, e o `both` retém o último keyframe para sempre — inclusive
  o `transform`. Uma seção com `transform` (mesmo o `matrix(1,0,0,1,0,0)` da identidade)
  vira o bloco de contenção dos descendentes `position: fixed`, então o quadro flutuante
  se media pela seção, não pela janela, e aparecia acima da tela. A folha agora usa
  `backwards`: o estado final do keyframe já é o padrão do elemento, nada fica retido e
  o centro volta a ser o centro da janela. Há teste travando essa regra.
- **Depoimento aprovado duas vezes era um card só.** A mesclagem comparava o digitado do
  bloco com os aprovados, mas não os aprovados entre si; a mesma pessoa aprovada pelo
  painel e pelo portal aparecia repetida na mesma faixa. A comparação de nome
  (sem espaço, acento nem pontuação) agora vale para as duas origens.

## [2.24.0] - 2026-10-25

### Adicionado
- **A galeria de vídeos tem páginação.** `/videos` mostrava os 24 tutoriais de uma vez e
  a lista só cresce: cada cartão puxa uma miniatura de fora, então a página ficava longa,
  pesada e lenta para abrir. Agora saem **12 por página** — régua única em
  `config('golden.videos_por_pagina')`, a tela não escolhe número próprio — com o resumo
  "1–12 de 24 tutoriais" e o pager abaixo da grade.
- **Pager do site, não do painel.** O view padrão do framework é o do Tailwind e o do
  painel é o do Bootstrap; nenhum dos dois veste o CSS do site. Nasceu
  `resources/views/pagination/site.blade.php`: disco dourado na página atual, chevron de
  Font Awesome nas setas, `aria-current`/`aria-label` em português, seta desativada (não
  sumida) na primeira e na última. O filtro de categoria é preservado em cada link.
- **A tecla de ar da campanha.** *Comercial → Marketing (funil)* ganhou um botão de
  ligar/desligar na listagem e no contador da campanha: publicar e pausar sem reescrever a
  página inteira no formulário. Confirmação em SweetAlert2, retorno em toastr, e a ação
  fica na auditoria (`funil_publicada` / `funil_pausada`). Uma campanha sem bloco nenhum
  não sobe — a tecla avisa e não abre um endereço em branco para quem clicou no anúncio.
- **Entrega estática é regra do repositório.** O `public/.htaccess` passou a declarar a
  entrega de tudo que sai da pasta `public`: um ano de cache para CSS, JS, imagem e fonte,
  zero para HTML, `Access-Control-Allow-Origin` nas fontes e compressão como par do
  brotli da hospedagem. Cada arquivo já é chamado com `?v=<filemtime>`, então o endereço
  muda sozinho quando o arquivo muda — cache longo sem risco de tela velha.

### Alterado
- **Os ícones são servidos pela casa.** O CSS de ícone vinha do cdnjs em site, funil,
  painel, portal e tela de acesso: resolução de DNS, aperto de mão TLS e uma conexão nova
  no caminho do primeiro pixel. Font Awesome 6.5.2 copiado para
  `public/css/fontawesome/` (a distribuição oficial, `css/` e `webfonts/` lado a lado,
  porque o CSS chama a fonte por caminho relativo) e chamado por um único
  `partials/icones.blade.php`. As miniaturas do YouTube ganharam `preconnect` — só na
  página de vídeos, que é a única que as usa.
- **A foto do bloco de texto virou plano de fundo.** No bloco "Texto sobre a foto" da
  página de campanha a imagem aparecia emoldurada à direita do texto. Agora ela cobre a
  faixa inteira, com o mesmo véu em gradiente já aprovado no herói: o texto entra por
  cima, a foto aparece por trás, e no celular o véu é uniforme porque a coluna é estreita
  demais para um degradê lateral. O campo no painel passou a se chamar **Foto de fundo**,
  com a dica de usar paisagem 1600 × 900 px.
- O item do menu saiu de "Páginas de funil" para **Marketing (funil)**, que é o nome que o
  operador usa para a coisa.

### Corrigido
- **A página atual do pager do site encolhia.** Faltava a classe base no `<span>` do
  número corrente, então ele não recebia a caixa compartilhada e virava um traço dourado
  apertado em volta do algarismo.

### Detalhes
- A galeria é fatiada **em memória**, sobre o cache do modelo: paginar consultando o banco
  a cada página jogaria fora o que faz a tela abrir sem nenhum `SELECT`.
- `GaleriaDeVideosTest` ganhou três testes (página cheia, `?page=99` volta para a última,
  categoria preservada) e rejeita o markup do framework; `PaginaDeFunilTest` cobre a tecla
  nos dois sentidos, a guarda da campanha sem bloco e o `position:absolute` da foto no CSS;
  `LevezaDasPaginasTest` verifica o caminho relativo de cada fonte declarada no CSS — foi
  ele que pegou o quadradinho no lugar do ícone antes de publicar — e o `.htaccess`.
- Suíte completa: **310 testes, 3.378 asserções**.

## [2.23.1] - 2026-10-25

### Corrigido
- **A paginação do painel voltou a ser do painel.** Toda lista paginada — leads, clientes,
  pedidos, catálogo, conteúdo, vídeos, auditoria, as interações da ficha do CRM e as listas
  do portal do cliente — saía com duas caixas brancas de "Anterior / Próximo", o contador em
  inglês ("Showing 1 to 20 of 24 results") e, no meio da página, um chevron SVG do tamanho da
  tela. A causa era uma só: o Laravel entrega o view de paginação do **Tailwind** por padrão,
  e ele não existe dentro do Bootstrap — as classes não casam com nada e o SVG, sem largura
  definida, cresce até o limite do container.
- Agora há **um único view de paginação no sistema** (`resources/views/pagination/painel.blade.php`),
  registrado uma vez no `AppServiceProvider` e coberto também pelo `simplePaginate`. Ele é
  Bootstrap 5 (`pagination pagination-sm`), fala português ("1–20 de 24", rótulos `aria-label`
  em pt-BR) e mostra os números das páginas, que antes sumiam.
- A página ativa saiu do ciano padrão do Bootstrap — que não tem par no tema escuro — e entrou
  no dourado do painel, por token: `--bs-pagination-*` recebe `var(--gold)`, `var(--brand-gold-ink)`
  e os tokens de borda/fundo, então claro e escuro vêm juntos sem regra extra.

### Detalhes
- `PaginasDoPainelTest::test_a_paginacao_e_do_painel_e_nao_do_framework` abre duas listas e
  exige o markup do painel, rejeitando qualquer rastro do view do framework (`Showing`,
  `px-4 py-2`, `dark:bg-gray-800`). É o teste que impede a volta do bug.
- Suíte completa: **300 testes, 3.279 asserções**.

## [2.23.0] - 2026-10-24

### Adicionado
- **Página de campanha: o funil de vendas que só existe para quem recebe o link.**
  `/c/{slug}` é montada bloco a bloco no painel (*Comercial → Páginas de funil*),
  captura lead na própria página e não aparece em lugar nenhum do site: nem no menu,
  nem no rodapé, nem no `sitemap.xml`, e nasce com `noindex,follow`. Cada campanha
  tem título, descrição, palavras-chave, imagem de compartilhamento, `canonical`,
  pele, píxeis e nota legal próprios — a visibilidade é escolha por página, então dá
  para publicar uma delas na busca sem tocar em código.
- **Nove tipos de bloco, todos editáveis:** abertura com foto, benefícios, oferta,
  prova social, vídeo, urgência com prazo, formulário, dúvidas e conteúdo de apoio.
  O tipo decide quais campos aparecem na tela, e um bloco que muda de tipo perde o
  que o novo tipo não usa — nada de texto velho escondido. O conteúdo dos blocos é
  texto digitável (uma linha por item, colunas separadas por `|`), a lista de tipos
  mora em `config('golden.funil_blocos')` e criar um tipo novo é acrescentar um bloco
  na config: sem controller novo, sem view nova.
- **Contador de visitas por campanha.** A tela da campanha separa gente de robô e
  mostra visitas do período (com únicos e o total desde sempre), telas vistas,
  cliques medidos, cadastros, a taxa visita→cadastro, vendas fechadas, a fatia de
  tráfego pago e os robôs à parte; gráfico dia a dia com as duas séries, origem por
  canal, rede e dispositivo, o **clique por clique** de cada anúncio, a visita por
  visita com a jornada de cada uma e os últimos cadastros — com link para a listagem
  de leads já filtrada naquela página.
- **O clique do anúncio chega ao cadastro, de qualquer plataforma.** Google
  (`gclid`/`gbraid`/`wbraid`), Meta, TikTok, Bing, LinkedIn, X, Yahoo, Instagram e as
  demais redes colam nomes diferentes na URL; o lead agora guarda `clique_id` e
  `clique_valor` além da página que o gerou, então o painel sabe qual anúncio abriu a
  carteira, não só qual rede. O formulário oculto herda da visita registrada quando a
  URL já foi embora, e o pixel de conversão do Google Ads, do Meta e do TikTok ecoa no
  cadastro gravado — medindo aquela campanha, não o site inteiro.
- Tabelas `funnel_pages` e `funnel_blocks`
  (`2026_10_24_000005_criam_as_paginas_de_funil`), as três colunas novas em `leads`
  (`2026_10_24_000006_amarram_o_lead_a_pagina_de_funil`) e a campanha
  **Campanha de frota — J16 4G/2G** (`/c/frota-j16`) semeada pronta com os nove
  blocos na ordem de leitura.

### Detalhes
- **Trocar o endereço não apaga o histórico.** Antes de a mudança ir ao banco, o
  caminho antigo entra no histórico JSON `caminhos` da página: os dois endereços
  seguem servindo a mesma campanha e seguem somando visitas no contador. Um rename
  nunca deixa o relatório mudo.
- O SEO da campanha é completo, mas honesto: `LocalBusiness` sempre, `FAQPage` quando
  há dúvidas preenchidas e `Product` com `Offer` e `aggregateRating` **só com o preço
  declarado** no painel. O seletor não inventa valor nem píxel — as campanhas semeadas
  deixam os dois em branco para o anunciante preencher, e apagar o preço derruba o
  `Product` na hora.
- A carcaça `layouts/funil` leva as mesmas folhas do site (`golden-theme` +
  `golden-site`) e `golden-funil.css`, que só define as três peles (`ouro`, `noite`,
  `claro`) como conjuntos de tokens — sem cor solta no painel. Não há navegação para o
  resto do site: quem chegou por um anúncio tem um único caminho, que é a chamada da
  página. A barra de consentimento, o modal de vídeo e a caixa de WhatsApp viraram
  parciais para as duas carcaças usarem a mesma tela.
- A página inteira (campanha + blocos ativos) sai do cache `golden.paginas_funil`:
  `/c/{slug}` aquecido não faz nenhum SELECT, e gravar no painel derruba a chave na
  hora. O endereço da campanha semeada entrou na promessa de leveza.
- Rascunho e página pausada **não têm endereço e não recebem cadastro** — só página
  publicada aceita um `funnel_page_id`, senão qualquer POST atribuiria lead a uma
  campanha guardada. O funil é área de administrador: o vendedor não abre a tela.
- O formulário de lead continuou um só: a página de contato e o bloco de formulário do
  funil chamam a mesma parcial, mudando só o texto do botão, a linha de confiança e a
  campanha que pede o cadastro.
- O contador é por caminho, não por página com id no rastreador: ele continua
  independente do funil e sem consultar a tabela de campanhas a cada visita.
- Testes novos em `tests/Feature/PaginaDeFunilTest.php` (13 casos: SEO e invisibilidade,
  campanha ausente do site, ciclo rascunho→publicada→pausada, endereço antigo vivo,
  cadastro guardando página e clique do TikTok, herança do `gclid` pela visita, rascunho
  que não recebe lead, gente separada de robô no contador, bloco gravando só o que o tipo
  declara, troca de tipo esvaziando o resto, preço apagando o `Product`, e a área fechada
  ao vendedor). Suíte completa: 298 testes, 3.251 asserções.

## [2.22.0] - 2026-10-24

### Adicionado
- **A galeria de vídeos tutoriais do site antigo voltou.** `/videos` lista os
  tutoriais do aplicativo, da plataforma, das frotas, do bloqueio e das faturas,
  com as categorias no alto da página e o player abrindo na mesma tela — sem
  levar o visitante para fora do site.
- **Admin → Conteúdo → Vídeos tutoriais.** Quem administra cola o link que copiou
  do YouTube (`watch`, `youtu.be`, `shorts`, `embed`, `live` ou os onze caracteres
  sozinhos) e o sistema guarda só o código; a miniatura, o endereço de assistir e o
  player saem dele. O mesmo código não entra duas vezes na galeria, e o link colado
  continua na tela quando o código não é encontrado.
- Tabela `videos` (migration `2026_10_24_000004_cria_a_galeria_de_videos`) e os
  24 tutoriais reais do site antigo já entram semeados, sem numeração de passo e
  com os títulos corrigidos.

### Detalhes
- As categorias vivem em `config('golden.video_categorias')` — rótulo, ícone e cor
  (`tons_status.video`) de uma fonte só, como os kits, os segmentos e os canais.
- A galeria é servida do cache `golden.videos_site`: abrir `/videos` não consulta o
  banco, e gravar no painel derruba o cache na hora. Filtro por categoria é link
  verdadeiro (`?categoria=frotas`), então funciona sem JavaScript e é rastreável.
- O `<iframe>` do YouTube (endereço `youtube-nocookie.com`) só entra no DOM quando o
  visitante abre o vídeo, e sai quando fecha — esconder o modal deixaria o áudio
  tocando. Sem JavaScript o cartão segue sendo um link para o YouTube.
- O modal fica em `z-index:1080`: acima da gaveta do menu e do botão ao topo,
  abaixo da camada do toastr, para o aviso nunca sumir atrás do vídeo. Abertura e
  fechamento simétricos, `Esc` fecha, o foco entra no botão de fechar e volta para o
  cartão, e a navegação por `Tab` fica dentro da caixa (`aria-modal`).
- Cada cartão anuncia o passo "Abriu um tutorial em vídeo" na jornada (`video` em
  `config('golden.rastreio.eventos')`), medido pelo farol próprio.
- A home ganhou só o link para a galeria na seção de dúvidas, sem repetir os
  cartões; o rodapé e o menu institucional também apontam para `/videos`.
- Testes novos em `tests/Feature/GaleriaDeVideosTest.php` (11 casos: normalização do
  link, vídeo repetido, erro em português, galeria sem player, filtro, vídeo oculto,
  invalidação do cache e a home sem seção duplicada). Suíte completa: 267 testes,
  2.932 asserções.

## [2.21.4] - 2026-10-24

### Corrigido
- **O ícone em mosaico do toastr não volta mais, nem com stylesheet velha no
  cache.** O fundo de cada notificação saiu do CDN com o *sprite* de ícones
  repetido em mosaico (o print que chegou mostrava isso nos quatro tons). A
  trava estava em `golden-avisos.css`, e a regra do CDN tem a mesma
  especificidade e o mesmo `!important` — ganha quem chega por último no
  documento. Um navegador com a folha anterior em cache reabria o mosaico mesmo
  com o servidor atualizado.

### Detalhes
- A trava passou a viver no próprio `partials/avisos`, como `<style>` logo depois
  do `<link>` do CDN: `#toast-container>.toast{background-image:none!important}`.
  Uma regra só, para os quatro tons, e ela viaja no HTML — que não fica em cache
  longo — então não existe mais combinação de cache que reabra o sprite.
- `golden-avisos.css` ficou sem nenhuma declaração de `background-image`: o
  desenho do tom (borda esquerda + glifo Font Awesome) continua lá, a anulação do
  fundo tem um único dono.
- Teste novo em `RetornoEConfirmacaoTest` confere a ordem no documento (trava
  depois da folha do CDN) e reclama se `background-image` reaparecer na stylesheet.

## [2.21.3] - 2026-10-24

### Novo
- **Todo retorno do sistema passou a sair por notificação.** Nasceu
  `resources/views/partials/avisos.blade.php` com a folha
  `public/css/golden-avisos.css`: uma camada única que lê `success`, `status`,
  `info`, `warning`, `error` e o `$errors` da view e entrega tudo como *toastr* no
  canto superior direito. Qualquer controller que já fazia `->with('success', …)`
  ou `->withErrors(…)` passou a funcionar sem código novo.
- **As cinco superfícies agora usam a mesma folha**: painel, portal do cliente,
  `/login`, `/portal/entrar`, `/portal/cadastro` e o site público includes do
  mesmo partial. No site ele entra com `confirmacoes => false` — sem ato
  destrutivo na tela pública, o SweetAlert2 nem é baixado.
- **Confirmação de ato destrutivo é SweetAlert2.** O `<form>` marca a pergunta com
  `data-confirmar="…"` e o interceptor bloqueia o envio até a resposta;
  cancelar não navega. O componente `components/admin/delete.blade.php` já nasce
  com o atributo, então as 14 chamadas existentes herdaram o comportamento.
- **O sistema fala português nas mensagens da framework.** O Laravel 13 não traz
  texto de validação no repositório, e a tela de acesso do portal mostrava a chave
  crua `validation.required` (foi assim que o problema chegou, com print). Entraram
  `lang/pt_BR/validation.php` (83 chaves + mapa `attributes` com o nome brasileiro
  de cada campo), `auth.php`, `pagination.php` e `passwords.php`.

### Detalhes
- Cinco telas pintavam a própria caixa de feedback e foram esvaziadas: o bloco
  `alert alert-danger alert-dismissible` do painel ("Não deu para salvar:") e do
  portal ("Não deu para seguir:"), os cinco `div.alert.ok|error` do `/login`, o
  `.form-alert` do formulário de lead e as regras CSS mortas que sobravam
  (`.flash*` em `golden-site.css`, `.alert*` em `golden-login.css`). A marcação
  por campo (`is-invalid`, `.erro`, `.form-err`) ficou — o que sumiu é a caixa.
- A camada de aviso não cria cor própria: consome `--login-surface`, `--t-*-fg` e
  os dois tokens novos, `--aviso-sombra` e `--aviso-veu`, declarados nas duas
  paletas de `golden-theme.css`. Para isso o site público passou a carregar
  `golden-theme.css` **antes** de `golden-site.css`, mantendo o próprio visual.
- Corrigidos dois empates de especificidade contra o CSS do toastr: o ícone
  original é um *sprite* declarado com `!important` (virou `background-image:none`
  com `!important` e ícone Font Awesome por `::before`), e o tom do border/icon
  só venceu quando cada regra ganhou o `#toast-container>` na frente.
- `public/js/golden-site.js` deixou de escrever no `.form-alert` e passou a chamar
  `GoldenAvisos.sucesso/erro`; o sucesso do lead carrega o link de WhatsApp que a
  resposta devolve.
- Travado por teste: `RetornoEConfirmacaoTest` (8 casos) exige o partial em toda
  página, proíbe `alert(`/`confirm(` em Blade e no JS público, proíbe caixa pintada
  por `{{ session(…`/`{{ $errors->… }}`, e confere que a folha de avisos só usa
  token existente nas duas paletas. `TraducoesPtBrTest` (4 casos) varre as regras de
  validação usadas em `app/` e reclama se alguma não tem texto em português.
  Suíte completa: 252 testes, 2.781 asserções.
- `docs/INSTALACAO.md` ganhou a seção *Retorno e confirmação: uma camada só*, a
  linha de diagnóstico do `validation.required` na tabela de problemas e dois itens
  de checklist; o PDF foi regerado.

## [2.21.2] - 2026-10-24

### Novo
- **O sistema passou a ter caixa de saída própria.** Foi criada a conta
  `disparos@foco.server-golden.com.br` — de máquina, só para conexão e disparo, sem
  ser o e-mail de ninguém do dia a dia — e a produção trocou `MAIL_MAILER=log` por
  `smtp` apontando para ela (`foco.server-golden.com.br:587`, STARTTLS). Desde então
  os avisos de chamado, resposta, depoimento e orçamento saem de verdade.
- **A criação dessa caixa está documentada e não precisa do painel.**
  `docs/INSTALACAO.md` ganhou a seção *A caixa de disparo se cria pela SSH, sem abrir
  o painel*, com o comando (`uapi Email add_pop`), a checagem de SPF/DKIM e as três
  pegadinhas que só aparecem na hora.

### Detalhes
- O domínio da caixa não foi escolha livre: a hospedagem nega acesso a
  `goldenrastreamento.com.br` (`you do not have access to the domain`), que está em
  outra conta do mesmo servidor. `uapi Email list_mail_domains` devolve
  `foco.server-golden.com.br` como o único habilitado, e ele já tem SPF
  (`v=spf1 ip4:88.99.209.75 +a +mx ~all`) e DKIM (`default._domainkey`).
- A prova foi feita pelo caminho real, não por script avulso: chamado aberto no
  `/portal` em produção, `ChamadoEquipe` processado pela fila em 136 ms com aceite do
  servidor, e a mesma mensagem lida na caixa de destino (`From: Golden Rastreamento
  <disparos@foco.server-golden.com.br>`). Nessa primeira tentativa o `ChamadoRecebido`
  da conta de demonstração falhou com `550 account or domain may not exist` — o login
  dela era `portal@exemplo.com.br`, endereço que não existe fora do exemplo. A conta
  passou a `juliana@foco.server-golden.com.br` (mesma senha), a cópia para o cliente
  chegou na caixa, e a linha de diagnóstico do `550` entrou na tabela de problemas.
- `.env.example` passou a trazer o remetente de máquina como referência, com o aviso
  de que senha contendo `#` só funciona entre aspas no `.env` (sem aspas vira
  comentário e o SMTP responde como senha errada).
- Nada de credencial entra no repositório: a senha fica só no `.env` (chmod 600) da
  instalação e nas notas do servidor em `~/backups-golden/acesso-painel.txt`, que
  agora também registram o comando de troca de senha (`uapi Email passwd_pop`).

## [2.21.1] - 2026-10-24

### Corrigido
- **As quatro telas de acesso do cliente saíram sem estilo.** `/portal/entrar`, `/portal/cadastro`,
  `/portal/esquecer` e `/portal/redefinir` desenhavam classes que não existem em folha nenhuma —
  `entrada`, `entrada-grid`, `entrada-lista`, `alterna`, `senha-dica` — então o suposto
  "formulário ao lado do argumento" era uma pilha: título gigante, campo de e-mail de 1.100px de
  largura e o "Esqueci minha senha" parecendo texto solto. A carcaça antiga vivia em
  `layouts.site`, que nunca teve essas regras.

### Novo
- **O cliente entra pela mesma porta do painel.** As quatro telas passaram a estender
  `portal.layout-auth.blade.php`, que é a folha `golden-login.css` da entrada do `/admin` com a
  marca "Portal do cliente": mesma favicon, mesmo `theme-init`, mesmo `localStorage` de tema, e um
  botão de claro/escuro no canto — a preferência escolhida antes de entrar já vale dentro do portal.
  Em tela larga o argumento fica à esquerda, separado por um filete, e o formulário à direita; em
  celular o formulário vem primeiro e o argumento desce.
- `golden-login.css` ganhou as classes que faltavam (`.box-largo`, `.apresenta`, `.lista`, `.dica`,
  `.erro`, `.consent`, `.alternar`, `.hp`, `.troca-tema`) só com tokens de `golden-theme.css`, e
  `.field` de texto/telefone, antes cobertos apenas por e-mail e senha.

### Detalhes
- `tests/Feature/TemasEIconesDoPainelTest.php` ganha dois travamentos: as quatro telas têm de
  estender a carcaça compartilhada, e **toda** classe escrita numa delas precisa existir na folha —
  foi exatamente a ausência dessa checagem que deixou o bug chegar à produção. 239 testes,
  1.745 asserções.
- `auth/login.blade.php` deixa de repetir o script do tema e passa a incluir
  `admin.partials.theme-init`, como o painel e o portal.

## [2.21.0] - 2026-10-24

### Novo
- **O portal do cliente agora é o painel, com a porta do cliente.** As seis telas de `/portal`
  (Início, Pedidos, Chamados — lista e abertura —, Contratos, Meus dados e a nova *Deixar um
  depoimento*) foram reescritas sobre o AdminLTE 4 do `/admin`: a mesma sidebar com treeview, o
  mesmo claro/escuro guardado em `golden_admin_theme`, os mesmos *toasts* de resposta, os mesmos
  componentes de tela (`x-admin.card|field|table|stat|status|filters|switch`) e as mesmas classes
  de cronologia, ficha de definição e conversa. Nada no portal desenha cor ou caixa própria.
- **A barra lateral deixou de ser markup copiado.** `MenuPainel::montar()` recebe a árvore que
  deve ler — `config/golden.menu_painel` para a equipe, `config/golden.menu_portal` para o
  cliente — e um único partial (`admin/partials/sidebar-menu`) desenha as duas. Os rótulos, os
  ícones e os `prefixo` de rota ficam só no config, e as bolhas do portal contam os pedidos e os
  chamados abertos **daquela ficha**, não do sistema inteiro. Saem de cena
  `public/css/golden-portal.css` (a folha própria) e `resources/views/portal/pager.blade.php`
  (quem pagina é a tabela do painel, com a mesma régua).
- **O depoimento aprendeu de onde veio.** `testimonials` ganhou `fonte` (`site` = escrito no
  painel, `google` = avaliação do perfil, `cliente` = mandado pelo portal), `avatar`, `externo_id`
  e `customer_id`. A tela *Conteúdo → Depoimentos* mostra o selo da origem, o filtro **Origem**,
  a foto do autor e um aviso no topo contando quantos aguardam aprovação; o formulário aceita
  avatar pelo dropzone (campo `depoimento_avatar`, 112 × 112 px) e a **ficha do cliente** que
  assinou o texto.
- **As avaliações do Google entram no carrossel com a foto de quem as escreveu.**
  `App\Support\DepoimentosDoGoogle` lê a *Business Profile API v4*
  (`accounts/{a}/locations/{l}/reviews`) — a única que devolve `profilePhotoUrl`; o Places API
  comum só entrega avaliação para conta Enterprise. `php artisan depoimentos:google`
  (`--limite`, `--apenas-leia`) e o botão **Atualizar do Google** chamam o mesmo `importar()`,
  que é idempotente pelo `reviewId` guardado em `externo_id`: reimportar atualiza texto, nota e
  foto em vez de duplicar, ignora quem não escreveu nada e quem ficou abaixo de
  `GOOGLE_BUSINESS_NOTA_MINIMA`, e **nunca reacende** um importado que a equipe desligou. A foto
  é baixada para `storage/app/public/site/depoimentos` em vez de hotlinkar
  `googleusercontent.com`, que morre sem avisar e deixaria buraco no carrossel.
- **O cliente ativo conta a experiência dele pelo portal.** Em *Deixar um depoimento* o texto
  nasce `fonte = cliente`, `aprovado = false` e `customer_id` assinando: publicar é decisão da
  equipe, que recebe `App\Mail\DepoimentoRecebido` no `contato_email` já apontando para a fila de
  pendentes. A escrita só abre para ficha `status = ativo`, e um texto já aguardando aprovação
  bloqueia o segundo envio em vez de acumular duplicados. Na tela aparecem os últimos enviados da
  própria ficha — nunca os de outro cliente.
- **Misturadas no site.** `Testimonial::aprovadosParaSite()` entrega as três origens na mesma
  lista, ordenada por `ordem` e depois pela data; o cartão da home desenha a foto quando existe
  arquivo, a inicial quando não, e o selo do Google quando a avaliação veio de lá. Não há chave
  nova para "misturar": aprovar sempre foi o único interruptor, agora ele vale para as três
  origens ao mesmo tempo.

### Segurança
- **O campo de avatar não aceita URL escolhida por quem edita.** `avatar` é texto livre no
  formulário e vira atributo `src` no site público, então só passam um caminho conferido por
  `Envio::caminhoConfere()` (precisa existir em `site/depoimentos/`, sem `..`) ou um `https://`
  terminado em `.googleusercontent.com` — o que a própria importação devolve quando o download
  falha. Qualquer outro endereço é recusado com erro no campo.

### Detalhes
- A chave `avatar` ausente do pedido vale "deixa a foto como está", não "apaga": o dropzone só
  emite o campo quando há arquivo na fila, e tratar ausente como nulo faria toda edição que não
  encosta no avatar perder a foto do autor.
- `Testimonial` ganhou `CAMPO_ENVIO`, `FONTES`, `customer()`, `fonteLabel()`, `avatarUrl()` e
  `iniciais()`; o cache `golden.depoimentos_site` projeta origem, rótulo, avatar e iniciais, e é
  derrubado por `CachesContent` em cada gravação.
- As rotas do painel registraram `POST depoimentos/importar-google` **antes** das rotas por id:
  `importar-google` tem um segmento só e seria engolido por `{depoimento}`.
- `.env.example` e a documentação (`docs/INSTALACAO.md`, seção 11) explicam as cinco chaves
  `GOOGLE_BUSINESS_*`, o que precisa existir do lado do Google (perfil verificado há mais de 60
  dias, API liberada no projeto, Bearer com escopo `business.manage`) e o cron opcional. Sem as
  chaves o sistema funciona: comando e botão dizem quais três variáveis faltam em vez de chamar
  a API no escuro.
- `php artisan test`: 236 testes, 1.681 asserções verdes, com
  `tests/Feature/DepoimentosDasTresOrigensTest.php` (12) cobrindo origem, avatar recusado,
  edição que mantém a foto, importação idempotente com avatar baixado, filtro de nota mínima,
  envio pelo portal assinado pela ficha e o carrossel mesclado da home.
- A migration `2026_10_24_000003_da_origem_ao_depoimento` é aditiva e tem `down()`; os
  depoimentos existentes recebem `fonte = 'site'` pelo default da coluna. Rodada no MySQL da
  hospedagem e conferida na tabela: `externo_id varchar(191)` com índice único aceito em
  `utf8mb4` — o teto que o SQLite dos testes não fiscaliza —, `fonte` com default `site`,
  `avatar varchar(500)`, o índice `(aprovado, fonte)` e a FK de `customer_id` com `SET NULL`.

## [2.20.5] - 2026-10-24

### Corrigido
- **O aviso enviado com `MAIL_MAILER=log` volta a deixar rastro.** O driver `log` grava a mensagem
   inteira em nível `debug`, e a produção roda com `LOG_LEVEL=warning`: o e-mail era filtrado junto
   com o resto do log. Na prática o `queue:work` respondia `DONE`, a tabela `jobs` ficava zerada e
   não havia como provar que o cliente foi avisado — o `storage/logs/laravel.log` não tinha uma
   linha de e-mail desde a instalação. Entra o canal `email` em `config/logging.php`, com nível
   próprio fixo em `debug` e arquivo separado (`storage/logs/email.log`), e `MAIL_LOG_CHANNEL`
   passa a apontar para ele por padrão. O log da aplicação continua no nível que a hospedagem
   pede; o que sai por e-mail fica auditável. Nada muda quando o SMTP estiver de pé — o canal só
   é usado pelo driver `log`.
- A documentação de instalação apontava `storage/logs/laravel.log` como o lugar onde conferir o
   aviso — tabela de `.env`, seção do portal, tabela de erros e checklist. Nenhum deles tinha
   razão de existir enquanto `LOG_LEVEL=warning` filtrava a mensagem.

### Detalhes
- `PortalDoClienteTest` ganha `test_o_aviso_gravado_em_log_deixa_rastro_apesar_do_log_level`, que
   lê o canal do mailer `log` e exige que ele exista, que o nível dele seja `debug` e que o
   arquivo seja outro senão o do log da aplicação — a trava pega tanto o canal sumido quanto o
   canal herdando `LOG_LEVEL`.
- `.env.example` traz `MAIL_LOG_CHANNEL=email` comentado com o porquê, então toda instalação nova
   já nasce com o rastro; a instalação que está no ar continua funcionando sem mexer no `.env`,
   porque o padrão agora vem do `config/mail.php`.
- `php artisan test`: 221 testes, 1.579 asserções verdes.

## [2.20.4] - 2026-10-24

Drenar a fila pela primeira vez na produção revelou o quarto defeito do portal — e o pior dos
quatro: ele não atrasa o aviso, ele o destrói.

### Corrigido
- **A resposta da equipe ao chamado volta a chegar por e-mail ao cliente.** O assunto de
   `ChamadoResposta` lia `$this->mensagem->autor`, sem parêntese. `autor` não é coluna da tabela:
   é um método, e o Eloquent, ao ver um atributo desconhecido, procura uma relação — como
   `TicketMessage::autor()` devolve string e não um `Relation`, o job estourava antes de montar a
   mensagem (`LogicException: App\Models\TicketMessage::autor must return a relationship
   instance`) e ia inteiro para `failed_jobs`. Na prática, toda vez que a equipe respondia pelo
   painel o cliente era o único que não era avisado. A chamada agora é `autor()`, como já faziam
   as views do portal, do painel e do próprio corpo do e-mail.

### Detalhes
- **Por que os testes deixaram passar:** `Mail::fake()` intercepta o envio antes de construir o
   objeto, então `Mail::assertQueued(ChamadoResposta::class)` ficava verde com o mailable quebrado
   — o assunto só é lido na hora de entregar, e essa hora nunca chegava no teste. As duas travas
   novas renderizam os seis mailables de verdade (`->render()`): assunto, corpo e os dois autores
   da conversa (equipe pela conta do painel, cliente pela ficha). Verificado ao contrário: trocando
   `autor()` de volta por `autor`, o teste falha com exatamente a exceção da produção.
- `php artisan test`: 220 testes, 1.576 asserções verdes.

## [2.20.3] - 2026-10-24

Ao abrir a conta de demonstração e percorrer o portal no ar, apareceram três defeitos que nenhum
teste de tela pegava: nenhum deles quebra o HTML, todos quebram a entrega.

### Corrigido
- **Os avisos do portal saem da fila.** Todo e-mail que o cliente espera — "a equipe respondeu",
   o recibo do chamado aberto, o link de recuperação de senha — entra em fila (`Mail::queue`), e
   a hospedagem tem um único cron: `schedule:run`. Sem worker pendurado nele, nada é consumido;
   na conta de demonstração havia quatro mensagens presas na tabela `jobs`, nenhuma delas com
   menos de um dia. O agendador passa a rodar `queue:work --stop-when-empty --max-time=50` a cada
   minuto, com `withoutOverlapping`: o `--stop-when-empty` devolve o processo quando a fila esvazia
   (um worker solto nunca terminaria e o cron empilharia um por minuto) e o `--max-time` deixa a
   janela livre antes do próximo disparo.
- **Os dois links do rodapé do portal voltaram para a mesma linha.** `.site-footer a` é
   `display:block` porque as colunas *Navegação*, *Suporte* e *Contato* são listas de link — e a
   mesma regra alcançava a barra inferior, onde "Falar com a equipe" e "Privacidade (LGPD)" são
   texto corrido. O resultado medido no navegador: os dois links e o separador empilhados em três
   linhas, com um "·" órfão no meio do rodapé. A trava fica em `.foot-bottom a`, na fonte da regra.
- **As cinco abas do portal cabem no celular.** A barra usava `overflow-x:auto` com a barra de
   rolagem escondida de propósito: em 638px a última aba entrava cortada no meio da palavra
   ("Meus dad") e nada indicava que a fila rola. Abaixo de 640px ela quebra em duas linhas, com
   cada aba centrada e crescendo até preencher a linha.

### Detalhes
- `PortalDoClienteTest` ganha três travas: a fila tem quem rode no cron (expressão, `--stop-when-empty`
   e `withoutOverlapping` lidos do próprio `Schedule`), o rodapé não empilha (CSS + a forma do
   markup) e as abas quebram dentro do `@media (max-width:640px)` — o teste exige que o bloco exista,
   senão a busca vazia passaria verde com a regra fora do breakpoint.
- Reentra na 2.20.2 o cabeçalho `## [2.20.1]`, que a edição da vez tinha derrubado junto com a
   própria entrada — o corpo da 2.20.1 ficou órfão dentro da 2.20.2.
- `php artisan test`: 218 testes, 1.554 asserções verdes.

## [2.20.2] - 2026-10-23

### Corrigido
- **A linha de kits da home parou de estourar a coluna.** Os três cartões moravam dentro da
   coluna direita de um `.split-larga`, e o grid não cabia ali: com `repeat(3,1fr)` a menor
   largura do conteúdo (o botão é `nowrap`) empurra as trilhas, então cada cartão media ~285px
   dentro de uma coluna de 692px. O transbordo apertava a coluna do texto de cabeça, que era
   o motivo real do "Escolha a solução ideal para a sua frota." quebrar em cinco linhas.
- **O rótulo "Mais escolhido" voltou a ser uma linha só.** Absoluto com `left:50%` e sem
   largura própria, ele tinha como teto metade do cartão; o texto ia para duas linhas, virava
   um blob e descia sobre o subtítulo "Frota Profissional".

### Alterado
- A seção segue o arranjo que a "Para quem é" já usa: cabeça em cima (`.split` com o título à
   esquerda e o texto + o botão à direita) e a linha de cartões na largura inteira do miolo.
   Cada cartão passou de ~214px para 361px de caixa, e com isso os três subtítulos, os três
   números, as três listas e os três botões caem na mesma linha — nada mais quebra ao meio.
- Trilhas `repeat(3,minmax(0,1fr))`, `min-width:0` no cartão e botão na largura do cartão: os
   três ficam com a mesma medida e a mesma altura, e um rótulo longo do CMS cede a linha em vez
   de vazar pela borda.
- O cartão em destaque perdeu a `border:2px` e ganhou anel (`box-shadow:0 0 0 1px`): pesa igual
   aos vizinhos, então as bordas dos três alinham no mesmo pixel.
- Em tablet (≤960px) os três cartões deixavam um buraco na segunda linha: o destaque passa a
   faixa de cima, com o botão limitado a 360px, e os outros dois dividem a de baixo. Em celular
   (≤600px) a ordem volta a ser 5, 10 e 10+.

### Detalhes
- `HomeDaReferenciaTest` ganha `test_os_tres_kits_formam_uma_linha_alinhada`, que trava pelo
   CSS (`minmax(0,1fr)`, `white-space:nowrap` no rótulo, botão a 100%) e pela estrutura — o
   `<section>` seguido de `.wrap` e só então de `.split`, forma que o arranjo antigo não tem.
- `php artisan test`: 215 testes, 1.545 asserções verdes.

## [2.20.1] - 2026-10-23

### Corrigido
- **A chamada final "Pronto para ter mais controle?" voltou a ser faixa de fundo total.** Ela
   era a única das três faixas escuras da referência que ainda vinha como cartão arredondado
   dentro do miolo: `section.sec-light > div.wrap > div.cta-final.cta-foto`, com a fotografia
   recortada dentro do quadro. Agora o `<section>` da faixa é o próprio fundo
   (`section.cta-final.cta-foto.sec-foto.sec-foto-esq`), a `<img class="foto">` vem antes do
   `.wrap`, e o gradiente que escurece o lado do texto é o de `.sec-foto-esq` — o mesmo
   tratamento do herói, do aplicativo e do J16, sem regra duplicada: as três linhas de `.foto`
   e `::before` que a faixa tinha por conta própria saíram do CSS.

### Detalhes
- `HomeDaReferenciaTest` passou a varrer as **três** faixas (antes eram duas) e a travar o
   tratamento pelo CSS: exige `.sec-foto > .foto{object-fit:cover}` e
   `.cta-final.cta-foto{…border-radius:0}`, porque foi exatamente o quadro arredondado que
   derrubou a entrega anterior. Nome de arquivo continua fora das travas.
- `php artisan test`: 214 testes, 1.540 asserções verdes.

## [2.20.0] - 2026-10-23

### Adicionado
- **Portal do cliente em `/portal`** — a pessoa passa a ver a própria casa sem depender da
  equipe: *Início* (resumo com pedidos, investido, chamados abertos e contratos), *Pedidos*
  com a linha do tempo da jornada e os documentos, *Chamados*, *Contratos* com o PDF e *Meus
  dados*. Guard próprio (`cliente` sobre a tabela `customers`, chave de sessão
  `login_cliente`), então a sessão do portal nunca abre uma rota do painel e vice-versa —
  inclusive com as duas abertas no mesmo navegador. Folha de estilo própria
  (`public/css/golden-portal.css`) sobre o tema do site, sem cor nova.
- **Auto-cadastro que não duplica ficha.** `App\Support\PortalDoCliente` procura a ficha do
  CRM primeiro pelo documento (normalizado, com ou sem máscara) e depois pelo e-mail, e
  entrega a senha a ela — `email_portal` + `password` + `remember_token` + `ultimo_acesso_em`
  são as quatro colunas novas, nada de segunda tabela de usuário. Entre candidatas, a que tem
  mais pedidos ganha. Quem nunca passou pela Golden ganha cadastro novo, já com `status`
  `ativo`, e os pedidos feitos como convidado são puxados por `vincularPedidosSemFicha()`: a
  primeira tela já vem cheia.
- **Chamados (`tickets` + `ticket_messages`).** O cliente abre do portal (com o pedido
  vinculado, quando ele escolhe um) e acompanha a conversa; a equipe atende em
  **Admin → Atendimento → Chamados**, com KPIs de abertos / sem resposta / aguardando o
  cliente / resolvidos, filtro por status e prioridade, responsável e mudança de status. A
  conversa vive numa fila só — a primeira mensagem é o relato que abriu o chamado —, então não
  existe campo "última resposta" para sincronizar entre as duas telas, e a autoria sai de
  `user_id`/`customer_id` da mensagem, nunca de um campo "de".
- **Contratos (`contracts`).** A equipe cadastra em **Admin → Atendimento → Contratos**,
  anexa o PDF assinado pelo mesmo dropzone de todos os envios (regra `contrato_documento` em
  `config/envios.php`, `contratos/`, PDF até 8 MB) e controla visibilidade pelo status:
  **rascunho não aparece para o cliente** — nem na lista, nem no resumo da home, nem no
  download. Número comercial `CT26-0001`, vigência com aviso de vencimento em 30 dias.
- **Quatro e-mails novos** na mesma identidade visual do pedido: `ChamadoRecebido` (cliente),
  `ChamadoEquipe` (painel), `ChamadoResposta` (bidirecional, com o link de quem respondeu) e
  `RedefinirSenhaPortal`. Como em todo o resto do sistema, **sem SMTP configurado o aviso se
  perde e a ação do cliente nunca é barrada** — o envio é tentado em `try/catch` dentro de
  `PortalBaseController::avisarEquipe()`/`avisarCliente()` e do `TicketController`, regra que
  mora numa fonte só.
- **Recuperação de acesso sem broker.** Like link assinado
  (`URL::temporarySignedRoute`, 60 minutos), sem tabela `password_resets`: a POST repete a URL
  com a assinatura (`request()->fullUrl()`), porque a `UrlGenerator::current()` devolve só o
  caminho. A resposta para e-mail com ou sem conta é a mesma frase, para a tela não revelar
  quem é cliente da Golden.
- **Entrada "Área do cliente" no site.** `config/golden.nav` ganhou o grupo dinâmico `conta` e
  `AppServiceProvider` passou a expor `golden.portal_url`: header, gaveta mobile e a coluna
  *Suporte* do rodapé apontam todos para a mesma rota, que é `/portal` para quem já entrou e
  `/portal/entrar` para quem ainda não.
- **`PortalDoClienteTest`** — 18 testes/175 asserções sobre as promessas do portal: nenhuma
  tela abre sem login, o auto-cadastro reaproveita a ficha e herda o pedido de convidado, o
  login usa `email_portal` (não o e-mail do CRM), ficha inativa perde o acesso, sessão de
  portal ≠ sessão de painel nos dois sentidos, recurso de outro cliente é 404, rascunho não
  chega ao portal, o PDF é entregue só ao dono, caminho de arquivo forjado é recusado e o
  e-mail de recuperação não diz quem é cliente.

### Alterado
- **`Activity::log()` ganhou o argumento `$por`.** No portal quem age é a ficha, não um
  usuário do painel; sem isso uma ação do cliente sairia assinada no nome da equipe que por
  acaso está logada no mesmo navegador. `portal_autocadastro`, `portal_criou_ficha`,
  `portal_login`, `portal_sair`, `portal_senha_*`, `portal_dados_atualizados`,
  `portal_chamado_aberto`/`respondeu`, `chamado_respondido`, `chamado_status` e
  `contrato_*` compõem a trilha.
- **Ficha do CRM (Admin → Clientes) mostra a conta do portal** — selo *conta ativa* com o
  e-mail de acesso e o último acesso, ou *sem conta* com o aviso de que ela nasce sozinha no
  auto-cadastro —, mais as tabelas de chamados e contratos da mesma pessoa, paginadas cada una
  no próprio nome de página.
- **`Customer` é autenticável** e o menu do painel passou a ter cinco grupos (entrou
  **Atendimento**), com bolha de chamados abertos. `config/golden.tons_status` ganhou os
  grupos `chamado`, `prioridade` e `contrato`, então os selos das duas telas saem da mesma
  régua de cor.
- **Testes do painel atualizados**: `MenuDoPainelTest` conhece o grupo novo (admin vê cinco,
  vendedor vê três) e `PaginasDoPainelTest` varre as novas páginas GET de chamados e
  contratos, criando chamado + contrato sobre a ficha do CRM para as telas de detalhe terem o
  que abrir.

### Segurança
- Recurso de outro cliente devolve **404, não 403**: a barra de endereços não descobre quais
  ids de pedido, chamado e contrato existem.
- Cadastro com **honeypot** (`website`), throttle dedicado por rota (`8,1` cadastro,
  `10,1` login, `6,1` recuperação, `12,1` abrir chamado) e consentimento LGPD obrigatório.
- `password` e `remember_token` estão em `$hidden` da ficha; o documento é só leitura no
  portal (é a chave do vínculo com pedido e contrato); e o caminho de PDF salvo no formulário
  é revalidado contra a pasta do campo de envio, então texto livre não faz o sistema apontar
  para arquivo fora de `storage/contratos`.

### Detalhes
- Toda contagem do portal sai de consulta **sem a ordem embutida da relação** (`reorder()`):
  `SUM/COUNT` com `ORDER BY criado_at` é o tipo de coisa que o SQLite aceita e o MySQL recusa.
  As duas migrações novas trazem comprimento explícito em cada coluna de texto e
  `email_portal` em 190 — o MySQL da hospedagem não aceita índice único maior.
- `docs/INSTALACAO.md` ganhou a seção 10 — *Portal do cliente* — com as rotas, o auto-cadastro
  por documento/e-mail, a diferença entre `email_portal` e o e-mail da ficha (o chamado de
  suporte nº 1), o que a equipe faz em Atendimento, o link assinado de recuperação e as duas
  migrations novas; as seções seguintes renumeraram, o `.env` de loja ganhou a linha
  `contrato_documento` na tabela de campos de envio, e a seção de segurança, a de problemas
  comuns e o checklist de saída passaram a cobrir o portal. O PDF
  (`docs/Golden-Rastreamento-Instalacao.pdf`) foi regerado da mesma fonte, e o README traz a
  seção do portal e a estrutura nova (`Portal/`, `Ticket`, `Contract`, `PortalDoCliente`).
- `php artisan test`: 214 testes, 1.534 asserções verdes.

## [2.19.1] - 2026-10-23

### Corrigido
- **A home agora é a referência aprovada, seção por seção.** Duas faixas — *Aplicativo e
  plataforma* e *Rastreador J16 4G/2G* — eram um fundo escuro liso com a foto dentro de um
  quadro arredondado ao lado do texto. Na referência a fotografia **é** o fundo: ela cobre a
  largura inteira da seção e o texto fica sobre o lado escurecido. As duas viraram faixas de
  fotografia em largura total, com o novo par de classes `.sec-foto` + `.sec-foto-esq` /
  `.sec-foto-dir` em `public/css/golden-site.css` — o mesmo desenho que já valia para
  `.hero-foto` e `.cta-foto`, agora com o gradiente espelhado (texto à esquerda na
  plataforma, texto à direita no J16, onde o equipamento aparece à esquerda).
- **A foto do herói era outra.** `img/home/hero-monitoramento.jpg` mostrava um analista de
  madrugada diante de três telas de mapa escuro; a referência é o escritório aquecido, o
  analista de camisa preta Golden visto de costas, dois monitores com o mapa claro e o
  letreiro GOLDEN na parede à direita. A foto do pacote foi refeita nesse enquadramento.
- **As fotos da plataforma e do equipamento também.** `plataforma-monitor.jpg` e
  `rastreador-j16.jpg` foram regeneradas no enquadramento da referência (o iMac com o mapa
  claro sobre a mesa de madeira, planta à esquerda e livros à direita; o J16 com o chicote
  aberto sobre a mesa, à esquerda), agora em 16:9 (`1600 × 914`) para cobrir a faixa inteira
  sem cortar o assunto. O registro de slots em `config/golden.php` acompanhou: novas
  dimensões, novas medidas de texto alternativo e `dica` de composição, e cada slot passou a
  se agrupar na própria seção da tela *Imagens do site* (`Home · Aplicativo e plataforma`,
  `Home · Rastreador J16`).
- **A placa de marca duplicada no herói.** `<div class="hero-marca">` (logo + GOLDEN /
  Rastreamento) flutuava sobre os monitores, exatamente onde a nova fotografia já traz o
  letreiro da parede. Removido do Blade e do CSS, junto da regra que o escondia no
  responsivo. A marca continua no menu, no rodapé e na chamada final.

### Detalhes
- No responsivo (≤960px) as duas faixas perdem as colunas e o gradiente passa a cobrir a
  seção inteira de cima a baixo; o `object-position` de cada uma aponta para o próprio
  assunto, porque na coluna estreita o recorte da foto é lateral.
- **Agora há teste para isso.** `HomeDaReferenciaTest` travava apenas a *presença* das onze
  seções — foi por isso que a faixa liso com foto encaixada passou. O marcador frágil
  `plataforma-monitor.jpg` saiu da lista e entraram `sec-foto-esq` e `sec-foto-dir`, mais o
  teste `as duas faixas escuras tem a fotografia como fundo da secao`, que exige que o
  `<img class="foto">` venha antes do conteúdo da seção e que `.sec-foto>.foto` ainda tenha
  `object-fit:cover` no CSS. Voltar ao quadro arredondado quebra a home em teste, não em
  opinião.
- `php artisan test`: 174 testes, 1.293 asserções verdes. Render verificado a 1440px e a
  500px com captura direta do navegador.

## [2.19.0] - 2026-10-23

### Adicionado
- **Atribuição de tráfego de primeira mão** — o sistema agora responde, sozinho e sem
  Google Analytics, *de onde veio cada clique, cada tela e cada cadastro*. `config/golden.php`
  ganhou `canais` (anúncio, rede social, busca orgânica, **busca por IA**, indicação, e-mail,
  parceiro, direto, indexador, desconhecido — cada um com rótulo, ícone, `pago` e `valido`),
  `meios_pagos|sociais|ia|busca|email|indicacao`, `ids_clique` (`gclid`, `fbclid`, `ttclid`,
  `msclkid`, `li_fat_id`, `yclid`), `redes_sociais`, `buscadores`, `ia_humanos`, `ia_usuarios`,
  `indexadores`, `redes_rotulos`, `tons_status.canal` e a chave `rastreio` (janela de sessão,
  retenção, teto de eventos e o vocabulário dos eventos). Trocar ou acrescentar uma rede é
  editar config; nenhuma regra de origem fica escrita solta em controller.
- **`App\Services\Atribuidor`** — o classificador. Decide na ordem da prova mais forte:
  identificador de clique → `utm_medium` → `utm_source` → referer → direto (o marco do
  gestor de tráfego vence o referer cru). Guarda também o **motivo** da decisão, para a
  atribuição ser auditável, e resolve `dispositivo`/`navegador`/`sistema` do user-agent —
  inclusive os **navegadores embutidos** (Instagram, Facebook, WhatsApp, TikTok, LinkedIn,
  X, Snapchat, Telegram), que é onde o clique social realmente acontece no celular.
  Reconhece **busca por IA** dos dois lados: o acesso humano que veio de ChatGPT,
  Perplexity, Gemini, Copilot, Claude, Grok ou Mistral, e o robô que só varre o índice.
- **Registro da jornada**: migrations `visits` + `visit_events` e os models `Visit` /
  `VisitEvent`. Toda tela do site vira visita (`App\Http\Middleware\RegistrarRastreio`, só
  GET `site.*` com 200 e HTML) e o cookie anônimo `golden_visitor` (90 dias) emenda as
  telas na mesma janela de 30 minutos; clique de outra origem abre visita nova. IP nunca
  entra em claro (`ip_hash`), robôs entram marcados (`bot`) e ficam **fora** de todo número
  (`Visit::pessoas()`).
- **Farol próprio**: `POST /rastreio/evento` (`Site\RastreioController`, `throttle:120,1`)
  alimentado por `initFarol()` em `public/js/golden-site.js` com `fetch(...,
  credentials:'same-origin', keepalive:true)` — CTA, telefone, WhatsApp, e-mail, link
  externo, início de formulário, kit, sacola, checkout e pedido. O controller valida o
  vocabulário do config e teto de 2000 caracteres por campo; a cota de `max_eventos` é dos
  cliques medidos (a tela que passou já tem contador próprio).
- **Atribuição no lead**: migration `add_atribuicao_to_leads_table` (`visit_id`, `canal`,
  `rede`, `pago`, `canal_primeiro`, `rede_primeiro`, `primeiro_toque_em`, `toques`,
  `dispositivo`, `navegador`). O vendedor vê o **último toque** e a ficha mostra o
  **primeiro** — rede social apresentou e anúncio fechou é exatamente o que aparece
  ("Rede social → Anúncio pago"). Sem visita prévia (API, aba anônima, POST sem cookie),
  `Rastreio::atribuicaoParaLead()` classifica das marcações que o próprio lead carrega, então
  nenhum cadastro fica cego.
- **Painel `/admin/atribuicao`** (`Admin\AttributionController` + `resources/views/admin/
  attribution/`): KPIs do período, tabelas de origem por **canal, rede, IA, página e site de
  origem** no mesmo molde de oito colunas (visitas, views, cliques, cadastros, vendas,
  conversão, receita) com barra de peso por linha, filtros de período/canal, "Robôs à parte"
  e a ficha `/admin/atribuicao/{visita}` com a linha do tempo completa. Componentes novos:
  `x-admin.tabela-origem` e `x-admin.link-copiar`.
- **`php artisan rastreio:podar`** (`App\Console\Commands\PodarRastreio`) com agendamento
  semanal (terça 03:30) em `bootstrap/app.php` — apaga o que passou de
  `golden.rastreio.dias_retencao` e os eventos junto, na mão, sem depender do *cascade* do
  banco. Deixa trilha na auditoria.
- **`campaigns.channel` no vocabulário real** (`padroniza_canais_de_campanhas`): `pago` →
  `anuncio`, `organico` → `busca_organica`, `referencia` → `indicacao`, e o que não
  pertence à lista vira `desconhecido`. A campaign criada por UTM passa a carregar o canal
  que o classificador decidiu, não um rótulo inventado.
- **`tests/Feature/AtribuicaoDeOrigemTest.php`** — 20 travas: o clique de cada plataforma,
  a ordem dos sinais, IA humana × robô, o app social no registro, visita que continua ×
  visita que reabre, referer da própria casa, farol (aceita/clique medido), robô fora dos
  números, primeiro e último toque no lead, canal da campanha, cadastro sem visita, painel,
  ficha, link rastreado, limites de tamanho do banco, teto de eventos e a poda.

### Alterado
- **Robô ≠ pessoa, com critério**: a regra de índice só roda quando houve user-agent de
  verdade, e o navegador embutido vence o robô do mesmo nome — o WhatsApp dá azar de nomear
  as duas coisas, e perder um clique humano custaria mais que tolerar uma prévia a mais.
  `Telegram` virou `Telegram/` para o `TelegramBot` continuar sendo robô.
- **Referer interno não é origem** — navegar do `/planos` para o `/contato` não "veio de"
  lugar nenhum. `Rastreio::atribuicaoParaLead()` e o novo `LeadService::veioDeFora()` só
  gravam referer quando o host aponta para fora da casa; a ficha do lead parou de mostrar a
  Golden como origem dela mesma.
- **`eventos` da visita é cota de clique medido** — antes somava as telas também e o número
  que o painel anuncia como "Cliques medidos" (`SUM(eventos)`) inflava em todo pageview.
- **`golden-site.js`: última chegada de UTM substitui, não mistura** — guardar o bloco antigo
  junto fazia um clique do Google herdar o `utm_medium=reels` do Reels e a campanha sair com
  duas origens costuradas; o servidor já abre visita nova pelo mesmo motivo.
- **Comprimentos garantidos na fonte** (`Atribuidor::resultado()` limita `rede`/`motivo` a
  40, `sinais()` limita `origem_host` a 120) e `leads.toques` deixou de ser `tinyInteger`:
  SQLite ignora comprimento de `varchar` e int pequeno, MySQL devolve *Data too long* /
  *Out of range* no tráfego real — exatamente a classe de bug que já matou o seed uma vez.
- **Nome da origem em uma linha só** (`.origem-nome` com `nowrap` + reticências e `title`):
  quebrar no meio empilhava letras na coluna e a tabela cabia onde não deveria.
- **`bootstrap/app.php`** passou a registrar o agendador; `IdentificadorVisita` e
  `RegistrarRastreio` completam o *middleware* `web`.
- **Docs**: README ganhou a seção *Atribuição de tráfego* e a lista de arquivos novos;
  `docs/INSTALACAO.md` ganhou a **seção 9 — Tráfego e atribuição** (cookie, o que é robô,
  `rastreio:podar` e a linha de cron do agendador), com as seções seguintes renumeradas e o
  checklist atualizado. PDF regenerado (`docs/gerar-pdf.py`).

## [2.18.0] - 2026-10-23

### Adicionado
- **`config/golden.php` → `itens_por_pagina` e `tons_status`** — o painel passou a ter uma régua
  por domínio. Os 20 itens de cada listagem moram numa chave só; a cor de cada situação mora em
  `tons_status`, agrupada por `funil`, `cliente`, `pedido`, `usuario`, `estoque`, `movimento` e
  `slot_imagem`, e todo valor usa os tons `tone-*` do tema, que já têm par no claro e no escuro.
- **`tom_por_status(string $grupo, ?string $valor)` e `tom_por_flag(bool)`** em
  `app/Support/helpers.php` — a view pergunta o tom, nunca escolhe. Quem inventa um status novo
  no model e esquece a cor cai no `fallback`, e o teste avisa.
- **`resources/views/components/admin/status.blade.php`** — o selo de situação do painel
  (`.badge-status` + `tone-X`). Substituiu as três linguagens paralelas que existiam: os chips
  `.st-*`, os `.badge tone-green` escritos à mão e os ternários de cor dentro das tabelas.
- **`resources/views/components/admin/pager.blade.php`** — o rodapé de paginação compartilhado
  entre tabela e linha do tempo; ele só aparece quando há para onde ir.
- **`Gate::define('admin')`** em `AppServiceProvider` — a pergunta única da tela
  (`@can('admin')`), enquanto o middleware `role:admin,vendedor` continua sendo a regra que vale
  na rota. Nenhuma view volta a chamar `isAdmin()`.
- **Regras que estavam repetidas em view e controller viraram método do model**:
  `Product::estado()` / `estadoEstoque()`, `Product::estoqueBaixo()` / `estoqueZerado()`
  (as duas scopes que alimentam filtro e KPIs), `Campaign::conversao()`, `Customer::label()`,
  `StockMovement::TIPOS` / `rotulo()` e `Interaction::ICONES` / `icone()`.
- **`tests/Feature/PadronizacaoDoPainelTest.php`** — 5 travas: a lista para na página do config,
  nenhum controller de lista escolhe número próprio, todo status dos models tem cor no config e
  o tom existe no tema, as listagens usam o selo padronizado, e o botão de excluir só aparece
  para quem a rota deixa excluir.

### Alterado
- **As 12 listagens do painel** (`Activity`, `Campaign`, `Category`, `Customer`, `Faq`, `Lead`,
  `Order`, `Page`, `Product`, `Supplier`, `Testimonial`, `User`) passaram ao mesmo molde: um
  `private function query(Request)` usado pelo filtro e pela paginação, `paginate(itens_por_pagina())`
  com `withQueryString()`, o parâmetro de busca chamado `busca` em todo lugar, e o
  `<x-admin.table :paginator>` na view. Produtos e campanhas ganharam a fileira de KPIs.
- **Auditoria** ganhou o filtro **Quem agiu**, que o controller já aceitava mas nenhuma tela
  oferecia; **Configurações** e **Imagens do site** tiveram os últimos campos escritos à mão
  levados para `<x-admin.field>` e `<x-admin.switch>` (cada canal agora é um interruptor com o
  próprio campo "Número"); a **nota rápida do lead** e o **formulário de interação do cliente**
  idem; a ficha do cliente paginou o histórico e os pedidos com nomes de página próprios
  (`?historico=2` não mexe em `?pedidos=`).
- **CSS**: `public/css/admin-golden.css` perdeu `.st-*`, `.slot-selo` e `.slot-voltar`; as barras
  do funil (`.fb-*`) passaram a ser escolhidas pelo **nome do tom**, o mesmo que o selo usa, para
  barra e badge nunca discordarem.

### Corrigido
- **Erro de validação invisível em campo de array.** `SiteContentController` gravava as chaves
  como `alt[home_hero]` e `paginas_site[faq][titulo]`, enquanto `old()` e `@error` procuram por
  ponto: o texto alternativo estourado voltava para a tela sem nenhuma mensagem. As chaves
  passaram à notação de ponto e `<x-admin.field>` / `<x-admin.switch>` convertem o nome HTML
  (`alt[x]`) na chave de ponto (`alt.x`) — com isso a view de textos dispensou o `old()` próprio
  que tinha.
- **Página 2 da lista de categorias estava inacessível** — o controller paginava e a view não
  tinha rodapé.
- **Dois N+1**: a listagem de pedidos carregava `with('items')` para contar linha a linha
  (`withCount('items')` agora) e o histórico de estoque consultava o usuário registro a registro.
- **Movimentação "ajuste" aparecia como entrada ou saída** — a tela deduzia o rótulo do sinal do
  número e perdia o tipo real que o cadastro grava.
- **A ficha de usuário tinha a própria lista de perfis**, separada da lista do filtro e da regra
  de validação; as três passaram a ler as constantes de `UserController`.

### Adicionado
- **`config/golden.php` → `menu_painel`** — o menu do painel inteiro passou a ser um registro:
  cinco entradas de topo (Dashboard solto e os grupos **Comercial**, **Vendas**, **Conteúdo do
  site** e **Sistema**), cada uma com `icone`, `rota`, `prefixo`, `bolha`, `tom`, `externo`,
  `admin` e `filhos`. A sidebar não tem mais um `<li>` escrito à mão: o que muda aqui muda no
  painel, e nenhuma view decide agrupamento.
- **`app/Support/MenuPainel.php`** — resolve a árvore contra a rota atual: `prefixo` acende o
  item em qualquer rota do recurso (`/admin/leads/12` marca *Leads*), o grupo herda o estado dos
  filhos, só o grupo da página aberta nasce `menu-open`, e o que é `admin` some para quem não é.
- **`resources/views/admin/partials/sidebar-menu.blade.php`** — treeview de dois níveis no
  desenho do AdminLTE 4 (`.nav-item.has-child` + `.nav-treeview` + `data-lte-toggle="treeview"`).
  O abrir/fechar e o **um grupo por vez** são o accordion nativo do plugin (`accordion:true`,
  300 ms), então a seta, a altura animada e o `prefers-reduced-motion` já vêm prontos.
- **`tests/Feature/MenuDoPainelTest.php`** — 8 testes: nenhuma listagem do painel fica sem item
  no menu, todo grupo tem gatilho e submenu, a página aberta abre um só grupo e fica marcada,
  rota interna acende o pai pelo prefixo, Dashboard fica fora de grupo, a bolha de pendências
  mora dentro do grupo sem abri-lo, o vendedor vê só Comercial e Vendas, e a loja desligada
  continua no menu com o aviso.

### Corrigido
- **O item ativo do menu ficava branco** — a regra do AdminLTE que pinta o link ativo
  (`[data-bs-theme=dark] .app-sidebar`, seis seletores) ganhava do dourado escrito à mão no CSS
  do tema. O dourado passou a entrar pelos tokens do próprio framework
  (`--lte-sidebar-active-color`, `--lte-sidebar-menu-active-color`,
  `--lte-sidebar-submenu-active-color`), com o `[data-bs-theme]` no seletor para ter a mesma
  força; a barra de página aberta é uma só, no filho ativo, e o grupo que o contém fica dourado
  sem barra.
- **"Produtos e estoque" aparecia reticenciado** no submenu (135 px de texto em 133 px): o recuo
  do treeview caiu de 2,7rem para 2,2rem, e nenhum título de item é cortado nos 250 px da sidebar.
- **`aria-expanded` ficava em `false` com o grupo aberto** — o plugin dispara
  `expanded.lte.treeview`/`collapsed.lte.treeview` com o ponto no próprio nome do evento e sem
  bolha, então a escuta passou a ser em cada `.nav-item`, não delegada na lista.
- **Menu memoizado atravessava de uma requisição para outra** — o `AppServiceProvider` guardava o
  pacote do site, as pendências e agora a árvore em memória de instância, e duas requisições na
  no mesmo processo compartilhavam o menu da primeira. Cada `RouteMatched` limpa as três memórias.

### Alterado
- Os cinco rótulos de seção (`Visão geral`, `Comercial`, `Vendas`, `Conteúdo do site`,
  `Sistema`) deixaram de ser texto solto: os quatro últimos viraram os grupos clicáveis da
  árvore, e a regra de CSS do `.nav-header`, que ficou sem uso, saiu do `admin-golden.css`.
- *Conteúdo do site* e *Sistema* agora se escondem inteiros para o vendedor, em vez de item por
  item dentro da lista plana.

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
