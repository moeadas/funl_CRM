<?php
/**
 * Country data with dial codes and flag emojis
 * Used by phone picker and country dropdown components
 */

if (!function_exists('getCountriesList')) {
    function getCountriesList() {
        return [
            ['AF', 'Afghanistan', '+93', '🇦🇫'],
            ['AL', 'Albania', '+355', '🇦🇱'],
            ['DZ', 'Algeria', '+213', '🇩🇿'],
            ['AD', 'Andorra', '+376', '🇦🇩'],
            ['AO', 'Angola', '+244', '🇦🇴'],
            ['AR', 'Argentina', '+54', '🇦🇷'],
            ['AM', 'Armenia', '+374', '🇦🇲'],
            ['AU', 'Australia', '+61', '🇦🇺'],
            ['AT', 'Austria', '+43', '🇦🇹'],
            ['AZ', 'Azerbaijan', '+994', '🇦🇿'],
            ['BH', 'Bahrain', '+973', '🇧🇭'],
            ['BD', 'Bangladesh', '+880', '🇧🇩'],
            ['BY', 'Belarus', '+375', '🇧🇾'],
            ['BE', 'Belgium', '+32', '🇧🇪'],
            ['BZ', 'Belize', '+501', '🇧🇿'],
            ['BJ', 'Benin', '+229', '🇧🇯'],
            ['BT', 'Bhutan', '+975', '🇧🇹'],
            ['BO', 'Bolivia', '+591', '🇧🇴'],
            ['BA', 'Bosnia and Herzegovina', '+387', '🇧🇦'],
            ['BW', 'Botswana', '+267', '🇧🇼'],
            ['BR', 'Brazil', '+55', '🇧🇷'],
            ['BN', 'Brunei', '+673', '🇧🇳'],
            ['BG', 'Bulgaria', '+359', '🇧🇬'],
            ['BF', 'Burkina Faso', '+226', '🇧🇫'],
            ['BI', 'Burundi', '+257', '🇧🇮'],
            ['KH', 'Cambodia', '+855', '🇰🇭'],
            ['CM', 'Cameroon', '+237', '🇨🇲'],
            ['CA', 'Canada', '+1', '🇨🇦'],
            ['CV', 'Cape Verde', '+238', '🇨🇻'],
            ['CF', 'Central African Republic', '+236', '🇨🇫'],
            ['TD', 'Chad', '+235', '🇹🇩'],
            ['CL', 'Chile', '+56', '🇨🇱'],
            ['CN', 'China', '+86', '🇨🇳'],
            ['CO', 'Colombia', '+57', '🇨🇴'],
            ['KM', 'Comoros', '+269', '🇰🇲'],
            ['CG', 'Congo', '+242', '🇨🇬'],
            ['CD', 'Congo (DRC)', '+243', '🇨🇩'],
            ['CR', 'Costa Rica', '+506', '🇨🇷'],
            ['CI', "Côte d'Ivoire", '+225', '🇨🇮'],
            ['HR', 'Croatia', '+385', '🇭🇷'],
            ['CU', 'Cuba', '+53', '🇨🇺'],
            ['CY', 'Cyprus', '+357', '🇨🇾'],
            ['CZ', 'Czech Republic', '+420', '🇨🇿'],
            ['DK', 'Denmark', '+45', '🇩🇰'],
            ['DJ', 'Djibouti', '+253', '🇩🇯'],
            ['DO', 'Dominican Republic', '+1', '🇩🇴'],
            ['EC', 'Ecuador', '+593', '🇪🇨'],
            ['EG', 'Egypt', '+20', '🇪🇬'],
            ['SV', 'El Salvador', '+503', '🇸🇻'],
            ['GQ', 'Equatorial Guinea', '+240', '🇬🇶'],
            ['ER', 'Eritrea', '+291', '🇪🇷'],
            ['EE', 'Estonia', '+372', '🇪🇪'],
            ['SZ', 'Eswatini', '+268', '🇸🇿'],
            ['ET', 'Ethiopia', '+251', '🇪🇹'],
            ['FJ', 'Fiji', '+679', '🇫🇯'],
            ['FI', 'Finland', '+358', '🇫🇮'],
            ['FR', 'France', '+33', '🇫🇷'],
            ['GA', 'Gabon', '+241', '🇬🇦'],
            ['GM', 'Gambia', '+220', '🇬🇲'],
            ['GE', 'Georgia', '+995', '🇬🇪'],
            ['DE', 'Germany', '+49', '🇩🇪'],
            ['GH', 'Ghana', '+233', '🇬🇭'],
            ['GR', 'Greece', '+30', '🇬🇷'],
            ['GL', 'Greenland', '+299', '🇬🇱'],
            ['GT', 'Guatemala', '+502', '🇬🇹'],
            ['GN', 'Guinea', '+224', '🇬🇳'],
            ['GW', 'Guinea-Bissau', '+245', '🇬🇼'],
            ['GY', 'Guyana', '+592', '🇬🇾'],
            ['HT', 'Haiti', '+509', '🇭🇹'],
            ['HN', 'Honduras', '+504', '🇭🇳'],
            ['HK', 'Hong Kong', '+852', '🇭🇰'],
            ['HU', 'Hungary', '+36', '🇭🇺'],
            ['IS', 'Iceland', '+354', '🇮🇸'],
            ['IN', 'India', '+91', '🇮🇳'],
            ['ID', 'Indonesia', '+62', '🇮🇩'],
            ['IR', 'Iran', '+98', '🇮🇷'],
            ['IQ', 'Iraq', '+964', '🇮🇶'],
            ['IE', 'Ireland', '+353', '🇮🇪'],
            ['IL', 'Israel', '+972', '🇮🇱'],
            ['IT', 'Italy', '+39', '🇮🇹'],
            ['JM', 'Jamaica', '+1', '🇯🇲'],
            ['JP', 'Japan', '+81', '🇯🇵'],
            ['JO', 'Jordan', '+962', '🇯🇴'],
            ['KZ', 'Kazakhstan', '+7', '🇰🇿'],
            ['KE', 'Kenya', '+254', '🇰🇪'],
            ['KI', 'Kiribati', '+686', '🇰🇮'],
            ['KP', 'North Korea', '+850', '🇰🇵'],
            ['KR', 'South Korea', '+82', '🇰🇷'],
            ['KW', 'Kuwait', '+965', '🇰🇼'],
            ['KG', 'Kyrgyzstan', '+996', '🇰🇬'],
            ['LA', 'Laos', '+856', '🇱🇦'],
            ['LV', 'Latvia', '+371', '🇱🇻'],
            ['LB', 'Lebanon', '+961', '🇱🇧'],
            ['LS', 'Lesotho', '+266', '🇱🇸'],
            ['LR', 'Liberia', '+231', '🇱🇷'],
            ['LY', 'Libya', '+218', '🇱🇾'],
            ['LI', 'Liechtenstein', '+423', '🇱🇮'],
            ['LT', 'Lithuania', '+370', '🇱🇹'],
            ['LU', 'Luxembourg', '+352', '🇱🇺'],
            ['MO', 'Macao', '+853', '🇲🇴'],
            ['MG', 'Madagascar', '+261', '🇲🇬'],
            ['MW', 'Malawi', '+265', '🇲🇼'],
            ['MY', 'Malaysia', '+60', '🇲🇾'],
            ['MV', 'Maldives', '+960', '🇲🇻'],
            ['ML', 'Mali', '+223', '🇲🇱'],
            ['MT', 'Malta', '+356', '🇲🇹'],
            ['MR', 'Mauritania', '+222', '🇲🇷'],
            ['MU', 'Mauritius', '+230', '🇲🇺'],
            ['MX', 'Mexico', '+52', '🇲🇽'],
            ['MD', 'Moldova', '+373', '🇲🇩'],
            ['MC', 'Monaco', '+377', '🇲🇨'],
            ['MN', 'Mongolia', '+976', '🇲🇳'],
            ['ME', 'Montenegro', '+382', '🇲🇪'],
            ['MA', 'Morocco', '+212', '🇲🇦'],
            ['MZ', 'Mozambique', '+258', '🇲🇿'],
            ['MM', 'Myanmar', '+95', '🇲🇲'],
            ['NA', 'Namibia', '+264', '🇳🇦'],
            ['NP', 'Nepal', '+977', '🇳🇵'],
            ['NL', 'Netherlands', '+31', '🇳🇱'],
            ['NZ', 'New Zealand', '+64', '🇳🇿'],
            ['NI', 'Nicaragua', '+505', '🇳🇮'],
            ['NE', 'Niger', '+227', '🇳🇪'],
            ['NG', 'Nigeria', '+234', '🇳🇬'],
            ['MK', 'North Macedonia', '+389', '🇲🇰'],
            ['NO', 'Norway', '+47', '🇳🇴'],
            ['OM', 'Oman', '+968', '🇴🇲'],
            ['PK', 'Pakistan', '+92', '🇵🇰'],
            ['PS', 'Palestine', '+970', '🇵🇸'],
            ['PA', 'Panama', '+507', '🇵🇦'],
            ['PG', 'Papua New Guinea', '+675', '🇵🇬'],
            ['PY', 'Paraguay', '+595', '🇵🇾'],
            ['PE', 'Peru', '+51', '🇵🇪'],
            ['PH', 'Philippines', '+63', '🇵🇭'],
            ['PL', 'Poland', '+48', '🇵🇱'],
            ['PT', 'Portugal', '+351', '🇵🇹'],
            ['QA', 'Qatar', '+974', '🇶🇦'],
            ['RO', 'Romania', '+40', '🇷🇴'],
            ['RU', 'Russia', '+7', '🇷🇺'],
            ['RW', 'Rwanda', '+250', '🇷🇼'],
            ['SA', 'Saudi Arabia', '+966', '🇸🇦'],
            ['SN', 'Senegal', '+221', '🇸🇳'],
            ['RS', 'Serbia', '+381', '🇷🇸'],
            ['SC', 'Seychelles', '+248', '🇸🇨'],
            ['SL', 'Sierra Leone', '+232', '🇸🇱'],
            ['SG', 'Singapore', '+65', '🇸🇬'],
            ['SK', 'Slovakia', '+421', '🇸🇰'],
            ['SI', 'Slovenia', '+386', '🇸🇮'],
            ['SO', 'Somalia', '+252', '🇸🇴'],
            ['ZA', 'South Africa', '+27', '🇿🇦'],
            ['SS', 'South Sudan', '+211', '🇸🇸'],
            ['ES', 'Spain', '+34', '🇪🇸'],
            ['LK', 'Sri Lanka', '+94', '🇱🇰'],
            ['SD', 'Sudan', '+249', '🇸🇩'],
            ['SR', 'Suriname', '+597', '🇸🇷'],
            ['SE', 'Sweden', '+46', '🇸🇪'],
            ['CH', 'Switzerland', '+41', '🇨🇭'],
            ['SY', 'Syria', '+963', '🇸🇾'],
            ['TW', 'Taiwan', '+886', '🇹🇼'],
            ['TJ', 'Tajikistan', '+992', '🇹🇯'],
            ['TZ', 'Tanzania', '+255', '🇹🇿'],
            ['TH', 'Thailand', '+66', '🇹🇭'],
            ['TL', 'Timor-Leste', '+670', '🇹🇱'],
            ['TG', 'Togo', '+228', '🇹🇬'],
            ['TT', 'Trinidad and Tobago', '+1', '🇹🇹'],
            ['TN', 'Tunisia', '+216', '🇹🇳'],
            ['TR', 'Turkey', '+90', '🇹🇷'],
            ['TM', 'Turkmenistan', '+993', '🇹🇲'],
            ['UG', 'Uganda', '+256', '🇺🇬'],
            ['UA', 'Ukraine', '+380', '🇺🇦'],
            ['AE', 'United Arab Emirates', '+971', '🇦🇪'],
            ['GB', 'United Kingdom', '+44', '🇬🇧'],
            ['US', 'United States', '+1', '🇺🇸'],
            ['UY', 'Uruguay', '+598', '🇺🇾'],
            ['UZ', 'Uzbekistan', '+998', '🇺🇿'],
            ['VU', 'Vanuatu', '+678', '🇻🇺'],
            ['VA', 'Vatican City', '+39', '🇻🇦'],
            ['VE', 'Venezuela', '+58', '🇻🇪'],
            ['VN', 'Vietnam', '+84', '🇻🇳'],
            ['YE', 'Yemen', '+967', '🇾🇪'],
            ['ZM', 'Zambia', '+260', '🇿🇲'],
            ['ZW', 'Zimbabwe', '+263', '🇿🇼'],
        ];
    }
}

