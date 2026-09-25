<?php
declare(strict_types=1);

namespace Nikto\Data;

final class EventTranslator
{
    private const PREFIX = [
        'german'        => 'آلمان',
        'french'        => 'فرانسه',
        'italian'       => 'ایتالیا',
        'spanish'       => 'اسپانیا',
        'chinese'       => 'چین',
        'japanese'      => 'ژاپن',
        'flash'         => 'اولیه',
        'prelim'        => 'مقدماتی',
        'preliminary'   => 'مقدماتی',
        'final'         => 'نهایی',
        'revised'       => 'بازنگری‌شده',
        'advance'       => 'پیش‌نگر',
        'core'          => 'هسته',
        'annual'        => 'سالانه',
        'monthly'       => 'ماهانه',
        'weekly'        => 'هفتگی',
    ];

    private const BANKS = [
        'fed'    => 'فدرال رزرو آمریکا',
        'fomc'   => 'کمیته بازار آزاد فدرال (FOMC)',
        'ecb'    => 'بانک مرکزی اروپا',
        'boe'    => 'بانک مرکزی انگلستان',
        'boj'    => 'بانک مرکزی ژاپن',
        'boc'    => 'بانک مرکزی کانادا',
        'rba'    => 'بانک مرکزی استرالیا',
        'rbnz'   => 'بانک مرکزی نیوزیلند',
        'snb'    => 'بانک مرکزی سوئیس',
        'pboc'   => 'بانک مرکزی چین',
        'riksbank' => 'بانک مرکزی سوئد',
        'norges' => 'بانک مرکزی نروژ',
    ];

