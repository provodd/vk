<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
require_once dirname(__FILE__, 2) . '/vendor/autoload.php';
$config = require dirname(__FILE__, 2) . '/config.php';

use App\Services\Database\DatabaseService;
use App\Services\Chatbot\ChatbotService;

DatabaseService::instance();

if (isset($_POST)) {
    if (isset($_POST['id'])){
        $chatbot = new ChatbotService($_POST, $config, $_POST['id']);
        $chatbot->start();
    }
}