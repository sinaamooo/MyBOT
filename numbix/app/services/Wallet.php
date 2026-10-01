<?php

/**
 * User wallet. Every balance change is atomic and leaves a transaction row.
 */
final class Wallet
{
    public const TYPES = [
        'deposit' => ['شارژ کیف پول', 'success'],
        'order' => ['خرید سرویس', 'primary'],
        'refund' => ['بازگشت وجه', 'info'],
        'admin_add' => ['افزایش توسط مدیریت', 'success'],
        'admin_sub' => ['کسر توسط مدیریت', 'danger'],
        'bonus' => ['هدیه', 'pink'],
    ];

    public static function credit(int $userId, int $amount, string $type, string $description, ?string $refType = null, ?int $refId = null): int
    {
        if ($amount <= 0) {
            return (int)DB::value('SELECT balance FROM users WHERE id = ?', [$userId]);
        }
        return DB::transaction(static function () use ($userId, $amount, $type, $description, $refType, $refId) {
            $spentDelta = $type === 'refund' ? $amount : 0;
            DB::query('UPDATE users SET balance = balance + ?, total_spent = GREATEST(0, total_spent - ?) WHERE id = ?', [$amount, $spentDelta, $userId]);
            $balance = (int)DB::value('SELECT balance FROM users WHERE id = ?', [$userId]);
            DB::insert('transactions', [
                'user_id' => $userId,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $balance,
                'description' => mb_substr($description, 0, 255),
                'ref_type' => $refType,
                'ref_id' => $refId,
            ]);
            return $balance;
        });
    }

    /** Returns false when the balance is not enough. */
    public static function debit(int $userId, int $amount, string $type, string $description, ?string $refType = null, ?int $refId = null): bool
    {
        if ($amount <= 0) {
            return true;
        }
        return DB::transaction(static function () use ($userId, $amount, $type, $description, $refType, $refId) {
            $spent = $type === 'order' ? $amount : 0;
            $ok = DB::query(
                'UPDATE users SET balance = balance - ?, total_spent = total_spent + ? WHERE id = ? AND balance >= ?',
                [$amount, $spent, $userId, $amount]
            )->rowCount();
            if (!$ok) {
                return false;
            }
            $balance = (int)DB::value('SELECT balance FROM users WHERE id = ?', [$userId]);
            DB::insert('transactions', [
                'user_id' => $userId,
                'type' => $type,
                'amount' => -$amount,
                'balance_after' => $balance,
                'description' => mb_substr($description, 0, 255),
                'ref_type' => $refType,
                'ref_id' => $refId,
            ]);
            return true;
        });
    }

    public static function balance(int $userId): int
    {
        return (int)DB::value('SELECT balance FROM users WHERE id = ?', [$userId]);
    }
}
