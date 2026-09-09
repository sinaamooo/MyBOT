# Vazirmatn

فونت وزیرمتن، ساخته صابر راستی‌کردار — https://github.com/rastikerdar/vazirmatn

این فونت با لایسنس **SIL Open Font License 1.1** منتشر شده (متن کامل در `OFL.txt`).
همراه پروژه قرار داده شده تا کارت‌های سیگنال بدون هیچ نصب اضافه‌ای روی هاست،
متن فارسی را مستقیماً روی تصویر رندر کنند.

`card.php` به‌صورت پیش‌فرض `Vazirmatn-Regular.ttf` را برای برچسب‌ها و
`Vazirmatn-Bold.ttf` را برای اعداد و تیترها استفاده می‌کند. اگر فونت دیگری
می‌خواهید، مسیرش را در `env.php` بنویسید:

    'CARD_FONT_PATH'      => __DIR__ . '/fonts/YourFont-Regular.ttf',
    'CARD_FONT_PATH_BOLD' => __DIR__ . '/fonts/YourFont-Bold.ttf',

اگر این پوشه را حذف کنید یا FreeType روی سرور نباشد، کارت‌ها با فونت برداری
داخلی `card.php` و برچسب‌های لاتین رندر می‌شوند — چیزی از کار نمی‌افتد.
