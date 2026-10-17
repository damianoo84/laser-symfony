/* Lightweight motion layer. All content and controls remain usable without it. */
(function () {
  'use strict';
  var body = document.body;
  body.classList.add('motion-initialized');
  var mode = body.dataset.motion || 'full';
  var preference = matchMedia('(prefers-reduced-motion: reduce)');
  var desktop = matchMedia('(min-width: 921px) and (pointer: fine)');
  var revealObserver, counterObserver, scrollFrame = 0;
  var counterFrames = new Map();
  var elements = Array.from(document.querySelectorAll('.reveal'));
  var counters = Array.from(document.querySelectorAll('[data-count]'));
  var cards = Array.from(document.querySelectorAll('.offer-card, .app-card'));
  var hero = document.querySelector('#heroSlider');
  var header = document.querySelector('#siteHeader');

  function enabled() { return mode !== 'none' && !preference.matches; }
  function rich() { return enabled() && mode === 'full'; }

  elements.forEach(function (element) {
    var siblings = Array.from(element.parentElement.children).filter(function (child) { return child.classList.contains('reveal'); });
    var index = siblings.indexOf(element);
    element.dataset.reveal = element.closest('.gallery') ? 'zoom' : (element.parentElement.classList.contains('grid2') ? (index % 2 ? 'right' : 'left') : 'up');
    element.style.setProperty('--reveal-delay', Math.min(Math.max(index, 0) * 70, 210) + 'ms');
  });

  function finishCounter(element) {
    if (counterFrames.has(element)) cancelAnimationFrame(counterFrames.get(element));
    counterFrames.delete(element);
    element.textContent = Number(element.dataset.count).toLocaleString('pl-PL');
  }

  function animateCounter(element) {
    var end = Number(element.dataset.count);
    if (!Number.isFinite(end) || !enabled()) return;
    var start = performance.now();
    function frame(now) {
      var position = Math.min((now - start) / 1400, 1);
      element.textContent = Math.round(end * (1 - Math.pow(1 - position, 3))).toLocaleString('pl-PL');
      if (position < 1 && enabled()) counterFrames.set(element, requestAnimationFrame(frame));
      else finishCounter(element);
    }
    counterFrames.set(element, requestAnimationFrame(frame));
  }

  function setupObservers() {
    if (revealObserver) revealObserver.disconnect();
    if (counterObserver) counterObserver.disconnect();
    if (!enabled() || !('IntersectionObserver' in window)) {
      elements.forEach(function (element) { element.classList.remove('reveal-ready'); element.classList.add('in'); });
      counters.forEach(finishCounter);
      return;
    }
    revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add('in'); revealObserver.unobserve(entry.target); }
      });
    }, { threshold: 0.08 });
    elements.forEach(function (element) { element.classList.add('reveal-ready'); revealObserver.observe(element); });
    counterObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { animateCounter(entry.target); counterObserver.unobserve(entry.target); }
      });
    }, { threshold: 0.5 });
    counters.forEach(function (element) { counterObserver.observe(element); });
  }

  // Scroll effects update at most once per frame, never in a permanent animation loop.
  function updateScroll() {
    scrollFrame = 0;
    if (header) header.classList.toggle('is-scrolled', scrollY > 24);
    if (hero) {
      var rect = hero.getBoundingClientRect();
      var offset = rich() && desktop.matches && rect.bottom > 0 ? Math.min(55, Math.max(-25, -rect.top * 0.12)) : 0;
      hero.style.setProperty('--parallax-y', offset + 'px');
    }
  }
  function scheduleScroll() { if (!scrollFrame) scrollFrame = requestAnimationFrame(updateScroll); }
  addEventListener('scroll', scheduleScroll, { passive: true });
  addEventListener('resize', scheduleScroll, { passive: true });

  cards.forEach(function (card) {
    var frame = 0, point;
    card.addEventListener('pointermove', function (event) {
      if (!rich() || !desktop.matches || event.pointerType === 'touch') return;
      point = { x: event.clientX, y: event.clientY };
      if (frame) return;
      frame = requestAnimationFrame(function () {
        frame = 0;
        if (!rich() || !desktop.matches) return;
        var rect = card.getBoundingClientRect();
        var x = (point.x - rect.left) / rect.width;
        var y = (point.y - rect.top) / rect.height;
        card.style.setProperty('--tilt-x', ((0.5 - y) * 5).toFixed(2) + 'deg');
        card.style.setProperty('--tilt-y', ((x - 0.5) * 5).toFixed(2) + 'deg');
        card.style.setProperty('--spot-x', (x * 100).toFixed(1) + '%');
        card.style.setProperty('--spot-y', (y * 100).toFixed(1) + '%');
      });
    });
    card.addEventListener('pointerleave', function () {
      cancelAnimationFrame(frame); frame = 0;
      card.style.removeProperty('--tilt-x'); card.style.removeProperty('--tilt-y');
    });
  });

  var marquee = document.querySelector('.marquee');
  var track, marqueePaused = false;
  if (marquee && rich()) {
    var original = marquee.querySelector('.marquee-in');
    if (original && original.querySelector('strong')) {
      track = document.createElement('div'); track.className = 'marquee-track';
      original.before(track); track.appendChild(original);
      var duplicate = original.cloneNode(true); duplicate.setAttribute('aria-hidden', 'true'); duplicate.classList.add('marquee-copy');
      track.appendChild(duplicate);
      var toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'marquee-toggle';
      toggle.textContent = 'Ⅱ'; toggle.setAttribute('aria-label', 'Wstrzymaj pasek materiałów'); toggle.setAttribute('aria-pressed', 'false');
      toggle.addEventListener('click', function () {
        marqueePaused = !marqueePaused;
        marquee.classList.toggle('is-paused', marqueePaused);
        toggle.textContent = marqueePaused ? '▶' : 'Ⅱ';
        toggle.setAttribute('aria-pressed', String(marqueePaused));
        toggle.setAttribute('aria-label', marqueePaused ? 'Wznów pasek materiałów' : 'Wstrzymaj pasek materiałów');
      });
      marquee.appendChild(toggle); marquee.classList.add('motion-ready');
    }
  }

  function policyChanged() {
    body.classList.toggle('motion-off', !enabled());
    if (marquee) marquee.classList.toggle('motion-static', !rich());
    if (!enabled()) cards.forEach(function (card) { card.style.removeProperty('--tilt-x'); card.style.removeProperty('--tilt-y'); });
    setupObservers(); scheduleScroll();
  }
  document.addEventListener('visibilitychange', function () {
    body.classList.toggle('page-hidden', document.hidden);
    if (document.hidden) counters.forEach(finishCounter);
  });
  preference.addEventListener('change', policyChanged);
  desktop.addEventListener('change', scheduleScroll);
  policyChanged();
})();