    private const DICTIONARY = [
        '/^adp non-?farm employment change$/i'      => 'گزارش تغییرات اشتغال بخش خصوصی و غیرکشاورزی — ADP',
        '/^non-?farm employment change$/i'          => 'تغییرات اشتغال بخش غیرکشاورزی (NFP)',
        '/^non-?farm payrolls?$/i'                  => 'اشتغال بخش غیرکشاورزی (NFP)',
        '/^unemployment rate$/i'                    => 'نرخ بیکاری',
        '/^unemployment claims$/i'                  => 'مدعیان بیکاری هفتگی',
        '/^initial jobless claims$/i'               => 'مدعیان اولیه بیکاری',
        '/^continuing jobless claims$/i'            => 'مدعیان مستمر بیکاری',
        '/^claimant count change$/i'                => 'تغییر شمار متقاضیان مزایای بیکاری',
        '/^employment change$/i'                    => 'تغییرات اشتغال',
        '/^average (hourly|weekly) earnings/i'      => 'میانگین درآمد ساعتی',
        '/^jolts job openings$/i'                   => 'فرصت‌های شغلی JOLTS',
        '/^challenger job cuts/i'                   => 'گزارش تعدیل نیروی چلنجر',
        '/^labou?r cost/i'                          => 'هزینه نیروی کار',
        '/^participation rate$/i'                   => 'نرخ مشارکت اقتصادی',

        '/^core cpi/i'                              => 'شاخص قیمت مصرف‌کننده هسته (CPI هسته)',
        '/^cpi flash estimate/i'                    => 'برآورد اولیه شاخص قیمت مصرف‌کننده',
        '/^cpi/i'                                   => 'شاخص قیمت مصرف‌کننده (CPI)',
        '/^core ppi/i'                              => 'شاخص قیمت تولیدکننده هسته (PPI هسته)',
        '/^ppi/i'                                   => 'شاخص قیمت تولیدکننده (PPI)',
        '/^core pce price index/i'                  => 'شاخص قیمت هزینه مصرف شخصی هسته (Core PCE)',
        '/^pce price index/i'                       => 'شاخص قیمت هزینه مصرف شخصی (PCE)',
        '/^rpi/i'                                   => 'شاخص قیمت خرده‌فروشی (RPI)',
        '/^hpi/i'                                   => 'شاخص قیمت مسکن (HPI)',
        '/^import prices/i'                         => 'شاخص قیمت واردات',
        '/^inflation (rate|expectations)/i'         => 'نرخ/انتظارات تورم',
        '/^wpi/i'                                   => 'شاخص قیمت عمده‌فروشی',

        '/^ism (manufacturing|services|non-?manufacturing) pmi$/i' => 'شاخص PMI مؤسسه ISM',
        '/^ism (manufacturing|services|non-?manufacturing) prices$/i' => 'شاخص قیمت‌های ISM',
        '/^manufacturing pmi$/i'                    => 'شاخص PMI بخش تولید',
        '/^services pmi$/i'                         => 'شاخص PMI بخش خدماتی',
        '/^composite pmi$/i'                        => 'شاخص PMI ترکیبی',
        '/^construction pmi$/i'                     => 'شاخص PMI بخش ساخت‌وساز',
        '/^ivey pmi$/i'                             => 'شاخص مدیران خرید Ivey',
        '/^caixin (manufacturing|services) pmi$/i'  => 'شاخص PMI کایشین',
        '/^industrial production/i'                 => 'تولیدات صنعتی',
        '/^manufacturing production/i'              => 'تولیدات کارخانه‌ای',
        '/^factory orders/i'                        => 'سفارش‌های کارخانه‌ای',
        '/^core durable goods orders/i'             => 'سفارش‌های کالاهای بادوام هسته',
        '/^durable goods orders/i'                  => 'سفارش‌های کالاهای بادوام',
        '/^empire state manufacturing index$/i'     => 'شاخص تولید ایالت نیویورک (Empire State)',
        '/^philly fed manufacturing index$/i'       => 'شاخص تولید فیلادلفیا (Philly Fed)',
        '/^richmond manufacturing index$/i'         => 'شاخص تولید ریچموند',
        '/^chicago pmi$/i'                          => 'شاخص PMI شیکاگو',
        '/^capacity utilization rate$/i'            => 'نرخ بهره‌وری ظرفیت تولید',
        '/^zew economic sentiment$/i'               => 'شاخص انتظارات اقتصادی ZEW',
        '/^ifo business climate$/i'                 => 'شاخص فضای کسب‌وکار IFO',
        '/^sentix investor confidence$/i'           => 'اعتماد سرمایه‌گذاران Sentix',
        '/^tankan/i'                                => 'نظرسنجی تانکان ژاپن',

        '/^core retail sales/i'                     => 'خرده‌فروشی هسته',
        '/^retail sales/i'                          => 'خرده‌فروشی',
        '/^cb consumer confidence$/i'               => 'اعتماد مصرف‌کننده کنفرانس بورد (CB)',
        '/^(prelim |revised )?uom consumer sentiment$/i' => 'شاخص اعتماد مصرف‌کننده میشیگان',
        '/^(prelim |revised )?uom inflation expectations$/i' => 'انتظارات تورمی میشیگان',
        '/^consumer confidence$/i'                  => 'شاخص اعتماد مصرف‌کننده',
        '/^consumer credit/i'                       => 'اعتبار مصرف‌کننده',
        '/^personal (spending|income)/i'            => 'درآمد و مخارج شخصی',

        '/^building permits$/i'                     => 'مجوزهای ساخت‌وساز',
        '/^housing starts$/i'                       => 'شروع ساخت مسکن',
        '/^new home sales$/i'                       => 'فروش خانه‌های نوساز',
        '/^existing home sales$/i'                  => 'فروش خانه‌های موجود',
        '/^pending home sales/i'                    => 'فروش‌های در انتظار مسکن',
        '/^s&p\/cs composite/i'                     => 'شاخص قیمت مسکن کیس-شیلر',

        '/gdp( (q\/q|m\/m|y\/y))?$/i'               => 'تولید ناخالص داخلی (GDP)',
        '/^trade balance$/i'                        => 'تراز تجاری',
        '/^current account$/i'                      => 'حساب جاری',
        '/^goods trade balance$/i'                  => 'تراز تجاری کالا',
        '/^exports?$/i'                             => 'صادرات',
        '/^imports?$/i'                             => 'واردات',
        '/^business inventories/i'                  => 'موجودی انبار کسب‌وکارها',
        '/^wholesale inventories/i'                 => 'موجودی انبار عمده‌فروشی',
        '/^federal budget balance$/i'               => 'تراز بودجه فدرال',

        '/^crude oil inventories$/i'                => 'گزارش هفتگی ذخایر نفت خام — اداره اطلاعات انرژی آمریکا (EIA)',
        '/^api weekly (statistical bulletin|crude oil stock)/i' => 'گزارش هفتگی ذخایر نفت خام — مؤسسه فرآورده‌های نفتی آمریکا (API)',
        '/^cushing crude oil inventories$/i'        => 'ذخایر نفت خام Cushing — اداره اطلاعات انرژی آمریکا (EIA)',
        '/^natural gas storage$/i'                  => 'ذخایر گاز طبیعی',
        '/^gasoline inventories$/i'                 => 'ذخایر بنزین',
        '/^distillate (fuel )?inventories$/i'       => 'ذخایر سوخت تقطیری',
        '/^baker hughes/i'                          => 'شمار دکل‌های نفتی بیکر هیوز',
        '/^opec/i'                                  => 'نشست/گزارش اوپک',

        '/^federal funds rate$/i'                   => 'نرخ بهره فدرال رزرو آمریکا',
        '/^fomc statement$/i'                       => 'بیانیه کمیته بازار آزاد فدرال (FOMC)',
        '/^fomc press conference$/i'                => 'کنفرانس خبری فدرال رزرو',
        '/^fomc meeting minutes$/i'                 => 'صورت‌جلسه نشست فدرال رزرو',
        '/^fomc economic projections$/i'            => 'پیش‌بینی‌های اقتصادی فدرال رزرو',
        '/^main refinancing rate$/i'                => 'نرخ بهره بانک مرکزی اروپا',
        '/^ecb press conference$/i'                 => 'کنفرانس خبری بانک مرکزی اروپا',
        '/^monetary policy statement$/i'            => 'بیانیه سیاست پولی',
        '/^official bank rate$/i'                   => 'نرخ بهره بانک مرکزی انگلستان',
        '/^official cash rate$/i'                   => 'نرخ بهره بانک مرکزی',
        '/^cash rate$/i'                            => 'نرخ بهره بانک مرکزی استرالیا',
        '/^overnight rate$/i'                       => 'نرخ بهره شبانه بانک مرکزی کانادا',
        '/^policy rate$/i'                          => 'نرخ بهره سیاستی',
        '/^interest rate decision$/i'               => 'تصمیم نرخ بهره',
        '/^(mpc|monetary policy) (meeting )?minutes$/i' => 'صورت‌جلسه سیاست پولی',
        '/^mpc official bank rate votes$/i'         => 'آرای اعضای کمیته سیاست پولی انگلستان',
        '/^financial stability report$/i'           => 'گزارش ثبات مالی بانک مرکزی',
        '/^(monetary policy|rate statement)$/i'     => 'بیانیه سیاست پولی',
        '/^beige book$/i'                           => 'گزارش بژ بوک فدرال رزرو',
        '/^bank (of )?(japan|england|canada) (outlook|report)/i' => 'گزارش چشم‌انداز بانک مرکزی',

        '/(\d+)-?(y|yr|year) bond auction$/i'       => 'حراج اوراق قرضه دولتی',
        '/^(\d+)-?(m|month) bill auction$/i'        => 'حراج اسناد خزانه',
        '/^treasury currency report$/i'             => 'گزارش ارزی خزانه‌داری آمریکا',

        '/bank holiday$/i'                          => 'تعطیلی بانکی',
        '/^constitution day$/i'                     => 'روز قانون اساسی',
        '/^(labou?r|may) day$/i'                    => 'روز کارگر',
        '/^independence day$/i'                     => 'روز استقلال',
        '/^national day$/i'                         => 'روز ملی',
        '/^christmas day$/i'                        => 'تعطیلات کریسمس',
        '/^new year\'?s? day$/i'                    => 'روز اول سال نو میلادی',
        '/^good friday$/i'                          => 'جمعه نیک (تعطیل)',
        '/^easter monday$/i'                        => 'دوشنبه عید پاک (تعطیل)',
        '/^thanksgiving day$/i'                     => 'روز شکرگزاری',
        '/^memorial day$/i'                         => 'روز یادبود',
        '/^boxing day$/i'                           => 'روز باکسینگ',
        '/^greenery day$/i'                         => 'روز سبز (ژاپن)',
        '/^children\'?s day$/i'                     => 'روز کودک',
        '/^showa day$/i'                            => 'روز شووا (ژاپن)',
        '/^victoria day$/i'                         => 'روز ویکتوریا (کانادا)',
        '/^golden week/i'                           => 'تعطیلات هفته طلایی ژاپن',
    ];

