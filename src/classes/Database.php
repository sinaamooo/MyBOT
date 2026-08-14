<?php

namespace TelegramShop\Classes;

class Database {
    private $conn;
    private $host;
    private $user;
    private $pass;
    private $db;
    private $charset;

    public function __construct() {
        $this->host = DB_HOST;
        $this->user = DB_USER;
        $this->pass = DB_PASS;
        $this->db = DB_NAME;
        $this->charset = DB_CHARSET;
        $this->connect();
    }

    private function connect() {
        try {
            $this->conn = new \mysqli(
                $this->host,
                $this->user,
                $this->pass,
                $this->db
            );

            if ($this->conn->connect_error) {
                throw new \Exception("Connection failed: " . $this->conn->connect_error);
            }

            $this->conn->set_charset($this->charset);
        } catch (\Exception $e) {
            Logger::error("Database connection error: " . $e->getMessage());
            die("Database connection failed");
        }
    }

    public function query($sql, $params = []) {
        if (!empty($params)) {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                Logger::error("Prepare failed: " . $this->conn->error);
                return false;
            }

            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) $types .= 'i';
                elseif (is_float($param)) $types .= 'd';
                else $types .= 's';
            }

            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            return $stmt;
        } else {
            return $this->conn->query($sql);
        }
    }

    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $values = implode(',', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO $table ($columns) VALUES ($values)";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            Logger::error("Insert prepare failed: " . $this->conn->error);
            return false;
        }

        $types = '';
        $values = [];
        foreach ($data as $value) {
            if (is_int($value)) $types .= 'i';
            elseif (is_float($value)) $types .= 'd';
            else $types .= 's';
            $values[] = $value;
        }

        $stmt->bind_param($types, ...$values);
        return $stmt->execute();
    }

    public function update($table, $data, $where) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "$key = ?";
        }
        $set = implode(', ', $set);

        $whereStr = [];
        foreach ($where as $key => $value) {
            $whereStr[] = "$key = ?";
        }
        $whereStr = implode(' AND ', $whereStr);

        $sql = "UPDATE $table SET $set WHERE $whereStr";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            Logger::error("Update prepare failed: " . $this->conn->error);
            return false;
        }

        $allValues = array_merge(array_values($data), array_values($where));
        $types = '';
        foreach ($allValues as $value) {
            if (is_int($value)) $types .= 'i';
            elseif (is_float($value)) $types .= 'd';
            else $types .= 's';
        }

        $stmt->bind_param($types, ...$allValues);
        return $stmt->execute();
    }

    public function getOne($table, $where) {
        $whereStr = [];
        foreach ($where as $key => $value) {
            $whereStr[] = "$key = ?";
        }
        $whereStr = implode(' AND ', $whereStr);

        $sql = "SELECT * FROM $table WHERE $whereStr LIMIT 1";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            Logger::error("Select prepare failed: " . $this->conn->error);
            return null;
        }

        $types = '';
        foreach ($where as $value) {
            if (is_int($value)) $types .= 'i';
            elseif (is_float($value)) $types .= 'd';
            else $types .= 's';
        }

        $stmt->bind_param($types, ...array_values($where));
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getAll($table, $where = null, $limit = null) {
        $sql = "SELECT * FROM $table";

        if ($where) {
            $whereStr = [];
            foreach ($where as $key => $value) {
                $whereStr[] = "$key = ?";
            }
            $sql .= " WHERE " . implode(' AND ', $whereStr);
        }

        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }

        $stmt = $this->conn->prepare($sql);

        if ($where) {
            $types = '';
            foreach ($where as $value) {
                if (is_int($value)) $types .= 'i';
                elseif (is_float($value)) $types .= 'd';
                else $types .= 's';
            }
            $stmt->bind_param($types, ...array_values($where));
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function delete($table, $where) {
        $whereStr = [];
        foreach ($where as $key => $value) {
            $whereStr[] = "$key = ?";
        }
        $whereStr = implode(' AND ', $whereStr);

        $sql = "DELETE FROM $table WHERE $whereStr";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            Logger::error("Delete prepare failed: " . $this->conn->error);
            return false;
        }

        $types = '';
        foreach ($where as $value) {
            if (is_int($value)) $types .= 'i';
            elseif (is_float($value)) $types .= 'd';
            else $types .= 's';
        }

        $stmt->bind_param($types, ...array_values($where));
        return $stmt->execute();
    }

    public function close() {
        $this->conn->close();
    }
}
