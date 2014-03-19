<?php

namespace Halo\Log;


class BaseCliLogger implements LoggerInterface {

    protected static $_showLog = true;


    public function log($level, $message, array $context = array()) {
        if (php_sapi_name() == 'cli' && (!defined('ENV_TEST') || defined('SCRIPT_LOG_TESTS'))) {

            if ($level == LogLevel::ERROR || $level == LogLevel::WARNING) {
                $all_bt = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
                $bt = array_pop($all_bt);

                error_log(" [ ".$level." ] ". $message. " in ".$bt['file']. " on line ".$bt['line']);
                Error::log($level, $message);
            }
        }
    }


    public function emergency($message, array $context = array()) {
        $this->log(LogLevel::EMERGENCY,$message,$context);

    }

    public function alert($message, array $context = array()) {
        $this->log(LogLevel::ALERT,$message,$context);
    }

    public function critical($message, array $context = array()){
        $this->log(LogLevel::CRITICAL,$message,$context);
    }

    public function error($message, array $context = array()){
        $this->log(LogLevel::ERROR,$message,$context);
    }

    public function warning($message, array $context = array()){
        $this->log(LogLevel::WARNING,$message,$context);
    }

    public function notice($message, array $context = array()){
        $this->log(LogLevel::NOTICE,$message,$context);
    }

    public function info($message, array $context = array()){
        $this->log(LogLevel::INFO,$message,$context);
    }

    public function debug($message, array $context = array()){
        $this->log(LogLevel::WARNING,$message,$context);
    }

} 