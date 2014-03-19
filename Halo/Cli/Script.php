<?php
namespace Halo\Cli;
use Halo\Error;
use Halo\HaloBase;
use Halo\Log\LoggerInterface;
use Halo\Log\LogLevel;

/**
 * Base class for creating cli scripts
 * Implements protect from not intended parallel runs and master-shadow mode
 * (after master termination shadow script run and continue processing)
 *
 * @author Vasily Bespalov
 */
abstract class Script {
    // error levels
    const ER_OK  = 'OK';
    const ER_WRN = 'WRN';
    const ER_ERR = 'ERR';

    protected static $_showLog = true;

    private $_script_name = false; // script name for lock file generation
    private $_lock = false; // Lock instance
    protected $_use_shadow = false; // shadow mode: Запущено 2 скрипта, один работает другой ожидает завершения активного
    protected $_debug_mode = false; // Режим отладки. Выключен скриптовый фреймворк, локи и тп

    private $_initialized = false; // индикатор вызывался ли метод init
    private $_scriptargs = null; // распарсенные параметры командной строки
    private $_finished = false;

    public function __construct(array $params = array())
    {
        $this->_script_name = basename($_SERVER['SCRIPT_NAME']).'_'.md5(join(' ', $GLOBALS['argv']));
        $this->_lock = new Lock(isset($params['lockDir']) ? $params['lockDir'] : $this->_getCliLockDir());
        $this->_scriptargs = new ScriptArgs();

        $this->_use_shadow = !empty($params['use_shadow']) ? true : $this->_scriptargs->isShadowMode();
        $this->_debug_mode = !empty($params['debug_mode']) ? true : $this->_scriptargs->isDebugMode();
    }

    /**
     * Get the default dir for lock files
     *
     * @throws \Exception
     * @return string
     */
    protected function _getCliLockDir() {
        return HaloBase::getInstance()->getScriptLockPath();
    }

    /**
     * Initiating script
     */
    public function init()
    {
        $this->_initialized = true;

        // попробуем получить лок на запуск
        if (!$this->_getLock(true)) {
            // не удалось получить обычный лок (копия работает)
            // попробуем получить shadow лок (2я копия)
            if (!$this->_use_shadow || !$this->_getShadowLock()) {
                exit;
            }

            // шадоу копия. Ожидание освобождения основного лока
            while (!$this->_getLock(true)) {
                if ($this->_debug_mode)
                    self::log("Can't get lock. Sleep 3 seconds", self::ER_OK);
                sleep(3);
            }
            $this->_unLockShadow();
        }

        self::log('Started', self::ER_OK);
    }

    /**
     * Запускается перед выполнением process()
     */
    public function preProcess() {}

    /**
     * Необходимо определить в наследнике. Основная логика скрипта
     */
    abstract function process();

    /**
     * Запускается после выполнением process()
     */
    public function postProcess() {}

    /**
     * запуск скрипта
     */
    final public function run()
    {
        // проверяем вызывался ли метод init этого базового класса
        if (!$this->_initialized) {
            self::log("Script should be initialized first! Call methods init()", self::ER_ERR);
            exit;
        }

        $this->preProcess();
        $this->process();
        $this->postProcess();
    }

    /**
     * Logging script messages
     * @param string $message
     * @param string $level on of Script::ER_xx
     */
    static public function log($message, $level = self::ER_OK)
    {
        if (php_sapi_name() == 'cli' && (!defined('ENV_TEST') || defined('SCRIPT_LOG_TESTS'))) {

            if (self::$_showLog) {
                echo date('Y-m-d H:i:s') . ' :: '.getmypid().' [' . $level . '] ' . $message . "\n";
            }
        }
        HaloBase::getInstance()->getLogger()->log(self::ScriptLevelToLogLevel($level),$message);
    }

    static protected function ScriptLevelToLogLevel($level)
    {

        switch ($level) {
            case self::ER_ERR:
                $new_level = LogLevel::ERROR;
                break;
            case self::ER_OK:
                $new_level = LogLevel::INFO;
                break;
            case self::ER_WRN:
                $new_level = LogLevel::WARNING;
                break;
            default:
                $new_level = LogLevel::ERROR;
        }


        return $new_level;
    }

    /**
     * Окончание скрипта
     */
    public function finish()
    {
        $this->_finished = true;
        self::log('Successfully finished.', self::ER_OK);
        $this->_unlock();
    }

    /**
     * Окончание скрипта
     */
    public function failedScript($msg)
    {
        $this->_finished = true;
        self::log('Script failed: '.$msg, self::ER_ERR);
        $this->_unlock();
        exit;
    }

    // private functions

    /**
     * Получение лока на запуск основного скрипта
     *
     * @param boolean $return_false Прекращать выполнение в случае ошибки или невозможности получить лок или
     * возвращать false
     * @return boolean false - если не удалось получить лок
     *
     */
    private function _getLock($return_false = false)
    {
        $result = $this->_lock->setLock($this->_script_name);
        if ($result == Lock::LOCKED || $result == Lock::FAILED) {
            if ($this->_debug_mode && $result == Lock::LOCKED) {
                self::log('Already blocked ' . $this->_script_name, self::ER_OK);
            }
            if ($result == Lock::FAILED) {
                self::log('Can not get lock: lock dir ('.$this->_lock->locksDir.') doesn\'t exist, access rights issue etc. - ' . $this->_script_name, self::ER_ERR);
            }
            if (!$return_false) {
                exit();
            } else {
                return false;
            }
        } elseif ($result == Lock::OK_LAST_FAILED) {
            //self::log('Last run was failed! ' . $this->_script_name, self::ER_WRN);
        }
        return true;
    }

    /**
     * Снятие блокировки основного скрипта
     *
     * @return boolean
     */
    private function _unlock()
    {
        return $this->_lock->removeLock($this->_script_name);
    }

    /**
     * Шадоу лок. Позволяет запустить ожидающий основного лока скрипт
     * который быстро подхватит выполнение если что.
     *
     * @return boolean
     */
    private function _getShadowLock()
    {
        if (!$this->_use_shadow) {
            return false;
        }
        // lock for shadow mode
        $result = $this->_lock->setLock($this->_script_name . '.shadow');
        if ($result == Lock::LOCKED) {
            if ($this->_debug_mode) {
                self::log("Exit: lock file (" . $this->_script_name . '.shadow' . ") already exist!", self::ER_WRN);
            }
            return false;
        }
        elseif ($result == Lock::OK_LAST_FAILED) {
            //self::log("Last run was failed!", self::ER_WRN);
        }
        return true;
    }

    /**
     * Удаление shadow лока
     *
     * @return boolean
     */
    private function _unLockShadow()
    {
        if ($this->_use_shadow) {
            return $this->_lock->removeLock($this->_script_name . '.shadow');
        }
        return false;
    }


    /**
     * @return void
     */
    public static function clearConsole()
    {
        print chr(27)."[H".chr(27)."[2J";
    }

    public static function showLogMessages()
    {
       self::$_showLog = true;
    }

    public static function hideLogMessages()
    {
        self::$_showLog = false;
    }

    public function chunkItems(array $items, $size)
    {
        $packs = array_chunk($items, $size);
        self::log('Total ' . count($items) . ' items, in '.count($packs).' packs', self::ER_OK);
        return $packs;
    }

    /**
     * Возвращает список аргументов командной строки
     */
    public function getScriptArgs() {
        return $this->_scriptargs;
    }
}