if (!function_exists('getCountryByCode')) {
    function getCountryByCode($code) {
        $code = strtoupper($code);
        foreach (getCountriesList() as $c) {
            if ($c[0] === $code) return $c;
        }
        return null;
    }
}

if (!function_exists('getCountryByName')) {
    function getCountryByName($name) {
        $nameLower = strtolower(trim($name));
        foreach (getCountriesList() as $c) {
            if (strtolower($c[1]) === $nameLower) return $c;
        }
        foreach (getCountriesList() as $c) {
            if (strpos($nameLower, strtolower($c[1])) !== false) return $c;
        }
        return null;
    }
}

if (!function_exists('getCountryByDialCode')) {
    function getCountryByDialCode($dialCode) {
        foreach (getCountriesList() as $c) {
            if ($c[2] === $dialCode) return $c;
        }
        return null;
    }
}

if (!function_exists('parsePhoneNumber')) {
    function parsePhoneNumber($phone) {
        $phone = trim($phone);
        if (empty($phone)) return null;
        if ($phone[0] !== '+') {
            $phone = '+' . $phone;
        }
        $countries = getCountriesList();
        usort($countries, function($a, $b) {
            return strlen($b[2]) - strlen($a[2]);
        });
        foreach ($countries as $c) {
            if (strpos($phone, $c[2]) === 0) {
                $national = substr($phone, strlen($c[2]));
                $national = ltrim($national, " \t\n\r\0\x0B-()");
                return ['code' => $c[0], 'dial_code' => $c[2], 'national' => $national, 'flag' => $c[3], 'name' => $c[1]];
            }
        }
        return null;
    }
}

