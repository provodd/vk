<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//ini_set("error_log", "./vk.log");
require_once dirname(__FILE__, 2) . '/vendor/autoload.php';
$config = require dirname(__FILE__, 2) . '/config.php';

use App\Services\Database\DatabaseService;
use App\Services\Victorina\VictorinaDTO;
use App\Services\Victorina\VictorinaService;

DatabaseService::instance();

$victorina = DatabaseService::getAll('SELECT * FROM ' . VictorinaDTO::VICTORINA_TABLE . ' WHERE active=1');

foreach ($victorina as $item) {

    $logs = DatabaseService::findOne('logs', 'id=?', array($item['id_log']));

    if (isset($logs)) {
        $v = new VictorinaService(json_decode($logs['payload'], true), $config, $logs->id);
        $v->check();
    }
}
