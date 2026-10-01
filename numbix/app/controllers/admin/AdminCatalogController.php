<?php

final class AdminCatalogController
{
    private const P = ['area' => 'admin'];

    // ------------------------------------------------------------------ services (products)

    public function services(): string
    {
        $where = ['1=1'];
        $params = [];
        if ($cat = input_int('cat')) {
            $where[] = 's.category_id = ?';
            $params[] = $cat;
        }
        if ($q = trim((string)input('q', ''))) {
            $where[] = '(s.title LIKE ? OR s.id = ?)';
            array_push($params, "%$q%", (int)en_digits($q));
        }
        $state = input('state', '');
        if ($state === 'active') {
            $where[] = 's.is_active = 1';
        } elseif ($state === 'inactive') {
            $where[] = 's.is_active = 0';
        } elseif ($state === 'api') {
            $where[] = 's.provider_id IS NOT NULL';
        }
        $page = paginate('s.*, c.name AS category_name, c.icon, c.color, c.color2, p.name AS provider_name',
            'FROM services s JOIN categories c ON c.id = s.category_id LEFT JOIN providers p ON p.id = s.provider_id WHERE ' . implode(' AND ', $where),
            $params, 20, 'ORDER BY c.sort, s.sort, s.id');
        $k = DB::row('SELECT COUNT(*) total, SUM(is_active) active, SUM(provider_id IS NOT NULL) api, SUM(is_featured) featured FROM services');
        return view('admin/services', self::P + [
            'title' => 'مدیریت محصولات',
            'page' => $page,
            'k' => $k,
            'categories' => DB::all('SELECT * FROM categories ORDER BY sort, id'),
        ], 'panel');
    }

    public function serviceForm(?string $id = null): string
    {
        $s = $id ? DB::row('SELECT * FROM services WHERE id = ?', [(int)$id]) : null;
        if ($id && !$s) {
            abort(404);
        }
        return view('admin/service_form', self::P + [
            'title' => $s ? 'ویرایش محصول' : 'محصول جدید',
            's' => $s,
            'categories' => DB::all('SELECT * FROM categories ORDER BY sort, id'),
            'providers' => DB::all('SELECT id, name FROM providers ORDER BY id'),
        ], 'panel');
    }

