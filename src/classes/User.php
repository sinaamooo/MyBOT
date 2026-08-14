<?php

namespace TelegramShop\Classes;

class User {
    private $db;
    private $userId;
    private $userData;

    public function __construct($userId) {
        $this->db = new Database();
        $this->userId = $userId;
        $this->loadUser();
    }

    private function loadUser() {
        $this->userData = $this->db->getOne('users', ['telegram_id' => $this->userId]);
    }

    public static function create($telegramId, $firstName, $username = null, $phoneNumber = null) {
        $db = new Database();
        $now = date('Y-m-d H:i:s');

        $data = [
            'telegram_id' => $telegramId,
            'first_name' => $firstName,
            'username' => $username,
            'phone' => $phoneNumber,
            'balance' => 0,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now
        ];

        return $db->insert('users', $data);
    }

    public function exists() {
        return $this->userData !== null;
    }

    public function getData() {
        return $this->userData;
    }

    public function getBalance() {
        return $this->userData['balance'] ?? 0;
    }

    public function updateBalance($amount) {
        $newBalance = $this->getBalance() + $amount;
        return $this->db->update('users', [
            'balance' => $newBalance,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['telegram_id' => $this->userId]);
    }

    public function setPhoneNumber($phone) {
        return $this->db->update('users', [
            'phone' => $phone,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['telegram_id' => $this->userId]);
    }

    public function setPaymentMethod($method) {
        $methods = array_keys(PAYMENT_METHODS);
        if (!in_array($method, $methods)) {
            return false;
        }

        return $this->db->update('users', [
            'payment_method' => $method,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['telegram_id' => $this->userId]);
    }

    public function getPurchases() {
        return $this->db->getAll('purchases', ['buyer_id' => $this->userId]);
    }

    public function getSales() {
        return $this->db->getAll('sales', ['seller_id' => $this->userId]);
    }
}
