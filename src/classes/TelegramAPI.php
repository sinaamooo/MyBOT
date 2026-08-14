<?php

namespace TelegramShop\Classes;

class TelegramAPI {
    private $apiUrl;
    private $botToken;

    public function __construct() {
        $this->botToken = BOT_TOKEN;
        $this->apiUrl = TELEGRAM_API_URL;
    }

    public function sendMessage($chatId, $text, $options = []) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        if (isset($options['reply_markup'])) {
            $data['reply_markup'] = json_encode($options['reply_markup']);
        }

        if (isset($options['disable_web_page_preview'])) {
            $data['disable_web_page_preview'] = $options['disable_web_page_preview'];
        }

        return $this->apiCall('sendMessage', $data);
    }

    public function editMessage($chatId, $messageId, $text, $options = []) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        if (isset($options['reply_markup'])) {
            $data['reply_markup'] = json_encode($options['reply_markup']);
        }

        return $this->apiCall('editMessageText', $data);
    }

    public function answerCallbackQuery($callbackQueryId, $text, $options = []) {
        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $options['show_alert'] ?? false
        ];

        return $this->apiCall('answerCallbackQuery', $data);
    }

    public function getChat($chatId) {
        return $this->apiCall('getChat', ['chat_id' => $chatId]);
    }

    public function getChatMember($chatId, $userId) {
        return $this->apiCall('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
    }

    public function restrictChatMember($chatId, $userId, $permissions) {
        $data = [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => json_encode($permissions)
        ];

        return $this->apiCall('restrictChatMember', $data);
    }

    public function getMe() {
        return $this->apiCall('getMe', []);
    }

    private function apiCall($method, $data) {
        $url = $this->apiUrl . '/' . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Logger::error("Telegram API Error ($method): HTTP $httpCode - $response");
            return false;
        }

        return json_decode($response, true);
    }
}
