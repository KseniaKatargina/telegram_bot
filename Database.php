<?php

class Database
{
    private $pdo;

    public function __construct($host, $dbname, $user, $password) {
        $dsn = "pgsql:host=$host;dbname=$dbname";
        $this->pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }

    public function getUserByTelegramId($telegramId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE telegram_id = :telegram_id");
        $stmt->execute(['telegram_id' => $telegramId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUser($telegramId) {
        $stmt = $this->pdo->prepare("INSERT INTO users (telegram_id) VALUES (:telegram_id)");
        $stmt->execute(['telegram_id' => $telegramId]);
    }

    public function updateUserBalance($telegramId, $newBalance) {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("UPDATE users SET balance = :balance WHERE telegram_id = :telegram_id");
            $stmt->execute(['balance' => $newBalance, 'telegram_id' => $telegramId]);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function isUpdateProcessed($updateId) {
        $stmt = $this->pdo->prepare("SELECT 1 FROM processed_updates WHERE update_id = :update_id");
        $stmt->execute(['update_id' => $updateId]);
        return $stmt->fetchColumn() !== false;
    }

    public function markUpdateAsProcessed($updateId) {
        $stmt = $this->pdo->prepare("INSERT INTO processed_updates (update_id) VALUES (:update_id)");
        $stmt->execute(['update_id' => $updateId]);
    }
}