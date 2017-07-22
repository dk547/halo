<?php

namespace Halo;

class DB extends \CDbConnection
{
    public $enableParamLogging = true;
    public $enableProfiling = true;
    //public $schemaCachingDuration = 10000000;

    public $host = 'undefined';

    public $name = null;

    public function __construct($dsn='',$username='',$password='') {
        parent::__construct($dsn, $username, $password);
        $matches = [];
        if (preg_match('/host=([^:\s]+)/', $dsn, $matches)) {
            $this->host = $matches[1];
        }
    }

    public function createCommand($query=null)
    {
        $this->setActive(true);
        return new DBCommand($this,$query);
    }

    public function closeConnection() {
        $this->close();
    }
}