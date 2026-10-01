<?php
$brandsRow = ['instagram', 'telegram', 'youtube', 'tiktok', 'x', 'spotify', 'aparat', 'rubika', 'twitch', 'facebook', 'threads', 'soundcloud', 'discord', 'whatsapp'];
$heroTags = array_slice($categories, 0, 4);
$orbs = [
    // [brand, c1, c2, a, b, speed, phase, tilt, small]
    ['instagram', '#F77737', '#C13584', .40, .30, .30, 20, -14, false],
    ['telegram', '#37BBFE', '#0369A1', .40, .30, .30, 200, -14, false],
    ['youtube', '#FF4E45', '#B91C1C', .62, .42, -.2, 110, -6, false],
    ['tiktok', '#3A3A4A', '#05050A', .62, .42, -.2, 290, -6, true],
    ['spotify', '#1ED760', '#0E7A36', .88, .56, .13, 60, 4, true],
    ['x', '#4B4B5A', '#0A0A10', .88, .56, .13, 180, 4, true],
    ['rubika', '#A855F7', '#DB2777', .88, .56, .13, 300, 4, true],
];
$typing = [
    ['# Numbix API — automatic order placement', 'cmt'],
    ['$ curl -X POST ' . abs_url('api/v2') . ' \\', 'cmd'],
    ['    -d key=YOUR_API_KEY -d action=add \\', 'kv'],
    ['    -d service=12 -d quantity=1000 \\', 'kv'],
    ['    -d link=https://instagram.com/your.brand', 'kv'],
    ['{ "order": 23501 }', 'out'],
    ['$ curl ... -d action=status -d order=23501', 'cmd'],
    ['{ "status": "In progress", "start_count": "1520", "remains": "240" }', 'out'],
];
?>
<section class="dark-zone hero">
  <span class="mini-planet b" data-depth="1.4" aria-hidden="true"></span>
  <div class="container">
    <div class="hero-copy">
      <a href="<?= url('services') ?>" class="hero-pill reveal"><span class="pulse-dot"></span> سفارش‌ها کاملاً خودکار و ۲۴ ساعته انجام می‌شوند <span class="tag">LIVE</span></a>
      <h1 class="reveal" style="--d:.08s">
        رشد کهکشانی در
        <span class="rotator"><span class="on">اینستاگرام</span><span>تلگرام</span><span>یوتیوب</span><span>تیک‌تاک</span><span>شبکه‌های اجتماعی</span></span>
      </h1>
      <p class="lead reveal" style="--d:.16s"><?= e(setting('hero_text', 'افزایش فالوور، ممبر، لایک و بازدید با کیفیت بالا؛ سفارش در چند ثانیه، شروع خودکار و پیگیری لحظه‌ای — همه در یک پنل حرفه‌ای.')) ?></p>
      <div class="hero-search reveal" style="--d:.24s">
        <form action="<?= url('services') ?>" method="get" role="search">
          <?= icon('search', 'i-s') ?>
          <input type="search" name="q" placeholder="جستجوی خدمات، مثلاً ممبر تلگرام…" data-search autocomplete="off" aria-label="جستجوی خدمات">
          <button class="btn btn-primary" data-magnetic><?= icon('rocket') ?><span>پرتاب</span></button>
        </form>
        <div class="search-suggest"></div>
      </div>
      <?php if ($heroTags): ?>
        <div class="hero-tags reveal" style="--d:.3s"><span>محبوب:</span>
          <?php foreach ($heroTags as $t): ?><a href="<?= url('services/' . $t['slug']) ?>"><?= e($t['name']) ?></a><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="stage" data-orbit-system data-cy=".56" aria-hidden="true">
      <svg class="orbit-svg">
        <defs><linearGradient id="orbitGrad" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#67E8F9"/><stop offset=".5" stop-color="#C084FC"/><stop offset="1" stop-color="#F472B6"/></linearGradient></defs>
        <ellipse data-ring data-a=".40" data-b=".30" data-tilt="-14"/>
        <ellipse data-ring data-a=".62" data-b=".42" data-tilt="-6"/>
        <ellipse data-ring data-a=".88" data-b=".56" data-tilt="4"/>
      </svg>
      <div class="planet-wrap" data-depth=".35">
        <div class="planet-ring back"></div>
        <div class="planet"></div>
        <div class="planet-atmo"></div>
        <div class="planet-ring front"></div>
      </div>
      <span class="moon"></span>
      <?php foreach ($orbs as [$b, $c1, $c2, $a, $bb, $sp, $ph, $tilt, $sm]): ?>
        <div class="sat<?= $sm ? ' sm' : '' ?>" data-a="<?= $a ?>" data-b="<?= $bb ?>" data-speed="<?= $sp ?>" data-phase="<?= $ph ?>" data-tilt="<?= $tilt ?>"><?= brand_tile(['icon' => $b, 'color' => $c1, 'color2' => $c2], 'lg') ?></div>
      <?php endforeach; ?>
      <div class="hud h1"><span class="h-ic"><?= icon('user-plus') ?></span><div><b class="ltr">+12,840</b><small>فالوور جدید امروز</small></div></div>
      <div class="hud h2"><span class="h-ic"><?= icon('heart') ?></span><div><b class="ltr">24.6K</b><small>لایک در یک ساعت</small><div class="bar"><i></i></div></div></div>
      <div class="hud h3"><span class="h-ic"><?= icon('zap') ?></span><div><b>سفارش #۲۴۵۱</b><small>شروع خودکار شد</small><div class="bar"><i></i></div></div></div>
      <div class="hud h4"><span class="h-ic"><?= icon('check') ?></span><div><b>٪۹۸</b><small>رضایت کاربران</small></div></div>
      <div class="horizon"></div>
    </div>

    <div class="hero-feats">
      <div class="hero-feat reveal"><span class="ic"><?= icon('zap') ?></span><div><b>تحویل لحظه‌ای</b><small>شروع در کمترین زمان</small></div></div>
      <div class="hero-feat reveal" style="--d:.06s"><span class="ic" style="background:radial-gradient(circle at 30% 25%,#CFFAFE,#06B6D4 45%,#083344);box-shadow:0 0 20px -4px #06B6D4"><?= icon('shield') ?></span><div><b>کیفیت تضمینی</b><small>با ضمانت جبران ریزش</small></div></div>
      <div class="hero-feat reveal" style="--d:.12s"><span class="ic" style="background:radial-gradient(circle at 30% 25%,#FCE7F3,#DB2777 45%,#500724);box-shadow:0 0 20px -4px #DB2777"><?= icon('repeat') ?></span><div><b>بازگشت خودکار وجه</b><small>برای سفارش‌های ناقص</small></div></div>
      <div class="hero-feat reveal" style="--d:.18s"><span class="ic" style="background:radial-gradient(circle at 30% 25%,#FEF3C7,#F59E0B 45%,#451A03);box-shadow:0 0 20px -4px #F59E0B"><?= icon('headset') ?></span><div><b>پشتیبانی ۲۴ ساعته</b><small>همیشه کنار شما</small></div></div>
    </div>
  </div>
