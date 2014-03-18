<?php

namespace Halo;


class HaloBase {

    protected static $_instance;

    protected $params = [];

    private function __construct()
    {

    }

    private function __clone(){
    }

    public static function getInstance() {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    protected function getParam($param_name)
    {
        if(isset($this->params[$param_name]))
        {
            return $this->params[$param_name];
        }
        return null;
    }

    public function getScriptLockPath()
    {
        return $this->getParam('path_to_lock_files');
    }


    public function setLogger( iLoggerComponent $logger )
    {
        $this->params['logger'] = $logger;
        return $this;
    }


    /**
     * @return iLoggerComponent
     */
    public function getLogger()
    {
        if (is_null($this->getParam('logger')))
        {
            $this->setLogger(new CliStdoutLogger());
        }

        return $this->getParam('logger');
    }

    public function setScriptLockPath($path_to_lock_files)
    {
        $this->params['path_to_lock_files'] = $path_to_lock_files;
        return $this;
    }

}