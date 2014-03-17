<?php

namespace Halo;

class DB extends \CDbConnection
{
    public $enableParamLogging = true;
    public $enableProfiling = true;
    //public $schemaCachingDuration = 10000000;

    public $name = null;

    public function createCommand($query=null)
    {
        $this->setActive(true);
        return new DBCommand($this,$query);
    }

    public function closeConnection() {
        $this->close();
    }
}