</section>

<?php if ($categories): ?>
<section class="section-sm" style="padding-top:34px">
  <div class="container">
    <div class="sec-head center" style="margin-bottom:24px"><span class="sec-kicker"><?= icon('globe') ?> منظومه پلتفرم‌ها</span></div>
    <div class="cat-strip reveal">
      <div class="inner">
        <a href="<?= url('services') ?>" class="cat-tile active" style="--c1:#8B5CF6"><i class="orbit-ring"></i>
          <span class="btile btile-lg" style="--c1:#A78BFA;--c2:#4C1D95"><?= icon('grid') ?></span>
          <b>همه خدمات</b><small><?= num(array_sum(array_column($categories, 'services_count'))) ?> سرویس</small></a>
        <?php foreach ($categories as $c): ?>
          <a href="<?= url('services/' . $c['slug']) ?>" class="cat-tile" style="--c1:<?= e($c['color']) ?>"><i class="orbit-ring"></i>
            <?= brand_tile($c, 'lg') ?><b><?= e($c['name']) ?></b><small><?= num($c['services_count']) ?> سرویس</small></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="container">
  <div class="marquee" aria-hidden="true">
    <div class="marquee-track">
      <?php for ($k = 0; $k < 2; $k++): foreach ($brandsRow as $b): ?>
        <span class="marquee-item"><?= brand($b) ?><?= e($b === 'x' ? 'X (Twitter)' : ucfirst($b)) ?></span>
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
        <h2 class="sec-title">ستاره‌های <span class="gtext-p">نامبیکس</span></h2>
        <p class="sec-sub">سرویس‌هایی که بیشترین سفارش و رضایت را داشته‌اند.</p>
      </div>
      <a href="<?= url('services') ?>" class="btn btn-outline">مشاهده همه خدمات <?= icon('arrow-left') ?></a>
    </div>
    <div class="svc-grid">
      <?php foreach ($popular as $s): ?><?= partial('service_card', ['s' => $s, 'favs' => $favs]) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="sec-head center">
      <span class="sec-kicker"><?= icon('sparkles') ?> چرا <?= e(site_name()) ?>؟</span>
      <h2 class="sec-title">همه چیز برای یک رشد <span class="gtext-p">بی‌وقفه</span></h2>
      <p class="sec-sub">زیرساختی خودکار و هوشمند که سفارش شما را از لحظه ثبت تا تحویل نهایی مدیریت می‌کند.</p>
    </div>
    <div class="grid g-3">
      <?php
      $feats = [
          ['rocket', 'شروع خودکار سفارش', 'سفارش‌ها بلافاصله پس از پرداخت به صورت خودکار پردازش و آغاز می‌شوند؛ بدون انتظار.', '#8B5CF6', '#4C1D95'],
          ['shield', 'پرداخت امن و آنی', 'شارژ کیف پول با درگاه بانکی معتبر و پرداخت سفارش‌ها تنها با یک کلیک.', '#0EA5E9', '#0C4A6E'],
          ['repeat', 'بازگشت وجه خودکار', 'اگر سفارشی ناقص انجام شود یا لغو گردد، مبلغ آن خودکار به کیف پولتان برمی‌گردد.', '#10B981', '#064E3B'],
          ['activity', 'پیگیری لحظه‌ای', 'وضعیت، تعداد شروع و باقی‌مانده هر سفارش را به‌صورت زنده در پنل ببینید.', '#F59E0B', '#78350F'],
          ['code', 'وب‌سرویس برای همکاران', 'با API استاندارد، خدمات ما را مستقیم در ربات یا پنل خودتان بفروشید.', '#EC4899', '#831843'],
          ['headset', 'پشتیبانی واقعی', 'تیم پشتیبانی از طریق تیکت و پیام‌رسان‌ها، هر روز هفته پاسخگوی شماست.', '#22D3EE', '#164E63'],
      ];
      foreach ($feats as $i => [$ic, $t, $d, $c1, $c2]): ?>
        <div class="feat-card reveal" data-tilt style="--d:<?= $i * .06 ?>s;--fc:<?= $c1 ?>;--fc2:<?= $c2 ?>">
          <span class="f-num"><?= fa(str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)) ?></span>
          <div class="f-ic"><?= icon($ic) ?></div>
          <h3><?= $t ?></h3>
          <p><?= $d ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:20px">
  <div class="container">
    <div class="sec-head center">
      <span class="sec-kicker"><?= icon('target') ?> توالی پرتاب</span>
      <h2 class="sec-title">از ثبت سفارش تا <span class="gtext-p">مدار رشد</span></h2>
    </div>
    <div class="launch">
      <svg class="launch-path" viewBox="0 0 1000 110" preserveAspectRatio="none" aria-hidden="true">
        <defs><linearGradient id="launchGrad" x1="1" y1="0" x2="0" y2="0"><stop offset="0" stop-color="#67E8F9"/><stop offset=".5" stop-color="#F0ABFC"/><stop offset="1" stop-color="#A78BFA"/></linearGradient></defs>
        <path class="track" pathLength="100" d="M985 48 C 880 -10, 760 110, 625 48 S 380 -10, 375 48 S 120 110, 15 48"/>
        <path class="glow" pathLength="100" d="M985 48 C 880 -10, 760 110, 625 48 S 380 -10, 375 48 S 120 110, 15 48"/>
      </svg>
      <div class="grid g-4 steps">
        <?php foreach ([
            ['grid', 'انتخاب سرویس', 'از بین ده‌ها سرویس متنوع، گزینه مناسب خود را انتخاب کنید.'],
            ['link', 'وارد کردن لینک', 'لینک پست یا آیدی پیج و تعداد مورد نظر را وارد کنید.'],
            ['card', 'پرداخت امن', 'از کیف پول یا درگاه بانکی، سفارش را پرداخت کنید.'],
            ['rocket', 'پرتاب و رشد', 'سفارش خودکار شروع می‌شود؛ رشد پیج خود را تماشا کنید.'],
        ] as $i => [$ic, $t, $d]): ?>
          <div class="step reveal" style="--d:<?= $i * .1 ?>s"><div class="s-ic" data-n="<?= fa($i + 1) ?>"><?= icon($ic) ?></div><h4><?= $t ?></h4><p><?= $d ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php if (setting('home_stats', '1') === '1' && $stats['orders'] > 0): ?>
