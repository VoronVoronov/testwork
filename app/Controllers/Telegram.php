<?php

namespace App\Controllers;

use CURLFile;
use App\Models\MessageModel;
use ReflectionException;

class Telegram extends BaseController
{

    static private string $botToken = '6976716302:AAF0H4nS2RUInOpergzJKnzdyqxfLeOdGMM';

    public function index() {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);

        if (!isset($update['message'])) {
            return;
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $name = $message['from']['first_name'] ?? 'Друг';


        if (isset($message['photo']) || isset($message['video'])) {
            $fileId = $message['photo'] ? $message['photo'][0]['file_id'] : $message['video']['file_id'];
            $filePath = $this->getFile($fileId);

            if ($filePath) {
                $fileUrl = "https://api.telegram.org/file/bot".self::$botToken."/".$filePath;
                $savedPath = $this->saveFile($fileUrl);
                $responseMessage = "Спасибо за " . (isset($message['photo']) ? "фото" : "видео") . ". Вот ссылка на него: $savedPath";
                $this->saveMessage([
                    'message_type' => 'text',
                    'content' => $responseMessage,
                    'user_id' => $chatId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                if (isset($message['photo'])) {
                    $this->sendPhoto($chatId, $savedPath, "Вы отправили нам это фото");
                } else {
                    $this->sendMessage($chatId, $responseMessage);
                }
            }
        }

        if ($this->isGreeting($text)) {
            $this->saveMessage([
                'message_type' => 'text',
                'content' => $text,
                'user_id' => $chatId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $this->sendGreeting($chatId, $name);
        } else {
            $this->sendMessage($chatId, "Спасибо за сообщение");
        }
    }

    private function isGreeting($text): bool
    {
        $greetings = ['привет', 'здравствуйте', 'добрый день', 'доброе утро'];
        foreach ($greetings as $greeting) {
            if (strpos(strtolower($text), $greeting) !== false) {
                return true;
            }
        }
        return false;
    }

    private function sendGreeting($chatId, $name) {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Вопрос', 'callback_data' => 'question'],
                    ['text' => 'Сайт', 'url' => 'https://www.google.com']
                ]
            ]
        ];

        $text = "Здравствуйте, $name";
        $this->saveMessage([
            'message_type' => 'text',
            'content' => $text,
            'user_id' => $chatId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $this->sendMessage($chatId, $text, $keyboard);
    }

    private function getFile($fileId) {
        $response = file_get_contents("https://api.telegram.org/bot".self::$botToken."/getFile?file_id=".$fileId);
        $response = json_decode($response, true);

        if ($response['ok']) {
            return $response['result']['file_path'];
        }

        return false;
    }

    private function saveFile($fileUrl): string
    {
        $path = 'uploads/' . basename($fileUrl);
        file_put_contents($path, file_get_contents($fileUrl));
        return $path;
    }

    private function sendPhoto($chatId, $photo, $caption = ''): void
    {
        $data = [
            'chat_id' => $chatId,
            'photo' => new CURLFile(realpath($photo)),
            'caption' => $caption
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot".self::$botToken."/sendPhoto");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_exec($ch);

    }

    private function sendMessage($chatId, $text, $keyboard = null): void
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }
        $this->saveMessage([
            'message_type' => 'text',
            'content' => json_encode($data),
            'user_id' => $chatId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        file_get_contents("https://api.telegram.org/bot".self::$botToken."/sendMessage?" . http_build_query($data));
    }

    /**
     * @throws ReflectionException
     */
    private function saveMessage($message) {
        $messageModel = new MessageModel();
        $messageModel->insert($message);
    }
}
