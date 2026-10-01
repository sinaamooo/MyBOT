<?php

final class AdminContentController
{
    private const P = ['area' => 'admin'];
    public const THEMES = ['violet' => 'بنفش کهکشانی', 'pink' => 'صورتی', 'ocean' => 'اقیانوسی', 'sunset' => 'غروب', 'emerald' => 'زمردی'];
    public const POSITIONS = ['services' => 'بالای صفحه خدمات'];

    public function banners(): string
    {
        return view('admin/banners', self::P + [
            'title' => 'بنرها و تبلیغات',
            'rows' => DB::all('SELECT * FROM banners ORDER BY sort, id DESC'),
            'edit' => input_int('edit'),
        ], 'panel');
    }

    public function bannerSave(): never
    {
        $id = input_int('id');
        $old = $id ? DB::row('SELECT * FROM banners WHERE id = ?', [$id]) : null;
        $title = trim((string)input('title'));
        if ($title === '') {
            fail('عنوان بنر را وارد کنید.');
        }
        $data = [
            'title' => mb_substr($title, 0, 160),
            'subtitle' => mb_substr((string)input('subtitle', ''), 0, 255) ?: null,
            'badge' => mb_substr((string)input('badge', ''), 0, 40) ?: null,
            'button_text' => mb_substr((string)input('button_text', ''), 0, 40) ?: null,
            'link' => mb_substr((string)input('link', ''), 0, 255) ?: null,
            'position' => array_key_exists(input('position'), self::POSITIONS) ? input('position') : 'services',
            'theme' => array_key_exists(input('theme'), self::THEMES) ? input('theme') : 'violet',
            'sort' => input_int('sort'),
            'is_active' => input('is_active') === '1' ? 1 : 0,
        ];
        if (Uploader::has('image')) {
            try {
                $data['image'] = Uploader::image('image', 'banners', 4096);
                Uploader::delete($old['image'] ?? null);
            } catch (RuntimeException $e) {
                fail($e->getMessage());
            }
        }
        $id ? DB::update('banners', $data, 'id = ?', [$id]) : DB::insert('banners', $data);
        flash('success', 'بنر ذخیره شد.');
        redirect('admin/banners');
    }

    public function bannerDelete(string $id): never
    {
        $b = DB::row('SELECT * FROM banners WHERE id = ?', [(int)$id]);
        if ($b) {
            Uploader::delete($b['image']);
            DB::delete('banners', 'id = ?', [$b['id']]);
        }
        flash('success', 'بنر حذف شد.');
        redirect('admin/banners');
    }

    public function index(): string
    {
        $tab = in_array(input('tab'), ['post', 'page', 'faq'], true) ? input('tab') : 'post';
        $rows = $tab === 'faq'
            ? DB::all('SELECT * FROM faqs ORDER BY sort, id')
            : DB::all('SELECT * FROM posts WHERE type = ? ORDER BY id DESC', [$tab]);
        $counts = DB::row("SELECT SUM(type = 'post') posts, SUM(type = 'page') pages, (SELECT COUNT(*) FROM faqs) faqs FROM posts");
        return view('admin/content', self::P + ['title' => 'مدیریت محتوا', 'tab' => $tab, 'rows' => $rows, 'counts' => $counts, 'editFaq' => input_int('edit')], 'panel');
    }

    public function form(?string $id = null): string
    {
        $p = $id ? DB::row('SELECT * FROM posts WHERE id = ?', [(int)$id]) : null;
        if ($id && !$p) {
            abort(404);
        }
        $type = $p['type'] ?? (input('type') === 'page' ? 'page' : 'post');
        return view('admin/content_form', self::P + ['title' => $p ? 'ویرایش محتوا' : ($type === 'page' ? 'صفحه جدید' : 'مقاله جدید'), 'p' => $p, 'type' => $type], 'panel');
    }

    public function save(): never
    {
        $id = input_int('id');
        $old = $id ? DB::row('SELECT * FROM posts WHERE id = ?', [$id]) : null;
        $type = $old['type'] ?? (input('type') === 'page' ? 'page' : 'post');
        $title = trim((string)input('title'));
        if (mb_strlen($title) < 2) {
            fail('عنوان را وارد کنید.');
        }
        $slug = slugify((string)(input('slug') ?: $title));
        if (DB::value('SELECT id FROM posts WHERE type = ? AND slug = ? AND id <> ?', [$type, $slug, $id])) {
            fail('این نامک قبلاً استفاده شده است.');
        }
        $data = [
            'type' => $type,
            'title' => mb_substr($title, 0, 200),
            'slug' => $slug,
            'excerpt' => mb_substr((string)input('excerpt', ''), 0, 400) ?: null,
            'content' => (string)($_POST['content'] ?? ''),
            'is_published' => input('is_published') === '1' ? 1 : 0,
        ];
        if (Uploader::has('cover')) {
            try {
                $data['cover'] = Uploader::image('cover', 'posts', 4096);
                Uploader::delete($old['cover'] ?? null);
            } catch (RuntimeException $e) {
                fail($e->getMessage());
            }
        }
        if ($old) {
            DB::update('posts', $data, 'id = ?', [$id]);
        } else {
            $data['author_id'] = Auth::id();
            $id = DB::insert('posts', $data);
        }
        flash('success', 'محتوا ذخیره شد.');
        redirect('admin/content?tab=' . $type);
    }

    public function delete(string $id): never
    {
        $p = DB::row('SELECT * FROM posts WHERE id = ?', [(int)$id]);
        if ($p) {
            Uploader::delete($p['cover']);
            DB::delete('posts', 'id = ?', [$p['id']]);
        }
        flash('success', 'حذف شد.');
        redirect('admin/content?tab=' . ($p['type'] ?? 'post'));
    }

    public function faqSave(): never
    {
        $id = input_int('id');
        $q = trim((string)input('question'));
        $a = trim((string)input('answer'));
        if ($q === '' || $a === '') {
            fail('سوال و پاسخ را وارد کنید.');
        }
        $data = ['question' => mb_substr($q, 0, 255), 'answer' => $a, 'sort' => input_int('sort'), 'is_active' => input('is_active') === '1' ? 1 : 0];
        $id ? DB::update('faqs', $data, 'id = ?', [$id]) : DB::insert('faqs', $data);
        flash('success', 'سوال ذخیره شد.');
        redirect('admin/content?tab=faq');
    }

    public function faqDelete(string $id): never
    {
        DB::delete('faqs', 'id = ?', [(int)$id]);
        flash('success', 'سوال حذف شد.');
        redirect('admin/content?tab=faq');
    }
}