<section class="section-sm">
  <div class="container">
    <div class="mission dark-zone reveal">
      <div class="grid" style="grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);gap:44px;align-items:center" id="mission-grid">
        <div>
          <span class="sec-kicker"><?= icon('award') ?> مرکز کنترل ماموریت</span>
          <h2 class="sec-title" style="color:#fff">اعتماد هزاران کاربر، <span class="gtext">در اعداد</span></h2>
          <div class="grid g-2 mt-3" style="gap:14px">
            <div class="stat-big"><b data-count="<?= $stats['orders'] ?>">۰</b><span>سفارش ثبت شده</span></div>
            <div class="stat-big"><b data-count="<?= $stats['completed'] ?>">۰</b><span>سفارش تکمیل شده</span></div>
            <div class="stat-big"><b data-count="<?= $stats['users'] ?>">۰</b><span>کاربر فعال</span></div>
            <div class="stat-big"><b data-count="<?= $stats['services'] ?>">۰</b><span>سرویس فعال</span></div>
          </div>
        </div>
        <div>
          <div class="radar mb-2">
            <span class="blip" style="top:28%;left:62%;animation-delay:.3s"></span>
            <span class="blip" style="top:64%;left:30%;animation-delay:1.4s"></span>
            <span class="blip" style="top:40%;left:22%;animation-delay:2.4s"></span>
            <span class="blip" style="top:72%;left:66%;animation-delay:3s"></span>
          </div>
          <?php if ($live): ?>
            <div class="row-between mb-1"><b>سیگنال‌های زنده</b><span class="live"><i></i> LIVE</span></div>
            <div class="ticker">
              <?php foreach (array_slice($live, 0, 3) as $i => $o): ?>
                <div class="ticker-item" style="animation-delay:<?= $i * .12 ?>s">
                  <?= brand_tile($o, 'sm') ?>
                  <div><b><?= e($o['title']) ?></b><small><?= e(mb_substr($o['first_name'] ?: 'کاربر', 0, 1)) ?>*** — <?= num($o['quantity']) ?> عدد</small></div>
                  <span class="t-time"><?= time_ago($o['created_at']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
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
        <h2 class="sec-title">وب‌سرویس API <span class="gtext-p">استاندارد</span></h2>
        <p class="sec-sub mb-2">پنل یا ربات تلگرامی خودتان را به <?= e(site_name()) ?> وصل کنید و سفارش‌ها را کاملاً خودکار ثبت و پیگیری کنید. سازگار با استاندارد رایج پنل‌های SMM.</p>
        <div class="row" style="flex-wrap:wrap">
          <span class="chip"><?= icon('check') ?> ثبت سفارش</span><span class="chip"><?= icon('check') ?> استعلام وضعیت</span>
          <span class="chip"><?= icon('check') ?> لیست سرویس‌ها</span><span class="chip"><?= icon('check') ?> موجودی حساب</span>
        </div>
        <a href="<?= url(auth() ? 'dashboard/api' : 'register') ?>" class="btn btn-primary btn-lg mt-3" data-magnetic><?= icon('key') ?> دریافت کلید API</a>
      </div>
      <div class="reveal" style="--d:.1s">
        <div class="terminal">
          <div class="t-bar"><i style="background:#FB7185"></i><i style="background:#FBBF24"></i><i style="background:#34D399"></i><span>numbix — api/v2</span></div>
          <div class="t-body" data-typing="<?= e(json_encode($typing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($faqs): ?>
<section class="section" style="padding-top:10px">
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
<section class="section" style="padding-top:10px">
  <div class="container">
    <div class="sec-head">
      <div><span class="sec-kicker"><?= icon('file') ?> وبلاگ</span><h2 class="sec-title">آموزش‌ها و ترفندها</h2></div>
      <a href="<?= url('blog') ?>" class="btn btn-outline">همه مقالات <?= icon('arrow-left') ?></a>
    </div>
    <div class="grid g-3"><?php foreach ($posts as $p): ?><?= render('site/_post_card', ['p' => $p]) ?><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>

<section class="section-sm">
  <div class="container">
    <div class="cta dark-zone reveal">
      <div class="wormhole" aria-hidden="true"><i></i><i></i><i></i></div>
      <div class="cta-copy">
        <div>
          <span class="sec-kicker"><?= icon('sparkles') ?> آماده پرتاب؟</span>
          <h2>همین حالا وارد مدار رشد شو!</h2>
          <p>ثبت‌نام رایگان است و کمتر از یک دقیقه طول می‌کشد. اولین سفارشت را با خیال راحت ثبت کن.</p>
        </div>
        <div class="row mt-2" style="flex-wrap:wrap">
          <a href="<?= url(auth() ? 'dashboard/new-order' : 'register') ?>" class="btn btn-white btn-lg" data-magnetic><?= icon('rocket') ?> <?= auth() ? 'ثبت سفارش جدید' : 'ثبت‌نام رایگان' ?></a>
          <a href="<?= url('services') ?>" class="btn btn-glass btn-lg">مشاهده خدمات</a>
        </div>
      </div>
    </div>
  </div>
</section>
<style>@media (max-width: 960px) { #faq-grid, #mission-grid { grid-template-columns: 1fr !important; gap: 24px !important; } }</style>
