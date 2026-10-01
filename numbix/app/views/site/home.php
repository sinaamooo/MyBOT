<?php
$brandsRow = [
    ['instagram', '#F58529', '#C13584'], ['telegram', '#37BBFE', '#007DBB'], ['youtube', '#FF4E45', '#C4302B'],
    ['tiktok', '#25F4EE', '#FE2C55'], ['x', '#3A3A3A', '#000000'], ['spotify', '#1ED760', '#0E8A3A'],
    ['aparat', '#F0457E', '#B3093F'], ['rubika', '#A855F7', '#DB2777'], ['twitch', '#A970FF', '#6441A5'],
    ['facebook', '#1877F2', '#0B4FB3'], ['threads', '#3A3A3A', '#000'], ['soundcloud', '#FF7700', '#FF3300'],
];
$heroTags = array_slice($categories, 0, 4);
?>
<section class="dark-zone hero">
  <div class="aurora"><div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div><div class="grid-bg"></div><div class="noise"></div></div>
  <div class="container hero-grid">
    <div>
      <div class="hero-pill reveal"><span class="tag">جدید</span> تحویل خودکار و ۲۴ ساعته سفارش‌ها <?= icon('arrow-left') ?></div>
      <h1 class="reveal" style="--d:.08s">
        رشد واقعی در<br>
        <span class="rotator">
          <span class="on">اینستاگرام</span><span>تلگرام</span><span>یوتیوب</span><span>تیک‌تاک</span><span>شبکه‌های اجتماعی</span>
        </span>
      </h1>
      <p class="lead reveal" style="--d:.16s"><?= e(setting('hero_text', 'افزایش فالوور، ممبر، لایک و بازدید با کیفیت بالا؛ سفارش در چند ثانیه، شروع خودکار و پیگیری لحظه‌ای — همه در یک پنل حرفه‌ای.')) ?></p>
      <div class="hero-search reveal" style="--d:.24s">
        <form action="<?= url('services') ?>" method="get" role="search">
          <?= icon('search', 'i-s') ?>
          <input type="search" name="q" placeholder="جستجوی خدمات، مثلاً ممبر تلگرام…" data-search autocomplete="off" aria-label="جستجوی خدمات">
          <button class="btn btn-primary"><span>جستجو</span></button>
        </form>
        <div class="search-suggest"></div>
      </div>
      <?php if ($heroTags): ?>
        <div class="hero-tags reveal" style="--d:.3s">
          <span>پرطرفدار:</span>
          <?php foreach ($heroTags as $t): ?><a href="<?= url('services/' . $t['slug']) ?>"><?= e($t['name']) ?></a><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="hero-feats reveal" style="--d:.36s">
        <div class="hero-feat"><span class="ic"><?= icon('zap') ?></span><div><b>تحویل سریع</b><small>شروع در کمترین زمان</small></div></div>
        <div class="hero-feat"><span class="ic"><?= icon('shield') ?></span><div><b>کیفیت تضمینی</b><small>با ضمانت جبران ریزش</small></div></div>
        <div class="hero-feat"><span class="ic"><?= icon('headset') ?></span><div><b>پشتیبانی ۲۴ ساعته</b><small>همیشه کنار شما</small></div></div>
      </div>
    </div>

    <div class="hero-visual" aria-hidden="true">
      <div class="hv-glow"></div>
      <div class="hv-ring r1"></div>
      <div class="hv-ring r2"></div>
      <div class="phone">
        <div class="phone-screen">
          <div class="ph-profile">
            <div class="ph-avatar"><span><?= icon('user') ?></span></div>
            <div class="ph-name">@your.brand</div>
          </div>
          <div class="ph-stats">
            <div class="up"><b data-count="12840">۰</b><small>فالوور</small></div>
            <div><b data-count="842">۰</b><small>پست</small></div>
            <div class="up"><b data-count="98" data-suffix="%">۰</b><small>تعامل</small></div>
          </div>
          <div class="ph-chart">
            <?php foreach ([35, 50, 42, 64, 58, 76, 70, 88, 82, 100] as $i => $h): ?><i style="height:<?= $h ?>%;animation-delay:-<?= $i * .25 ?>s"></i><?php endforeach; ?>
          </div>
          <div class="ph-row"><?= brand_tile(['icon' => 'instagram', 'color' => '#F58529', 'color2' => '#C13584'], 'sm') ?><div><b>۵,۰۰۰ فالوور</b><small>تکمیل شد</small></div><span class="ok"><?= icon('check') ?></span></div>
          <div class="ph-row"><?= brand_tile(['icon' => 'telegram', 'color' => '#37BBFE', 'color2' => '#007DBB'], 'sm') ?><div><b>۱۰,۰۰۰ ممبر</b><small>در حال انجام</small></div><span class="ok"><?= icon('check') ?></span></div>
        </div>
      </div>
      <div class="float-tile t1"><?= brand_tile(['icon' => 'instagram', 'color' => '#F58529', 'color2' => '#C13584'], 'xl') ?></div>
      <div class="float-tile t4"><?= brand_tile(['icon' => 'telegram', 'color' => '#37BBFE', 'color2' => '#007DBB'], 'lg') ?></div>
      <div class="float-tile t3"><?= brand_tile(['icon' => 'youtube', 'color' => '#FF4E45', 'color2' => '#C4302B'], 'lg') ?></div>
      <div class="float-tile t5"><?= brand_tile(['icon' => 'tiktok', 'color' => '#2B2B2B', 'color2' => '#000000'], 'lg') ?></div>
      <div class="float-card c1"><span class="fc-ic"><?= icon('user-plus') ?></span><div><b class="ltr">+۹۹۹</b><small>فالوور جدید</small></div></div>
      <div class="float-card c2"><span class="fc-ic"><?= icon('heart') ?></span><div><b>۲۴K</b><small>لایک امروز</small></div></div>
      <div class="float-card c3"><span class="fc-ic"><?= icon('trending-up') ?></span><div><b>٪۳۲۰</b><small>رشد پیج</small></div></div>
    </div>
  </div>
