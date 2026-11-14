<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-card-prod]').forEach(function (card) {
    var thumb = card.querySelector('[data-prod-thumb]');
    if (!thumb) return;
    var imgs = thumb.querySelectorAll('.prod-slide-img');
    var dots = thumb.querySelectorAll('.prod-dot');
    if (imgs.length <= 1) return;

    var cur = 0;
    function setIndex(idx) {
      if (idx < 0 || idx >= imgs.length) return;
      cur = idx;
      imgs.forEach(function (img, i) { img.classList.toggle('active', i === cur); });
      dots.forEach(function (dot, i) { dot.classList.toggle('active', i === cur); });
    }

    dots.forEach(function (dot) {
      dot.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        setIndex(parseInt(this.dataset.dot, 10));
      });
      dot.addEventListener('mouseenter', function () {
        setIndex(parseInt(this.dataset.dot, 10));
      });
    });

    thumb.addEventListener('mousemove', function (e) {
      var rect = thumb.getBoundingClientRect();
      var x = e.clientX - rect.left;
      var ratio = x / rect.width;
      var targetIdx = Math.min(imgs.length - 1, Math.max(0, Math.floor(ratio * imgs.length)));
      if (targetIdx !== cur) {
        setIndex(targetIdx);
      }
    });

    card.addEventListener('mouseleave', function () {
      setIndex(0);
    });
  });
});
</script>
