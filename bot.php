<?php

require 'vendor/autoload.php';
require 'Database.php';

use TelegramBot\Api\Client;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;

$config = require 'config.php';

$bot = new Client($config['telegram_token']);
try {
    $bot_api = new BotApi($config['telegram_token']);
    $bot_api->setCurlOption(CURLOPT_SSL_VERIFYPEER, false);
    $bot_api->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
    $bot_api->deleteWebhook();
} catch (Exception $e) {
    file_put_contents('debug.log', "{$e->getMessage()}\n", FILE_APPEND);
}

$db = new Database(
    $config['db']['host'],
    $config['db']['dbname'],
    $config['db']['user'],
    $config['db']['password']
);

/**
 * @throws \TelegramBot\Api\Exception
 * @throws \TelegramBot\Api\InvalidArgumentException
 */
function handleUpdate(Update $update, BotApi $bot_api, Database $db): void
{
    $message = $update->getMessage();
    if (!$message) {
        return;
    }

    $chatId = $message->getChat()->getId();
    $text = trim($message->getText());
    $updateId = $update->getUpdateId();

    if ($db->isUpdateProcessed($updateId)) {
        return;
    }

    $db->markUpdateAsProcessed($updateId);
    $user = $db->getUserByTelegramId($chatId);


    if (!$user) {
        $db->createUser($chatId);
        $response = 'Добро пожаловать! Ваш аккаунт создан. Отправьте число для пополнения или списания средств.';
        $bot_api->sendMessage($chatId, $response);
        return;
    }

    if (is_numeric(str_replace(',', '.', $text))) {
        $amount = (float)str_replace(',', '.', $text);
        $currentBalance = $user['balance'];

        if ($amount > 9999999999.99 || $amount < -9999999999.99) {
            $response = "Ошибка: значение баланса выходит за допустимый диапазон.";
        } elseif ($amount < 0 && abs($amount) > $currentBalance) {
            $response = "Ошибка: недостаточно средств на счете.";
        } else {
            $newBalance = $currentBalance + $amount;
            if ($newBalance > 9999999999.99 || $newBalance < -9999999999.99) {
                $response = "Ошибка: новое значение баланса выходит за допустимый диапазон.";
            } else {
                try {
                    $db->updateUserBalance($chatId, $newBalance);
                    $response = "Ваш баланс: $" . number_format($newBalance, 2);
                } catch (Exception $e) {
                    file_put_contents('debug.log', "Error updating balance: {$e->getMessage()}\n", FILE_APPEND);
                    $response = "Ошибка обновления баланса. Попробуйте снова позже.";
                }
            }
        }
    } else {
        $response = "Пожалуйста, отправьте число для пополнения или списания со счета.";
    }
    $bot_api->sendMessage($chatId, $response);
}

$offset = 0;
while (true) {
    try {
        $updates = $bot_api->getUpdates([
            'offset' => $offset,
            'limit' => 10,
            'timeout' => 30
        ]);

        if (!empty($updates)) {
            foreach ($updates as $updateData) {
                handleUpdate($updateData, $bot_api, $db);
                $lastUpdateId = $updateData->getUpdateId();
                $offset = $lastUpdateId + 1;
            }
        }
    } catch (\TelegramBot\Api\Exception $e) {
        file_put_contents('debug.log', "Error fetching updates: {$e->getMessage()}\n", FILE_APPEND);
    }
    sleep(1);
}