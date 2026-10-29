/* Atendimento com o assistente — o lado do navegador da conversa.
   O gatilho é o atendente virtual dentro da caixa do WhatsApp (`[data-ia-abrir]`), não um botão
   próprio: o robô é um dos atendentes da casa. Fala com os endereços JSON da bolha (abrir, fala,
   arquivo, falas, avaliar, encerrar, nova) e não sabe nada de IA: quem decide responder, escalar
   ou calar é o servidor. Esta folha só pinta, anexa, pergunta e fecha a caixa quando a conversa
   abre. */
(function () {
  'use strict';

  var cfg = window.GOLDEN_IA;
  var botao = document.querySelector('[data-ia-abrir]');
  var painel = document.getElementById('iaPainel');
  if (!cfg || !botao || !painel) return;

  var corpo = painel.querySelector('[data-ia-corpo]');
  var digitando = painel.querySelector('[data-ia-digitando]');
  var form = painel.querySelector('[data-ia-form]');
  var campo = document.getElementById('iaTexto');
  var envio = painel.querySelector('[data-ia-enviar]');
  var saida = painel.querySelector('[data-ia-humano]');
  var pesquisa = painel.querySelector('[data-ia-avaliacao]');
  var estrelas = pesquisa ? Array.prototype.slice.call(pesquisa.querySelectorAll('[data-ia-nota]')) : [];
  var aviso = document.querySelector('[data-ia-aviso]');
  var caixa = document.getElementById('waPopup');
  var flutuante = document.getElementById('waFloat');
  var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var anexar = painel.querySelector('[data-ia-anexar]');
  var entradaArquivo = painel.querySelector('[data-ia-arquivo]');
  var chip = painel.querySelector('[data-ia-anexo]');
  var chipNome = painel.querySelector('[data-ia-anexo-nome]');
  var chipLimpar = painel.querySelector('[data-ia-anexo-limpar]');
  var fecho = painel.querySelector('[data-ia-fecho]');
  var botaoEncerrar = painel.querySelector('[data-ia-encerrar]');
  var fim = painel.querySelector('[data-ia-fim]');
  var botaoNova = painel.querySelector('[data-ia-nova]');

  var placeholderOriginal = campo ? (campo.getAttribute('placeholder') || '') : '';
  var arquivoEscolhido = null;

  var aberto = false;      // o balão está na tela
  var criada = false;      // a conversa já foi aberta com o servidor
  var de = 0;              // a última fala pintada — o servidor devolve só o que veio depois
  var ocupado = false;     // uma chamada está no ar
  var escalada = false;    // a equipe assumiu: pode chegar resposta humana
  var encerrada = false;   // o cliente encerrou: o campo dá lugar ao convite a recomeçar
  var guardadas = [];      // o que foi pintado, para o resumo levado ao formulário
  var relógio = null;

  /* ---------- a chamada ---------- */

  function token() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  function enviar_post(url, dados) {
    return fetch(url, {
      method: 'POST',
      keepalive: true,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token() },
      body: JSON.stringify(Object.assign({ canal: cfg.canal, pagina: location.pathname }, dados || {}))
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .catch(function () { return {}; });
  }

  function obter_falas() {
    return fetch(cfg.falas + '?de=' + de, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .catch(function () { return {}; });
  }

  /* O arquivo vai por formulário multipart, e não por JSON: o corpo binário não cabe num campo de
     texto. O `Accept` continua sendo JSON para a resposta ser lida como o resto da conversa. */
  function enviar_arquivo(url, arquivo, texto) {
    var dados = new FormData();
    dados.append('arquivo', arquivo);
    if (texto) dados.append('texto', texto);
    dados.append('canal', cfg.canal);
    dados.append('pagina', location.pathname);

    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token() },
      body: dados
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .catch(function () { return {}; });
  }

  /* ---------- a tela ---------- */

  /* A Ana pergunta por um arquivo justamente quando ele serve: "manda o comprovante", "envie o
     print da tela". Nessa hora o clipe acende e o campo diz o que se espera — o anexo aparece no
     contexto da conversa, e não como um botão perdido no canto. */
  var PEDIDO_DE_ARQUIVO = /(?:envie|envia|anexe|anexa|mande|manda|mandar|anexar|compartilhe)[^.!?]{0,70}(?:arquivo|documento|foto|imagem|comprovante|nota|fatura|pdf|print|contrato|áudio|audio)/i;

  function pedeAnexo(texto) {
    if (!anexar || !campo) return;

    var pediu = PEDIDO_DE_ARQUIVO.test(String(texto || ''));
    anexar.classList.toggle('pede', pediu);

    if (!arquivoEscolhido) {
      campo.setAttribute('placeholder', pediu ? 'Anexe o arquivo aqui ou escreva a sua mensagem…' : placeholderOriginal);
    }
  }

  function pintura(fala) {
    var balao = document.createElement('div');
    balao.className = 'ia-fala ' + fala.autor;

    if (fala.autor !== 'sistema') {
      var quem = document.createElement('b');
      quem.textContent = (fala.assinatura || (fala.autor === 'cliente' ? 'Você' : cfg.nome))
        + (fala.em ? ' · ' + fala.em : '');
      balao.appendChild(quem);
    }

    var legenda = String(fala.texto || '');
    var nomeArquivo = String(fala.midia_nome || '');
    var semLegenda = legenda === '' || legenda === nomeArquivo;

    if (fala.tipo === 'imagem' && fala.midia) {
      var moldura = document.createElement('a');
      moldura.href = fala.midia;
      moldura.target = '_blank';
      moldura.rel = 'noopener noreferrer';

      var imagem = document.createElement('img');
      imagem.className = 'ia-midia';
      imagem.src = fala.midia;
      imagem.alt = semLegenda ? 'Imagem enviada' : legenda;
      imagem.loading = 'lazy';
      imagem.decoding = 'async';
      // A foto muda a altura da conversa depois de pintada: com o balão aberto, a rolagem desce
      // de novo para a última fala continuar à vista — ninguém fica procurando o fim do fio.
      imagem.addEventListener('load', function () { if (aberto) rolar(); });

      moldura.appendChild(imagem);
      balao.appendChild(moldura);
    } else if (fala.tipo === 'arquivo' && fala.midia) {
      var link = document.createElement('a');
      link.className = 'ia-arquivo';
      link.href = fala.midia;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';

      var clipe = document.createElement('i');
      clipe.className = 'fa-solid fa-paperclip';
      clipe.setAttribute('aria-hidden', 'true');

      link.appendChild(clipe);
      link.appendChild(document.createTextNode(nomeArquivo || 'Documento'));
      balao.appendChild(link);
    }

    if (fala.tipo === 'audio' && fala.midia) {
      var voz = document.createElement('audio');
      voz.controls = true;
      voz.preload = 'metadata';
      voz.src = fala.midia;
      balao.appendChild(voz);
    }

    // A imagem sem legenda já se explica: o nome do arquivo não é fala e não vai para a tela.
    if (legenda !== '' && !(semLegenda && fala.tipo !== 'audio')) {
      var texto = document.createElement('span');
      // textContent, sempre: é o que o visitante escreveu, e nunca vira HTML na tela.
      texto.textContent = legenda;
      balao.appendChild(texto);
    }

    if (fala.audio) {
      var falado = document.createElement('audio');
      falado.controls = true;
      falado.preload = 'metadata';
      falado.src = fala.audio;
      balao.appendChild(falado);
    }

    if (fala.autor === 'assistente') pedeAnexo(legenda);

    return balao;
  }

  function nota(texto) {
    corpo.appendChild(pintura({ autor: 'sistema', texto: texto }));
    rolar();
  }

  function rolar() {
    corpo.scrollTop = corpo.scrollHeight;
  }

  /* Toda fala nova leva a conversa ao fim: a mensagem mais recente é a que a pessoa precisa ler, e
     ela nunca deve puxar a rolagem para encontrá-la. Fechado o balão, nada se mexe. */
  function pinta(falas) {
    var trouxe = false;

    (falas || []).forEach(function (fala) {
      if (!fala || fala.id <= de) return;
      de = fala.id;
      // A fala do cliente volta do servidor com o texto aparado: é ela que fica na tela,
      // no lugar do balão provisório pintado no momento do envio.
      if (fala.autor === 'cliente') limpaPendente();
      corpo.appendChild(pintura(fala));
      guardadas.push(fala);
      if (fala.autor === 'equipe' && !aberto) marcaAviso();
      trouxe = true;
    });

    if (trouxe && aberto) rolar();

    return trouxe;
  }

  // O balão desenhado no clique, antes de o servidor confirmar a fala.
  function limpaPendente() {
    var balao = corpo.querySelector('[data-pendente]');
    if (balao) balao.remove();
  }

  function marcaAviso() {
    if (aviso) aviso.hidden = false;
  }

  /* ---------- a avaliação ---------- */

  /* A pesquisa é acendida pelo servidor, nunca pelo texto pintado: `avaliacao_pedida_em` já é a
     prova de que a Ana perguntou, e uma nota só pode ser dada uma vez. Caída a nota, a fileira
     inteira sai de cena — estrelas que continuam ali depois do toque convidam a segunda nota, e a
     casa passaria a somar uma avaliação repetida da mesma pessoa. */
  function mostraPesquisa(d) {
    if (!pesquisa) return;
    pesquisa.hidden = d.avaliacao !== true;
    if (pesquisa.hidden) pintaEstrelas(0);
  }

  function pintaEstrelas(até) {
    estrelas.forEach(function (estrela, i) {
      var icone = estrela.querySelector('i');
      if (icone) icone.className = (i < até ? 'fa-solid' : 'fa-regular') + ' fa-star';
      estrela.classList.toggle('cheia', i < até);
    });
  }

  estrelas.forEach(function (estrela, i) {
    estrela.addEventListener('mouseenter', function () {
      if (!estrela.disabled) pintaEstrelas(i + 1);
    });
    estrela.addEventListener('mouseleave', function () { pintaEstrelas(0); });

    estrela.addEventListener('click', function () {
      if (ocupado) return;

      ocupado = true;
      estrelas.forEach(function (b) { b.disabled = true; });

      var nota = Number(estrela.getAttribute('data-ia-nota'));
      pintaEstrelas(nota);

      enviar_post(cfg.avaliar, { nota: nota }).then(function (d) {
        if (!d.ativo) {
          // A conversa já tinha nota, ou a equipe assumiu o fio: a fileira some e nada mais.
          mostraPesquisa({});
          return;
        }
        mostraPesquisa(d);
        mostraFim(d);
        pinta(d.falas);
      }).finally(function () {
        ocupado = false;
      });
    });
  });

  /* ---------- o fim da conversa ---------- */

  /* Encerrada a conversa, o campo de envio sai de cena e o balão mostra o convite a começar outra:
     reabrir sozinha seria a tela contrariando o fim que a pessoa pediu. A varredura para junto
     porque não há mais resposta a esperar — a nota ainda pode ser dada pelas estrelas. */
  function mostraFim(d) {
    encerrada = d && d.encerrada === true;

    if (form) form.hidden = encerrada;
    if (fecho) fecho.hidden = encerrada;
    if (fim) fim.hidden = !encerrada;

    if (encerrada) {
      limpaAnexo();
      if (anexar) anexar.classList.remove('pede');
      paraVarredura();
    }
  }

  function confirmaEncerrar() {
    if (ocupado || encerrada) return;

    var aviso = window.GoldenAvisos;
    var pedido = aviso && aviso.confirmar
      ? aviso.confirmar('A conversa será encerrada e o campo de mensagem será fechado. Você pode começar outra depois.', 'Encerrar a conversa?')
      : Promise.resolve(true);

    pedido.then(function (aceitou) {
      if (!aceitou) return;

      ocupado = true;
      envio.disabled = true;
      if (anexar) anexar.disabled = true;
      digitando.hidden = false;

      enviar_post(cfg.encerrar, {}).then(function (d) {
        if (d.ativo === false) { desaparece(); return; }
        if (!d.falas) { mostraErros(d); return; }
        escalada = d.status === 'aguardando_equipe' || escalada;
        mostraPesquisa(d);
        pinta(d.falas);
        mostraFim(d);
      }).finally(function () {
        ocupado = false;
        envio.disabled = false;
        if (anexar) anexar.disabled = false;
        digitando.hidden = true;
      });
    });
  }

  /* A conversa nova limpa a tela inteira: o id das falas não se mistura com o da anterior, e a
     saudação da casa é pintada de novo no balão vazio. A limpeza só acontece com a resposta na
     mão — se o servidor não criar a conversa, a anterior continua na tela em vez de sumir. */
  function comecarNova() {
    if (ocupado) return;

    ocupado = true;
    digitando.hidden = false;

    enviar_post(cfg.nova, {}).then(function (d) {
      if (d.ativo === false) { desaparece(); return; }
      if (!d.falas) { mostraErros(d); return; }

      corpo.innerHTML = '';
      de = 0;
      guardadas = [];
      escalada = d.status === 'aguardando_equipe';
      encerrada = false;
      criada = true;
      if (pesquisa) pesquisa.hidden = true;
      pintaEstrelas(0);
      mostraPesquisa(d);
      mostraFim(d);
      pinta(d.falas);
      if (aberto && !encerrada) campo.focus();
    }).finally(function () {
      ocupado = false;
      digitando.hidden = true;
      ajustaVarredura();
    });
  }

  /* A caixa do WhatsApp e a conversa ocupam o mesmo canto da tela: abrir uma fecha a outra,
     senão os dois painéis se sobrepõem. */
  function fechaCaixa() {
    if (caixa) caixa.classList.remove('show');
    if (flutuante) flutuante.setAttribute('aria-expanded', 'false');
  }

  /* A ficha foi desligada no painel depois de a página ser pintada, ou o servidor disse que este
     visitante não é gente: o atendente sai da caixa e o balão sai de cena inteiro. */
  function desaparece() {
    botao.hidden = true;
    painel.hidden = true;
    fechaCaixa();
    paraVarredura();
  }

  /* ---------- a varredura ---------- */

  function varre() {
    if (ocupado || !criada) return;

    obter_falas().then(function (d) {
      if (d.ativo === false) { desaparece(); return; }
      if (d.status === 'aguardando_equipe') escalada = true;
      mostraPesquisa(d);
      mostraFim(d);
      pinta(d.falas);
    });
  }

  /* Com o balão aberto a resposta tem que chegar logo; fechado, só quem foi passado para a equipe
     é varrido — e devagar. Ninguém fica martelando o servidor de hospedagem compartilhada. */
  function ajustaVarredura() {
    if (encerrada) { paraVarredura(); return; }

    var intervalo = aberto ? 5000 : 30000;

    if (!aberto && !(escalada && criada)) { paraVarredura(); return; }

    paraVarredura();
    relógio = setInterval(function () {
      if (document.visibilityState === 'visible') varre();
    }, intervalo);
  }

  function paraVarredura() {
    if (relógio) clearInterval(relógio);
    relógio = null;
  }

  /* ---------- abrir e fechar ---------- */

  function abre() {
    fechaCaixa();
    aberto = true;
    painel.classList.add('aberto');
    botao.setAttribute('aria-expanded', 'true');
    if (aviso) aviso.hidden = true;
    ajustaVarredura();

    if (criada) {
      varre();
      // A conversa já estava pintada de outra vez: o balão abre no fim do fio, não no começo.
      rolar();
    } else {
      ocupado = true;
      digitando.hidden = false;
      enviar_post(cfg.abrir, { de: de }).then(function (d) {
        ocupado = false;
        digitando.hidden = true;
        if (!d.ativo) { desaparece(); return; }
        criada = true;
        escalada = d.status === 'aguardando_equipe';
        mostraPesquisa(d);
        mostraFim(d);
        pinta(d.falas);
        rolar();
        ajustaVarredura();
      });
    }

    // O teclado só sobe em tela larga: no celular ele cobriria o balão recém-aberto.
    if (!encerrada && window.matchMedia('(min-width: 900px)').matches) campo.focus();
  }

  function fecha() {
    aberto = false;
    painel.classList.remove('aberto');
    botao.setAttribute('aria-expanded', 'false');
    ajustaVarredura();
  }

  botao.addEventListener('click', function () { aberto ? fecha() : abre(); });
  painel.querySelectorAll('[data-ia-fechar]').forEach(function (b) { b.addEventListener('click', fecha); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && aberto) fecha(); });
  // O golden-site.js avisa quando a caixa do WhatsApp abre: a conversa sai de cena para não
  // ficar embaixo dela.
  document.addEventListener('golden:wa-aberto', function () { if (aberto) fecha(); });

  /* ---------- o anexo ---------- */

  function limpaAnexo() {
    arquivoEscolhido = null;
    if (entradaArquivo) entradaArquivo.value = '';
    if (chip) chip.hidden = true;
    if (campo) campo.setAttribute('placeholder', placeholderOriginal);
  }

  function escolheuArquivo() {
    var escolhido = entradaArquivo && entradaArquivo.files ? entradaArquivo.files[0] : null;
    arquivoEscolhido = escolhido || null;

    if (!chip || !chipNome) return;

    if (!escolhido) {
      limpaAnexo();
      return;
    }

    chipNome.textContent = escolhido.name;
    chip.hidden = false;
    if (campo) campo.setAttribute('placeholder', 'Escreva uma legenda (opcional)…');
  }

  if (anexar) anexar.addEventListener('click', function () { if (entradaArquivo) entradaArquivo.click(); });
  if (entradaArquivo) entradaArquivo.addEventListener('change', escolheuArquivo);
  if (chipLimpar) chipLimpar.addEventListener('click', limpaAnexo);
  if (botaoEncerrar) botaoEncerrar.addEventListener('click', confirmaEncerrar);
  if (botaoNova) botaoNova.addEventListener('click', comecarNova);

  /* ---------- a fala do cliente ---------- */

  campo.addEventListener('input', function () {
    campo.style.height = 'auto';
    campo.style.height = Math.min(campo.scrollHeight, 112) + 'px';
  });

  campo.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var texto = campo.value.trim();
    var anexo = arquivoEscolhido;

    if ((!texto && !anexo) || ocupado || encerrada) return;

    campo.value = '';
    campo.style.height = 'auto';
    if (texto === '') campo.setAttribute('placeholder', placeholderOriginal);
    limpaAnexo();

    ocupado = true;
    envio.disabled = true;
    if (anexar) anexar.disabled = true;
    digitando.hidden = false;

    // O balão do cliente entra na hora; quando a resposta voltar, a fala real do servidor
    // ocupa o lugar dele. Sem isso, dez segundos de IA pareceriam nada acontecendo.
    var previa = { autor: 'cliente', tipo: 'texto' };
    previa.texto = anexo
      ? (texto !== '' ? texto + '\n' : '') + 'Anexo: ' + anexo.name
      : texto;

    var balao = pintura(previa);
    balao.setAttribute('data-pendente', '');
    corpo.appendChild(balao);
    rolar();

    var pedido = anexo ? enviar_arquivo(cfg.arquivo, anexo, texto) : enviar_post(cfg.fala, { texto: texto });

    pedido.then(function (d) {
      // `ativo === false` é a máquina desligada; a recusa de um arquivo (422) não tem esse campo
      // e precisa continuar na tela, com a frase da regra que foi quebrada.
      if (d.ativo === false) {
        limpaPendente();
        desaparece();
        return;
      }

      if (!d.falas) {
        limpaPendente();
        mostraErros(d);
        if (texto !== '') campo.value = texto;
        return;
      }

      escalada = d.status === 'aguardando_equipe' || escalada;
      mostraPesquisa(d);
      mostraFim(d);
      pinta(d.falas);
    }).finally(function () {
      ocupado = false;
      envio.disabled = false;
      if (anexar) anexar.disabled = false;
      digitando.hidden = true;
      if (aberto && !encerrada) campo.focus();
      ajustaVarredura();
    });
  });

  function erro(mensagem) {
    if (window.GoldenAvisos && window.GoldenAvisos.erro) {
      window.GoldenAvisos.erro(mensagem);
      return;
    }

    nota(mensagem);
  }

  /* A recusa de um anexo chega como 422 com a frase presa ao campo `arquivo` — "Documento até
     8 MB.", "Documento só nos formatos pdf, csv…". Mostrar essa frase é o que diz o que corrigir;
     um "travou aqui" genérico obrigaria a pessoa a adivinhar o motivo. */
  function mostraErros(d) {
    var lista = d && d.errors ? Object.keys(d.errors).map(function (campo) { return d.errors[campo]; }) : [];
    lista = [].concat.apply([], lista).filter(Boolean);

    if (lista.length && window.GoldenAvisos && window.GoldenAvisos.erros) {
      window.GoldenAvisos.erros(lista);
      return;
    }

    erro(d && d.message ? d.message : 'A conversa travou aqui. Tente de novo em um instante.');
  }

  /* ---------- a pessoa por trás do robô ---------- */

  /* O cadastro é do formulário que a página já tem: a bolha rola até ele em vez de montar outro.
     Sem formulário na tela, o /contato da casa leva o resumo da conversa — ninguém reescreve
     o que já contou. */
  function temFormulario() {
    return document.querySelector('[data-lead-form]');
  }

  function resumo() {
    var linhas = guardadas.slice(-8).map(function (fala) {
      var texto = String(fala.texto || '').replace(/\s+/g, ' ').trim();

      if (fala.midia_nome) {
        texto = (texto !== '' ? texto + ' ' : '') + '[arquivo: ' + fala.midia_nome + ']';
      }

      return (fala.autor === 'cliente' ? 'Eu: ' : (fala.assinatura || cfg.nome) + ': ') + texto;
    });

    var texto = 'Conversa com ' + cfg.nome + ' no site:\n' + linhas.join('\n');

    return texto.length > 900 ? texto.slice(0, 900) + '…' : texto;
  }

  saida.addEventListener('click', function (e) {
    var alvo = temFormulario();

    if (!alvo) {
      // Sem preventDefault: é um link comum para /contato, com o resumo no campo de mensagem.
      saida.setAttribute('href', cfg.contato + '?message=' + encodeURIComponent(resumo()));
      return;
    }

    e.preventDefault();
    fecha();
    alvo.scrollIntoView({ behavior: semMovimento ? 'auto' : 'smooth', block: 'center' });

    var primeiro = alvo.querySelector('input:not([type=hidden]):not([type=checkbox]), textarea, select');
    if (primeiro) setTimeout(function () { primeiro.focus({ preventScroll: true }); }, semMovimento ? 0 : 420);
  });
})();
