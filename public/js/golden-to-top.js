/* Voltar ao topo: o anel dourado preenche com a rolagem e esvazia no mesmo ritmo ao subir.
   Um único arquivo para o site e o painel — a página só precisa do botão #toTop no HTML. */
(function () {
  var btn = document.getElementById('toTop');
  if (!btn) return;

  var anel = btn.querySelector('.to-top-bar');
  var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var limite = 0;
  var ultimo = 0;

  function medir() {
    var p = limite > 0 ? Math.min(1, Math.max(0, window.scrollY / limite)) : 0;
    anel.style.strokeDashoffset = 100 - p * 100;
    btn.classList.toggle('show', window.scrollY > 320);
  }

  // scrollHeight força layout: só é lido no carregamento, no resize e após a página parar.
  function calcularLimite() {
    limite = document.documentElement.scrollHeight - window.innerHeight;
    ultimo = performance.now();
    medir();
  }

  window.addEventListener('scroll', function () {
    var agora = performance.now();
    if (agora - ultimo < 16) return;
    if (agora - ultimo > 500) { calcularLimite(); return; }
    ultimo = agora;
    medir();
  }, { passive: true });

  window.addEventListener('resize', calcularLimite, { passive: true });

  btn.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: semMovimento ? 'auto' : 'smooth' });
  });

  calcularLimite();
})();
