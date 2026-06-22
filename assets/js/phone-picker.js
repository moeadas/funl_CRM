/**
 * Phone Picker & Country Select JavaScript
 * Searchable country dropdowns with flags and dial codes
 */

// Update phone picker when hidden select changes
function updatePhonePicker(selectEl) {
    var selected = selectEl.options[selectEl.selectedIndex];
    var flag = selected.getAttribute('data-flag') || '🌍';
    var dial = selected.getAttribute('data-dial') || '';
    var targetId = selectEl.getAttribute('data-target');
    var phoneInput = document.getElementById(targetId);
    var hiddenInput = document.getElementById(targetId + '_full');

    // Update the button display
    var picker = selectEl.closest('.phone-picker');
    var flagEl = picker?.querySelector('.phone-flag');
    var dialEl = picker?.querySelector('.phone-dial');
    if (flagEl) flagEl.textContent = flag;
    if (dialEl) dialEl.textContent = dial || '+?';

    // Update hidden field
    if (phoneInput && hiddenInput) {
        var national = phoneInput.value.trim().replace(/[^0-9]/g, '');
        if (dial && national) {
            hiddenInput.value = dial + ' ' + national;
        } else {
            hiddenInput.value = national;
        }
    }

    // Sync country dropdown
    var countryCode = selected.value;
    var countrySelect = document.querySelector('.country-select');
    if (countrySelect && countryCode) {
        for (var i = 0; i < countrySelect.options.length; i++) {
            if (countrySelect.options[i].getAttribute('data-code') === countryCode) {
                countrySelect.selectedIndex = i;
                break;
            }
        }
    }
}

// Update flag when country dropdown changes
function updateCountryFlag(selectEl) {
    var selected = selectEl.options[selectEl.selectedIndex];
    var countryCode = selected.getAttribute('data-code') || '';
    var dial = selected.getAttribute('data-dial') || '';

    // Sync phone country code
    var phoneCountrySelect = document.querySelector('.phone-country-select');
    if (phoneCountrySelect && countryCode) {
        for (var i = 0; i < phoneCountrySelect.options.length; i++) {
            if (phoneCountrySelect.options[i].value === countryCode) {
                phoneCountrySelect.selectedIndex = i;
                phoneCountrySelect.dispatchEvent(new Event('change'));
                break;
            }
        }
    }
}

