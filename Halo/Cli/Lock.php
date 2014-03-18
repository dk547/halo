<?php
namespace Halo\Cli;

/**
 * Cli locks to prevent not intended parallel runs
 *
 * @author Vasily Bespalov
 */
class Lock
{
    public $locksDir = false;
    private $_locks = array();

    const LOCKED = 1; // lock file already blocked (lock is exist)
    const FAILED = 2; // lock failed, there are no file or folder
    const OK = 3; // ok
    const OK_LAST_FAILED = 4; // ok, but last run was failed

    public function __construct($lockDir) {
        $this->locksDir = $lockDir;
    }

    /**
     * @param string $name Script name
     * @return int on of constants LOCKED etc
     */
    public function setLock($name) {
        if (!$this->_checkLocksDir()) {
            return self::FAILED;
        }

        $lock_file = $this->_getLockFile($name);

        if (file_exists($lock_file)) {
            $this->_locks[$lock_file] = @fopen($lock_file, "r");
            if ($this->_locks[$lock_file])
            {
                $result = $this->_addLock($this->_locks[$lock_file]);
                return  (!$result) ? self::LOCKED : self::OK_LAST_FAILED;
            }
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
     * @param string $name Script name
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
        // commented due to race condition http://world.std.com/~swmcd/steven/tech/flock.html
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
     * @param resource $fp
     * @return bool
     */
    private function _addLock($fp)
    {
        return flock($fp, LOCK_EX + LOCK_NB);
    }

}
