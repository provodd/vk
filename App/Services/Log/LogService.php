<?php

namespace App\Services\Log;

use App\Services\Database\DatabaseService;

class LogService
{
    //какие события логгировать
    const available_types = [
        'wall_post_new', 'message_new'
    ];

    //игнорировать пользователей или группы
    const ignore_list = [
        -219343075, -84305220
    ];

    private $table;

    public function __construct($data)
    {
        $this->data = $data;
        $this->table = 'logs';
    }

    public function add()
    {
        if (in_array($this->data['type'], self::available_types) AND $this->checkIgnoreList()) {
            $log = DatabaseService::dispense($this->table);
            $log->id_group = $this->data['group_id'] ?? '';
            $log->text = $this->data['object']['text'] ?? $this->data['object']['message']['text'];
            $log->id_event = $this->data['event_id'];
            $log->event_type = $this->data['type'];
            $log->id_user = $this->data['object']['user_id'] ?? $this->data['object']['from_id'] ?? $this->data['object']['message']['from_id'];
            $log->secret = $this->data['secret'];
            $log->stamp = $this->data['object']['date'] ?? $this->data['object']['message']['date'];
            $log->secret = $this->data['secret'];
            $log->status = '';
            $log->hash = $this->data['object']['hash'] ?? '';
            $log->payload = json_encode($this->data);
            $log->date = date('Y-m-d H:i:s');
            $log->created_at = date('Y-m-d H:i:s');
            return DatabaseService::store($log);
        }
    }

    public function checkIgnoreList(): bool
    {
        $from = $this->data['object']['user_id'] ?? $this->data['object']['from_id'] ?? $this->data['object']['message']['from_id'] ?? null;
        if (in_array(intval($from), self::ignore_list)) {
            return false;
        }
        return true;
    }

    static function getLogsCountByUser($id_user)
    {
        //за последний месяц
        return DatabaseService::count('logs', 'id_user=? AND event_type=? AND created_at > (NOW() - INTERVAL 1 MONTH)', array($id_user, 'wall_post_new'));
    }

    public static function test($data)
    {
        $test = DatabaseService::dispense('test');
        $test->data = var_export($data, true);
        DatabaseService::store($test);
        return false;
    }
}
