(function (root) {
  /* Runs before first paint: decides motion, glass quality and whether to show the intro loader. */
  root.classList.add('js');
  var nav = navigator;
  var matches = function (query) { return window.matchMedia(query).matches; };
  var saved = null;
  try { saved = localStorage.getItem('nx-perf'); } catch (e) {}
  var weak = (nav.connection && nav.connection.saveData) || nav.deviceMemory <= 2 || nav.hardwareConcurrency <= 2;
  if (saved === '1' || (saved === null && weak)) root.dataset.perf = 'on';

  window.nxQuality = function () {
    if (root.dataset.perf === 'on' || matches('(prefers-reduced-motion: reduce)')) return 'low';
    var compact = Math.min(innerWidth, innerHeight) < 700 || !matches('(hover: hover) and (pointer: fine)');
    var modest = (nav.hardwareConcurrency || 8) <= 4 || (nav.deviceMemory || 8) <= 4;
    return compact || modest ? 'medium' : 'high';
  };
  root.dataset.quality = window.nxQuality();

})(document.documentElement);
