/**
 * Phone Picker & Country Select JavaScript
 * Handles flag display, country code sync, and phone number formatting
 */

// Update flag when country code dropdown changes
function updatePhoneFlag(selectEl) {
    var selected = selectEl.options[selectEl.selectedIndex];
    var flag = selected.getAttribute('data-flag') || '🌍';
    var dial = selected.getAttribute('data-dial') || '';
    var targetId = selectEl.getAttribute('data-target');
    var phoneInput = document.getElementById(targetId);
    var hiddenInput = document.getElementById(targetId + '_full');
    
    // Update the select display to show flag
    selectEl.style.fontSize = '18px';
    
    // Update hidden field with full phone number (dial code + national)
    if (phoneInput && hiddenInput) {
        var national = phoneInput.value.trim();
        // Remove leading zeros and non-digits from national number
        national = national.replace(/[^0-9]/g, '');
        if (dial && national) {
            hiddenInput.value = dial + ' ' + national;
        } else {
            hiddenInput.value = national;
        }
    }
    
    // Sync country select if on same form
    var countrySelect = document.querySelector('.country-select');
    if (countrySelect && selected.value) {
        // Find matching country in the country dropdown
        for (var i = 0; i < countrySelect.options.length; i++) {
            var opt = countrySelect.options[i];
            if (opt.getAttribute('data-code') === selected.value) {
                countrySelect.selectedIndex = i;
                countrySelect.dispatchEvent(new Event('change'));
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
    
    // Sync phone country code if on same form
    var phoneCountrySelect = document.querySelector('.phone-country-select');
    if (phoneCountrySelect && countryCode) {
        for (var i = 0; i < phoneCountrySelect.options.length; i++) {
            var opt = phoneCountrySelect.options[i];
            if (opt.value === countryCode) {
                phoneCountrySelect.selectedIndex = i;
                phoneCountrySelect.dispatchEvent(new Event('change'));
                break;
            }
        }
    }
}

// Update hidden full phone field when user types in phone input
function initPhonePickers() {
    document.querySelectorAll('.phone-picker').forEach(function(picker) {
        var id = picker.getAttribute('data-id');
        var phoneInput = document.getElementById(id);
        var hiddenInput = document.getElementById(id + '_full');
        var countrySelect = picker.querySelector('.phone-country-select');
        
        if (!phoneInput || !hiddenInput) return;
        
        // Update hidden field on input
        phoneInput.addEventListener('input', function() {
            var dial = '';
            var selected = countrySelect.options[countrySelect.selectedIndex];
            if (selected) dial = selected.getAttribute('data-dial') || '';
            
            var national = phoneInput.value.trim();
            // Keep formatting but ensure digits for the hidden field
            if (dial && national) {
                // Remove any leading + or dial code if user typed it
                national = national.replace(new RegExp('^\\' + dial.replace(/\+/g, '\\+') + '\\s*'), '');
                national = national.replace(/^\+/, '');
                hiddenInput.value = dial + ' ' + national;
            } else {
                hiddenInput.value = national;
            }
        });
        
        // Also update on blur for final cleanup
        phoneInput.addEventListener('blur', function() {
            var dial = '';
            var selected = countrySelect.options[countrySelect.selectedIndex];
            if (selected) dial = selected.getAttribute('data-dial') || '';
            
            var national = phoneInput.value.trim();
            if (dial && national) {
                // Strip non-digits for clean storage
                var digits = national.replace(/[^0-9]/g, '');
                hiddenInput.value = dial + ' ' + digits;
            } else {
                hiddenInput.value = national;
            }
        });
    });
}

// Auto-init on page load
document.addEventListener('DOMContentLoaded', function() {
    initPhonePickers();
});