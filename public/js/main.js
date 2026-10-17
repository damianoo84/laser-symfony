(function () {
  'use strict';
  var $ = function (selector, root) { return (root || document).querySelector(selector); };
  var $$ = function (selector, root) { return Array.from((root || document).querySelectorAll(selector)); };
  var motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
  var reduceMotion = motionPreference.matches || document.body.dataset.motion === 'none';

  // A broken external photograph must never leave an empty hero. No CMS data is overwritten.
  $$('.slide-photo').forEach(function (photo) {
    function fallback() {
      if (photo.dataset.fallback && photo.src !== new URL(photo.dataset.fallback, location.href).href) {
        photo.src = photo.dataset.fallback;
        delete photo.dataset.fallback;
      } else if (photo.dataset.placeholder) {
        photo.src = photo.dataset.placeholder;
        delete photo.dataset.placeholder;
      }
    }
    photo.addEventListener('error', fallback);
    if (photo.complete && !photo.naturalWidth) fallback();
  });

  $$('.ba').forEach(function (comparison) {
    var after = $('.after', comparison), handle = $('.handle', comparison);
    if (!after || !handle) return;
    function set(position) {
      position = Math.max(0, Math.min(100, position));
      after.style.clipPath = 'inset(0 ' + (100 - position) + '% 0 0)';
      handle.style.left = position + '%';
      comparison.setAttribute('aria-valuenow', Math.round(position));
    }
    function pointer(event) {
      var rect = comparison.getBoundingClientRect();
      if (rect.width) set((event.clientX - rect.left) / rect.width * 100);
    }
    comparison.addEventListener('pointerdown', function (event) {
      if (event.button !== 0) return;
      comparison.setPointerCapture(event.pointerId); pointer(event);
    });
    comparison.addEventListener('pointermove', function (event) { if (comparison.hasPointerCapture(event.pointerId)) pointer(event); });
    comparison.addEventListener('keydown', function (event) {
      var current = Number(comparison.getAttribute('aria-valuenow'));
      if (event.key === 'ArrowLeft') current -= 5;
      else if (event.key === 'ArrowRight') current += 5;
      else if (event.key === 'Home') current = 0;
      else if (event.key === 'End') current = 100;
      else return;
      event.preventDefault(); set(current);
    });
  });

  var burger = $('#burger'), nav = $('#navLinks');
  function closeMenu() { nav.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); burger.setAttribute('aria-label', 'Otwórz menu'); }
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', String(open)); burger.setAttribute('aria-label', open ? 'Zamknij menu' : 'Otwórz menu');
    });
    $$('a', nav).forEach(function (link) { link.addEventListener('click', closeMenu); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && nav.classList.contains('open')) { closeMenu(); burger.focus(); } });
  }

  var slider = $('#heroSlider'), slidesBox = $('#slides');
  if (slider && slidesBox) {
    slider.classList.add('slider-ready');
    var slides = $$('.slide', slidesBox), index = 0, timer = null;
    var duration = Math.max(4, Math.min(15, Number(slider.dataset.duration) || 7)) * 1000;
    var remaining = duration, deadline = 0, paused = reduceMotion || slider.dataset.autoplay !== '1';
    var hovering = false, inViewport = true;
    var dots = $('#dots'), pause = $('#pauseSlider');
    var progressBar = $('.slider-progress span', slider), number = $('#slideNumber');
    function stop() {
      if (timer !== null) {
        clearTimeout(timer); timer = null;
        remaining = Math.max(0, deadline - performance.now());
      }
      if (progressBar) {
        progressBar.style.transition = 'none';
        progressBar.style.transform = 'scaleX(' + (1 - remaining / duration) + ')';
      }
    }
    function auto() {
      stop();
      var running = slides.length > 1 && !paused && !hovering && inViewport && !document.hidden && !slider.contains(document.activeElement);
      slider.classList.toggle('is-paused', !running);
      if (pause) {
        pause.textContent = paused ? 'Wznów slajdy' : 'Wstrzymaj slajdy';
        pause.setAttribute('aria-pressed', String(paused));
      }
      if (!running) return;
      deadline = performance.now() + remaining;
      if (progressBar) {
        progressBar.getBoundingClientRect();
        progressBar.style.transition = 'transform ' + remaining + 'ms linear';
        progressBar.style.transform = 'scaleX(1)';
      }
      timer = setTimeout(function () { timer = null; go(index + 1); }, remaining);
    }
    function go(next) {
      stop();
      index = (next + slides.length) % slides.length;
      slides.forEach(function (slide, i) {
        slide.classList.toggle('is-active', i === index);
        slide.inert = i !== index;
        slide.setAttribute('aria-hidden', String(i !== index));
      });
      if (number) number.textContent = String(index + 1).padStart(2, '0');
      if (dots) $$('button', dots).forEach(function (dot, i) { dot.classList.toggle('on', i === index); dot.setAttribute('aria-pressed', String(i === index)); });
      remaining = duration;
      slider.dispatchEvent(new CustomEvent('lazer:slide', { detail: { index: index } }));
      auto();
    }
    if (dots) slides.forEach(function (_, i) {
      var dot = document.createElement('button'); dot.type = 'button'; dot.setAttribute('aria-label', 'Slajd ' + (i + 1));
      dot.addEventListener('click', function () { go(i); }); dots.appendChild(dot);
    });
    if (pause) {
      pause.addEventListener('click', function () { paused = !paused; auto(); });
    }
    var prev = $('#prevS'), next = $('#nextS');
    if (prev) prev.addEventListener('click', function () { go(index - 1); });
    if (next) next.addEventListener('click', function () { go(index + 1); });
    slider.addEventListener('mouseenter', function () { hovering = true; auto(); });
    slider.addEventListener('mouseleave', function () { hovering = false; auto(); });
    slider.addEventListener('focusin', auto);
    slider.addEventListener('focusout', function () { requestAnimationFrame(auto); });
    document.addEventListener('visibilitychange', auto);
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) { inViewport = entries[0].isIntersecting; auto(); }, { threshold: 0.05 }).observe(slider);
    }
    var touchStart = null;
    slider.addEventListener('touchstart', function (event) {
      touchStart = event.target.closest('.ba, a, button') ? null : { x: event.touches[0].clientX, y: event.touches[0].clientY };
    }, { passive: true });
    slider.addEventListener('touchend', function (event) {
      if (!touchStart) return;
      var dx = event.changedTouches[0].clientX - touchStart.x;
      var dy = event.changedTouches[0].clientY - touchStart.y;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) go(index + (dx < 0 ? 1 : -1));
      touchStart = null;
    }, { passive: true });
    motionPreference.addEventListener('change', function (event) {
      reduceMotion = event.matches || document.body.dataset.motion === 'none';
      if (reduceMotion) paused = true;
      auto();
    });
    if (slides.length < 2) { if (prev) prev.hidden = true; if (next) next.hidden = true; if (pause) pause.hidden = true; if (dots) dots.hidden = true; }
    go(0);
  }

  var progress = $('#progress'), toTop = $('#toTop'), ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      var top = window.scrollY, height = document.documentElement.scrollHeight - innerHeight;
      if (progress) progress.style.width = (height > 0 ? top / height * 100 : 0) + '%';
      if (toTop) toTop.classList.toggle('show', top > 500);
      ticking = false;
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  if (toTop) toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduceMotion ? 'instant' : 'smooth' }); });

  var lightbox = $('#lightbox'), image = $('#lbImg');
  if (lightbox && image) {
    $$('.gallery-open').forEach(function (button) {
      button.addEventListener('click', function () {
        var thumbnail = $('img', button); image.src = thumbnail.dataset.full; image.alt = thumbnail.alt;
        lightbox.showModal(); document.body.style.overflow = 'hidden';
      });
    });
    $('button', lightbox).addEventListener('click', function () { lightbox.close(); });
    lightbox.addEventListener('click', function (event) { if (event.target === lightbox) lightbox.close(); });
    lightbox.addEventListener('close', function () { document.body.style.overflow = ''; });
  }
  var form = $('#qform');
  if (form) form.addEventListener('submit', function () {
    var button = $('button[type=submit]', form);
    if (form.checkValidity() && button) { button.disabled = true; button.textContent = 'Zapisywanie…'; }
  });
})();
