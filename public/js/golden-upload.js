/* Campos de arrastar-e-soltar do painel: o arquivo entra no campo, aparece a preview
   interna, a barra acompanha o progresso real do envio, o rodapé conta o tempo que
   levou e fecha com o tamanho e as dimensões do arquivo.
   Um único arquivo para imagem, vídeo e documento — o comportamento vem dos data-*
   do container, que o partial partials/upload preenche a partir de config/envios.php. */
(function () {
  var raizes = document.querySelectorAll('[data-upload]');
  if (!raizes.length) return;

  for (var i = 0; i < raizes.length; i++) {
    iniciar(raizes[i]);
  }

  function textoTamanho(bytes) {
    if (!bytes || bytes < 0) return '0 B';
    if (bytes >= 1048576) {
      return (bytes / 1048576).toFixed(1).replace('.', ',').replace(/,0$/, '') + ' MB';
    }
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return Math.round(bytes) + ' B';
  }

  function textoTempo(ms) {
    if (ms < 1000) return Math.max(1, Math.round(ms)) + ' ms';
    return (ms / 1000).toFixed(1).replace('.', ',') + ' s';
  }

  function iniciar(raiz) {
    var cfg = {
      campo: raiz.getAttribute('data-campo'),
      tipo: raiz.getAttribute('data-tipo'),
      nome: raiz.getAttribute('data-nome'),
      multiplo: raiz.getAttribute('data-multiplo') === '1',
      max: parseInt(raiz.getAttribute('data-max-bytes'), 10) || 0,
      formatos: (raiz.getAttribute('data-formatos') || '').split(','),
      dimensoes: raiz.getAttribute('data-dimensoes') || '',
      envio: raiz.getAttribute('data-envio'),
      descarte: raiz.getAttribute('data-descarte'),
      token: raiz.getAttribute('data-token')
    };

    var alvo = raiz.querySelector('[data-upload-alvo]');
    var entrada = raiz.querySelector('.upload-arquivo');
    var miniatura = raiz.querySelector('[data-upload-miniatura]');
    var chamada = raiz.querySelector('[data-upload-chamada]');
    var barra = raiz.querySelector('[data-upload-barra]');
    var estado = raiz.querySelector('[data-upload-estado]');
    var fila = raiz.querySelector('[data-upload-fila]');
    var form = raiz.closest('form');

    var itens = [];
    var seq = 0;
    var aguardando = 0;
    var enviarDepois = false;
    var paraApagar = [];

    if (chamada) chamada.setAttribute('data-padrao', chamada.textContent);

    // O que já estava salvo no registro aparece no campo como se tivesse acabado de subir.
    var existentes = [];
    try { existentes = JSON.parse(raiz.getAttribute('data-arquivos') || '[]'); } catch (e) { existentes = []; }
    existentes.forEach(function (registro) {
      registro.salvo = true;
      registro.enviado = true;
      registro.id = ++seq;
      itens.push(registro);
    });
    if (itens.length) atualizar();

    function aceito(file) {
      var ext = (file.name.indexOf('.') > -1 ? file.name.split('.').pop() : '?').toLowerCase();
      if (cfg.formatos.indexOf(ext) === -1) {
        return 'Formato .' + ext + ' não entra aqui. Use ' + cfg.formatos.join(', ').toUpperCase() + '.';
      }
      if (cfg.max && file.size > cfg.max) {
        return 'Este arquivo tem ' + textoTamanho(file.size) + ' e o limite do campo é ' + textoTamanho(cfg.max) + '.';
      }
      return null;
    }

    function receber(files) {
      var lista = Array.prototype.slice.call(files || []);
      if (!lista.length) return;

      var aproveitaveis = [];
      lista.forEach(function (file) {
        var erro = aceito(file);
        if (erro) { marcarErro(erro); return; }
        aproveitaveis.push(file);
      });
      if (!aproveitaveis.length) return;

      if (!cfg.multiplo) {
        // Um arquivo só: o novo substitui o anterior, que sai do campo e do disco.
        derrubar(itens.slice(), true);
        itens = [];
      }

      aproveitaveis.slice(0, cfg.multiplo ? aproveitaveis.length : 1).forEach(function (file) {
        var item = { id: ++seq, file: file, nome: file.name, bytes: file.size, salvo: false, url: URL.createObjectURL(file) };
        itens.push(item);
        atualizar();
        subir(item);
      });
    }

    function subir(item) {
      var dados = new FormData();
      dados.append('campo', cfg.campo);
      dados.append('arquivo', item.file, item.nome);

      var xhr = new XMLHttpRequest();
      var inicio = performance.now();
      var fracao = 0;

      item.ativo = xhr;
      aguardando++;
      raiz.classList.add('enviando');
      raiz.classList.remove('com-erro');
      barra.style.width = '0%';

      function andamento() {
        escreverEstado('fa-hourglass-half',
          'Carregando ' + Math.round(fracao * 100) + '% de ' + textoTamanho(item.bytes) + ' · ' + textoTempo(performance.now() - inicio));
      }
      var reloginio = setInterval(andamento, 100);

      xhr.upload.addEventListener('progress', function (e) {
        if (!e.lengthComputable) return;
        fracao = e.loaded / e.total;
        barra.style.width = (fracao * 100).toFixed(1) + '%';
        andamento();
      });

      function encerrar() {
        clearInterval(reloginio);
        aguardando = Math.max(0, aguardando - 1);
        if (aguardando === 0) {
          raiz.classList.remove('enviando', 'processando');
          barra.style.width = '0%';
        }
      }

      xhr.addEventListener('load', function () {
        var resposta = null;
        try { resposta = JSON.parse(xhr.responseText); } catch (e) { resposta = null; }

        if (xhr.status >= 200 && xhr.status < 300 && resposta && resposta.ok) {
          item.enviado = true;
          item.tempo = performance.now() - inicio;
          ['caminho', 'url', 'nome', 'bytes', 'bytes_texto', 'largura', 'altura'].forEach(function (chave) {
            item[chave] = resposta.arquivo[chave];
          });
          if (item.file) {
            URL.revokeObjectURL(item.url);
            item.file = null;
            item.ativo = null;
          }
          // O brilho segura a barra enquanto o servidor finaliza a gravação.
          raiz.classList.add('processando');
          setTimeout(function () {
            encerrar();
            marcarConcluido(item);
            atualizar();
            if (enviarDepois && aguardando === 0 && form) form.requestSubmit();
          }, 260);
          return;
        }

        encerrar();
        remover(item);
        marcarErro((resposta && resposta.erro) || 'O envio falhou (' + xhr.status + '). Tente de novo.');
      });

      xhr.addEventListener('error', function () {
        encerrar();
        remover(item);
        marcarErro('Não foi possível enviar agora. Confira a conexão e tente de novo.');
      });

      xhr.open('POST', cfg.envio);
      xhr.setRequestHeader('X-CSRF-TOKEN', cfg.token);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.send(dados);
    }

    function escreverEstado(icone, texto, pronto) {
      estado.textContent = '';
      if (icone) {
        var i = document.createElement('i');
        i.className = 'fa-solid ' + icone;
        i.setAttribute('aria-hidden', 'true');
        estado.appendChild(i);
        estado.appendChild(document.createTextNode(' ' + texto));
      } else {
        estado.textContent = texto;
      }
      estado.classList.toggle('pronto', !!pronto);
    }

    function marcarConcluido(item) {
      var detalhes = [textoTempo(item.tempo || 0), item.bytes_texto || textoTamanho(item.bytes)];
      if (item.largura && item.altura) detalhes.push(item.largura + ' × ' + item.altura + ' px');
      escreverEstado('fa-check', 'Carregado em ' + detalhes.join(' · '), true);
    }

    function marcarErro(texto) {
      raiz.classList.add('com-erro');
      escreverEstado('fa-triangle-exclamation', texto);
    }

    function derrubar(lista, apagarArquivo) {
      lista.forEach(function (item) {
        if (item.url && item.file) URL.revokeObjectURL(item.url);
        if (item.ativo) item.ativo.abort();
        if (!apagarArquivo || !item.caminho) return;
        // Arquivo recém-enviado some na hora; o que já está no registro só sai quando o
        // formulário for salvo — cancelar a edição não pode quebrar a página.
        if (item.salvo) paraApagar.push(item.caminho); else descartar(item.caminho);
      });
    }

    function remover(item) {
      var idx = itens.indexOf(item);
      if (idx > -1) itens.splice(idx, 1);
      derrubar([item], true);
      atualizar();
    }

    function descartar(caminho) {
      var peticao = {
        method: 'DELETE',
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.token },
        body: JSON.stringify({ campo: cfg.campo, caminho: caminho })
      };
      fetch(cfg.descarte, peticao).catch(function () { });
    }

    function sincronizarValores() {
      var antigos = raiz.querySelectorAll('[data-upload-valor]');
      for (var i = 0; i < antigos.length; i++) antigos[i].remove();

      itens.forEach(function (item) {
        if (!item.caminho) return;
        var oculto = document.createElement('input');
        oculto.type = 'hidden';
        oculto.name = cfg.multiplo ? cfg.nome + '[]' : cfg.nome;
        oculto.value = item.caminho;
        oculto.setAttribute('data-upload-valor', '');
        raiz.appendChild(oculto);
      });
    }

    function midia(item) {
      if (cfg.tipo === 'imagem') {
        var img = document.createElement('img');
        img.alt = '';
        img.src = item.url;
        return img;
      }
      if (cfg.tipo === 'video') {
        var video = document.createElement('video');
        video.muted = true;
        video.playsInline = true;
        video.preload = 'metadata';
        video.src = item.url;
        video.addEventListener('loadedmetadata', function () {
          // O vídeo só revela a própria dimensão quando o metadado chega.
          if (!video.videoWidth || item.largura) return;
          item.largura = video.videoWidth;
          item.altura = video.videoHeight;
          if (item.enviado) { marcarConcluido(item); renderizarFila(); }
        });
        return video;
      }
      var icone = document.createElement('i');
      icone.className = 'fa-solid fa-file-lines';
      icone.setAttribute('aria-hidden', 'true');
      return icone;
    }

    function renderizarFila() {
      fila.textContent = '';
      if (!cfg.multiplo) return;

      itens.forEach(function (item) {
        var li = document.createElement('li');
        li.className = 'upload-chip';

        var caixa = document.createElement('span');
        caixa.className = 'upload-chip-mini';
        caixa.appendChild(midia(item));

        var corpo = document.createElement('span');
        corpo.className = 'upload-chip-corpo';
        var nome = document.createElement('b');
        nome.textContent = item.nome || 'arquivo';
        if (item.enviado && item.url) {
            // Nome vira link: é por ele que o operador confere o arquivo já gravado.
            var abrir = document.createElement('a');
            abrir.href = item.url;
            abrir.target = '_blank';
            abrir.rel = 'noopener';
            abrir.appendChild(nome);
            corpo.appendChild(abrir);
        } else {
            corpo.appendChild(nome);
        }
        var meta = document.createElement('span');
        meta.textContent = item.enviado
          ? (item.bytes_texto || textoTamanho(item.bytes)) + (item.largura ? ' · ' + item.largura + ' × ' + item.altura + ' px' : '')
          : 'carregando…';
        corpo.appendChild(meta);

        var x = document.createElement('button');
        x.type = 'button';
        x.className = 'upload-chip-x';
        x.setAttribute('aria-label', 'Remover ' + (item.nome || 'arquivo'));
        var cruz = document.createElement('i');
        cruz.className = 'fa-solid fa-xmark';
        cruz.setAttribute('aria-hidden', 'true');
        x.appendChild(cruz);
        x.addEventListener('click', function () { remover(item); });

        li.appendChild(caixa);
        li.appendChild(corpo);
        li.appendChild(x);
        fila.appendChild(li);
      });
    }

    function atualizar() {
      var primeiro = itens[0];
      var cheio = itens.length > 0;

      raiz.classList.toggle('tem-arquivo', !cfg.multiplo && cheio);
      if (!cfg.multiplo) {
        miniatura.textContent = '';
        miniatura.hidden = !primeiro;
        if (primeiro) miniatura.appendChild(midia(primeiro));
      }

      renderizarFila();
      sincronizarValores();

      if (chamada) {
        chamada.textContent = !cheio
          ? chamada.getAttribute('data-padrao')
          : (cfg.multiplo ? 'Arraste ou clique para acrescentar mais arquivos' : 'Solte ou clique para substituir o arquivo atual');
      }
      if (!cheio) {
        estado.textContent = '';
        raiz.classList.remove('com-erro');
      }
    }

    alvo.addEventListener('click', function () { entrada.click(); });

    entrada.addEventListener('change', function () {
      receber(entrada.files);
      entrada.value = '';
    });

    var dentro = 0;
    raiz.addEventListener('dragenter', function (e) { e.preventDefault(); dentro++; raiz.classList.add('em-cima'); });
    raiz.addEventListener('dragover', function (e) { e.preventDefault(); if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy'; });
    raiz.addEventListener('dragleave', function () { if (--dentro <= 0) { dentro = 0; raiz.classList.remove('em-cima'); } });
    raiz.addEventListener('drop', function (e) {
      e.preventDefault();
      dentro = 0;
      raiz.classList.remove('em-cima');
      receber(e.dataTransfer && e.dataTransfer.files);
    });

    if (form) {
      form.addEventListener('submit', function (e) {
        if (aguardando > 0) {
          e.preventDefault();
          enviarDepois = true;
          marcarErro('Segure um instante: ' + aguardando + ' arquivo(s) ainda estão carregando.');
          return;
        }
        enviarDepois = false;
        // Só apaga um arquivo que já estava no registro se o formulário realmente for salvo.
        paraApagar.splice(0).forEach(descartar);
      });
    }
  }
})();