    private const MONTHS = [
        'jan' => 'ژانویه', 'feb' => 'فوریه', 'mar' => 'مارس', 'apr' => 'آپریل',
        'may' => 'مِی', 'jun' => 'ژوئن', 'jul' => 'جولای', 'aug' => 'آگوست',
        'sep' => 'سپتامبر', 'oct' => 'اکتبر', 'nov' => 'نوامبر', 'dec' => 'دسامبر',
    ];

    private const QUALIFIERS = [
        'm/m'  => 'ماهانه',
        'y/y'  => 'سالانه',
        'q/q'  => 'فصلی',
        'w/w'  => 'هفتگی',
        'q1'   => 'فصل اول',
        'q2'   => 'فصل دوم',
        'q3'   => 'فصل سوم',
        'q4'   => 'فصل چهارم',
    ];

    public static function translate(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        if ($title === '') {
            return '';
        }

        if (preg_match('/^(.*?)\s+speaks$/i', $title, $m)) {
            return 'سخنرانی ' . self::speaker(trim($m[1]));
        }
        if (preg_match('/^(.*?)\s+(testifies|testimony)$/i', $title, $m)) {
            return 'اظهارات ' . self::speaker(trim($m[1]));
        }

        $qualifiers = [];
        $core = $title;

        if (preg_match('/\(([^)]*)\)\s*$/', $core, $m)) {
            $inner = trim($m[1]);
            $qualifiers[] = self::qualifier($inner);
            $core = trim(substr($core, 0, (int) strpos($core, '(' . $m[1] . ')')));
        }
        if (preg_match('/\b(m\/m|y\/y|q\/q|w\/w)\b/i', $core, $m)) {
            $qualifiers[] = self::QUALIFIERS[strtolower($m[1])] ?? $m[1];
            $core = trim(str_ireplace($m[1], '', $core));
        }

        $translated = self::lookup($core);
        if ($translated === null) {
            if (preg_match('/\b(day|holiday|festival|observed)\b/i', $core)) {
                return 'تعطیل رسمی — ' . $core;
            }
            return $title;
        }

        $qualifiers = array_values(array_filter($qualifiers));
        if ($qualifiers !== []) {
            $translated .= ' (' . implode(' | ', $qualifiers) . ')';
        }

        return $translated;
    }