/**
 * Render a searchable phone picker with country code dropdown and flag
 * Options: id, label, value (existing phone number), required
 */
if (!function_exists('renderPhonePicker')) {
    function renderPhonePicker($opts = []) {
        $id = $opts['id'] ?? 'phone';
        $label = $opts['label'] ?? 'Phone';
        $value = $opts['value'] ?? '';
        $required = !empty($opts['required']);
        $countries = getCountriesList();

        $selectedCode = '';
        $nationalNumber = '';
        $flag = '🌍';
        $dialCode = '';
        if ($value) {
            $parsed = parsePhoneNumber($value);
            if ($parsed) {
                $selectedCode = $parsed['code'];
                $nationalNumber = $parsed['national'];
                $flag = $parsed['flag'];
                $dialCode = $parsed['dial_code'];
            } else {
                $nationalNumber = $value;
            }
        }

        // Build options HTML
        $optionsHtml = '<option value="" data-dial="" data-flag="🌍">' . __('Select...') . '</option>';
        foreach ($countries as $c) {
            $sel = ($c[0] === $selectedCode) ? ' selected' : '';
            $optionsHtml .= '<option value="' . $c[0] . '" data-dial="' . $c[2] . '" data-flag="' . $c[3] . '"' . $sel . '>'
                . $c[3] . ' ' . htmlspecialchars($c[1]) . ' (' . $c[2] . ')</option>';
        }

        $html = '<div class="phone-picker" data-id="' . htmlspecialchars($id) . '">';
        if ($label) {
            $html .= '<label class="form-label">' . htmlspecialchars($label);
            if ($required) $html .= ' *';
            $html .= '</label>';
        }
        $html .= '<div style="display:flex;gap:0;border:1px solid var(--color-border);border-radius:8px;overflow:hidden;align-items:stretch;">';
        // Searchable country code button
        $html .= '<button type="button" class="phone-country-btn" data-target="' . htmlspecialchars($id) . '"'
            . ' onclick="openPhoneCountrySearch(this)"'
            . ' style="display:flex;align-items:center;gap:4px;padding:0 8px;border:none;border-right:1px solid var(--color-border);background:var(--color-bg-secondary);cursor:pointer;font-size:14px;white-space:nowrap;min-width:60px;justify-content:center;">'
            . '<span class="phone-flag">' . $flag . '</span>'
            . '<span class="phone-dial">' . ($dialCode ?: '+?') . '</span>'
            . '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"/></svg>'
            . '</button>';
        // Hidden select for the actual value
        $html .= '<select class="phone-country-select" data-target="' . htmlspecialchars($id) . '" style="display:none;" onchange="updatePhonePicker(this)">';
        $html .= $optionsHtml;
        $html .= '</select>';
        // Phone input
        $html .= '<input type="tel" id="' . htmlspecialchars($id) . '" class="form-control phone-input" value="' . htmlspecialchars($nationalNumber) . '" placeholder="555-0100" style="border:none;border-radius:0;flex:1;"';
        if ($required) $html .= ' required';
        $html .= '>';
        $html .= '</div>';
        $html .= '<input type="hidden" id="' . htmlspecialchars($id) . '_full" name="' . htmlspecialchars($id) . '_full" value="' . htmlspecialchars($value) . '">';
        $html .= '</div>';

        return $html;
    }
}

