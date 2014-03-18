<?php

namespace Halo;

class CliStdoutLogger implements iLoggerComponent {

    protected static $_showLog = true;

    public function log($message, $level) {
        if (php_sapi_name() == 'cli' && (!defined('ENV_TEST') || defined('SCRIPT_LOG_TESTS'))) {

            if (self::$_showLog) {
                echo date('Y-m-d H:i:s') . ' :: '.getmypid().' [' . $level . '] ' . $message . "\n";
            }

            if ($level == self::ER_ERR || $level == self::ER_WRN) {
                $all_bt = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
                $bt = array_pop($all_bt);

                error_log(" [ ".$level." ] ". $message. " in ".$bt['file']. " on line ".$bt['line']);
                Error::log($level, $message);
            }
        }
    }

    public function showLogMessages()
    {
        $this->_showLog = true;
    }

    public function hideLogMessages()
    {
        $this->_showLog = false;
    }


} 