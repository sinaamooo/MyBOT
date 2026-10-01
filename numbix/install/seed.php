<?php
/**
 * Default settings + optional demo content for a fresh Numbix install.
 */

function nbx_default_settings(string $siteName): array
{
    return [
        'site_name' => $siteName,
        'site_tagline' => 'رشد واقعی در شبکه‌های اجتماعی',
        'site_description' => 'نامبیکس؛ خرید فالوور، ممبر، لایک و بازدید واقعی برای اینستاگرام، تلگرام، یوتیوب، تیک‌تاک و… با تحویل خودکار و پشتیبانی ۲۴ ساعته.',
        'hero_text' => 'افزایش فالوور، ممبر، لایک و بازدید با کیفیت بالا؛ سفارش در چند ثانیه، شروع خودکار و پیگیری لحظه‌ای — همه در یک پنل حرفه‌ای.',
        'currency' => 'تومان',
        'default_theme' => 'light',
        'register_enabled' => '1',
        'require_mobile' => '0',
        'register_bonus' => '0',
        'min_deposit' => '10000',
        'max_deposit' => '100000000',
        'zarinpal_merchant' => '',
        'zarinpal_sandbox' => '0',
        'test_gateway' => '1',
        'test_gateway_public' => '0',
        'card_number' => '',
        'card_holder' => '',
        'card_bank' => '',
        'auto_cron' => '1',
        'auto_cron_minutes' => '5',
        'cron_last_run' => '0',
        'low_stock_default' => '500',
        'unpaid_order_hours' => '24',
        'ticket_autoclose_days' => '7',
        'home_stats' => '1',
        'home_live_orders' => '1',
        'api_enabled' => '1',
        'api_currency' => 'IRT',
        'support_phone' => '021-12345678',
        'support_email' => 'support@example.com',
        'support_hours' => 'همه روزه ۹ صبح تا ۱۲ شب',
        'footer_about' => 'نامبیکس؛ مرجع تخصصی خدمات شبکه‌های اجتماعی. افزایش ممبر، فالوور، لایک و بازدید با کیفیت بالا، تحویل خودکار و پشتیبانی واقعی.',
        'social_telegram' => 'https://t.me/',
        'social_instagram' => 'https://instagram.com/',
        'maintenance' => '0',
        'telegram_login' => '0',
    ];
}

