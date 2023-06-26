<?php

namespace App\Services\Queue;

use App\Services\Database\DatabaseService;

class QueueService
{
    //какие события добавлять в очередь
    const available_types = [
        'wall_post_new'
    ];

    const queue_table = 'queue';
    const queue_completed_table = 'queue_completed';

    public function getQueues()
    {
        return DatabaseService::getAll("SELECT * FROM queue WHERE available_at >= NOW() AND user>0");
    }

    public function getOldQueues()
    {
        return DatabaseService::getAll("SELECT * FROM queue WHERE available_at < NOW() AND user>0");
    }

    public function add($data)
    {
        $from = $this->data['object']['user_id'] ?? $this->data['object']['from_id'] ?? $this->data['object']['message']['from_id'] ?? null;
        if (isset($from)) {
            $fromGroup = mb_stripos($from, '-');
            if (in_array($data['type'], self::available_types) and $fromGroup === false) {
                $log = DatabaseService::dispense(self::queue_table);
                $log->type = $data['type'];
                $log->text = $data['object']['text'] ?? '';
                $log->user = $data['object']['user_id'] ?? $data['object']['from_id'];
                $log->id_owner = $data['object']['owner_id'];
                $log->id_post = $data['object']['id'];
                $log->id_event = $data['event_id'];
                $log->payload = json_encode($data);
                $log->attempts = 1;
                $log->available_at = date('Y-m-d H:i:s', strtotime("+1 day"));
                $log->created_at = date('Y-m-d H:i:s');
                return DatabaseService::store($log);
            }
        }
    }

    public function checkingOldQueues()
    {
        $queues = $this->getOldQueues();
        foreach ($queues as $queue) {
            $this->complete($queue, 'Просрочено');
        }
    }

    public function complete($properties, $message, $status = 'error')
    {
        $old_queue = DatabaseService::load('queue', $properties['id']);
        $properties = $old_queue->getProperties();
        unset($properties['id']);
        $completed_queue = DatabaseService::xdispense(self::queue_completed_table);
        $completed_queue->import($properties);
        $completed_queue->status = $status;
        $completed_queue->status_text = $message;
        $completed_queue->updated_at = date('Y-m-d H:i:s');
        if (DatabaseService::store($completed_queue)) {
            DatabaseService::trash($old_queue);
        }
    }

}
