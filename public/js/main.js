(function () {
  'use strict';
  var $ = function (selector, root) { return (root || document).querySelector(selector); };
  var $$ = function (selector, root) { return Array.from((root || document).querySelectorAll(selector)); };
  var motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
  var reduceMotion = motionPreference.matches || document.body.dataset.motion === 'none';

  var mobileCta = $('.mobile-cta');
  function updateMobileCta() {
    if (!mobileCta) return;
    var hero = $('#heroSlider'), header = $('#siteHeader'), contact = $('#kontakt');
    var headerBottom = header ? header.getBoundingClientRect().bottom : 0;
    var heroPassed = !hero || hero.getBoundingClientRect().bottom <= headerBottom;
    var contactRect = contact ? contact.getBoundingClientRect() : null;
    var contactVisible = contactRect && contactRect.top < innerHeight && contactRect.bottom > headerBottom;
    var editing = document.activeElement && document.activeElement.matches('input, textarea, select, [contenteditable="true"]');
    var menuOpen = $('#navLinks.open'), modalOpen = $('dialog[open]');
    var viewportHeight = window.visualViewport ? window.visualViewport.height : innerHeight;
    var visible = window.matchMedia('(max-width: 560px)').matches && viewportHeight >= 420
      && heroPassed && !contactVisible && !editing && !menuOpen && !modalOpen;
    mobileCta.hidden = !visible;
    document.body.classList.toggle('mobile-contact-shown', !!visible);
  }
  window.addEventListener('resize', updateMobileCta, { passive: true });
  if (window.visualViewport) window.visualViewport.addEventListener('resize', updateMobileCta, { passive: true });
  document.addEventListener('focusin', updateMobileCta);
  document.addEventListener('focusout', function () { requestAnimationFrame(updateMobileCta); });

  function useOriginal(photo) {
    var picture = photo.closest('picture');
    var sources = picture ? $$('source', picture) : [];
    if (!sources.length) return false;
    sources.forEach(function (source) { source.remove(); });
    photo.src = photo.getAttribute('src');
    return true;
  }
  $$('.responsive-picture > img:not(.slide-photo)').forEach(function (photo) {
    photo.addEventListener('error', function () { useOriginal(photo); });
    if (photo.complete && !photo.naturalWidth) useOriginal(photo);
  });

  // A missing variant falls back to the original; a broken hero then uses its placeholder.
  $$('.slide-photo').forEach(function (photo) {
    function fallback() {
      if (useOriginal(photo)) return;
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
  function closeMenu() { nav.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); burger.setAttribute('aria-label', 'Otwórz menu'); updateMobileCta(); }
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', String(open)); burger.setAttribute('aria-label', open ? 'Zamknij menu' : 'Otwórz menu');
      updateMobileCta();
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
      updateMobileCta();
      ticking = false;
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  if (toTop) toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduceMotion ? 'instant' : 'smooth' }); });

  var lightbox = $('#lightbox'), image = $('#lbImg');
  if (lightbox && image) {
    var galleryEntries = $$('[data-gallery-item]'), galleryIndex = 0, galleryOpener = null;
    var galleryPrevious = $('[data-gallery-prev]', lightbox), galleryNext = $('[data-gallery-next]', lightbox);
    var galleryCounter = $('[data-gallery-counter]', lightbox), galleryCaption = $('#gallery-caption');
    function showGalleryImage(nextIndex) {
      if (!galleryEntries.length) return;
      galleryIndex = (nextIndex + galleryEntries.length) % galleryEntries.length;
      var entry = galleryEntries[galleryIndex];
      image.src = entry.dataset.full || entry.href;
      image.alt = entry.dataset.alt || '';
      if (galleryCaption) { galleryCaption.textContent = entry.dataset.caption || ''; galleryCaption.hidden = !galleryCaption.textContent; }
      if (galleryCounter) galleryCounter.textContent = galleryCounter.dataset.label + ' ' + (galleryIndex + 1) + ' / ' + galleryEntries.length;
    }
    galleryEntries.forEach(function (entry, index) {
      if (!entry.matches('.gallery-open')) return;
      entry.addEventListener('click', function () {
        galleryOpener = entry;
        showGalleryImage(index);
        lightbox.showModal(); document.body.style.overflow = 'hidden'; updateMobileCta();
      });
    });
    if (galleryPrevious) { galleryPrevious.hidden = galleryEntries.length < 2; galleryPrevious.addEventListener('click', function () { showGalleryImage(galleryIndex - 1); }); }
    if (galleryNext) { galleryNext.hidden = galleryEntries.length < 2; galleryNext.addEventListener('click', function () { showGalleryImage(galleryIndex + 1); }); }
    $('[data-gallery-close]', lightbox).addEventListener('click', function () { lightbox.close(); });
    lightbox.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { event.preventDefault(); lightbox.close(); return; }
      if (event.altKey || event.ctrlKey || event.metaKey) return;
      if (event.key === 'ArrowLeft') { event.preventDefault(); showGalleryImage(galleryIndex - 1); }
      else if (event.key === 'ArrowRight') { event.preventDefault(); showGalleryImage(galleryIndex + 1); }
      else if (event.key === 'Home') { event.preventDefault(); showGalleryImage(0); }
      else if (event.key === 'End') { event.preventDefault(); showGalleryImage(galleryEntries.length - 1); }
    });
    var galleryTouch = null;
    image.addEventListener('pointerdown', function (event) {
      if (event.pointerType !== 'touch' || !event.isPrimary) return;
      galleryTouch = { x: event.clientX, y: event.clientY };
      image.setPointerCapture(event.pointerId);
    });
    image.addEventListener('pointerup', function (event) {
      if (!galleryTouch) return;
      var dx = event.clientX - galleryTouch.x, dy = event.clientY - galleryTouch.y;
      if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.2) showGalleryImage(galleryIndex + (dx < 0 ? 1 : -1));
      galleryTouch = null;
    });
    image.addEventListener('pointercancel', function () { galleryTouch = null; });
    image.addEventListener('error', function () { if (lightbox.open && galleryCounter) galleryCounter.textContent = galleryCounter.dataset.error; });
    lightbox.addEventListener('click', function (event) { if (event.target === lightbox) lightbox.close(); });
    lightbox.addEventListener('close', function () { document.body.style.overflow = ''; if (galleryOpener) galleryOpener.focus({ preventScroll: true }); updateMobileCta(); });
  }
  var form = $('#qform');
  var topicTargets = Object.create(null);
  $$('[data-content-switcher]').forEach(function (switcher) {
    var panels = $$('[data-topic-panel]', switcher), content = $('[data-topic-content]', switcher);
    if (!panels.length || !content) return;
    var triggers = $$('[data-topic-trigger]').filter(function (link) { return panels.some(function (panel) { return link.hash === '#' + panel.id; }); });
    content.setAttribute('role', 'region');
    function activate(id) {
      var selected = panels.find(function (panel) { return panel.id === id; });
      if (!selected) return;
      var group = selected.dataset.topicGroup || selected.id;
      switcher.hidden = false;
      content.hidden = false;
      content.setAttribute('aria-labelledby', 'heading-' + selected.id);
      panels.forEach(function (panel) {
        var active = (panel.dataset.topicGroup || panel.id) === group;
        panel.hidden = !active;
        if (!active) $$('video', panel).forEach(function (video) { video.pause(); });
      });
      triggers.forEach(function (link) {
        var panel = panels.find(function (item) { return link.hash === '#' + item.id; });
        var active = (panel.dataset.topicGroup || panel.id) === group;
        link.setAttribute('aria-expanded', String(active));
        var card = link.closest('.offer-card, .app-card');
        if (card) card.classList.toggle('is-selected', active);
      });
    }
    panels.forEach(function (panel) { panel.hidden = true; topicTargets[panel.id] = { panel: panel, activate: activate }; });
    triggers.forEach(function (link) {
      link.setAttribute('aria-controls', content.id);
      link.setAttribute('aria-expanded', 'false');
    });
  });
  function revealDescription(id, scroll) {
    var target = id ? document.getElementById(id) : null;
    if (!target) return false;
    if (topicTargets[id]) topicTargets[id].activate(id);
    else {
      var details = target.matches('details') ? target : target.closest('details');
      if (!details) return false;
      details.open = true;
    }
    if (scroll) requestAnimationFrame(function () { target.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'instant' : 'smooth' }); });
    return true;
  }
  function openLinkedDescription() {
    var id;
    try { id = decodeURIComponent(location.hash.slice(1)); } catch (_) { return; }
    revealDescription(id, true);
  }
  window.addEventListener('hashchange', openLinkedDescription);
  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    var link = event.target.closest('a[href^="#"]');
    if (!link && !event.target.closest('a, button, input, textarea, select')) {
      var card = event.target.closest('.offer-card, .application-choices .app-card');
      link = card ? $('[data-topic-trigger][href^="#"]', card) : null;
    }
    if (!link) return;
    var id;
    try { id = decodeURIComponent(link.hash.slice(1)); } catch (_) { return; }
    if (revealDescription(id, true)) {
      event.preventDefault();
      history.replaceState(null, '', location.pathname + location.search + '#' + encodeURIComponent(id));
    }
  });
  openLinkedDescription();
  if (form) form.addEventListener('submit', function () {
    var button = $('button[type=submit]', form);
    if (form.checkValidity() && button) { button.disabled = true; button.textContent = 'Zapisywanie…'; }
  });
  updateMobileCta();
})();