function nbx_seed_content(): void
{
    $cats = [
        ['تلگرام', 'telegram', 'telegram', '#37BBFE', '#007DBB', 'ممبر، بازدید و ری‌اکشن کانال و گروه تلگرام'],
        ['اینستاگرام', 'instagram', 'instagram', '#F77737', '#C13584', 'فالوور، لایک، بازدید و کامنت اینستاگرام'],
        ['یوتیوب', 'youtube', 'youtube', '#FF4E45', '#C4302B', 'بازدید، سابسکرایبر و لایک یوتیوب'],
        ['تیک‌تاک', 'tiktok', 'tiktok', '#2E2E3A', '#05050A', 'لایک، فالوور و بازدید تیک‌تاک'],
        ['توییتر (X)', 'x', 'x', '#3B3B46', '#0A0A0F', 'فالوور، لایک و ریتوییت'],
        ['روبیکا', 'rubika', 'rubika', '#A855F7', '#DB2777', 'عضو کانال و بازدید روبیکا'],
        ['آپارات', 'aparat', 'aparat', '#F0457E', '#B3093F', 'بازدید و دنبال‌کننده آپارات'],
        ['اسپاتیفای', 'spotify', 'spotify', '#1ED760', '#0E8A3A', 'فالوور و پلی اسپاتیفای'],
    ];
    $catIds = [];
    foreach ($cats as $i => [$name, $slug, $icon, $c1, $c2, $desc]) {
        $catIds[$slug] = DB::insert('categories', ['name' => $name, 'slug' => $slug, 'icon' => $icon, 'color' => $c1, 'color2' => $c2, 'description' => $desc, 'sort' => $i]);
    }

    $desc = [
        'member' => "اعضای این سرویس با کیفیت بالا و ماندگاری مناسب به کانال یا گروه شما اضافه می‌شوند.\n• لینک کانال باید عمومی باشد (مثل https://t.me/channel)\n• شروع خودکار پس از پرداخت\n• در صورت انجام ناقص، مبلغ باقی‌مانده خودکار به کیف پول برمی‌گردد",
        'follower' => "فالوورهای این سرویس دارای پروفایل و پست هستند و به صورت تدریجی و طبیعی اضافه می‌شوند.\n• پیج باید در طول سفارش عمومی باشد\n• یوزرنیم یا لینک پروفایل را وارد کنید\n• تحویل خودکار و قابل پیگیری در پنل",
        'like' => "لایک‌ها از حساب‌های فعال ارسال می‌شوند و باعث افزایش تعامل و دیده‌شدن پست شما خواهند شد.\n• لینک دقیق پست را وارد کنید\n• شروع سریع و تحویل خودکار",
        'view' => "بازدیدهای این سرویس با سرعت بالا و کاملاً ایمن ثبت می‌شوند.\n• لینک ویدیو یا پست را وارد کنید\n• مناسب برای بالا بردن آمار و اکسپلور",
        'comment' => "کامنت‌های طبیعی و مرتبط با محتوای پست شما ارسال می‌شود.\n• لینک دقیق پست را وارد کنید\n• کامنت‌ها فارسی و متنوع هستند",
    ];
    // [cat, title, subtitle, type, badge, price, compare, cost, min, max, stock, alert, start, delivery, quality, guarantee, featured, rating, hint]
    $services = [
        ['telegram', 'افزایش ممبر تلگرام (واقعی)', 'ممبر واقعی و فعال با بالاترین کیفیت', 'member', 'bestseller', 39000, 45000, 18000, 100, 50000, 25000, 2000, 'فوری', '۱ تا ۲ ساعت', 'واقعی و فعال', '۳۰ روز جبران ریزش', 1, 4.9, 'https://t.me/your_channel'],
        ['telegram', 'ممبر تلگرام اقتصادی', 'مناسب برای شروع کانال‌های جدید', 'member', null, 19000, null, 8000, 500, 100000, null, 0, 'زیر ۱۰ دقیقه', '۱ تا ۶ ساعت', 'اقتصادی', 'ندارد', 0, 4.5, 'https://t.me/your_channel'],
        ['telegram', 'بازدید پست تلگرام', 'بازدید سریع برای آخرین پست‌ها', 'view', 'new', 4000, null, 1500, 500, 1000000, null, 0, 'فوری', '۱۰ دقیقه', 'پرسرعت', 'ندارد', 0, 4.8, 'https://t.me/channel/123'],
        ['telegram', 'ری‌اکشن پست تلگرام', 'ری‌اکشن مثبت و متنوع', 'like', null, 9000, null, 3500, 100, 50000, null, 0, 'فوری', '۳۰ دقیقه', 'متنوع', 'ندارد', 0, 4.7, 'https://t.me/channel/123'],
        ['instagram', 'فالوور اینستاگرام (واقعی)', 'فالوور واقعی و ایرانی با کیفیت بالا', 'follower', 'special', 49000, 59000, 22000, 100, 30000, 12000, 1500, 'زیر ۱۵ دقیقه', '۱ تا ۴ ساعت', 'واقعی با پست', '۶۰ روز جبران ریزش', 1, 4.8, 'https://instagram.com/username'],
        ['instagram', 'فالوور ایرانی پریمیوم', 'فالوور ایرانی فعال؛ مناسب پیج‌های کسب‌وکار', 'follower', 'hot', 89000, null, 45000, 100, 10000, 6000, 1000, '۱ ساعت', '۱ تا ۲ روز', 'ایرانی فعال', '۹۰ روز جبران ریزش', 0, 4.9, 'https://instagram.com/username'],
        ['instagram', 'لایک اینستاگرام', 'لایک سریع از حساب‌های فعال', 'like', 'new', 12000, null, 5000, 50, 50000, null, 0, 'فوری', '۳۰ دقیقه', 'پرسرعت', '۳۰ روز', 0, 4.8, 'https://instagram.com/p/xxxx'],
        ['instagram', 'بازدید ریلز اینستاگرام', 'افزایش بازدید ویدیو و ریلز', 'view', null, 3000, null, 1000, 1000, 5000000, null, 0, 'فوری', '۱ ساعت', 'پرسرعت', 'ندارد', 0, 4.7, 'https://instagram.com/reel/xxxx'],
        ['instagram', 'کامنت فارسی اینستاگرام', 'کامنت طبیعی و مرتبط با پست', 'comment', null, 150000, null, 70000, 10, 1000, 0, 50, '۱ ساعت', '۲ تا ۶ ساعت', 'فارسی دست‌نویس', 'ندارد', 0, 4.6, 'https://instagram.com/p/xxxx'],
        ['youtube', 'بازدید ویدیو یوتیوب', 'بازدید واقعی و با ماندگاری بالا', 'view', 'hot', 39000, null, 17000, 500, 1000000, null, 0, 'زیر ۳۰ دقیقه', '۱۲ تا ۲۴ ساعت', 'واقعی با زمان تماشا', 'ریفیل ۳۰ روزه', 1, 4.7, 'https://youtube.com/watch?v=xxxx'],
        ['youtube', 'سابسکرایبر یوتیوب', 'افزایش مشترکین کانال', 'follower', null, 290000, null, 150000, 100, 10000, 3000, 500, '۶ ساعت', '۱ تا ۳ روز', 'پایدار', '۳۰ روز جبران ریزش', 0, 4.6, 'https://youtube.com/@channel'],
        ['youtube', 'لایک یوتیوب', 'لایک ویدیو از کاربران فعال', 'like', null, 45000, null, 20000, 50, 20000, null, 0, 'فوری', '۱ تا ۳ ساعت', 'واقعی', 'ندارد', 0, 4.7, 'https://youtube.com/watch?v=xxxx'],
        ['tiktok', 'لایک تیک‌تاک', 'افزایش لایک واقعی و سریع', 'like', 'new', 29000, 35000, 12000, 100, 100000, 300, 500, 'فوری', '۱ تا ۶ ساعت', 'واقعی', '۳۰ روز', 1, 4.8, 'https://tiktok.com/@user/video/xxxx'],
        ['tiktok', 'فالوور تیک‌تاک', 'فالوور با پروفایل کامل', 'follower', null, 69000, null, 30000, 100, 50000, 9000, 1000, 'زیر ۳۰ دقیقه', '۶ تا ۱۲ ساعت', 'با پروفایل', '۳۰ روز', 0, 4.7, 'https://tiktok.com/@user'],
        ['tiktok', 'بازدید ویدیو تیک‌تاک', 'بازدید فوق سریع برای ویدیوها', 'view', null, 2000, null, 600, 1000, 10000000, null, 0, 'فوری', '۱ ساعت', 'پرسرعت', 'ندارد', 0, 4.8, 'https://tiktok.com/@user/video/xxxx'],
        ['x', 'فالوور توییتر (X)', 'فالوور واقعی و فعال', 'follower', 'hot', 99000, null, 48000, 100, 20000, 7000, 1000, '۱ ساعت', '۱ تا ۲ روز', 'واقعی', '۳۰ روز', 1, 4.9, 'https://x.com/username'],
        ['x', 'لایک توییت', 'لایک سریع برای توییت‌ها', 'like', null, 39000, null, 16000, 50, 20000, null, 0, 'فوری', '۱ ساعت', 'پرسرعت', 'ندارد', 0, 4.6, 'https://x.com/user/status/xxxx'],
        ['rubika', 'افزایش عضو روبیکا', 'عضو واقعی و فعال برای کانال روبیکا', 'member', 'special', 49000, null, 21000, 100, 30000, 15000, 2000, 'زیر ۳۰ دقیقه', '۲ تا ۸ ساعت', 'واقعی', '۱۵ روز', 1, 4.9, 'https://rubika.ir/channel'],
        ['aparat', 'بازدید ویدیو آپارات', 'افزایش بازدید با کیفیت بالا', 'view', 'new', 25000, null, 9000, 500, 500000, null, 0, 'فوری', '۲ تا ۶ ساعت', 'ایرانی', 'ندارد', 1, 4.9, 'https://aparat.com/v/xxxx'],
        ['aparat', 'دنبال‌کننده آپارات', 'افزایش دنبال‌کننده کانال آپارات', 'follower', null, 79000, null, 35000, 100, 10000, 5000, 500, '۱ ساعت', '۱ روز', 'ایرانی', '۳۰ روز', 0, 4.7, 'https://aparat.com/channel'],
        ['spotify', 'فالوور اسپاتیفای', 'فالوور واقعی و باکیفیت', 'follower', 'special', 39000, null, 15000, 100, 50000, null, 0, 'زیر ۱ ساعت', '۶ تا ۲۴ ساعت', 'واقعی', '۳۰ روز', 1, 4.9, 'https://open.spotify.com/artist/xxxx'],
        ['spotify', 'پلی اسپاتیفای', 'افزایش پلی ترک‌ها', 'view', null, 15000, null, 6000, 1000, 1000000, null, 0, '۲ ساعت', '۲ تا ۳ روز', 'پرمیوم', 'ندارد', 0, 4.6, 'https://open.spotify.com/track/xxxx'],
    ];
    foreach ($services as $i => $s) {
        [$cat, $title, $sub, $type, $badge, $price, $compare, $cost, $min, $max, $stock, $alert, $start, $delivery, $quality, $guarantee, $featured, $rating, $hint] = $s;
        DB::insert('services', [
            'category_id' => $catIds[$cat],
            'title' => $title,
            'slug' => slugify($title),
            'subtitle' => $sub,
            'description' => $desc[$type] ?? $desc['view'],
            'type' => $type,
            'badge' => $badge,
            'price' => $price,
            'compare_price' => $compare,
            'cost' => $cost,
            'min_qty' => $min,
            'max_qty' => $max,
            'stock' => $stock,
            'stock_alert' => $alert,
            'start_time' => $start,
            'delivery_time' => $delivery,
            'quality' => $quality,
            'guarantee' => $guarantee,
            'is_featured' => $featured,
            'rating' => $rating,
            'link_hint' => $hint,
            'sales_count' => random_int(20, 900),
            'views' => random_int(300, 9000),
            'sort' => $i,
        ]);
    }

    $faqs = [
        ['آیا استفاده از خدمات برای پیج یا کانال من امن است؟', 'بله. ما هرگز رمز عبور شما را نمی‌خواهیم و تنها با لینک یا آیدی عمومی، سفارش را انجام می‌دهیم. تمام سرویس‌ها با روش‌های امن و تدریجی اجرا می‌شوند.'],
        ['سفارش من چه زمانی شروع می‌شود؟', 'اکثر سرویس‌ها بلافاصله پس از پرداخت و به صورت خودکار شروع می‌شوند. زمان دقیق شروع و تحویل هر سرویس در صفحه همان سرویس درج شده است.'],
        ['اگر سفارش ناقص انجام شود چه می‌شود؟', 'در صورت لغو یا انجام ناقص سفارش، مبلغ بخش انجام‌نشده به‌صورت خودکار به کیف پول شما بازگردانده می‌شود و می‌توانید برای سفارش‌های بعدی از آن استفاده کنید.'],
        ['چطور کیف پولم را شارژ کنم؟', 'از بخش «کیف پول» در پنل کاربری، مبلغ دلخواه را وارد و از طریق درگاه بانکی پرداخت کنید. شارژ کیف پول آنی است.'],
        ['جبران ریزش یعنی چه؟', 'در سرویس‌های دارای ضمانت، اگر در مدت تعیین‌شده ریزشی رخ دهد، کافی است تیکت ثبت کنید تا تعداد ریزش‌کرده جبران شود.'],
        ['آیا می‌توانم خدمات شما را در ربات یا پنل خودم بفروشم؟', 'بله! از بخش «وب‌سرویس API» در پنل کاربری، کلید اختصاصی خود را دریافت کنید و سفارش‌ها را کاملاً خودکار ثبت و پیگیری کنید.'],
    ];
    foreach ($faqs as $i => [$q, $a]) {
        DB::insert('faqs', ['question' => $q, 'answer' => $a, 'sort' => $i]);
    }

    DB::insert('posts', ['type' => 'page', 'title' => 'درباره ما', 'slug' => 'about', 'excerpt' => 'داستان نامبیکس و ارزش‌هایی که به آن‌ها پایبندیم',
        'content' => '<p>نامبیکس با هدف ارائه ساده، سریع و مطمئن خدمات شبکه‌های اجتماعی راه‌اندازی شده است. تیم ما با تکیه بر زیرساخت خودکار و پشتیبانی واقعی، تلاش می‌کند رشد پیج و کانال شما را بی‌دردسر کند.</p><h3>چرا نامبیکس؟</h3><ul><li>تحویل خودکار و ۲۴ ساعته سفارش‌ها</li><li>بازگشت خودکار وجه در صورت انجام ناقص</li><li>پشتیبانی پاسخگو از طریق تیکت</li><li>وب‌سرویس API برای همکاران</li></ul>']);
    DB::insert('posts', ['type' => 'page', 'title' => 'قوانین و مقررات', 'slug' => 'terms', 'excerpt' => 'لطفاً پیش از ثبت سفارش، قوانین را مطالعه کنید',
        'content' => '<ol><li>مسئولیت صحت لینک وارد شده بر عهده کاربر است؛ سفارش ثبت‌شده با لینک اشتباه قابل لغو نیست.</li><li>پیج یا کانال باید در طول انجام سفارش عمومی باشد.</li><li>برای یک لینک، تا تکمیل سفارش قبلی، سفارش جدید از همان سرویس ثبت نکنید.</li><li>مبالغ کیف پول تنها برای خرید خدمات سایت قابل استفاده است.</li><li>استفاده از خدمات برای محتوای غیرقانونی ممنوع است.</li></ol>']);
    $posts = [
        ['۱۰ ترفند طلایی برای افزایش فالوور اینستاگرام', 'از انتخاب هشتگ تا زمان‌بندی پست‌ها؛ راهنمای کامل رشد ارگانیک', '<p>رشد پیج اینستاگرام ترکیبی از محتوای خوب، ثبات و کمی هوشمندی است. در این مقاله ۱۰ ترفند کاربردی را مرور می‌کنیم.</p><h3>۱. محتوای ارزشمند تولید کنید</h3><p>مخاطب برای ارزش دنبال می‌کند؛ آموزش، سرگرمی یا الهام.</p><h3>۲. زمان انتشار را بهینه کنید</h3><p>با بررسی Insights، ساعات اوج فعالیت مخاطبانتان را پیدا کنید.</p><h3>۳. از ریلز غافل نشوید</h3><p>ریلز همچنان بیشترین شانس دیده شدن در اکسپلور را دارد.</p>'],
        ['چطور یک کانال تلگرام موفق بسازیم؟', 'قدم‌به‌قدم از ایده تا هزاران عضو فعال', '<p>کانال تلگرام موفق با یک ایده مشخص شروع می‌شود. موضوع کانال را دقیق انتخاب کنید و برنامه انتشار منظم داشته باشید.</p><h3>عضو اولیه، شروع حرکت</h3><p>تعداد اعضای اولیه، اعتماد مخاطبان جدید را افزایش می‌دهد. پس از آن، کیفیت محتواست که اعضا را نگه می‌دارد.</p>'],
        ['الگوریتم یوتیوب در سال جدید؛ چه چیزی مهم است؟', 'زمان تماشا، نرخ کلیک و تعامل؛ سه رکن اصلی دیده شدن', '<p>الگوریتم یوتیوب ویدیوهایی را پیشنهاد می‌کند که مخاطب را بیشتر نگه می‌دارند. تامبنیل جذاب و عنوان دقیق، نرخ کلیک را بالا می‌برد.</p><blockquote>نکته: ۳۰ ثانیه اول ویدیو مهم‌ترین بخش برای حفظ مخاطب است.</blockquote>'],
    ];
    foreach ($posts as $i => [$t, $ex, $c]) {
        DB::insert('posts', ['type' => 'post', 'title' => $t, 'slug' => slugify($t), 'excerpt' => $ex, 'content' => $c, 'views' => random_int(120, 3400),
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 4 + 1) . ' days'))]);
    }

    DB::insert('banners', ['title' => 'رشد سریع در شبکه‌های اجتماعی', 'subtitle' => 'خدمات باکیفیت، تحویل فوری، قیمت مناسب', 'badge' => 'تخفیف ویژه',
        'button_text' => 'مشاهده همه خدمات', 'link' => 'services', 'position' => 'services', 'theme' => 'violet']);
    DB::insert('coupons', ['code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'max_discount' => 50000, 'per_user' => 1]);
}

/** Demo customers + 30 days of orders, so the admin dashboard has something to show. */
function nbx_seed_demo_orders(): void
{
    $names = [['علی', 'رضایی'], ['سارا', 'محمدی'], ['رضا', 'کریمی'], ['مینا', 'احمدی'], ['حسین', 'محمدی'], ['ندا', 'حسینی'], ['امیر', 'صادقی'], ['زهرا', 'علیزاده'], ['مهدی', 'نوری'], ['الهام', 'کاظمی']];
    $users = [];
    foreach ($names as $i => [$f, $l]) {
        $users[] = DB::insert('users', ['first_name' => $f, 'last_name' => $l, 'email' => "demo$i@example.com", 'password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
            'balance' => random_int(0, 40) * 5000, 'admin_note' => '[demo]', 'created_at' => date('Y-m-d H:i:s', strtotime('-' . random_int(5, 60) . ' days'))]);
    }
    $services = DB::all('SELECT * FROM services');
    $statuses = ['completed', 'completed', 'completed', 'completed', 'in_progress', 'processing', 'pending', 'partial', 'canceled', 'unpaid'];
    for ($d = 29; $d >= 0; $d--) {
        $n = random_int(1, 4) + intdiv(30 - $d, 6);
        for ($k = 0; $k < $n; $k++) {
            $s = $services[array_rand($services)];
            $qty = max((int)$s['min_qty'], random_int(1, 10) * 500);
            $qty = min($qty, (int)$s['max_qty']);
            $price = (int)ceil($s['price'] * $qty / 1000);
            $status = $d < 2 ? ['pending', 'processing', 'in_progress', 'completed'][random_int(0, 3)] : $statuses[array_rand($statuses)];
            $uid = $users[array_rand($users)];
            $at = date('Y-m-d H:i:s', strtotime("-$d days") - random_int(0, 80000));
            $refunded = $status === 'canceled' ? $price : ($status === 'partial' ? (int)floor($price / 3) : 0);
            DB::insert('orders', [
                'user_id' => $uid, 'service_id' => $s['id'], 'link' => 'https://example.com/demo' . random_int(100, 999), 'quantity' => $qty,
                'price' => $price, 'cost' => (int)ceil($s['cost'] * $qty / 1000), 'status' => $status, 'refunded' => $refunded,
                'start_count' => $status !== 'unpaid' ? random_int(100, 20000) : null, 'remains' => $status === 'partial' ? intdiv($qty, 3) : ($status === 'completed' ? 0 : null),
                'completed_at' => $status === 'completed' ? $at : null, 'created_at' => $at, 'source' => 'web',
            ]);
            if ($status !== 'unpaid') {
                DB::query('UPDATE users SET total_spent = total_spent + ? WHERE id = ?', [$price - $refunded, $uid]);
            }
        }
        if (random_int(0, 1)) {
            $uid = $users[array_rand($users)];
            $amt = random_int(2, 30) * 10000;
            DB::insert('payments', ['user_id' => $uid, 'amount' => $amt, 'gateway' => 'zarinpal', 'purpose' => 'wallet', 'status' => 'paid', 'ref_id' => (string)random_int(100000000, 999999999),
                'paid_at' => date('Y-m-d H:i:s', strtotime("-$d days")), 'created_at' => date('Y-m-d H:i:s', strtotime("-$d days"))]);
        }
    }
    $t = DB::insert('tickets', ['user_id' => $users[0], 'subject' => 'سوال درباره زمان شروع سفارش', 'department' => 'support', 'status' => 'open']);
    DB::insert('ticket_messages', ['ticket_id' => $t, 'user_id' => $users[0], 'message' => "سلام، سفارش ممبر تلگرام من کی شروع می‌شه؟\nممنون"]);
    DB::insert('payments', ['user_id' => $users[1], 'amount' => 150000, 'gateway' => 'card', 'purpose' => 'wallet', 'status' => 'review', 'tracking_code' => '458712', 'card_pan' => '4321']);
}
