<?php

namespace App\Services\Antispam;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Services\Antispam\AntispamDTO;
use App\Services\Log\LogService;
use VK\Client\VKApiClient;

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

    public function __construct($data, $config, $id)
    {
        $this->text = $data['object']['message']['text'];
        $this->peer_id = $data['object']['message']['peer_id'];
        $this->group_id = $data['group_id'];
        $this->id_user = $data['object']['message']['from_id'];
        $this->id_log = $id;
        $this->vk = new VKApiClient();
        $this->token = $config['antispam_service']['access_token'];
    }

    /**
     * @throws \Exception
     */
    public function check()
    {
        try {
            if (!$this->checkStopWords()) {
                throw new \Exception('Фейк, спам, бот или либераст. Кик');
            }
            
        } catch (\Exception $ex) {
            $this->removeUserFromChat();
            $this->sendMessage($ex->getMessage());
        }

    }

    public function removeUserFromChat(){
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