    private static function lookup(string $core): ?string
    {
        $core = trim($core);
        if ($core === '') {
            return null;
        }

        foreach (self::DICTIONARY as $pattern => $fa) {
            if (preg_match($pattern, $core)) {
                return self::withPrefix($core, $fa);
            }
        }

        $words = preg_split('/\s+/', $core) ?: [];
        if (count($words) > 1) {
            $first = strtolower($words[0]);
            if (isset(self::PREFIX[$first])) {
                $rest = implode(' ', array_slice($words, 1));
                $inner = self::lookup($rest);
                if ($inner !== null) {
                    return self::PREFIX[$first] . ' — ' . $inner;
                }
            }
        }

        return null;
    }

    private static function withPrefix(string $core, string $fa): string
    {
        $prefixes = [];
        foreach (self::PREFIX as $en => $faPrefix) {
            if (preg_match('/^' . preg_quote($en, '/') . '\b/i', $core) && !str_contains($fa, $faPrefix)) {
                $prefixes[] = $faPrefix;
            }
        }
        if ($prefixes === []) {
            return $fa;
        }

        return implode(' ', array_unique($prefixes)) . ' — ' . $fa;
    }

    private static function speaker(string $who): string
    {
        $who = trim($who);
        $person = '';
        if (preg_match('/\b(gov|governor|chair|chairman|president|member|pres)\b\.?\s+([A-Z][A-Za-z\'\-]+)/i', $who, $m)) {
            $person = $m[2];
        } elseif (preg_match('/([A-Z][A-Za-z\'\-]+)$/', $who, $m)) {
            $person = $m[1];
        }

        $bankFa = '';
        foreach (self::BANKS as $abbr => $fa) {
            if (preg_match('/\b' . preg_quote($abbr, '/') . '\b/i', $who)) {
                $bankFa = $fa;
                break;
            }
        }

        $role = 'مقام';
        if (preg_match('/\b(gov|governor)\b/i', $who)) {
            $role = 'رئیس';
        } elseif (preg_match('/\b(chair|chairman)\b/i', $who)) {
            $role = 'رئیس';
        } elseif (preg_match('/\bpresident|pres\b/i', $who)) {
            $role = 'رئیس';
        } elseif (preg_match('/\bmember\b/i', $who)) {
            $role = 'عضو';
        } elseif (preg_match('/\btreasury secretary\b/i', $who)) {
            $role = 'وزیر خزانه‌داری';
        }

        $out = $role . ' ' . ($bankFa !== '' ? $bankFa : 'نهاد اقتصادی');
        if ($person !== '') {
            $out .= ' — ' . $person;
        }

        return $out;
    }

    private static function qualifier(string $inner): string
    {
        $key = strtolower(trim($inner));
        if (isset(self::QUALIFIERS[$key])) {
            return self::QUALIFIERS[$key];
        }
        foreach (self::MONTHS as $abbr => $fa) {
            if (str_starts_with($key, $abbr)) {
                return $fa;
            }
        }
        if (preg_match('/^q([1-4])$/', $key, $m)) {
            return self::QUALIFIERS['q' . $m[1]] ?? $inner;
        }

        return $inner;
    }
}
