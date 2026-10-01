<?php

final class HomeController
{
    private const SVC_SELECT = 's.*, c.name AS category_name, c.slug AS category_slug, c.icon, c.color, c.color2';

    public static function favs(): array
    {
        $id = Auth::id();
        return $id ? array_map('intval', DB::column('SELECT service_id FROM favorites WHERE user_id = ?', [$id])) : [];
    }

    public static function categories(): array
    {
        return DB::all('SELECT c.*, (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id AND s.is_active = 1) AS services_count
            FROM categories c WHERE c.is_active = 1 ORDER BY c.sort, c.id');
    }

    public function index(): string
    {
        $popular = DB::all('SELECT ' . self::SVC_SELECT . ' FROM services s JOIN categories c ON c.id = s.category_id
            WHERE s.is_active = 1 AND c.is_active = 1 ORDER BY s.is_featured DESC, s.sales_count DESC, s.sort, s.id LIMIT 8');
        $live = setting('home_live_orders', '1') === '1'
            ? DB::all("SELECT o.quantity, o.created_at, s.title, c.icon, c.color, c.color2, u.first_name
                FROM orders o JOIN services s ON s.id = o.service_id JOIN categories c ON c.id = s.category_id JOIN users u ON u.id = o.user_id
                WHERE o.status NOT IN ('unpaid','canceled') ORDER BY o.id DESC LIMIT 5")
            : [];
        $stats = [
            'orders' => (int)DB::value("SELECT COUNT(*) FROM orders WHERE status NOT IN ('unpaid','canceled')") + (int)setting('stats_orders_offset', 0),
            'users' => (int)DB::value('SELECT COUNT(*) FROM users') + (int)setting('stats_users_offset', 0),
            'services' => (int)DB::value('SELECT COUNT(*) FROM services WHERE is_active = 1'),
            'completed' => (int)DB::value("SELECT COUNT(*) FROM orders WHERE status = 'completed'") + (int)setting('stats_completed_offset', 0),
        ];
        return view('site/home', [
            'title' => '',
            'darkHeader' => true,
            'categories' => self::categories(),
            'popular' => $popular,
            'favs' => self::favs(),
            'live' => $live,
            'stats' => $stats,
            'faqs' => DB::all('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort, id LIMIT 6'),
            'posts' => DB::all("SELECT * FROM posts WHERE type = 'post' AND is_published = 1 ORDER BY id DESC LIMIT 3"),
            'canonical' => abs_url(),
        ], 'site');
    }

    public function services(?string $category = null): string
    {
        $categories = self::categories();
        $catSlug = $category ?? input('cat');
        $cat = null;
        foreach ($categories as $c) {
            if ($c['slug'] === $catSlug) {
                $cat = $c;
            }
        }
        if ($catSlug && !$cat) {
            abort(404, 'دسته‌بندی مورد نظر پیدا نشد.');
        }

        $where = ['s.is_active = 1', 'c.is_active = 1'];
        $params = [];
        if ($cat) {
            $where[] = 's.category_id = ?';
            $params[] = (int)$cat['id'];
        }
        $q = trim((string)input('q', ''));
        if ($q !== '') {
            $where[] = '(s.title LIKE ? OR s.subtitle LIKE ? OR c.name LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        $types = array_values(array_intersect((array)($_GET['type'] ?? []), array_keys(service_types())));
        if ($types) {
            $where[] = 's.type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            array_push($params, ...$types);
        }
        $maxPrice = (int)(DB::value('SELECT MAX(price) FROM services WHERE is_active = 1') ?: 100000);
        $pmin = max(0, input_int('pmin', 0));
        $pmax = input_int('pmax', 0) ?: $maxPrice;
        if ($pmin > 0) {
            $where[] = 's.price >= ?';
            $params[] = $pmin;
        }
        if ($pmax < $maxPrice) {
            $where[] = 's.price <= ?';
            $params[] = $pmax;
        }
        $state = input('state', 'all');
        if ($state === 'stock') {
            $where[] = '(s.stock IS NULL OR s.stock >= s.min_qty)';
        } elseif ($state === 'sale') {
            $where[] = 's.compare_price > s.price';
        }
        $sorts = [
            'popular' => ['محبوب‌ترین', 's.is_featured DESC, s.sales_count DESC, s.sort, s.id'],
            'newest' => ['جدیدترین', 's.id DESC'],
            'cheap' => ['ارزان‌ترین', 's.price ASC'],
            'expensive' => ['گران‌ترین', 's.price DESC'],
            'rating' => ['بیشترین امتیاز', 's.rating DESC, s.sales_count DESC'],
        ];
        $sort = isset($sorts[input('sort')]) ? input('sort') : 'popular';

        $page = paginate(self::SVC_SELECT, 'FROM services s JOIN categories c ON c.id = s.category_id WHERE ' . implode(' AND ', $where), $params, 12, 'ORDER BY ' . $sorts[$sort][1]);
        $banner = DB::row("SELECT * FROM banners WHERE is_active = 1 AND position = 'services' ORDER BY sort, id DESC LIMIT 1");

        return view('site/services', [
            'title' => $cat ? 'خدمات ' . $cat['name'] : ($q !== '' ? 'جستجو: ' . $q : 'همه خدمات'),
            'description' => $cat['description'] ?? null,
            'categories' => $categories,
            'cat' => $cat,
            'q' => $q,
            'types' => $types,
            'pmin' => $pmin,
            'pmax' => $pmax,
            'maxPrice' => $maxPrice,
            'state' => $state,
            'sorts' => $sorts,
            'sort' => $sort,
            'page' => $page,
            'favs' => self::favs(),
            'banner' => $banner,
            'totalActive' => array_sum(array_column($categories, 'services_count')),
        ], 'site');
    }

    public function service(string $slug): string
    {
        $s = DB::row('SELECT ' . self::SVC_SELECT . ' FROM services s JOIN categories c ON c.id = s.category_id WHERE s.slug = ? AND s.is_active = 1 AND c.is_active = 1', [$slug]);
        if (!$s) {
            abort(404, 'این سرویس پیدا نشد یا غیرفعال است.');
        }
        DB::query('UPDATE services SET views = views + 1 WHERE id = ?', [$s['id']]);
        $related = DB::all('SELECT ' . self::SVC_SELECT . ' FROM services s JOIN categories c ON c.id = s.category_id
            WHERE s.is_active = 1 AND s.category_id = ? AND s.id <> ? ORDER BY s.sales_count DESC LIMIT 4', [$s['category_id'], $s['id']]);
        $completed = (int)DB::value("SELECT COUNT(*) FROM orders WHERE service_id = ? AND status = 'completed'", [$s['id']]);
        return view('site/service', [
            'title' => $s['title'],
            'description' => $s['subtitle'] ?: str_limit($s['description'], 160),
            's' => $s,
            'related' => $related,
            'favs' => self::favs(),
            'completed' => $completed,
            'canonical' => abs_url('service/' . $s['slug']),
        ], 'site');
    }

    public function search(): array
    {
        $q = trim((string)input('q', ''));
        if (mb_strlen($q) < 2) {
            return ['items' => [], 'groups' => []];
        }
        $rows = DB::all('SELECT ' . self::SVC_SELECT . ' FROM services s JOIN categories c ON c.id = s.category_id
            WHERE s.is_active = 1 AND c.is_active = 1 AND (s.title LIKE ? OR c.name LIKE ? OR s.subtitle LIKE ?)
            ORDER BY s.sales_count DESC LIMIT 8', ["%$q%", "%$q%", "%$q%"]);
        $items = array_map(fn($s) => [
            'title' => $s['title'],
            'category' => $s['category_name'],
            'price' => (int)$s['price'],
            'url' => service_url($s),
            'icon' => brand($s['icon']),
            'c1' => $s['color'],
            'c2' => $s['color2'],
        ], $rows);
        return [
            'items' => $items,
            'groups' => [[
                'title' => 'خدمات',
                'items' => array_map(fn($i) => ['title' => $i['title'], 'sub' => $i['category'] . ' — هر ۱۰۰۰ عدد ' . money_text($i['price']), 'url' => $i['url'], 'icon' => $i['icon']], $items),
            ]],
        ];
    }

    public function blog(): string
    {
        $page = paginate('*', "FROM posts WHERE type = 'post' AND is_published = 1", [], 9, 'ORDER BY id DESC');
        return view('site/blog', ['title' => 'وبلاگ', 'darkHeader' => true, 'page' => $page], 'site');
    }

    public function post(string $slug): string
    {
        $p = DB::row("SELECT p.*, u.first_name, u.last_name FROM posts p LEFT JOIN users u ON u.id = p.author_id WHERE p.type = 'post' AND p.slug = ? AND p.is_published = 1", [$slug]);
        if (!$p) {
            abort(404, 'مطلب مورد نظر پیدا نشد.');
        }
        DB::query('UPDATE posts SET views = views + 1 WHERE id = ?', [$p['id']]);
        $more = DB::all("SELECT * FROM posts WHERE type = 'post' AND is_published = 1 AND id <> ? ORDER BY id DESC LIMIT 3", [$p['id']]);
        return view('site/post', ['title' => $p['title'], 'description' => $p['excerpt'], 'darkHeader' => true, 'p' => $p, 'more' => $more], 'site');
    }

    public function page(string $slug): string
    {
        $p = DB::row("SELECT * FROM posts WHERE type = 'page' AND slug = ? AND is_published = 1", [$slug]);
        if (!$p) {
            abort(404, 'صفحه مورد نظر پیدا نشد.');
        }
        return view('site/page', ['title' => $p['title'], 'description' => $p['excerpt'], 'darkHeader' => true, 'p' => $p], 'site');
    }

    public function faq(): string
    {
        return view('site/faq', [
            'title' => 'سوالات متداول',
            'darkHeader' => true,
            'faqs' => DB::all('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort, id'),
        ], 'site');
    }

    public function contact(): string
    {
        return view('site/contact', ['title' => 'پشتیبانی و تماس', 'darkHeader' => true], 'site');
    }

    public function bannerClick(string $id): never
    {
        $b = DB::row('SELECT * FROM banners WHERE id = ?', [(int)$id]);
        if (!$b || !$b['link']) {
            redirect('services');
        }
        DB::query('UPDATE banners SET clicks = clicks + 1 WHERE id = ?', [$b['id']]);
        $link = $b['link'];
        redirect(preg_match('~^https?://~', $link) ? $link : url(ltrim($link, '/')), true);
    }

    public function sitemap(): never
    {
        header('Content-Type: application/xml; charset=utf-8');
        $urls = [abs_url(), abs_url('services'), abs_url('blog'), abs_url('faq'), abs_url('contact')];
        foreach (DB::column('SELECT slug FROM categories WHERE is_active = 1') as $s) {
            $urls[] = abs_url('services/' . $s);
        }
        foreach (DB::column('SELECT slug FROM services WHERE is_active = 1') as $s) {
            $urls[] = abs_url('service/' . $s);
        }
        foreach (DB::all('SELECT type, slug FROM posts WHERE is_published = 1') as $p) {
            $urls[] = abs_url(($p['type'] === 'page' ? 'page/' : 'blog/') . $p['slug']);
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            echo '<url><loc>' . e($u) . '</loc></url>';
        }
        echo '</urlset>';
        exit;
    }

    public function robots(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin\nDisallow: /dashboard\nDisallow: /cart\nDisallow: /install\nSitemap: " . abs_url('sitemap.xml') . "\n";
        exit;
    }
}
