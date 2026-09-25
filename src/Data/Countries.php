<?php
declare(strict_types=1);

namespace Nikto\Data;

final class Countries
{
    private const CURRENCY = [
        'USD' => ['US', 'آمریکا'],
        'EUR' => ['EU', 'اتحادیه اروپا'],
        'GBP' => ['GB', 'انگلستان'],
        'JPY' => ['JP', 'ژاپن'],
        'CHF' => ['CH', 'سوئیس'],
        'CAD' => ['CA', 'کانادا'],
        'AUD' => ['AU', 'استرالیا'],
        'NZD' => ['NZ', 'نیوزیلند'],
        'CNY' => ['CN', 'چین'],
        'HKD' => ['HK', 'هنگ‌کنگ'],
        'SGD' => ['SG', 'سنگاپور'],
        'KRW' => ['KR', 'کره جنوبی'],
        'INR' => ['IN', 'هند'],
        'BRL' => ['BR', 'برزیل'],
        'MXN' => ['MX', 'مکزیک'],
        'ZAR' => ['ZA', 'آفریقای جنوبی'],
        'RUB' => ['RU', 'روسیه'],
        'TRY' => ['TR', 'ترکیه'],
        'SEK' => ['SE', 'سوئد'],
        'NOK' => ['NO', 'نروژ'],
        'DKK' => ['DK', 'دانمارک'],
        'PLN' => ['PL', 'لهستان'],
        'ILS' => ['IL', 'اسرائیل'],
        'AED' => ['AE', 'امارات'],
        'SAR' => ['SA', 'عربستان'],
        'ALL' => ['WW', 'جهانی'],
    ];

    private const COUNTRY_TO_CURRENCY = [
        'US' => 'USD', 'EU' => 'EUR', 'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR',
        'NL' => 'EUR', 'PT' => 'EUR', 'IE' => 'EUR', 'GR' => 'EUR', 'AT' => 'EUR', 'BE' => 'EUR',
        'FI' => 'EUR', 'GB' => 'GBP', 'JP' => 'JPY', 'CH' => 'CHF', 'CA' => 'CAD', 'AU' => 'AUD',
        'NZ' => 'NZD', 'CN' => 'CNY', 'HK' => 'HKD', 'SG' => 'SGD', 'KR' => 'KRW', 'IN' => 'INR',
        'BR' => 'BRL', 'MX' => 'MXN', 'ZA' => 'ZAR', 'RU' => 'RUB', 'TR' => 'TRY', 'SE' => 'SEK',
        'NO' => 'NOK', 'DK' => 'DKK', 'PL' => 'PLN', 'IL' => 'ILS',
    ];

    public static function code(string $currency): string
    {
        return self::CURRENCY[strtoupper($currency)][0] ?? 'WW';
    }

    public static function nameFa(string $currency): string
    {
        return self::CURRENCY[strtoupper($currency)][1] ?? strtoupper($currency);
    }

    public static function currencyOf(string $countryCode): string
    {
        return self::COUNTRY_TO_CURRENCY[strtoupper($countryCode)] ?? strtoupper($countryCode);
    }

    public static function currencies(): array
    {
        return array_keys(self::CURRENCY);
    }
}
