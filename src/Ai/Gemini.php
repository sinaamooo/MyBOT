<?php

declare(strict_types=1);

namespace App\Ai;

use App\Support\Http;

/**
 * Google Gemini (AI Studio API key) as the senior analyst: it gets the engine's
 * findings, raw candles and the chart image, decides the final scenario and
 * writes the Persian explanation. The caller validates every number it returns.
 */
final class Gemini
{
    private array $models;

    /** Short description of why the last call returned null ('' after a success). */
    public string $lastError = '';

    /** Tried after the configured model when it is overloaded or unavailable. */
    private const FALLBACK = ['gemini-3.5-flash', 'gemini-3.8-flash', 'gemini-flash-latest', 'gemini-3.1-flash-lite'];

    public function __construct(private string $apiKey, string|array $model = 'gemini-3.5-flash', private string $proxy = '')
    {
        $this->models = array_values(array_unique(array_filter(array_merge((array) $model, self::FALLBACK))));
    }

    public function enabled(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param array $facts      engine output (levels, indicators, draft plan)
     * @param array $candles    ['4h' => [[o,h,l,c,v], ...], '1d' => [...]]
     * @param string|null $image path to a chart image (JPEG/PNG)
     * @return array|null decoded JSON following the schema below
     */
    public function analyze(array $facts, array $candles, ?string $image, string $brainNotes = ''): ?array
    {
        $this->lastError = '';
        if (!$this->enabled()) {
            $this->lastError = 'no api key';
            return null;
        }

        $prompt = <<<TXT
تو یک تحلیلگر ارشد و محتاط بازار کریپتو هستی که به سبک پرایس اکشن و اسمارت مانی (SMC) تحلیل می‌کنی و برای یک کانال تلگرام فارسی می‌نویسی.

ورودی‌ها:
۱) خروجی موتور تحلیل (ساختار بازار در سه تایم‌فریم، BOS/CHoCH، اوردر بلاک‌ها، FVG، زون‌های حمایت و مقاومت، نقدینگی BSL/SSL، دیوارهای اوردربوک، اندیکاتورها و یک پلن پیش‌نویس).
۲) کندل‌های خام (هر ردیف: open, high, low, close, volume؛ قدیمی به جدید).
۳) تصویر چارت همین داده‌ها (اگر ضمیمه شده باشد).

وظیفه:
- داده‌ها و تصویر را بررسی کن و سناریوی محتمل‌تر را انتخاب کن: side = long یا short.
- نقطه ورود (entry_low تا entry_high)، حد ضرر (stop) و سه تارگت را روی سطوح معنادار همین داده‌ها بگذار (اوردر بلاک، زون، FVG، نقدینگی). عدد خیالی نساز.
- حد ضرر باید پشت ساختار یا زونی باشد که سناریو را باطل می‌کند.
- برای long: stop < entry_low <= entry_high < target1 < target2 < target3. برای short برعکس.
- تارگت اول حداقل ۱ برابر ریسک فاصله داشته باشد.
- اگر شواهد ضعیف یا متناقض است، confidence را پایین بگذار (زیر ۵۰) و در summary صریح بگو.
- اگر پلن پیش‌نویس موتور منطقی است می‌توانی همان را تایید کنی.

خروجی فارسی:
- summary: حداکثر ۲ جمله (حداکثر ۲۲۰ کاراکتر)، سناریوی اصلی.
- invalidation: یک جمله کوتاه درباره شرط ابطال سناریو با ذکر قیمت.
- reasons: ۴ یا ۵ دلیل کوتاه (هر کدام حداکثر ۷۵ کاراکتر) که از داده‌ها می‌آیند.
بدون اغراق و بدون وعده سود بنویس.
TXT;
        if ($brainNotes !== '') {
            $prompt .= "\n\nسبک و قوانین اختصاصی مدیر کانال (اولویت بالاتر از قوانین عمومی، به جز قوانین اعداد):\n" . $brainNotes;
        }
        $prompt .= "\n\nخروجی موتور تحلیل:\n" . json_encode($facts, JSON_UNESCAPED_UNICODE);
        foreach ($candles as $tf => $rows) {
            $prompt .= "\n\nکندل‌های {$tf}:\n" . json_encode($rows);
        }

        $parts = [['text' => $prompt]];
        if ($image !== null && is_file($image)) {
            $mime = str_ends_with(strtolower($image), '.png') ? 'image/png' : 'image/jpeg';
            $parts[] = ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode((string) file_get_contents($image))]];
        }

        $num = ['type' => 'NUMBER'];
        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'side' => ['type' => 'STRING', 'enum' => ['long', 'short']],
                'confidence' => ['type' => 'INTEGER'],
                'entry_low' => $num,
                'entry_high' => $num,
                'stop' => $num,
                'targets' => ['type' => 'ARRAY', 'items' => $num],
                'summary' => ['type' => 'STRING'],
                'invalidation' => ['type' => 'STRING'],
                'reasons' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
            ],
            'required' => ['side', 'confidence', 'entry_low', 'entry_high', 'stop', 'targets', 'summary', 'invalidation', 'reasons'],
        ];

        $body = [
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        // Total time budget so a user never waits more than ~1.5 minutes.
        $deadline = time() + 90;
        foreach ($this->models as $model) {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $left = $deadline - time();
                if ($left < 15) {
                    app_log('gemini: time budget exhausted');
                    $this->lastError = trim($this->lastError . ' | time budget exhausted', ' |');
                    return null;
                }
                $res = Http::request('POST', "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'headers' => ['x-goog-api-key: ' . $this->apiKey],
                    'json' => $body,
                    'timeout' => min(75, $left),
                ] + ($this->proxy !== '' ? ['proxy' => $this->proxy] : []));
                // Overloaded / rate limited: wait a moment and retry once before the next model.
                if (!in_array($res['status'], [429, 500, 503], true)) {
                    break;
                }
                sleep(2);
            }
            if ($res['status'] !== 200) {
                app_log("gemini {$model} HTTP {$res['status']}: " . substr($res['body'], 0, 300) . $res['error']);
                $msg = json_decode($res['body'], true)['error']['message'] ?? ($res['error'] ?: 'no response');
                $this->lastError = "{$model}: HTTP {$res['status']} " . mb_substr((string) $msg, 0, 160);
                continue;
            }
            $data = json_decode($res['body'], true);
            $text = '';
            foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
                if (empty($part['thought'])) {
                    $text .= $part['text'] ?? '';
                }
            }
            $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
            $out = json_decode($text, true);
            if (is_array($out) && isset($out['side'], $out['stop'], $out['targets'])) {
                $out['model'] = $model;
                return $out;
            }
            app_log("gemini {$model} unparsable output: " . substr($text, 0, 300));
            $this->lastError = "{$model}: unparsable output";
        }
        return null;
    }
}
