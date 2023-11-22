<?php

namespace App\Controllers;

use App\Models\MessageModel;

class Telegram extends BaseController
{

    static private string $botToken = '6976716302:AAF0H4nS2RUInOpergzJKnzdyqxfLeOdGMM';

    public function index()
    {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);

        file_put_contents(WRITEPATH . 'logs/telegram.txt', $input . PHP_EOL, FILE_APPEND);

        if (!isset($update['message'])) {
            return 2;
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $name = $message['from']['first_name'] ?? 'Друг';
        $data = $message['callback_query']['data'] ?? '';


        if (isset($message['photo']) || isset($message['video'])) {
            $fileId = $message['photo'] ? $message['photo'][0]['file_id'] : $message['video']['file_id'];
            $filePath = $this->getFile($fileId);

            if ($filePath) {
                $fileUrl = "https://api.telegram.org/file/bot".self::$botToken."/".$filePath;
                $savedPath = $this->saveFile($fileUrl);
                $responseMessage = "Спасибо за " . (isset($message['photo']) ? "фото" : "видео") . ". Вот ссылка на него: $savedPath";
                $this->saveMessage('text', $responseMessage, null, $chatId, date('Y-m-d H:i:s'));
                if (isset($message['photo'])) {
                    $post_fields = array(
                        'chat_id' => $chatId,
                        'photo' => $savedPath,
                        'caption' => 'Вы отправили нам это фото'
                    );
                } else {
                    $post_fields = array(
                        'chat_id' => $chatId,
                        'video' => $savedPath,
                        'caption' => $responseMessage
                    );
                }
                self::send('photo', $post_fields, true);
            }
        }

        if(!empty($data)){
            $post_fields = array(
                'chat_id' => $chatId,
                'text' => "Что подсказать?"
            );
            self::send('text', json_encode($post_fields), true);
        }

        if ($this->isGreeting($text)) {
            $this->saveMessage('text', $text, null, $chatId, date('Y-m-d H:i:s'));
            $this->sendGreeting($chatId, $name);
        } else {
            $post_fields = array(
                'chat_id' => $chatId,
                'text' => "Спасибо за сообщение"
            );
            $this->saveMessage('text', null, json_encode($post_fields), $chatId, date('Y-m-d H:i:s'));
        }

        return 1;
    }

    private function isGreeting($text): bool
    {
        $greetings = ['привет', 'здравствуйте', 'добрый день', 'доброе утро', '/start'];
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
        $this->saveMessage('text', $text, null, $chatId, date('Y-m-d H:i:s'));
        $post_fields = array(
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $keyboard
        );
        self::send('text', json_encode($post_fields), true);
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
        $path = $_SERVER['DOCUMENT_ROOT'].'/uploads/' . basename($fileUrl);
        file_put_contents($path, file_get_contents($fileUrl));
        return config('App')->baseURL . 'uploads/' . basename($fileUrl);
    }

    public static function send($type, $post_fields, $header = false)
    {
        $token = self::$botToken;
        $url = 'https://api.telegram.org/bot';
        $headers = array(
            'Content-Type: application/json'
        );

        if ($type == 'text') {
            $url .= $token.'/sendMessage';
        } else if($type == 'photo') {
            $url .= $token.'/sendPhoto';
            $headers = array(
                'Content-Type: multipart/form-data'
            );
            $chat_id = $post_fields['chat_id'];
            $photo = $post_fields['photo'];
            $caption = $post_fields['caption'];
            $post_fields = http_build_query(array(
                'chat_id' => $chat_id,
                'photo' => $photo,
                'caption' => $caption
            ));
        }

        set_time_limit(0);
        $ch = curl_init();

        if($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL             =>  $url,
                CURLOPT_POST            =>  TRUE,
                CURLOPT_RETURNTRANSFER  =>  TRUE,
                CURLOPT_TIMEOUT         =>  10,
                CURLOPT_POSTFIELDS      => $post_fields
            )
        );
        curl_exec($ch);
        curl_close($ch);
    }

    public static function saveMessage($message_type, $content, $payload, $user_id, $created_at): int
    {
        $messageModel = new MessageModel();
        $messageModel->insert([
            'message_type' => $message_type,
            'content' => $content,
            'payload' => $payload,
            'user_id' => $user_id,
            'created_at' => $created_at
        ]);
        return $messageModel->getInsertID();
    }
}
