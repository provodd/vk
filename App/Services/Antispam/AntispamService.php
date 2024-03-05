<?php

namespace App\Services\Antispam;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Services\Antispam\AntispamDTO;
use App\Services\Log\LogService;
use VK\Client\VKApiClient;
use App\Services\Database\DatabaseService;

class AntispamService
{
    public $vk;
    public $text;
    public $token;
    public $random;
    public $id_user;
    public $id_log;
    public $peer_id;
    public $group_id;
    public $access_token;
    public $data;

    public function __construct($data, $config, $id)
    {
        $this->text = $data['object']['message']['text'] ?? '';
        $this->peer_id = $data['object']['message']['peer_id'] ?? '';
        $this->group_id = $data['group_id'];
        $this->id_user = $data['object']['message']['from_id'] ?? $data['object']['user_id'] ?? $data['object']['from_id'];
        $this->id_log = $id;
        $this->vk = new VKApiClient();
        $this->token = $config['antispam_service']['access_token'];
        $this->data = $data;
    }

    /**
     * @throws \Exception
     */
    public function check()
    {
        try {
            $messages_count = $this->getMessagesCount();
            
            if (!$this->checkStopWords() AND $messages_count<3) {
                throw new \Exception('Фейк, спам, бот или просто нехороший человек. Кик');
            }

            $this->upsertUser();

        } catch (\Exception $ex) {
            $this->removeUserFromChat();
            $this->sendMessage($ex->getMessage());
        }

    }

    public function getMessagesCount(){
        $user = DatabaseService::findOne('chat_users', 'id_user=? AND id_group=? AND id_peer=?', array($this->id_user,$this->data['group_id'],$this->data['object']['message']['peer_id']));

        if (isset($user->message_count)) return $user->messages_count;
        return 0;
    }

    public function upsertUser()
    {
        if ($this->data['type'] === 'message_new') {
            $user = DatabaseService::findOne('chat_users', 'id_user=? AND id_group=? AND id_peer=?', array($this->id_user,$this->data['group_id'],$this->data['object']['message']['peer_id']));
            $response = $this->vk->users()->get($this->token, array(
                'user_ids' => array($this->id_user),
                'fields' => array('city, home_town, universities', 'bdate', 'photo_200', 'photo_400_orig', 'photo_id', 'sex', 'country', 'city', 'home_town', 'about')
            ));

            if (isset($response[0]['home_town'])) {
                if (!empty($response[0]['home_town'])) {
                    $town = $response[0]['home_town'];
                }
            }
            if (isset($response[0]['city'])) {
                if (!empty($response[0]['city'])) {
                    $city = $response[0]['city']['title'];
                }
            }
            $messages_count = 1;
            if ($user) {
                $user_chat = DatabaseService::load('chat_users', $user->id);
                $messages_count = $user_chat->messages_count + 1;
            } else {
                $user_chat = DatabaseService::xdispense('chat_users');
            }

            $user_chat->id_user = $this->id_user;
            $user_chat->id_group = $this->data['group_id'] ?? '';
            $user_chat->id_peer = $this->data['object']['message']['peer_id'] ?? '';

            $user_chat->first_name = $response[0]['first_name'] ?? '';
            $user_chat->last_name = $response[0]['last_name'] ?? '';
            $user_chat->birthdate = $response[0]['bdate'] ?? '';
            $user_chat->city = $city ?? $town;
            $user_chat->sex = $response[0]['sex'] ?? '';
            $user_chat->photo = $response[0]['photo_400_orig'] ?? '';
            $user_chat->messages_count = $messages_count;
            $user_chat->user = json_encode($response);
            $user_chat->payload = json_encode($this->data);
            $user_chat->created_at = date('Y-m-d H:i:s');
            DatabaseService::store($user_chat);
        }
    }

    public function removeUserFromChat()
    {
        return $this->vk->messages()->removeChatUser($this->token, array(
            'user_id' => $this->id_user,
            "chat_id" => $this->peer_id * 1 - 2000000000,
        ));
    }

    public function checkStopWords(): bool
    {
        if (in_array(strtolower($this->text), AntispamDTO::STOP_WORDS)) {
            return false;
        }
        return true;
    }

    public function sendMessage($msg)
    {
        $response = $this->vk->messages()->send($this->token, array(
            'peer_id' => $this->peer_id,
            'random_id' => rand(0, 9) . 7 . rand(1, 999),
            "chat_id" => $this->peer_id * 1 - 2000000000,
            "message" => $msg,
            'group_id' => $this->group_id,
        ));
    }
}