/**
 * Render a searchable country dropdown
 * Options: id, label, value (existing country name or code), required
 */
if (!function_exists('renderCountrySelect')) {
    function renderCountrySelect($opts = []) {
        $id = $opts['id'] ?? 'country';
        $label = $opts['label'] ?? 'Country';
        $value = $opts['value'] ?? '';
        $required = !empty($opts['required']);
        $countries = getCountriesList();

        $selectedCode = '';
        $selectedName = '';
        $selectedFlag = '';
        if ($value) {
            $country = getCountryByCode($value);
            if (!$country) {
                $country = getCountryByName($value);
            }
            if ($country) {
                $selectedCode = $country[0];
                $selectedName = $country[1];
                $selectedFlag = $country[3];
            }
        }

        $html = '<div class="form-group">';
        if ($label) {
            $html .= '<label class="form-label">' . htmlspecialchars($label);
            if ($required) $html .= ' *';
            $html .= '</label>';
        }
        $html .= '<select id="' . htmlspecialchars($id) . '" class="form-control country-select" data-target-flag="' . htmlspecialchars($id) . '_flag"';
        if ($required) $html .= ' required';
        $html .= ' onchange="updateCountryFlag(this)">';
        $html .= '<option value="">' . __('Select country...') . '</option>';
        foreach ($countries as $c) {
            $sel = ($c[0] === $selectedCode) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($c[1]) . '" data-code="' . $c[0] . '" data-dial="' . $c[2] . '" data-flag="' . $c[3] . '"' . $sel . '>'
                . $c[3] . ' ' . htmlspecialchars($c[1]) . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';

        return $html;
    }
}