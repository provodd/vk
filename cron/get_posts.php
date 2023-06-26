<?php
ini_set("error_log", "./vk.log");
require_once dirname(__FILE__,2) . '/vendor/autoload.php';
$config = require dirname(__FILE__,2) . '/config.php';

use App\Services\Database\DatabaseService;
use App\Services\Posts\PostService;

DatabaseService::instance();

$postService = new PostService($config);
$postService->getPosts($_GET['limit'] ?? 5);