// Open searchable country modal for phone picker
function openPhoneCountrySearch(btnEl) {
    var targetId = btnEl.getAttribute('data-target');
    var selectEl = document.querySelector('.phone-country-select[data-target="' + targetId + '"]');
    if (!selectEl) return;

    var currentVal = selectEl.value;
    var countries = [];
    for (var i = 0; i < selectEl.options.length; i++) {
        var opt = selectEl.options[i];
        countries.push({
            code: opt.value,
            dial: opt.getAttribute('data-dial') || '',
            flag: opt.getAttribute('data-flag') || '🌍',
            name: opt.textContent.replace(/^[^\s]+\s/, '').replace(/\s\(.+$/, ''),
            selected: opt.value === currentVal
        });
    }

    openCountrySearchModal(countries, function(selected) {
        // Update the hidden select
        for (var i = 0; i < selectEl.options.length; i++) {
            if (selectEl.options[i].value === selected.code) {
                selectEl.selectedIndex = i;
                selectEl.dispatchEvent(new Event('change'));
                break;
            }
        }
    });
}

// Open searchable country modal for country select
function openCountrySearch(selectEl) {
    var currentVal = selectEl.value;
    var countries = [];
    for (var i = 0; i < selectEl.options.length; i++) {
        var opt = selectEl.options[i];
        countries.push({
            code: opt.getAttribute('data-code') || '',
            dial: opt.getAttribute('data-dial') || '',
            flag: opt.getAttribute('data-flag') || '🌍',
            name: opt.textContent.replace(/^[^\s]+\s/, ''),
            value: opt.value,
            selected: opt.value === currentVal
        });
    }

    openCountrySearchModal(countries, function(selected) {
        for (var i = 0; i < selectEl.options.length; i++) {
            if (selectEl.options[i].value === selected.value) {
                selectEl.selectedIndex = i;
                selectEl.dispatchEvent(new Event('change'));
                break;
            }
        }
    });
}

// Shared modal for country search
function openCountrySearchModal(countries, onSelect) {
    // Remove existing modal
    var existing = document.getElementById('countrySearchModal');
    if (existing) existing.remove();

    var modal = document.createElement('div');
    modal.id = 'countrySearchModal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;display:flex;align-items:center;justify-content:center;padding:16px;';
    modal.onclick = function(e) { if (e.target === modal) { modal.remove(); } };

    var box = document.createElement('div');
    box.style.cssText = 'background:white;border-radius:12px;width:100%;max-width:420px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.2);';

    // Search input
    var searchWrap = document.createElement('div');
    searchWrap.style.cssText = 'padding:16px;border-bottom:1px solid #e5e7eb;flex-shrink:0;';
    var searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search country or code...';
    searchInput.style.cssText = 'width:100%;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:15px;outline:none;box-sizing:border-box;';
    searchInput.autofocus = true;
    searchWrap.appendChild(searchInput);
    box.appendChild(searchWrap);

    // Results list
    var listWrap = document.createElement('div');
    listWrap.style.cssText = 'overflow-y:auto;flex:1;';

    function renderList(filter) {
        listWrap.innerHTML = '';
        var filtered = countries.filter(function(c) {
            if (!filter) return true;
            var f = filter.toLowerCase();
            return c.name.toLowerCase().indexOf(f) !== -1
                || c.code.toLowerCase().indexOf(f) !== -1
                || c.dial.indexOf(f) !== -1;
        });
        filtered.forEach(function(c) {
            var item = document.createElement('div');
            item.style.cssText = 'padding:10px 16px;cursor:pointer;display:flex;align-items:center;gap:10px;font-size:14px;border-bottom:1px solid #f3f4f6;';
            if (c.selected) item.style.background = '#eff6ff';
            item.innerHTML = '<span style="font-size:22px;">' + c.flag + '</span>'
                + '<span style="flex:1;">' + c.name + '</span>'
                + '<span style="color:#6b7280;font-size:13px;">' + c.dial + '</span>';
            item.onmouseenter = function() { item.style.background = item.style.background === 'rgb(239, 246, 255)' ? '#eff6ff' : '#f9fafb'; };
            item.onmouseleave = function() { item.style.background = c.selected ? '#eff6ff' : 'white'; };
            item.onclick = function() {
                onSelect(c);
                modal.remove();
            };
            listWrap.appendChild(item);
        });
        if (filtered.length === 0) {
            listWrap.innerHTML = '<div style="padding:24px;text-align:center;color:#9ca3af;">No countries found</div>';
        }
    }

    renderList('');
    searchInput.addEventListener('input', function() { renderList(this.value); });
    box.appendChild(listWrap);
    modal.appendChild(box);
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';

    // Cleanup on close
    var origRemove = modal.remove.bind(modal);
    modal.remove = function() {
        origRemove();
        document.body.style.overflow = '';
    };
}

// Update hidden full phone field when user types
function initPhonePickers() {
    document.querySelectorAll('.phone-picker').forEach(function(picker) {
        var id = picker.getAttribute('data-id');
        var phoneInput = document.getElementById(id);
        var hiddenInput = document.getElementById(id + '_full');
        var selectEl = picker.querySelector('.phone-country-select');

        if (!phoneInput || !hiddenInput) return;

        phoneInput.addEventListener('input', function() {
            var dial = '';
            var selected = selectEl.options[selectEl.selectedIndex];
            if (selected) dial = selected.getAttribute('data-dial') || '';

            var national = phoneInput.value.trim();
            if (dial && national) {
                national = national.replace(new RegExp('^\\' + dial.replace(/\+/g, '\\+') + '\\s*'), '');
                national = national.replace(/^\+/, '');
                hiddenInput.value = dial + ' ' + national;
            } else {
                hiddenInput.value = national;
            }
        });

        phoneInput.addEventListener('blur', function() {
            var dial = '';
            var selected = selectEl.options[selectEl.selectedIndex];
            if (selected) dial = selected.getAttribute('data-dial') || '';

            var national = phoneInput.value.trim();
            if (dial && national) {
                var digits = national.replace(/[^0-9]/g, '');
                hiddenInput.value = dial + ' ' + digits;
            } else {
                hiddenInput.value = national;
            }
        });
    });

    // Make country selects searchable
    document.querySelectorAll('.country-select').forEach(function(sel) {
        // Wrap the select in a container with a search button
        if (sel.dataset.searchEnabled) return;
        sel.dataset.searchEnabled = '1';

        // Add click handler to open search on the select
        sel.addEventListener('dblclick', function() {
            openCountrySearch(sel);
        });

        // Add a search icon button next to the select
        var wrapper = document.createElement('div');
        wrapper.style.cssText = 'position:relative;';
        sel.parentNode.insertBefore(wrapper, sel);
        wrapper.appendChild(sel);

        var searchBtn = document.createElement('button');
        searchBtn.type = 'button';
        searchBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
        searchBtn.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;padding:4px;pointer-events:none;';
        wrapper.appendChild(searchBtn);

        // Replace the native dropdown with our search modal on click
        sel.addEventListener('mousedown', function(e) {
            e.preventDefault();
            openCountrySearch(sel);
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initPhonePickers();
});