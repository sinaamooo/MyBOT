<?php

namespace TelegramShop\Classes;

class FileStorage {
    private $dataDir;

    public function __construct() {
        $this->dataDir = __DIR__ . '/../../storage/data';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    public function save($collection, $data) {
        $file = $this->dataDir . '/' . $collection . '.json';

        // خواندن داده‌های موجود
        $existing = [];
        if (file_exists($file)) {
            $existing = json_decode(file_get_contents($file), true) ?: [];
        }

        // اگر داده دارای ID است، آپدیت کن، وگرنه اضافه کن
        if (isset($data['id'])) {
            $existing[$data['id']] = $data;
        } else {
            $data['id'] = time() . '_' . uniqid();
            $existing[$data['id']] = $data;
        }

        file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $data;
    }

    public function getAll($collection) {
        $file = $this->dataDir . '/' . $collection . '.json';

        if (!file_exists($file)) {
            return [];
        }

        return json_decode(file_get_contents($file), true) ?: [];
    }

    public function getOne($collection, $id) {
        $all = $this->getAll($collection);
        return $all[$id] ?? null;
    }

    public function getBy($collection, $field, $value) {
        $all = $this->getAll($collection);

        foreach ($all as $item) {
            if (isset($item[$field]) && $item[$field] == $value) {
                return $item;
            }
        }

        return null;
    }

    public function delete($collection, $id) {
        $file = $this->dataDir . '/' . $collection . '.json';
        $all = $this->getAll($collection);

        unset($all[$id]);

        file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }

    public function update($collection, $id, $data) {
        $all = $this->getAll($collection);

        if (isset($all[$id])) {
            $all[$id] = array_merge($all[$id], $data);
            file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $all[$id];
        }

        return null;
    }
}
