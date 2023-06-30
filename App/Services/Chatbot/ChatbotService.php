<?php

namespace App\Services\Chatbot;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Actions\Encoding;
use App\Services\Chatbot\ChatbotDTO;
use App\Services\Log\LogService;
use Orhanerday\OpenAi\OpenAi;
use VK\Client\VKApiClient;

class ChatbotService
{
    public $vk;
    public $ai;
    public $text;
    public $random;
    public $id_user;
    public $id_log;
    public $peer_id;
    public $group_id;
    public $access_token;
    public $open_ai_key;
    public $conversation_message_id;

    public function __construct($data, $config, $id)
    {
        $this->text = $data['object']['message']['text'];
        $this->peer_id = $data['object']['message']['peer_id'];
        $this->group_id = $data['group_id'];
        $this->id_user = $data['object']['message']['from_id'];
        $this->id_log = $id;
        $this->conversation_message_id = $data['object']['message']['conversation_message_id'];
        $this->vk = new VKApiClient();
        $this->access_token = $config['chatbot_service']['access_token'];
        $this->open_ai_key = $config['chatbot_service']['open_ai_key'];
        $this->ai = new OpenAi($this->open_ai_key);
        $this->random = ChatbotDTO::RANDOM_ANSWERS;
    }

    /**
     * @throws \Exception
     */
    public function start()
    {
        try {

            if (!$this->checkIgnoreList()) {
                throw new \Exception('Пользователь или группа из игнор-листа');
            }

            $result = $this->callChatbotApi();
            $text = $result->choices[0]->text;

            if (mb_substr($this->text,0,1)==='!'){
                throw new \Exception('Сообщение является командой');
            }

            $count = $this->getInvalidCharactersCount($text);

            if ($count > 5) {
                throw new \Exception('Некорректные символы в ответе');
            }

            if (mb_strlen($text) < 5) {
                throw new \Exception('Слишком короткий ответ');
            }

            if (mb_strlen($text) > 220) {
                $text = ChatbotDTO::RANDOM_ANSWERS[rand(0, 250)] ?? null;
            }

            if (isset($text) and $text !== '') {
                if (rand(0,10)>5){
                    $this->sendMessage($text);
                }
            }

        } catch (\Exception $ex) {
            LogService::test(['err' => $ex->getMessage()]);
        }

    }

    public function checkIgnoreList(): bool
    {
        if (in_array(intval($this->id_user), ChatbotDTO::IGNORE_LIST)) {
            return false;
        }
        return true;
    }

    public function getInvalidCharactersCount($text)
    {
        $text = Encoding::make_utf8($text);
        preg_match_all("/[^а-я ,.!?]+/iu", $text, $matches);
        $count = 0;
        if (isset($matches[0]) and is_array($matches[0])) {
            foreach ($matches[0] as $item) {
                $count = $count + mb_strlen($item);
            }
        }
        return $count;
    }

    public function callChatbotApi()
    {
        $complete = $this->ai->completion([
            'model' => ChatbotDTO::MODEL,
            'prompt' => $this->text,
            'temperature' => 0.8,
            'max_tokens' => 500,
            'frequency_penalty' => 0.5,
            'presence_penalty' => 0,
        ]);

        $complete = json_decode($complete);

        if (!isset($complete->choices[0]->text)) {
            throw new \Exception('Некорректный ответ');
        }

        return $complete;
    }

    public function sendMessage($msg)
    {
        $resp = $this->vk->messages()->getByConversationMessageId($this->access_token, array(
            'peer_id' => $this->peer_id,
            'conversation_message_ids' => $this->conversation_message_id,
            'extended' => 1,
            'group_id' => $this->group_id,
            //'fields' => array('name'),
        ));

        $msg_id = $resp['items'][0]['id'];

        $forward = array(
            'peer_id' => $this->peer_id,
            'conversation_message_id' => $this->conversation_message_id,
            'is_reply' => $msg
        );

        $response = $this->vk->messages()->send($this->access_token, array(
            'peer_id' => $this->peer_id,
            'random_id' => rand(0, 9) . 7 . rand(1, 999),
            "chat_id" => $this->peer_id * 1 - 2000000000,
            "message" => $msg,
            'group_id' => $this->group_id,
            'reply_to' => $msg_id ?? '',
        ));
    }
}