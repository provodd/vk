<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/vendor/autoload.php';
$config = require 'config.php';

use App\Services\Database\DatabaseService;
use App\Services\Log\LogService;
use App\Services\Queue\QueueService;
use App\Services\Victorina\VictorinaService;
use App\Services\Antispam\AntispamService;
use App\Services\Chatbot\ChatbotService;
use App\Actions\Curl;

DatabaseService::instance();

if (isset($_POST)) {

    $data = json_decode(file_get_contents("php://input"), true);
    $errors = array();

    if (isset($data['secret'])) {
        $LogService = new LogService($data);
        $id = $LogService->add();

        $LogService = new QueueService();
        $LogService->add($data);

        switch ($data['secret']) {
            case 'victorina':
                $victorina = new VictorinaService($data, $config, $id);
                $victorina->check();
                break;
            case 'ekb':
                $victorina = new AntispamService($data, $config, $id);
                $victorina->check();
                break;
            case 'chat':

                try {
                    $data['id'] = $id;
                    $host = "https://$_SERVER[HTTP_HOST]";
                    $url = $host . "/vk/curl/chatbot.php";
                    $curl = new Curl($url, $data, 3);
                    $curl_result = $curl->exec();

                } catch (Exception $ex) {
                    //LogService::test(['err' => $ex->getMessage()]);
                }

                break;
        }
    }
    echo 'ok';
}