    public function serviceSave(): never
    {
        $id = input_int('id');
        $old = $id ? DB::row('SELECT * FROM services WHERE id = ?', [$id]) : null;
        $title = trim((string)input('title'));
        $cat = input_int('category_id');
        if (mb_strlen($title) < 2 || !DB::value('SELECT id FROM categories WHERE id = ?', [$cat])) {
            fail('عنوان و دسته‌بندی محصول را وارد کنید.');
        }
        $slug = slugify((string)(input('slug') ?: $title));
        if (DB::value('SELECT id FROM services WHERE slug = ? AND id <> ?', [$slug, $id])) {
            $slug .= '-' . random_code(4, 'abcdefghjkmnpqrstuvwxyz23456789');
        }
        $min = max(1, input_int('min_qty', 100));
        $max = max($min, input_int('max_qty', 100000));
        $trackStock = input('track_stock') === '1';
        $data = [
            'category_id' => $cat,
            'title' => mb_substr($title, 0, 160),
            'slug' => $slug,
            'subtitle' => mb_substr((string)input('subtitle', ''), 0, 200) ?: null,
            'description' => (string)input('description', ''),
            'type' => array_key_exists(input('type'), service_types()) ? input('type') : 'other',
            'badge' => array_key_exists(input('badge'), service_badges()) ? input('badge') : null,
            'price' => max(0, input_int('price')),
            'compare_price' => input_int('compare_price') ?: null,
            'cost' => max(0, input_int('cost')),
            'min_qty' => $min,
            'max_qty' => $max,
            'stock' => $trackStock ? max(0, input_int('stock')) : null,
            'stock_alert' => max(0, input_int('stock_alert')),
            'start_time' => mb_substr((string)input('start_time', ''), 0, 60) ?: null,
            'delivery_time' => mb_substr((string)input('delivery_time', ''), 0, 60) ?: null,
            'quality' => mb_substr((string)input('quality', ''), 0, 60) ?: null,
            'guarantee' => mb_substr((string)input('guarantee', ''), 0, 60) ?: null,
            'link_hint' => mb_substr((string)input('link_hint', ''), 0, 160) ?: null,
            'rating' => min(5, max(0, (float)en_digits((string)input('rating', '5')))),
            'provider_id' => input_int('provider_id') ?: null,
            'provider_service_id' => trim((string)input('provider_service_id', '')) ?: null,
            'is_featured' => input('is_featured') === '1' ? 1 : 0,
            'is_active' => input('is_active') === '1' ? 1 : 0,
            'sort' => input_int('sort'),
        ];
        if ($data['price'] <= 0) {
            fail('قیمت فروش را وارد کنید.');
        }
        if (Uploader::has('image')) {
            try {
                $data['image'] = Uploader::image('image', 'services');
                Uploader::delete($old['image'] ?? null);
            } catch (RuntimeException $e) {
                fail($e->getMessage());
            }
        } elseif (input('remove_image') === '1' && $old) {
            Uploader::delete($old['image']);
            $data['image'] = null;
        }
        if ($old) {
            DB::update('services', $data, 'id = ?', [$id]);
            if ($trackStock && $old['stock'] !== null && (int)$old['stock'] !== (int)$data['stock']) {
                DB::insert('stock_logs', ['service_id' => $id, 'admin_id' => Auth::id(), 'amount' => (int)$data['stock'] - (int)$old['stock'], 'stock_after' => $data['stock'], 'note' => 'ویرایش دستی']);
            }
            flash('success', 'محصول بروزرسانی شد.');
        } else {
            $id = DB::insert('services', $data);
            if ($trackStock && $data['stock'] > 0) {
                DB::insert('stock_logs', ['service_id' => $id, 'admin_id' => Auth::id(), 'amount' => $data['stock'], 'cost' => $data['cost'], 'price' => $data['price'], 'stock_after' => $data['stock'], 'note' => 'موجودی اولیه']);
            }
            flash('success', 'محصول جدید ساخته شد.');
        }
        redirect(input('stay') ? 'admin/services/' . $id . '/edit' : 'admin/services');
    }

    public function serviceDelete(string $id): never
    {
        $s = DB::row('SELECT * FROM services WHERE id = ?', [(int)$id]);
        if ($s) {
            if (DB::value('SELECT COUNT(*) FROM orders WHERE service_id = ?', [$s['id']])) {
                DB::update('services', ['is_active' => 0], 'id = ?', [$s['id']]);
                flash('warning', 'این محصول سفارش ثبت‌شده دارد؛ به جای حذف، غیرفعال شد.');
            } else {
                DB::delete('services', 'id = ?', [$s['id']]);
                DB::delete('favorites', 'service_id = ?', [$s['id']]);
                Uploader::delete($s['image']);
                flash('success', 'محصول حذف شد.');
            }
        }
        back('admin/services');
    }

    public function serviceToggle(string $id): array|string
    {
        DB::query('UPDATE services SET is_active = 1 - is_active WHERE id = ?', [(int)$id]);
        $on = (bool)DB::value('SELECT is_active FROM services WHERE id = ?', [(int)$id]);
        if (is_ajax()) {
            return ['ok' => true, 'on' => $on, 'message' => $on ? 'محصول فعال شد.' : 'محصول غیرفعال شد.'];
        }
        back('admin/services');
    }

    public function serviceBulk(): never
    {
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        if (!$ids) {
            fail('هیچ موردی انتخاب نشده است.');
        }
        $in = implode(',', $ids);
        switch (input('action')) {
            case 'activate':
                DB::query("UPDATE services SET is_active = 1 WHERE id IN ($in)");
                break;
            case 'deactivate':
                DB::query("UPDATE services SET is_active = 0 WHERE id IN ($in)");
                break;
            case 'feature':
                DB::query("UPDATE services SET is_featured = 1 WHERE id IN ($in)");
                break;
            case 'price':
                $pct = (float)en_digits((string)input('percent', '0'));
                if ($pct == 0 || $pct < -90 || $pct > 500) {
                    fail('درصد تغییر قیمت نامعتبر است.');
                }
                DB::query("UPDATE services SET price = GREATEST(1, ROUND(price * (1 + ? / 100))) WHERE id IN ($in)", [$pct]);
                break;
            default:
                fail('عملیات نامعتبر است.');
        }
        flash('success', fa(count($ids)) . ' محصول بروزرسانی شد.');
        back('admin/services');
    }