</section>

<?php if ($categories): ?>
<section class="cat-strip">
  <div class="container">
    <div class="inner reveal">
      <a href="<?= url('services') ?>" class="cat-tile active" style="--c1:#6C4CF1">
        <span class="btile btile-lg" style="--c1:#8B5CF6;--c2:#4F46E5"><?= icon('grid') ?></span>
        <b>همه خدمات</b><small><?= num(array_sum(array_column($categories, 'services_count'))) ?> سرویس</small>
      </a>
      <?php foreach ($categories as $c): ?>
        <a href="<?= url('services/' . $c['slug']) ?>" class="cat-tile" style="--c1:<?= e($c['color']) ?>">
          <?= brand_tile($c, 'lg') ?>
          <b><?= e($c['name']) ?></b><small><?= num($c['services_count']) ?> سرویس</small>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="container">
  <div class="marquee" aria-hidden="true">
    <div class="marquee-track">
      <?php for ($k = 0; $k < 2; $k++): foreach ($brandsRow as [$b]): ?>
        <span class="marquee-item"><?= brand($b) ?><?= e(ucfirst($b === 'x' ? 'X (Twitter)' : $b)) ?></span>
      <?php endforeach; endfor; ?>
    </div>
  </div>
</div>

<?php if ($popular): ?>
<section class="section" style="padding-top:40px">
  <div class="container">
    <div class="sec-head">
      <div>
        <span class="sec-kicker"><?= icon('flame') ?> پرفروش‌های این هفته</span>
        <h2 class="sec-title">محبوب‌ترین خدمات</h2>
        <p class="sec-sub">سرویس‌هایی که بیشترین سفارش و رضایت را داشته‌اند.</p>
      </div>
      <a href="<?= url('services') ?>" class="btn btn-soft">مشاهده همه خدمات <?= icon('arrow-left') ?></a>
    </div>
    <div class="svc-grid">
      <?php foreach ($popular as $s): ?><?= partial('service_card', ['s' => $s, 'favs' => $favs]) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="background:linear-gradient(180deg, transparent, var(--bg-2) 40%, transparent)">
  <div class="container">
    <div class="sec-head center">
      <span class="sec-kicker"><?= icon('sparkles') ?> چرا <?= e(site_name()) ?>؟</span>
      <h2 class="sec-title">همه چیز برای یک رشد <span class="gtext-p">بی‌دردسر</span></h2>
      <p class="sec-sub">زیرساختی خودکار و هوشمند که سفارش شما را از لحظه ثبت تا تحویل نهایی مدیریت می‌کند.</p>
    </div>
    <div class="grid g-3">
      <?php
      $feats = [
          ['rocket', 'شروع خودکار سفارش', 'سفارش‌ها بلافاصله پس از پرداخت به صورت خودکار پردازش و آغاز می‌شوند؛ بدون انتظار.', '#A78BFA', '#6C4CF1'],
          ['shield', 'پرداخت امن و آنی', 'شارژ کیف پول با درگاه بانکی معتبر و پرداخت سفارش‌ها تنها با یک کلیک.', '#38BDF8', '#2563EB'],
          ['repeat', 'بازگشت وجه خودکار', 'اگر سفارشی ناقص انجام شود یا لغو گردد، مبلغ آن خودکار به کیف پولتان برمی‌گردد.', '#34D399', '#059669'],
          ['activity', 'پیگیری لحظه‌ای', 'وضعیت، تعداد شروع و باقی‌مانده هر سفارش را به‌صورت زنده در پنل ببینید.', '#FBBF24', '#EA580C'],
          ['code', 'وب‌سرویس برای همکاران', 'با API استاندارد، خدمات ما را مستقیم در ربات یا پنل خودتان بفروشید.', '#F472B6', '#DB2777'],
          ['headset', 'پشتیبانی واقعی', 'تیم پشتیبانی از طریق تیکت و پیام‌رسان‌ها، هر روز هفته پاسخگوی شماست.', '#22D3EE', '#0891B2'],
      ];
      foreach ($feats as $i => [$ic, $t, $d, $c1, $c2]): ?>
        <div class="feat-card reveal" style="--d:<?= $i * .06 ?>s;--fc:<?= $c1 ?>;--fc2:<?= $c2 ?>">
          <span class="f-num"><?= fa(str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)) ?></span>
          <div class="f-ic"><?= icon($ic) ?></div>
          <h3><?= $t ?></h3>
          <p><?= $d ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="sec-head center">
      <span class="sec-kicker"><?= icon('target') ?> فقط ۴ قدم</span>
      <h2 class="sec-title">سفارش در کمتر از یک دقیقه</h2>
    </div>
    <div class="grid g-4 steps">
      <?php foreach ([
          ['grid', 'انتخاب سرویس', 'از بین ده‌ها سرویس متنوع، گزینه مناسب خود را انتخاب کنید.'],
          ['link', 'وارد کردن لینک', 'لینک پست یا آیدی پیج و تعداد مورد نظر را وارد کنید.'],
          ['card', 'پرداخت امن', 'از کیف پول یا درگاه بانکی، سفارش را پرداخت کنید.'],
          ['trending-up', 'تحویل و رشد', 'سفارش خودکار شروع می‌شود؛ رشد پیج خود را تماشا کنید.'],
      ] as $i => [$ic, $t, $d]): ?>
        <div class="step reveal" style="--d:<?= $i * .08 ?>s"><div class="s-ic"><?= icon($ic) ?></div><h4><?= $t ?></h4><p><?= $d ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (setting('home_stats', '1') === '1'): ?>
