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

  /* ---------- formatação de links e botões de contato ---------- */

  function formataTelefone(num) {
    if (!num) return '';
    var limpo = String(num).replace(/\D/g, '');
    if (limpo.startsWith('55') && limpo.length >= 12) {
      limpo = limpo.slice(2);
    }
    if (limpo.length === 11) {
      return '(' + limpo.slice(0, 2) + ') ' + limpo.slice(2, 7) + '-' + limpo.slice(7);
    }
    if (limpo.length === 10) {
      return '(' + limpo.slice(0, 2) + ') ' + limpo.slice(2, 6) + '-' + limpo.slice(6);
    }
    return num;
  }

  function separaUrlEPontuacao(urlBruta) {
    var pontuacao = '';
    while (/[.,!?;:)\]]$/.test(urlBruta)) {
      pontuacao = urlBruta.slice(-1) + pontuacao;
      urlBruta = urlBruta.slice(0, -1);
    }
    return { url: urlBruta, sobra: pontuacao };
  }

  function criaBotaoWhatsapp(url, numeroTexto) {
    var link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.className = 'ia-btn-whatsapp';
    link.setAttribute('aria-label', 'Abrir conversa no WhatsApp ' + (numeroTexto || ''));
    link.setAttribute('title', 'Abrir conversa no WhatsApp (abre em nova aba)');

    var iconeSpan = document.createElement('span');
    iconeSpan.className = 'ia-btn-whatsapp-icone';
    var iconeWhats = document.createElement('i');
    iconeWhats.className = 'fa-brands fa-whatsapp';
    iconeSpan.appendChild(iconeWhats);
    link.appendChild(iconeSpan);

    var infoSpan = document.createElement('span');
    infoSpan.className = 'ia-btn-whatsapp-info';

    var titulo = document.createElement('span');
    titulo.className = 'ia-btn-whatsapp-titulo';
    titulo.textContent = 'Conversar no WhatsApp';
    infoSpan.appendChild(titulo);

    if (numeroTexto) {
      var numEl = document.createElement('span');
      numEl.className = 'ia-btn-whatsapp-numero';
      numEl.textContent = numeroTexto;
      infoSpan.appendChild(numEl);
    }
    link.appendChild(infoSpan);

    var seta = document.createElement('i');
    seta.className = 'fa-solid fa-arrow-up-right-from-square ia-btn-whatsapp-seta';
    seta.setAttribute('aria-hidden', 'true');
    link.appendChild(seta);

    return link;
  }

  function criaLinkExterno(url, label) {
    var a = document.createElement('a');
    a.href = url;
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    a.className = 'ia-link-externo';
    a.textContent = label || url;

    var icone = document.createElement('i');
    icone.className = 'fa-solid fa-arrow-up-right-from-square';
    icone.setAttribute('aria-hidden', 'true');
    a.appendChild(icone);

    return a;
  }

  function analisaSegmentosTexto(textoBruto) {
    var segmentos = [];
    var regexUrl = /(https?:\/\/[^\s<]+)/gi;
    var cursor = 0;
    var match;

    while ((match = regexUrl.exec(textoBruto)) !== null) {
      var inicio = match.index;
      if (inicio > cursor) {
        segmentos.push({ tipo: 'texto', valor: textoBruto.slice(cursor, inicio) });
      }

      var limpa = separaUrlEPontuacao(match[0]);
      var url = limpa.url;
      var sobra = limpa.sobra;

      var ehWa = /(?:wa\.me|api\.whatsapp\.com|whatsapp\.com\/send)/i.test(url);
      if (ehWa) {
        var numMatch = url.match(/(?:wa\.me\/|phone=)(\d+)/);
        var numero = numMatch ? formataTelefone(numMatch[1]) : '';
        segmentos.push({ tipo: 'whatsapp', url: url, numero: numero });
      } else {
        segmentos.push({ tipo: 'link', url: url, label: url });
      }

      if (sobra) {
        segmentos.push({ tipo: 'texto', valor: sobra });
      }

      cursor = inicio + match[0].length;
    }

    if (cursor < textoBruto.length) {
      segmentos.push({ tipo: 'texto', valor: textoBruto.slice(cursor) });
    }

    return segmentos;
  }

  function montaConteudoMensagem(textoBruto) {
    var container = document.createElement('span');
    container.className = 'ia-conteudo-texto';
    var segmentos = analisaSegmentosTexto(textoBruto);

    segmentos.forEach(function (seg) {
      if (seg.tipo === 'texto') {
        container.appendChild(document.createTextNode(seg.valor));
      } else if (seg.tipo === 'whatsapp') {
        container.appendChild(criaBotaoWhatsapp(seg.url, seg.numero));
      } else if (seg.tipo === 'link') {
        container.appendChild(criaLinkExterno(seg.url, seg.label));
      }
    });

    return container;
  }

  function pintura(fala, emDigitacao) {
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
      if (emDigitacao) {
        var container = document.createElement('span');
        container.className = 'ia-conteudo-texto';
        balao.appendChild(container);
      } else {
        balao.appendChild(montaConteudoMensagem(legenda));
      }
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

  var filaDigitacao = [];
  var digitandoTimer = null;
  var emDigitacao = false;
  var digitacaoAtiva = null;

  function cancelaDigitacaoAtiva() {
    if (digitacaoAtiva) {
      if (typeof digitacaoAtiva.parar === 'function') {
        digitacaoAtiva.parar();
      }
      digitacaoAtiva = null;
    }
  }

  function pulaDigitacaoAtiva() {
    if (digitacaoAtiva) {
      if (typeof digitacaoAtiva.pular === 'function') {
        digitacaoAtiva.pular();
      }
      digitacaoAtiva = null;
    }
  }

  function criaBalaoDigitando() {
    var balao = document.createElement('div');
    balao.className = 'ia-fala assistente ia-fala-digitando';
    balao.setAttribute('data-ia-balao-digitando', '');

    var quem = document.createElement('b');
    quem.textContent = cfg.nome || 'Ana';
    balao.appendChild(quem);

    var pontos = document.createElement('span');
    pontos.className = 'ia-pontos-animados';
    pontos.setAttribute('aria-hidden', 'true');
    for (var i = 0; i < 3; i++) {
      pontos.appendChild(document.createElement('i'));
    }
    balao.appendChild(pontos);

    return balao;
  }

  function mostraDigitandoBalao() {
    removeDigitandoBalao();
    corpo.appendChild(criaBalaoDigitando());
    if (digitando) digitando.hidden = false;
    if (aberto) rolar();
  }

  function removeDigitandoBalao() {
    var balao = corpo.querySelector('[data-ia-balao-digitando]');
    if (balao) balao.remove();
  }

  function cancelaDigitacao() {
    cancelaDigitacaoAtiva();
    if (digitandoTimer) {
      clearTimeout(digitandoTimer);
      digitandoTimer = null;
    }
    removeDigitandoBalao();
    filaDigitacao = [];
    emDigitacao = false;
  }

  function travaEnvioParaAbertura() {
    if (campo) {
      campo.disabled = true;
      campo.setAttribute('placeholder', (cfg.nome || 'Ana') + ' está iniciando o atendimento…');
    }
    if (envio) envio.disabled = true;
    if (anexar) anexar.disabled = true;
  }

  function liberaEnvioAposAbertura() {
    if (campo) {
      campo.disabled = false;
      campo.setAttribute('placeholder', placeholderOriginal);
      if (aberto && !encerrada && window.matchMedia('(min-width: 900px)').matches) {
        campo.focus();
      }
    }
    if (envio) envio.disabled = false;
    if (anexar) anexar.disabled = false;
  }

  function pintaUma(fala) {
    if (!fala || fala.id <= de) return false;
    de = fala.id;
    // A fala do cliente volta do servidor com o texto aparado: é ela que fica na tela,
    // no lugar do balão provisório pintado no momento do envio.
    if (fala.autor === 'cliente') limpaPendente();
    corpo.appendChild(pintura(fala, false));
    guardadas.push(fala);
    if (fala.autor === 'equipe' && !aberto) marcaAviso();
    if (aberto) rolar();
    return true;
  }

  /* Pinta imediatamente (para histórico existente e varredura de fundo). */
  function pinta(falas) {
    var trouxe = false;

    (falas || []).forEach(function (fala) {
      if (pintaUma(fala)) trouxe = true;
    });

    return trouxe;
  }

  /* Simula a digitação natural, fluida e humanizada de um texto na tela */
  function digitaSegmentosNaTela(container, segmentos, animar, aoConcluir) {
    if (!animar || !segmentos || !segmentos.length) {
      segmentos.forEach(function (seg) {
        if (seg.tipo === 'texto') {
          container.appendChild(document.createTextNode(seg.valor));
        } else if (seg.tipo === 'whatsapp') {
          container.appendChild(criaBotaoWhatsapp(seg.url, seg.numero));
        } else if (seg.tipo === 'link') {
          container.appendChild(criaLinkExterno(seg.url, seg.label));
        }
      });
      if (aberto) rolar();
      if (typeof aoConcluir === 'function') aoConcluir();
      return;
    }

    var cursorEl = document.createElement('span');
    cursorEl.className = 'ia-cursor';
    cursorEl.setAttribute('aria-hidden', 'true');
    container.appendChild(cursorEl);

    var segIdx = 0;
    var charIdx = 0;
    var timer = null;
    var noTextoAtual = null;
    var finalizado = false;

    var tamanhoTotal = segmentos.reduce(function (tot, s) { return tot + (s.valor ? s.valor.length : 12); }, 0);
    var cadenciaBase = tamanhoTotal > 240 ? 14 : (tamanhoTotal > 90 ? 18 : 22);

    function finaliza() {
      if (finalizado) return;
      finalizado = true;
      if (timer) { clearTimeout(timer); timer = null; }
      if (cursorEl && cursorEl.parentNode) cursorEl.remove();
      digitacaoAtiva = null;
      if (aberto) rolar();
      if (typeof aoConcluir === 'function') aoConcluir();
    }

    function pular() {
      if (finalizado) return;
      if (timer) { clearTimeout(timer); timer = null; }
      while (segIdx < segmentos.length) {
        var seg = segmentos[segIdx];
        if (seg.tipo === 'texto') {
          var restante = charIdx > 0 ? seg.valor.slice(charIdx) : seg.valor;
          if (noTextoAtual) {
            noTextoAtual.nodeValue += restante;
            noTextoAtual = null;
          } else {
            container.insertBefore(document.createTextNode(restante), cursorEl);
          }
        } else if (seg.tipo === 'whatsapp') {
          container.insertBefore(criaBotaoWhatsapp(seg.url, seg.numero), cursorEl);
        } else if (seg.tipo === 'link') {
          container.insertBefore(criaLinkExterno(seg.url, seg.label), cursorEl);
        }
        segIdx++;
        charIdx = 0;
      }
      finaliza();
    }

    function parar() {
      if (finalizado) return;
      finalizado = true;
      if (timer) { clearTimeout(timer); timer = null; }
      if (cursorEl && cursorEl.parentNode) cursorEl.remove();
      digitacaoAtiva = null;
    }

    digitacaoAtiva = { pular: pular, parar: parar };

    function passo() {
      if (finalizado) return;

      if (segIdx >= segmentos.length) {
        finaliza();
        return;
      }

      var seg = segmentos[segIdx];

      if (seg.tipo === 'whatsapp') {
        container.insertBefore(criaBotaoWhatsapp(seg.url, seg.numero), cursorEl);
        segIdx++;
        charIdx = 0;
        noTextoAtual = null;
        if (aberto) rolar();
        timer = setTimeout(passo, 140);
        return;
      }

      if (seg.tipo === 'link') {
        container.insertBefore(criaLinkExterno(seg.url, seg.label), cursorEl);
        segIdx++;
        charIdx = 0;
        noTextoAtual = null;
        if (aberto) rolar();
        timer = setTimeout(passo, 100);
        return;
      }

      var texto = seg.valor || '';
      if (charIdx >= texto.length) {
        segIdx++;
        charIdx = 0;
        noTextoAtual = null;
        passo();
        return;
      }

      if (!noTextoAtual) {
        noTextoAtual = document.createTextNode('');
        container.insertBefore(noTextoAtual, cursorEl);
      }

      var char = texto[charIdx];
      noTextoAtual.nodeValue += char;
      charIdx++;

      if (aberto) rolar();

      var delay = cadenciaBase + Math.floor(Math.random() * 8) - 4;
      if (delay < 10) delay = 10;

      if (char === '.' || char === '!' || char === '?') {
        delay += 180;
      } else if (char === ',' || char === ':' || char === ';' || char === '—') {
        delay += 95;
      } else if (char === '\n') {
        delay += 150;
      }

      timer = setTimeout(passo, delay);
    }

    passo();
  }

  /* Entrega sequencial com simulação realista e humana de digitação da Ana. */
  function processaFilaDigitacao(fila, animar, aoFinal) {
    if (!fila || !fila.length) {
      emDigitacao = false;
      removeDigitandoBalao();
      if (digitando) digitando.hidden = true;
      if (typeof aoFinal === 'function') aoFinal();
      return;
    }

    emDigitacao = true;
    var item = fila.shift();
    var fala = item.fala;

    if (!fala || fala.id <= de) {
      processaFilaDigitacao(fila, animar, aoFinal);
      return;
    }

    // Cliente e notas do sistema entram sem simulação de digitação da Ana
    if (fala.autor === 'cliente' || fala.autor === 'sistema' || !animar || semMovimento) {
      pintaUma(fala);
      processaFilaDigitacao(fila, animar, aoFinal);
      return;
    }

    mostraDigitandoBalao();

    var tempoPensando = item.tempoPensando != null ? item.tempoPensando : (fila.length ? 450 : 500);

    digitandoTimer = setTimeout(function () {
      removeDigitandoBalao();

      de = fala.id;
      var balao = pintura(fala, true);
      corpo.appendChild(balao);
      guardadas.push(fala);
      if (fala.autor === 'equipe' && !aberto) marcaAviso();
      if (aberto) rolar();

      var container = balao.querySelector('.ia-conteudo-texto');
      var segmentos = analisaSegmentosTexto(fala.texto || '');

      digitaSegmentosNaTela(container || balao, segmentos, animar && !semMovimento, function () {
        if (fila.length && (fila[0].fala.autor === 'assistente' || fila[0].fala.autor === 'equipe')) {
          digitandoTimer = setTimeout(function () {
            processaFilaDigitacao(fila, animar, aoFinal);
          }, 450);
        } else {
          processaFilaDigitacao(fila, animar, aoFinal);
        }
      });
    }, tempoPensando);
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
        if (d.encerrada || d.limpar_chat) {
          limpaHistoricoVisual();
          pinta((d.falas || []).filter(function (f) { return f && f.autor === 'assistente'; }));
          return;
        }
        pinta(d.falas);
      }).finally(function () {
        ocupado = false;
      });
    });
  });

  /* ---------- o fim da conversa ---------- */

  function limpaHistoricoVisual() {
    cancelaDigitacao();
    if (corpo) corpo.innerHTML = '';
    de = 0;
    guardadas = [];
    if (digitando) digitando.hidden = true;
    limpaAnexo();
    if (anexar) anexar.classList.remove('pede');
  }

  /* Encerrada a conversa, o campo de envio sai de cena e o balão mostra o convite a começar outra:
     reabrir sozinha seria a tela contrariando o fim que a pessoa pediu. A varredura para junto
     porque não há mais resposta a esperar — a nota ainda pode ser dada pelas estrelas. */
  function mostraFim(d) {
    encerrada = d && d.encerrada === true;

    if (form) form.hidden = encerrada;
    if (fecho) fecho.hidden = encerrada;
    if (fim) fim.hidden = !encerrada;

    if (encerrada) {
      cancelaDigitacao();
      limpaAnexo();
      if (anexar) anexar.classList.remove('pede');
      paraVarredura();
    }
  }

  function confirmaEncerrar() {
    if (ocupado || encerrada) return;

    var aviso = window.GoldenAvisos;
    var pedido = aviso && aviso.confirmar
      ? aviso.confirmar('A conversa será encerrada, o histórico desta janela será limpo e você poderá iniciar um novo atendimento quando quiser.', 'Encerrar a conversa?')
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
        limpaHistoricoVisual();
        mostraPesquisa(d);
        if (d.avaliacao === true) {
          pinta(d.falas.filter(function (f) { return f && f.autor === 'assistente'; }));
        }
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
     saudação da casa é digitada e iniciada do zero no balão vazio. */
  function comecarNova() {
    if (ocupado) return;

    ocupado = true;
    cancelaDigitacao();
    travaEnvioParaAbertura();
    mostraDigitandoBalao();

    enviar_post(cfg.nova, {}).then(function (d) {
      if (d.ativo === false) {
        removeDigitandoBalao();
        liberaEnvioAposAbertura();
        desaparece();
        return;
      }
      if (!d.falas) {
        removeDigitandoBalao();
        liberaEnvioAposAbertura();
        mostraErros(d);
        return;
      }

      limpaHistoricoVisual();
      escalada = d.status === 'aguardando_equipe';
      encerrada = false;
      criada = true;
      if (pesquisa) pesquisa.hidden = true;
      pintaEstrelas(0);
      mostraPesquisa(d);
      mostraFim(d);

      var novasFalas = (d.falas || []).filter(function (f) { return f && f.id > de; });
      var fila = novasFalas.map(function (f, idx) {
        return {
          fala: f,
          tempo: idx === 0 ? 1200 : 1300
        };
      });

      processaFilaDigitacao(fila, !semMovimento, function () {
        ocupado = false;
        liberaEnvioAposAbertura();
        ajustaVarredura();
      });
    }).catch(function () {
      ocupado = false;
      removeDigitandoBalao();
      liberaEnvioAposAbertura();
      if (digitando) digitando.hidden = true;
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
    if (ocupado || !criada || emDigitacao) return;

    obter_falas().then(function (d) {
      if (d.ativo === false) { desaparece(); return; }
      if (d.status === 'aguardando_equipe') escalada = true;
      if (d.limpar_chat || d.encerrada) limpaHistoricoVisual();
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
      if (!encerrada && !emDigitacao && window.matchMedia('(min-width: 900px)').matches) {
        campo.focus();
      }
    } else {
      ocupado = true;
      travaEnvioParaAbertura();
      mostraDigitandoBalao();

      enviar_post(cfg.abrir, { de: de }).then(function (d) {
        ocupado = false;
        if (!d.ativo) {
          removeDigitandoBalao();
          liberaEnvioAposAbertura();
          desaparece();
          return;
        }
        if (d.limpar_chat) limpaHistoricoVisual();
        criada = true;
        escalada = d.status === 'aguardando_equipe';
        mostraPesquisa(d);
        mostraFim(d);

        var novasFalas = (d.falas || []).filter(function (f) { return f && f.id > de; });

        if (d.abertura && novasFalas.length > 0) {
          // Abertura inicial da Ana: digita primeiro a saudação, depois a pergunta pelo nome
          var fila = novasFalas.map(function (f, idx) {
            return {
              fala: f,
              tempo: idx === 0 ? 1200 : 1300
            };
          });

          processaFilaDigitacao(fila, !semMovimento, function () {
            liberaEnvioAposAbertura();
            ajustaVarredura();
          });
        } else {
          // Conversa pré-existente
          removeDigitandoBalao();
          if (digitando) digitando.hidden = true;
          pinta(novasFalas);
          liberaEnvioAposAbertura();
          rolar();
          ajustaVarredura();
        }
      }).catch(function () {
        ocupado = false;
        removeDigitandoBalao();
        liberaEnvioAposAbertura();
        if (digitando) digitando.hidden = true;
      });
    }
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

  if (corpo) {
    corpo.addEventListener('click', function (e) {
      if (e.target && e.target.closest('a, button, input, textarea')) return;
      pulaDigitacaoAtiva();
    });
    corpo.addEventListener('touchstart', function (e) {
      if (e.target && e.target.closest('a, button, input, textarea')) return;
      pulaDigitacaoAtiva();
    }, { passive: true });
  }

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
    mostraDigitandoBalao();

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
        removeDigitandoBalao();
        desaparece();
        return;
      }

      if (!d.falas) {
        limpaPendente();
        removeDigitandoBalao();
        mostraErros(d);
        if (texto !== '') campo.value = texto;
        ocupado = false;
        envio.disabled = false;
        if (anexar) anexar.disabled = false;
        if (digitando) digitando.hidden = true;
        return;
      }

      escalada = d.status === 'aguardando_equipe' || escalada;
      mostraPesquisa(d);
      mostraFim(d);

      var novasFalas = (d.falas || []).filter(function (f) { return f && f.id > de; });
      var fila = novasFalas.map(function (f) {
        return {
          fala: f,
          tempo: f.autor === 'cliente' ? 0 : Math.min(1800, Math.max(900, Math.round(String(f.texto || '').length * 12)))
        };
      });

      processaFilaDigitacao(fila, !semMovimento, function () {
        ocupado = false;
        envio.disabled = false;
        if (anexar) anexar.disabled = false;
        if (aberto && !encerrada && window.matchMedia('(min-width: 900px)').matches) campo.focus();
        ajustaVarredura();
      });
    }).catch(function () {
      limpaPendente();
      removeDigitandoBalao();
      if (digitando) digitando.hidden = true;
      ocupado = false;
      envio.disabled = false;
      if (anexar) anexar.disabled = false;
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
    if (cfg.destino_humano === 'whatsapp' && cfg.whatsapp_humano) {
      e.preventDefault();
      var baseMsg = cfg.msg_humano || 'Olá! Gostaria de falar com um atendente humano.';
      var conversaResumo = resumo();
      var textoFinal = baseMsg + (conversaResumo ? '\n\n' + conversaResumo : '');
      var urlWhats = 'https://wa.me/' + cfg.whatsapp_humano + '?text=' + encodeURIComponent(textoFinal);
      window.open(urlWhats, '_blank', 'noopener,noreferrer');
      return;
    }

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
