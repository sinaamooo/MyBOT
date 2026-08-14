<?php

namespace TelegramShop\Classes;

class UserFile {
    private $storage;
    private $userId;
    private $userData;

    public function __construct($userId) {
        $this->storage = new FileStorage();
        $this->userId = $userId;
        $this->loadUser();
    }

    private function loadUser() {
        $this->userData = $this->storage->getBy('users', 'telegram_id', $this->userId);
    }

    public static function create($telegramId, $firstName, $username = null, $phoneNumber = null) {
        $storage = new FileStorage();

        $userData = [
            'telegram_id' => $telegramId,
            'first_name' => $firstName,
            'username' => $username,
            'phone' => $phoneNumber,
            'balance' => 0,
            'status' => 'active',
            'payment_method' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $storage->save('users', $userData);
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
        if (!$this->userData) return false;

        $newBalance = $this->getBalance() + $amount;
        $this->userData['balance'] = $newBalance;
        $this->userData['updated_at'] = date('Y-m-d H:i:s');

        $this->storage->save('users', $this->userData);
        return true;
    }

    public function setPhoneNumber($phone) {
        if (!$this->userData) return false;

        $this->userData['phone'] = $phone;
        $this->userData['updated_at'] = date('Y-m-d H:i:s');

        $this->storage->save('users', $this->userData);
        return true;
    }

    public function setPaymentMethod($method) {
        $methods = array_keys(PAYMENT_METHODS);
        if (!in_array($method, $methods)) {
            return false;
        }

        if (!$this->userData) return false;

        $this->userData['payment_method'] = $method;
        $this->userData['updated_at'] = date('Y-m-d H:i:s');

        $this->storage->save('users', $this->userData);
        return true;
    }

    public function getPurchases() {
        $purchases = $this->storage->getAll('purchases');
        $result = [];

        foreach ($purchases as $purchase) {
            if ($purchase['buyer_id'] == $this->userId) {
                $result[] = $purchase;
            }
        }

        return $result;
    }

    public function getSales() {
        $sales = $this->storage->getAll('sales');
        $result = [];

        foreach ($sales as $sale) {
            if ($sale['seller_id'] == $this->userId) {
                $result[] = $sale;
            }
        }

        return $result;
    }
}