<section class="section-sm">
  <div class="container">
    <div class="dark-zone stats-band">
      <div class="aurora"><div class="blob b1" style="opacity:.45"></div><div class="blob b3"></div><div class="grid-bg"></div></div>
      <div class="grid <?= $live ? 'g-2' : '' ?>" style="align-items:center;gap:40px">
        <div>
          <span class="sec-kicker" style="background:rgba(255,255,255,.08);color:#C4B5FD"><?= icon('award') ?> آمار <?= e(site_name()) ?></span>
          <h2 class="sec-title" style="color:#fff">اعتماد هزاران کاربر، <span class="gtext">در اعداد</span></h2>
          <div class="grid g-2 mt-3" style="gap:28px">
            <div class="stat-big"><b data-count="<?= $stats['orders'] ?>">۰</b><span>سفارش ثبت شده</span></div>
            <div class="stat-big"><b data-count="<?= $stats['completed'] ?>">۰</b><span>سفارش تکمیل شده</span></div>
            <div class="stat-big"><b data-count="<?= $stats['users'] ?>">۰</b><span>کاربر فعال</span></div>
            <div class="stat-big"><b data-count="<?= $stats['services'] ?>">۰</b><span>سرویس فعال</span></div>
          </div>
        </div>
        <?php if ($live): ?>
          <div>
            <div class="row-between mb-2"><b style="font-size:17px">آخرین سفارش‌ها</b><span class="live"><i></i> زنده</span></div>
            <div class="ticker">
              <?php foreach ($live as $i => $o): ?>
                <div class="ticker-item" style="background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.08);animation-delay:<?= $i * .1 ?>s">
                  <?= brand_tile($o, 'sm') ?>
                  <div><b><?= e($o['title']) ?></b><small><?= e(mb_substr($o['first_name'] ?: 'کاربر', 0, 1)) ?>*** — <?= num($o['quantity']) ?> عدد</small></div>
                  <span class="t-time"><?= time_ago($o['created_at']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="grid g-2" style="align-items:center;gap:48px">
      <div class="reveal">
        <span class="sec-kicker"><?= icon('code') ?> برای همکاران و ربات‌ها</span>
        <h2 class="sec-title">وب‌سرویس API استاندارد</h2>
        <p class="sec-sub mb-2">پنل یا ربات تلگرامی خودتان را به <?= e(site_name()) ?> وصل کنید و سفارش‌ها را کاملاً خودکار ثبت و پیگیری کنید. سازگار با استاندارد رایج پنل‌های SMM.</p>
        <div class="row" style="flex-wrap:wrap">
          <span class="chip"><?= icon('check') ?> ثبت سفارش</span><span class="chip"><?= icon('check') ?> استعلام وضعیت</span>
          <span class="chip"><?= icon('check') ?> لیست سرویس‌ها</span><span class="chip"><?= icon('check') ?> موجودی حساب</span>
        </div>
        <a href="<?= url(auth() ? 'dashboard/api' : 'register') ?>" class="btn btn-primary mt-3"><?= icon('key') ?> دریافت کلید API</a>
      </div>
      <div class="reveal" style="--d:.1s">
        <div class="code-box" style="font-family:ui-monospace,Menlo,Consolas,monospace;direction:ltr;text-align:left;background:#0B0C22;color:#C4B5FD;padding:22px;border-radius:22px;font-size:13px;line-height:1.9;white-space:pre;overflow-x:auto;box-shadow:var(--sh-lg)"><span style="color:#64748B"># Place a new order</span>
<span style="color:#67E8F9">POST</span> <?= e(abs_url('api/v2')) ?>

key=<span style="color:#FBBF24">YOUR_API_KEY</span>
action=<span style="color:#34D399">add</span>
service=<span style="color:#F472B6">12</span>
link=https://instagram.com/your.brand
quantity=<span style="color:#F472B6">1000</span>

<span style="color:#64748B"># Response</span>
{ <span style="color:#A78BFA">"order"</span>: <span style="color:#F472B6">23501</span> }</div>
      </div>
    </div>
  </div>
</section>

<?php if ($faqs): ?>
<section class="section" style="padding-top:20px">
  <div class="container">
    <div class="grid" style="grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr);gap:48px;align-items:start" id="faq-grid">
      <div class="reveal">
        <span class="sec-kicker"><?= icon('help') ?> سوالی دارید؟</span>
        <h2 class="sec-title">سوالات متداول</h2>
        <p class="sec-sub mb-2">پاسخ پرتکرارترین سوال‌ها را این‌جا ببینید. اگر جوابتان را پیدا نکردید، تیم پشتیبانی آماده کمک است.</p>
        <a href="<?= url('contact') ?>" class="btn btn-outline"><?= icon('headset') ?> تماس با پشتیبانی</a>
      </div>
      <div class="faq reveal" style="--d:.1s">
        <?php foreach ($faqs as $i => $f): ?>
          <details<?= $i === 0 ? ' open' : '' ?>><summary><?= e($f['question']) ?><?= icon('chevron-down') ?></summary><div class="ans"><?= nl2br(e($f['answer'])) ?></div></details>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($posts): ?>
<section class="section" style="padding-top:20px">
  <div class="container">
    <div class="sec-head">
      <div><span class="sec-kicker"><?= icon('file') ?> وبلاگ</span><h2 class="sec-title">آموزش‌ها و ترفندها</h2></div>
      <a href="<?= url('blog') ?>" class="btn btn-soft">همه مقالات <?= icon('arrow-left') ?></a>
    </div>
    <div class="grid g-3"><?php foreach ($posts as $p): ?><?= render('site/_post_card', ['p' => $p]) ?><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>

<section class="section-sm">
  <div class="container">
    <div class="cta reveal">
      <div class="row-between" style="gap:28px">
        <div>
          <h2>همین حالا رشد پیجت را شروع کن!</h2>
          <p class="mb-0">ثبت‌نام رایگان است و کمتر از یک دقیقه طول می‌کشد. اولین سفارشت را با خیال راحت ثبت کن.</p>
        </div>
        <div class="row" style="flex-wrap:wrap">
          <a href="<?= url(auth() ? 'dashboard/new-order' : 'register') ?>" class="btn btn-white btn-lg"><?= icon('rocket') ?> <?= auth() ? 'ثبت سفارش جدید' : 'ثبت‌نام رایگان' ?></a>
          <a href="<?= url('services') ?>" class="btn btn-glass btn-lg">مشاهده خدمات</a>
        </div>
      </div>
    </div>
  </div>
</section>
<style>@media (max-width: 960px) { #faq-grid { grid-template-columns: 1fr !important; gap: 24px !important; } }</style>
