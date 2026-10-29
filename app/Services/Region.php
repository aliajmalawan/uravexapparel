<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The store's delivery country (United States): states, ZIP and phone rules
 * shared by checkout, saved addresses and admin address edits.
 */
final class Region
{
    public const COUNTRY = 'US';
    public const COUNTRY_NAME = 'United States';

    /** USPS state codes => names (50 states, DC, and military/territory codes most carriers serve). */
    public const STATES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
        'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
        'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
        'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
        'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota',
        'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
        'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin',
        'WY' => 'Wyoming', 'PR' => 'Puerto Rico', 'AA' => 'Armed Forces Americas', 'AE' => 'Armed Forces Europe', 'AP' => 'Armed Forces Pacific',
    ];

    public const ZIP_RULE = 'regex:/^\d{5}(-\d{4})?$/';
    // 10-digit US number, optional +1 / punctuation: (555) 123-4567, 555-123-4567, +1 555 123 4567.
    public const PHONE_RULE = 'regex:/^(\+?1[\s.-]?)?\(?[2-9]\d{2}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/';

    /** Validation rules for a delivery address. */
    public static function addressRules(): array
    {
        return [
            'full_name' => 'required|string|min:2|max:120',
            'phone' => 'required|string|max:32|' . self::PHONE_RULE,
            'line1' => 'required|string|min:4|max:191',
            'line2' => 'string|max:191',
            'city' => 'required|string|min:2|max:100',
            'province' => 'required|in:' . implode(',', array_keys(self::STATES)),
            'postal_code' => 'required|string|max:10|' . self::ZIP_RULE,
        ];
    }

    /** "CA" => "California" (unknown values, e.g. older addresses, are returned as-is). */
    public static function stateName(?string $code): string
    {
        return self::STATES[strtoupper((string) $code)] ?? (string) $code;
    }

    /** One-line locality in US order: "Los Angeles, CA 90012". */
    public static function locality(array $a): string
    {
        $city = trim((string) ($a['city'] ?? ''));
        $state = trim((string) ($a['province'] ?? ''));
        $zip = trim((string) ($a['postal_code'] ?? ''));
        return trim($city . ($state !== '' ? ', ' . $state : '') . ($zip !== '' ? ' ' . $zip : ''), ', ');
    }
}
