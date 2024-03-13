<?php

namespace App\Services\ChatAdministration;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use VK\Client\VKApiClient;
use App\Services\Database\DatabaseService;

class ChatAdministrationService
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
        $this->reply = $data['object']['message']['reply_message'] ?? false;
        $this->id_log = $id;
        $this->vk = new VKApiClient();
        $this->token = $config['antispam_service']['access_token'];
        $this->data = $data;
        $this->message = $this->data['object']['message'];
    }

    /**
     * @throws \Exception
     */
    public function check()
    {
        switch (true) {
            case (stripos($this->text, '!кик') !== false and $this->checkPermissions()):
                if ($this->reply) {
                    $user_id = $this->reply['from_id'];
                } else {
                    $msg = explode(" ", $this->text);
                    $user_id = $msg[1];
                }
                $top = $this->getTopUsers(20);

                $userInTop = false;
                foreach ($top as $user) {
                    if (intval($user['id_user']) === intval($user_id)) {
                        $userInTop = true;
                    }
                }
                if (!$userInTop) {
                    $this->removeUserFromChat($user_id);
                } else {
                    $this->sendMessage('Нельзя кикнуть пользователя из топ 20');
                }
                break;
            case stripos($this->text, '!мут') !== false:
                $this->sendMessage('Функционал в разработке');
                break;
            case stripos($this->text, '!ник') !== false:
                $this->sendMessage('Функционал в рaзрaботке');
                break;
            case stripos($this->text, '!админы') !== false:
                $admins = $this->getAdmins();
                $msg = '';
                $test = '';
                if (isset($admins) and !empty($admins)) {
                    $i = 0;
                    foreach ($admins as $item) {
                        $i++;
                        $msg .= $i . '. [id' . $item['id_user'] . '|' . $item['first_name'] . ']' . $test . " \n";
                    }
                }
                $this->sendMessage($msg);
                break;
        }
    }

    public function checkPermissions()
    {
        $user = DatabaseService::findOne('chat_users', 'id_group=? AND id_peer=? AND id_user=?', array($this->group_id, $this->peer_id, $this->id_user));
        if ($user->is_admin) {
            return true;
        }
        return false;
    }

    public function getAdmins()
    {
        $users = DatabaseService::getAll("SELECT * FROM chat_users WHERE id_group = {$this->group_id} AND is_admin IS NOT NULL ORDER BY messages_count DESC");
        return $users;
    }

    public function removeUserFromChat($user_id)
    {
        return $this->vk->messages()->removeChatUser($this->token, array(
            'user_id' => $user_id,
            "chat_id" => $this->peer_id * 1 - 2000000000,
        ));
    }

    public function getTopUsers($limit = 3)
    {
        $users = DatabaseService::getAll("SELECT c.first_name as firstname, c.last_name as lastname, c.* FROM chat_users as c
        WHERE c.id_group = {$this->group_id}
        ORDER BY c.messages_count DESC LIMIT {$limit}"
        );
        $admins = array();
        foreach ($users as $key => $item) {
            $user = DatabaseService::findOne('victorina_rating', 'id_user=?', array($item['id_user']));
            $admins[$key] = $item;
            $admins[$key]['amount'] = $item['messages_count'] + ($user->rating ?? 0);
        }
        $key_values = array_column($admins, 'amount');
        array_multisort($key_values, SORT_DESC, $admins);

        //return array_slice($admins, 0, $limit);
        return $admins;
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