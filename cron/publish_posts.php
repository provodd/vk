<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set("error_log", "./vk.log");
require_once dirname(__FILE__, 2) . '/vendor/autoload.php';
$config = require dirname(__FILE__, 2) . '/config.php';

use App\Services\Database\DatabaseService;
use App\Services\Posts\PostPublishService;

DatabaseService::instance();
$vk = new \VK\Client\VKApiClient();

$postPublishService = new PostPublishService($config);
$time = $postPublishService->getLastPublishedTime();
$posts = $postPublishService->getPosts($_GET['limit'] ?? 5);
$postPublishService->publish($posts);