    // ------------------------------------------------------------------ categories

    public function categories(): string
    {
        $rows = DB::all('SELECT c.*, (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id) AS services_count,
            (SELECT COUNT(*) FROM orders o JOIN services s ON s.id = o.service_id WHERE s.category_id = c.id) AS orders_count
            FROM categories c ORDER BY c.sort, c.id');
        $brands = array_keys(require APP_PATH . '/core/brands.php');
        return view('admin/categories', self::P + ['title' => 'خدمات و دسته‌بندی‌ها', 'rows' => $rows, 'edit' => input_int('edit'), 'brands' => $brands], 'panel');
    }

    public function categorySave(): never
    {
        $id = input_int('id');
        $name = trim((string)input('name'));
        if (mb_strlen($name) < 2) {
            fail('نام دسته‌بندی را وارد کنید.');
        }
        $slug = slugify((string)(input('slug') ?: $name));
        if (DB::value('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, $id])) {
            fail('این نامک قبلاً استفاده شده است.');
        }
        $hex = fn($v, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v) ? $v : $d;
        $data = [
            'name' => mb_substr($name, 0, 100),
            'slug' => $slug,
            'icon' => preg_replace('/[^a-z0-9\-]/', '', (string)input('icon', 'grid')) ?: 'grid',
            'color' => $hex(input('color'), '#6C4CF1'),
            'color2' => $hex(input('color2'), '#4F46E5'),
            'description' => mb_substr((string)input('description', ''), 0, 255) ?: null,
            'sort' => input_int('sort'),
            'is_active' => input('is_active') === '1' ? 1 : 0,
        ];
        $id ? DB::update('categories', $data, 'id = ?', [$id]) : DB::insert('categories', $data);
        flash('success', 'دسته‌بندی ذخیره شد.');
        redirect('admin/categories');
    }

    public function categoryDelete(string $id): never
    {
        if (DB::value('SELECT COUNT(*) FROM services WHERE category_id = ?', [(int)$id])) {
            fail('ابتدا محصولات این دسته را حذف یا جابه‌جا کنید.', 'admin/categories');
        }
        DB::delete('categories', 'id = ?', [(int)$id]);
        flash('success', 'دسته‌بندی حذف شد.');
        redirect('admin/categories');
    }

    // ------------------------------------------------------------------ stock

    public function stock(): string
    {
        $default = (int)setting('low_stock_default', 500);
        $k = DB::row("SELECT COUNT(*) total, SUM(is_active) active,
            SUM(stock IS NOT NULL AND stock > 0 AND stock >= min_qty AND stock <= IF(stock_alert > 0, stock_alert, $default)) low,
            SUM(stock IS NOT NULL AND (stock <= 0 OR stock < min_qty)) out_ FROM services");
        $where = 's.stock IS NOT NULL';
        $params = [];
        if ($c = input_int('cat')) {
            $where .= ' AND s.category_id = ?';
            $params[] = $c;
        }
        if ($q = trim((string)input('q', ''))) {
            $where .= ' AND s.title LIKE ?';
            $params[] = "%$q%";
        }
        $list = DB::all("SELECT s.*, c.icon, c.color, c.color2, c.name AS category_name FROM services s JOIN categories c ON c.id = s.category_id WHERE $where ORDER BY (s.stock <= IF(s.stock_alert > 0, s.stock_alert, $default)) DESC, s.stock ASC LIMIT 60", $params);
        $all = DB::all('SELECT s.*, c.icon, c.color, c.color2, c.name AS category_name FROM services s JOIN categories c ON c.id = s.category_id ORDER BY c.sort, s.sort, s.id');
        $logs = DB::all('SELECT l.*, s.title, c.icon, c.color, c.color2 FROM stock_logs l JOIN services s ON s.id = l.service_id JOIN categories c ON c.id = s.category_id ORDER BY l.id DESC LIMIT 12');
        return view('admin/stock', self::P + [
            'title' => 'افزایش موجودی',
            'k' => $k,
            'list' => $list,
            'all' => $all,
            'logs' => $logs,
            'selected' => input_int('service'),
            'categories' => DB::all('SELECT id, name FROM categories ORDER BY sort, id'),
        ], 'panel');
    }

    public function stockAdd(): never
    {
        $s = DB::row('SELECT * FROM services WHERE id = ?', [input_int('service_id')]);
        $amount = input_int('amount');
        if (!$s) {
            fail('محصول را انتخاب کنید.');
        }
        if ($amount === 0 || abs($amount) > 100000000) {
            fail('مقدار افزایش موجودی را وارد کنید.');
        }
        $cost = input_int('cost');
        $price = input_int('price');
        DB::transaction(function () use ($s, $amount, $cost, $price) {
            $stock = max(0, (int)($s['stock'] ?? 0) + $amount);
            $upd = ['stock' => $stock];
            if ($cost > 0) {
                $upd['cost'] = $cost;
            }
            if ($price > 0) {
                $upd['price'] = $price;
            }
            if (input('activate') === '1') {
                $upd['is_active'] = 1;
            }
            DB::update('services', $upd, 'id = ?', [$s['id']]);
            DB::insert('stock_logs', [
                'service_id' => $s['id'], 'admin_id' => Auth::id(), 'amount' => $amount,
                'cost' => $cost ?: null, 'price' => $price ?: null, 'stock_after' => $stock,
                'note' => mb_substr((string)input('note', ''), 0, 255) ?: null,
            ]);
        });
        Automation::checkLowStock();
        flash('success', 'موجودی «' . $s['title'] . '» ' . ($amount > 0 ? 'افزایش' : 'کاهش') . ' یافت و محصول به صورت خودکار برای خرید در سایت نمایش داده می‌شود.');
        redirect('admin/stock?service=' . $s['id']);
    }

    // ------------------------------------------------------------------ API providers

    public function providers(): string
    {
        $rows = DB::all('SELECT p.*, (SELECT COUNT(*) FROM services s WHERE s.provider_id = p.id) AS services_count,
            (SELECT COUNT(*) FROM orders o WHERE o.provider_id = p.id) AS orders_count FROM providers p ORDER BY p.id');
        $failing = DB::all("SELECT o.id, o.provider_error, o.provider_attempts, s.title FROM orders o JOIN services s ON s.id = o.service_id WHERE o.status = 'pending' AND o.provider_id IS NOT NULL AND o.provider_order_id IS NULL AND o.provider_error IS NOT NULL ORDER BY o.id DESC LIMIT 10");
        return view('admin/providers', self::P + ['title' => 'اتصال API خودکار', 'rows' => $rows, 'edit' => input_int('edit'), 'failing' => $failing], 'panel');
    }

    public function providerSave(): never
    {
        $id = input_int('id');
        $url = trim((string)input('api_url'));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~', $url)) {
            fail('آدرس API معتبر نیست.');
        }
        $data = ['name' => mb_substr(trim((string)input('name')) ?: parse_url($url, PHP_URL_HOST), 0, 100), 'api_url' => $url, 'is_active' => input('is_active') === '1' ? 1 : 0];
        if (($key = trim((string)input('api_key'))) !== '') {
            $data['api_key'] = $key;
        } elseif (!$id) {
            fail('کلید API را وارد کنید.');
        }
        if ($id) {
            DB::update('providers', $data, 'id = ?', [$id]);
        } else {
            $id = DB::insert('providers', $data);
        }
        $this->checkBalance($id);
        redirect('admin/providers');
    }

    private function checkBalance(int $id): void
    {
        $p = DB::row('SELECT * FROM providers WHERE id = ?', [$id]);
        try {
            $b = (new Provider($p))->balance();
            DB::update('providers', ['balance' => $b['balance'], 'currency' => $b['currency'], 'checked_at' => date('Y-m-d H:i:s'), 'last_error' => null], 'id = ?', [$id]);
            flash('success', 'اتصال برقرار است. موجودی: ' . number_format($b['balance'], 2) . ' ' . $b['currency']);
        } catch (Throwable $e) {
            DB::update('providers', ['checked_at' => date('Y-m-d H:i:s'), 'last_error' => mb_substr($e->getMessage(), 0, 250)], 'id = ?', [$id]);
            flash('error', 'اتصال ناموفق: ' . $e->getMessage());
        }
    }

    public function providerCheck(string $id): never
    {
        $this->checkBalance((int)$id);
        redirect('admin/providers');
    }

    public function providerDelete(string $id): never
    {
        DB::query('UPDATE services SET provider_id = NULL, provider_service_id = NULL WHERE provider_id = ?', [(int)$id]);
        DB::delete('providers', 'id = ?', [(int)$id]);
        flash('success', 'ارائه‌دهنده حذف شد و محصولات متصل به حالت دستی برگشتند.');
        redirect('admin/providers');
    }

    public function providerImport(string $id): string
    {
        $p = DB::row('SELECT * FROM providers WHERE id = ?', [(int)$id]);
        if (!$p) {
            abort(404);
        }
        $list = [];
        $error = null;
        try {
            $list = (new Provider($p))->services();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        $existing = array_flip(DB::column('SELECT provider_service_id FROM services WHERE provider_id = ?', [$p['id']]));
        return view('admin/provider_import', self::P + [
            'title' => 'ورود سرویس از ' . $p['name'],
            'p' => $p,
            'list' => array_slice($list, 0, 3000),
            'error' => $error,
            'existing' => $existing,
            'categories' => DB::all('SELECT id, name FROM categories ORDER BY sort, id'),
        ], 'panel');
    }

    public function providerImportSave(string $id): never
    {
        $p = DB::row('SELECT * FROM providers WHERE id = ?', [(int)$id]);
        $ids = array_map('strval', (array)($_POST['pick'] ?? []));
        $cat = input_int('category_id');
        if (!$p || !$ids || !DB::value('SELECT id FROM categories WHERE id = ?', [$cat])) {
            fail('سرویس‌ها و دسته‌بندی مقصد را انتخاب کنید.');
        }
        $markup = max(0, (float)en_digits((string)input('markup', '30')));
        $rate = max(0.0001, (float)en_digits((string)input('rate', '1')));
        $list = [];
        try {
            foreach ((new Provider($p))->services() as $s) {
                $list[(string)$s['service']] = $s;
            }
        } catch (Throwable $e) {
            fail('دریافت لیست سرویس‌ها ناموفق بود: ' . $e->getMessage());
        }
        $n = 0;
        foreach ($ids as $sid) {
            if (!isset($list[$sid]) || DB::value('SELECT id FROM services WHERE provider_id = ? AND provider_service_id = ?', [$p['id'], $sid])) {
                continue;
            }
            $s = $list[$sid];
            $cost = (int)ceil((float)$s['rate'] * $rate);
            $title = mb_substr(trim((string)$s['name']), 0, 160);
            $slug = slugify($title);
            if (DB::value('SELECT id FROM services WHERE slug = ?', [$slug])) {
                $slug .= '-' . $sid;
            }
            DB::insert('services', [
                'category_id' => $cat,
                'provider_id' => $p['id'],
                'provider_service_id' => $sid,
                'title' => $title,
                'slug' => $slug,
                'description' => (string)($s['description'] ?? ''),
                'type' => input('type', 'other'),
                'price' => max(1, (int)ceil($cost * (1 + $markup / 100))),
                'cost' => $cost,
                'min_qty' => max(1, (int)($s['min'] ?? 100)),
                'max_qty' => max(1, (int)($s['max'] ?? 100000)),
                'stock' => null,
                'start_time' => 'خودکار',
                'is_active' => input('activate') === '1' ? 1 : 0,
            ]);
            $n++;
        }
        flash('success', fa($n) . ' سرویس با موفقیت وارد شد. سفارش‌های این سرویس‌ها به صورت خودکار به API ارسال می‌شوند.');
        redirect('admin/services?state=api');
    }
}
