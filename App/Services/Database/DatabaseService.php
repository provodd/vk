<?php

namespace App\Services\Database;

use \RedBeanPHP\R as R;

class DatabaseService extends R
{
    private $config;
    private static $instance = null;

    public static function instance(): ?DatabaseService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->config = require dirname(__FILE__, 4) . '/config.php';
        R::setup('mysql:host=' . $this->config['mysql']['host'] . '; dbname=' . $this->config['mysql']['database'] . '', $this->config['mysql']['user'], $this->config['mysql']['password']);
        R::ext('xdispense', function($type){
            return R::getRedBean()->dispense($type);
        });
        self::initDatabase();
    }

    public static function initDatabase()
    {
        //TODO
    }
}