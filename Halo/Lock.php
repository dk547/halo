<?php
namespace Halo;
/**
 * Класс обеспечивает систему локов по заданному имени
 * @author bespalov
 */
class Lock
{

    public $locksDir = false; // директория где лежат файлы локов
    private $_locks = array(); // lock files

    const LOCKED = 1; // уже заблочен (че-й то лок висит)
    const FAILED = 2; // не удалось залочить, нет папки или прав
    const OK = 3; // окей, лок установлен
    const OK_LAST_FAILED = 4; // удалось получить лок, но последний запуск скрипта неожиданно умер


    public function __construct() {
        $this->locksDir = realpath(SCRIPT_LOCK_PATH);
    }

    /**
     * Устанавливаем файл лок
     * @param string $name Имя скрипта
     * @return int
     */
    public function setLock($name) {
        // проверим, что директория есть и в нее можно писать (есть права на запись)
        if (!$this->_checkLocksDir()) {
            return self::FAILED;
        }

        // имя файла лока
        $lock_file = $this->_getLockFile($name);

        $this->_locks[$lock_file] = @fopen($lock_file, "r");
        if ($this->_locks[$lock_file])
        {
            $result = $this->_addLock($this->_locks[$lock_file]);
            return  (!$result) ? self::LOCKED : self::OK_LAST_FAILED;
        }

        $this->_locks[$lock_file] = @fopen($lock_file, "w");
        if ($this->_locks[$lock_file])
        {
            $result = $this->_addLock($this->_locks[$lock_file]);
            return (!$result) ? self::LOCKED : self::OK;

        }

        return self::FAILED;
    }

    /**
     * Освобождение лока
     * @param string $name Имя скрипта
     * @return bool
     */
    public function removeLock($name)
    {
        if (!$this->_checkLocksDir()) {
            return false;
        }
        $lock_file = $this->_getLockFile($name);

        if(!isset ($this->_locks[$lock_file])) {
            //return false;
        }
        // For atomicity requirements of unlocking we remove lock-file firstly
        // and only after that clear memory of file descriptor.
        // In other case we had bug of unlinking file locked by other process already.
        // анлинк убран, т.к вроде бы создает race condition http://world.std.com/~swmcd/steven/tech/flock.html
        //@unlink($lock_file);

        fclose($this->_locks[$lock_file]);
        return true;
    }

    //
    private function _checkLocksDir()
    {
        return (file_exists($this->locksDir) && is_writable($this->locksDir));
    }

    private function _getLockFile($name)
    {
        return $this->locksDir.'/'.$name.'.lock';
    }

    /**
     *
     * @param resource $fp
     * @return bool
     */
    private function _addLock($fp)
    {
        return flock($fp, LOCK_EX + LOCK_NB);
    }

}
