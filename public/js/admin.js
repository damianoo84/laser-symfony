(function () {
  'use strict';
  var dirty = new Set();
  document.querySelectorAll('[data-dirty-form]').forEach(function (form) {
    form.addEventListener('input', function () {
      dirty.add(form);
      var state = document.querySelector('[data-save-state]');
      if (state) state.textContent = 'Masz niezapisane zmiany.';
    });
  });
  document.addEventListener('submit', function (event) {
    var message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) { event.preventDefault(); return; }
    if (event.target.checkValidity()) dirty.clear();
  });
  window.addEventListener('beforeunload', function (event) {
    if (dirty.size) { event.preventDefault(); event.returnValue = ''; }
  });
  var search = document.querySelector('[data-settings-search]');
  if (search) search.addEventListener('input', function () {
    var query = search.value.trim().toLocaleLowerCase('pl');
    document.querySelectorAll('.settings-group').forEach(function (group) {
      var visible = 0;
      group.querySelectorAll('[data-search]').forEach(function (field) {
        field.hidden = !field.dataset.search.includes(query);
        if (!field.hidden) visible++;
      });
      group.hidden = visible === 0;
      if (query && visible) group.open = true;
    });
  });
  document.querySelectorAll('[data-open-add]').forEach(function (link) {
    link.addEventListener('click', function () { document.getElementById('add-new').open = true; });
  });
  document.querySelectorAll('[data-image-upload]').forEach(function (input) {
    input.addEventListener('change', function () {
      var file = input.files[0];
      if (!file) return;
      if (file.size > 5 * 1024 * 1024) { window.alert('Zdjęcie jest za duże. Maksymalny rozmiar: 5 MB.'); input.value = ''; return; }
      var wrapper = input.closest('.field');
      var image = wrapper.querySelector('.img-prev img');
      if (!image) { var preview = document.createElement('div'); preview.className = 'img-prev'; image = document.createElement('img'); image.alt = 'Podgląd nowego zdjęcia'; preview.appendChild(image); wrapper.appendChild(preview); }
      var url = URL.createObjectURL(file); image.onload = function () { URL.revokeObjectURL(url); }; image.src = url;
    });
  });
})();
