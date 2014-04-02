<?php

namespace Halo;

use Halo\Log\BaseCliLogger;
use Halo\Log\LoggerAwareInterface;
use Halo\Log\LoggerInterface;

class HaloBase implements LoggerAwareInterface
{

    protected static $_instance;

    protected $params = [
        'logger' => null,
        'path_to_lock_files' => null,
        'send_errors_to_stats' => true,
        'path_to_stats_log' => null,
    ];

    private function __construct()
    {

    }

    private function __clone()
    {
    }

    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    protected function getParam($param_name)
    {
        if (isset($this->params[$param_name])) {
            return $this->params[$param_name];
        }
        return null;
    }

    public function getScriptLockPath()
    {
        return $this->getParam('path_to_lock_files');
    }


    public function setLogger(LoggerInterface $logger)
    {
        $this->params['logger'] = $logger;
        return $this;
    }


    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (is_null($this->getParam('logger'))) {
            $this->setLogger(new BaseCliLogger());
        }

        return $this->getParam('logger');
    }

    public function setScriptLockPath($path_to_lock_files)
    {
        $this->params['path_to_lock_files'] = $path_to_lock_files;
        return $this;
    }

    public function getSendErrorsToStats()
    {
        return $this->getParam('send_errors_to_stats');
    }

    /**
     * @param $v boolean
     */
    public function setSendErrorsToStats($v)
    {
        $this->params['send_errors_to_stats'] = boolval($v);
    }

    public function getPathToStatsLog()
    {
        return $this->getParam('path_to_stats_log');
    }

    public function setPathToStatsLog($path_to_stats_log)
    {
        $this->params['path_to_stats_log']=$path_to_stats_log;
        return $this;
    }

}
