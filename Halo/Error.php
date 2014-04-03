<?php
namespace Halo;

class Error
{
    static public function code2str($code)
    {
        if (!is_numeric($code)) {
            return $code;
        }
        switch ($code) {
            case E_ERROR: // 1 //
                return 'E_ERROR';
            case E_WARNING: // 2 //
                return 'E_WARNING';
            case E_PARSE: // 4 //
                return 'E_PARSE';
            case E_NOTICE: // 8 //
                return 'E_NOTICE';
            case E_CORE_ERROR: // 16 //
                return 'E_CORE_ERROR';
            case E_CORE_WARNING: // 32 //
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: // 64 //
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: // 128 //
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR: // 256 //
                return 'E_USER_ERROR';
            case E_USER_WARNING: // 512 //
                return 'E_USER_WARNING';
            case E_USER_NOTICE: // 1024 //
                return 'E_USER_NOTICE';
            case E_STRICT: // 2048 //
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR: // 4096 //
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: // 8192 //
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED: // 16384 //
                return 'E_USER_DEPRECATED';
        }
        return "";
    }

    public static function log($code, $message, array $params = [])
    {
        $request = '';
        if (php_sapi_name() == 'cli') {
            $request = isset($_SERVER["SCRIPT_FILENAME"]) ? $_SERVER["SCRIPT_FILENAME"] : '';
        } else {
            if (isset($_SERVER['REQUEST_URI'])) {
                $request = $_SERVER['REQUEST_URI'];
            }
        }

        $data = [
            'pid' => getmypid(),
            'user' => get_current_user(),
            'host' => gethostname(),
            'code' => self::code2str($code),
            'request' => $request,
            'message' => $message,
            'context' => isset($params['context']) ? $params['context'] : '',
            'trace' => '',
        ];

        $trace = isset($params['backtrace']) ? $params['backtrace'] : debug_backtrace();

        // skip the first 3 stacks as they do not tell the error position
        if (count($trace) > 3) {
            $trace = array_slice($trace, 3);
        }

        foreach ($trace as $i => $t) {
            if (!isset($t['file'])) {
                $t['file'] = 'unknown';
            }
            if (!isset($t['line'])) {
                $t['line'] = 0;
            }
            if (!isset($t['function'])) {
                $t['function'] = 'unknown';
            }

            $res = "#$i {$t['file']}({$t['line']}): ";
            if (isset($t['object']) && is_object($t['object'])) {
                $res .= get_class($t['object']) . '->';
            }
            $res .= "{$t['function']}()\n";
            $data['trace'] .= $res;
        }

        if (HaloBase::getInstance()->getSendErrorsToStats()) {
            \Yii::app()->stats->log($data, 'errors/errors' . date('YmdH') . '.log');
        }
    }

    public static function logException(\Exception $e)
    {
        static::log(
            'Exception',
            $e->getMessage(),
            [
                'backtrace' => $e->getTrace(),
            ]
        );
    }
}
