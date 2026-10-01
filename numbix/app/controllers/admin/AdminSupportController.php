<?php

final class AdminSupportController
{
    private const P = ['area' => 'admin'];

    public function index(): string
    {
        $status = input('status', 'waiting');
        $where = '1=1';
        $params = [];
        if ($status === 'waiting') {
            $where = "t.status IN ('open','customer_reply')";
        } elseif (isset(ticket_statuses()[$status])) {
            $where = 't.status = ?';
            $params[] = $status;
        }
        if ($q = trim((string)input('q', ''))) {
            $where .= ' AND (t.subject LIKE ? OR t.id = ? OR u.email LIKE ?)';
            array_push($params, "%$q%", (int)en_digits($q), "%$q%");
        }
        $page = paginate('t.*, u.first_name, u.last_name, u.email, u.avatar, u.id AS uid, (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS msgs',
            "FROM tickets t JOIN users u ON u.id = t.user_id WHERE $where", $params, 20, "ORDER BY FIELD(t.priority, 'high', 'normal', 'low'), t.updated_at DESC");
        $counts = DB::row("SELECT SUM(status IN ('open','customer_reply')) waiting, SUM(status = 'answered') answered, SUM(status = 'closed') closed, COUNT(*) total FROM tickets");
        return view('admin/tickets', self::P + ['title' => 'تیکت‌ها', 'page' => $page, 'status' => $status, 'counts' => $counts], 'panel');
    }

    public function show(string $id): string
    {
        $t = DB::row('SELECT t.*, u.first_name, u.last_name, u.email, u.mobile, u.balance, u.avatar, u.id AS uid FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.id = ?', [(int)$id]);
        if (!$t) {
            abort(404, 'تیکت پیدا نشد.');
        }
        $msgs = DB::all('SELECT m.*, u.first_name, u.last_name, u.avatar, u.id AS uid FROM ticket_messages m JOIN users u ON u.id = m.user_id WHERE m.ticket_id = ? ORDER BY m.id', [$t['id']]);
        $orders = DB::all('SELECT o.id, o.status, o.quantity, s.title FROM orders o JOIN services s ON s.id = o.service_id WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 5', [$t['uid']]);
        $replies = array_filter(array_map('trim', explode("\n", (string)setting('canned_replies', "سلام، سفارش شما در صف انجام است و به‌زودی تکمیل می‌شود.\nسلام، سفارش شما تکمیل شد. از اعتماد شما سپاسگزاریم.\nسلام، لطفاً مطمئن شوید پیج شما عمومی (Public) است."))));
        return view('admin/ticket', self::P + ['title' => 'تیکت #' . fa($t['id']), 't' => $t, 'msgs' => $msgs, 'orders' => $orders, 'replies' => $replies], 'panel');
    }

    public function reply(string $id): never
    {
        $t = DB::row('SELECT * FROM tickets WHERE id = ?', [(int)$id]);
        $message = trim((string)input('message'));
        if (!$t || mb_strlen($message) < 2) {
            fail('متن پاسخ را وارد کنید.');
        }
        $att = null;
        if (Uploader::has('attachment')) {
            try {
                $att = Uploader::file('attachment', 'tickets', 4096);
            } catch (RuntimeException $e) {
                fail($e->getMessage());
            }
        }
        DB::insert('ticket_messages', ['ticket_id' => $t['id'], 'user_id' => Auth::id(), 'is_admin' => 1, 'message' => mb_substr($message, 0, 5000), 'attachment' => $att]);
        $status = input('close') === '1' ? 'closed' : 'answered';
        DB::update('tickets', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$t['id']]);
        Notifier::user((int)$t['user_id'], 'پاسخ جدید به تیکت شما', $t['subject'], 'dashboard/tickets/' . $t['id'], 'message');
        flash('success', 'پاسخ ارسال شد.');
        redirect(input('next') === 'list' ? 'admin/tickets' : 'admin/tickets/' . $t['id']);
    }

    public function status(string $id): never
    {
        $st = (string)input('status');
        $pr = (string)input('priority');
        $upd = [];
        if (isset(ticket_statuses()[$st])) {
            $upd['status'] = $st;
        }
        if (in_array($pr, ['low', 'normal', 'high'], true)) {
            $upd['priority'] = $pr;
        }
        if ($upd) {
            DB::update('tickets', $upd, 'id = ?', [(int)$id]);
        }
        flash('success', 'تیکت بروزرسانی شد.');
        redirect('admin/tickets/' . (int)$id);
    }
}
