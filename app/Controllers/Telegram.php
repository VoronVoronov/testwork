<?php

namespace App\Controllers;

use App\Models\MessageModel;
use CURLFile;

class Telegram extends BaseController
{

    static private string $botToken = '6976716302:AAF0H4nS2RUInOpergzJKnzdyqxfLeOdGMM';

    public function index()
    {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);

        file_put_contents(WRITEPATH . 'logs/telegram.txt', $input . PHP_EOL, FILE_APPEND);

        if(!isset($update['message']) && !isset($update['callback_query'])){
            return 1;
        }

        if(isset($update['message'])){
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';
            $name = $message['from']['first_name'] ?? 'Друг';
        }

        if(isset($update['callback_query'])){
            $callback_query = $update['callback_query'];
            $data = $callback_query['data'] ?? '';
            $chatId = $callback_query['from']['id'];
            $name = $callback_query['from']['first_name'] ?? 'Друг';
            $text = null;
        }

        if (isset($message['photo']) || isset($message['video'])) {
            if(isset($message['photo'])){
                $fileId = $message['photo'][2]['file_id'];
            } else {
                $fileId = $message['video']['file_id'];
            }
            $filePath = $this->getFile($fileId);

            if ($filePath) {
                $fileUrl = "https://api.telegram.org/file/bot".self::$botToken."/".$filePath;
                $savedPath = $this->saveFile($fileUrl);
                $responseMessage = "Спасибо за " . (isset($message['photo']) ? "фото" : "видео") . ". Вот ссылка на него: $savedPath";
                $this->saveMessage('text', $responseMessage, null, $chatId, date('Y-m-d H:i:s'));

                if (isset($message['photo'])) {
                    $post_fields = array(
                        'chat_id' => $chatId,
                        'photo' => new CURLFile($savedPath),
                        'caption' => 'Вы отправили нам это фото'
                    );
                    self::send('photo', $post_fields, true);
                } else {
                    $post_fields = array(
                        'chat_id' => $chatId,
                        'video' => new CURLFile($savedPath),
                        'caption' => $responseMessage
                    );
                    self::send('video', $post_fields, true);
                }
            }

            return 3;
        }

        if ($this->isGreeting($text)) {
            $this->saveMessage('text', $text, null, $chatId, date('Y-m-d H:i:s'));
            $this->sendGreeting($chatId, $name);
        } else if(!empty($data)){
            $post_fields = array(
                'chat_id' => $chatId,
                'text' => "Что подсказать?"
            );
            $this->saveMessage('text', $post_fields['text'], json_encode($post_fields), $chatId, date('Y-m-d H:i:s'));
            self::send('text', json_encode($post_fields), true);
        } else {
            $post_fields = array(
                'chat_id' => $chatId,
                'text' => "Спасибо за сообщение"
            );
            $this->saveMessage('text', $post_fields['text'], json_encode($post_fields), $chatId, date('Y-m-d H:i:s'));
            self::send('text', json_encode($post_fields), true);
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
        } else if($type == 'video') {
            $url .= $token.'/sendVideo';
            $headers = array(
                'Content-Type: multipart/form-data'
            );
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
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
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
