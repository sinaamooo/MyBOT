<?php
declare(strict_types=1);

namespace Nikto\Data;

/**
 * شناسنامه‌ی ارزها: نام فارسی/انگلیسی، شناسه‌ی کوین‌گکو و رنگ برند.
 */
final class Coins
{
    private const MAP = [
        'BTC'   => ['Bitcoin', 'بیت‌کوین', 'bitcoin', '#F7931A'],
        'ETH'   => ['Ethereum', 'اتریوم', 'ethereum', '#627EEA'],
        'USDT'  => ['Tether', 'تتر', 'tether', '#26A17B'],
        'BNB'   => ['BNB', 'بی‌ان‌بی', 'binancecoin', '#F3BA2F'],
        'SOL'   => ['Solana', 'سولانا', 'solana', '#14F195'],
        'XRP'   => ['XRP', 'ریپل', 'ripple', '#2E3A45'],
        'USDC'  => ['USD Coin', 'یواس‌دی‌کوین', 'usd-coin', '#2775CA'],
        'ADA'   => ['Cardano', 'کاردانو', 'cardano', '#0033AD'],
        'DOGE'  => ['Dogecoin', 'دوج‌کوین', 'dogecoin', '#C2A633'],
        'TRX'   => ['TRON', 'ترون', 'tron', '#EF0027'],
        'TON'   => ['Toncoin', 'تون‌کوین', 'the-open-network', '#0098EA'],
        'AVAX'  => ['Avalanche', 'آوالانچ', 'avalanche-2', '#E84142'],
        'DOT'   => ['Polkadot', 'پولکادات', 'polkadot', '#E6007A'],
        'LINK'  => ['Chainlink', 'چین‌لینک', 'chainlink', '#2A5ADA'],
        'MATIC' => ['Polygon', 'پالیگان', 'matic-network', '#8247E5'],
        'POL'   => ['Polygon', 'پالیگان', 'polygon-ecosystem-token', '#8247E5'],
        'LTC'   => ['Litecoin', 'لایت‌کوین', 'litecoin', '#345D9D'],
        'SHIB'  => ['Shiba Inu', 'شیبا اینو', 'shiba-inu', '#FFA409'],
        'BCH'   => ['Bitcoin Cash', 'بیت‌کوین کش', 'bitcoin-cash', '#8DC351'],
        'UNI'   => ['Uniswap', 'یونی‌سواپ', 'uniswap', '#FF007A'],
        'NEAR'  => ['NEAR', 'نییر', 'near', '#00C08B'],
        'ATOM'  => ['Cosmos', 'کازموس', 'cosmos', '#2E3148'],
        'XLM'   => ['Stellar', 'استلار', 'stellar', '#14B6E7'],
        'ETC'   => ['Ethereum Classic', 'اتریوم کلاسیک', 'ethereum-classic', '#328332'],
        'FIL'   => ['Filecoin', 'فایل‌کوین', 'filecoin', '#0090FF'],
        'APT'   => ['Aptos', 'اپتاس', 'aptos', '#1E1E1E'],
        'ARB'   => ['Arbitrum', 'آربیتروم', 'arbitrum', '#28A0F0'],
        'OP'    => ['Optimism', 'اوپتیمیسم', 'optimism', '#FF0420'],
        'PEPE'  => ['Pepe', 'پپه', 'pepe', '#3D8130'],
        'SUI'   => ['Sui', 'سویی', 'sui', '#4DA2FF'],
        'HBAR'  => ['Hedera', 'هدرا', 'hedera-hashgraph', '#000000'],
        'ICP'   => ['Internet Computer', 'آی‌سی‌پی', 'internet-computer', '#3B00B9'],
        'VET'   => ['VeChain', 'وی‌چین', 'vechain', '#15BDFF'],
        'INJ'   => ['Injective', 'اینجکتیو', 'injective-protocol', '#00A3FF'],
        'RNDR'  => ['Render', 'رندر', 'render-token', '#FF4B4B'],
        'FTM'   => ['Fantom', 'فانتوم', 'fantom', '#1969FF'],
        'AAVE'  => ['Aave', 'آوه', 'aave', '#B6509E'],
        'GRT'   => ['The Graph', 'گراف', 'the-graph', '#6F4CFF'],
        'ALGO'  => ['Algorand', 'الگورند', 'algorand', '#000000'],
        'NOT'   => ['Notcoin', 'نات‌کوین', 'notcoin', '#000000'],
        'WIF'   => ['dogwifhat', 'داگ‌ویف‌هت', 'dogwifcoin', '#D4A574'],
        'TAO'   => ['Bittensor', 'بیت‌تنسور', 'bittensor', '#0F0F0F'],
    ];

    public static function name(string $symbol): string
    {
        return self::MAP[strtoupper($symbol)][0] ?? strtoupper($symbol);
    }

    public static function nameFa(string $symbol): string
    {
        return self::MAP[strtoupper($symbol)][1] ?? strtoupper($symbol);
    }

    public static function geckoId(string $symbol): ?string
    {
        return self::MAP[strtoupper($symbol)][2] ?? null;
    }

    public static function color(string $symbol): string
    {
        $c = self::MAP[strtoupper($symbol)][3] ?? '#5B6478';
        // رنگ‌های خیلی تیره روی پس‌زمینه‌ی روشن دیده نمی‌شوند
        return in_array(strtoupper($c), ['#000000', '#1E1E1E', '#0F0F0F'], true) ? '#3A4256' : $c;
    }

    public static function isKnown(string $symbol): bool
    {
        return isset(self::MAP[strtoupper($symbol)]);
    }

    /** @return string[] */
    public static function popular(): array
    {
        return array_slice(array_keys(self::MAP), 0, 24);
    }
}
