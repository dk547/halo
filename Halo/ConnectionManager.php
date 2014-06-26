<?php
namespace Halo;

class ConnectionManagerException extends \Exception {}

/**
 * Manage db connections
 *
 * @package Halo
 * @author Vasily Bespalov
 */
class ConnectionManager
{
    private $_connections = array(); // массив открытых коннекшенов
    private $_transactions = 0; // счетчик открытых транзакций
    private $_transactions_cache = array(); // массив аналогичный коннектам, в нем хранятся объекты транзакций для каждого коннекта

    /** @var Platform */
    private $_platform = null;

    // имя класса Наследника CDbConnection
    // если не указано используется \Halo\DB
    public $db_class = false;

    //
    /**
     * Get connection to sql server with caching
     *
     * @param string $server_name connection params will be get from config by server name
     * @param bool|string $user connection username, automatic if empty
     * @param bool|string $passwd Должно быть указано если указан пользователь
     * @throws ConnectionManagerException
     * @return DB object
     */
    public function get($server_name, $user = false, $passwd = false) {

        if ($user) {
            $key = $server_name.'_'.$user.'_'.$passwd;
        } else {
            $key = $server_name.'__';
        }

        if (isset($this->_connections[$key]))
            return $this->_connections[$key];

        $Platform = $this->_getPlatform();
        $connection_params = $Platform->getServerByName($server_name);

        if (empty($connection_params)) {
            throw new ConnectionManagerException('Server name {'.$server_name.'} not found in config files');
        }

        if (empty($connection_params['host'])) {
            throw new ConnectionManagerException('parameter host not specified with server_name='.$server_name);
        }

        $dsn = HaloBase::getInstance()->getSqlProtocol().':host='.$connection_params['host'];

        if (!empty($connection_params['port'])) {
            $dsn .= ':'.$connection_params['port'];
        }

        if (!$user) {
            $user = $connection_params['user'];
            $passwd = $connection_params['pass'];
        }

        if (!$this->db_class) {
            $this->_connections[$key] = new DB($dsn, $user, $passwd);
        } else {
            $classname = $this->db_class;
            $this->_connections[$key] = new $classname($dsn, $user, $passwd);
        }

        if ($this->_transactions > 0) {
            $this->_transactions_cache[$key] = $this->_connections[$key]->beginTransaction();
        }
        return $this->_connections[$key];
    }

    protected function _getPlatform() {
        if (!$this->_platform) {
            $this->_platform = new Platform();
            $this->_platform->init();
        }
        return $this->_platform;
    }

    /**
     * Начинает транзакцию
     * Внимание! транзакции начинаются у всех открытых коннектов и у всех
     * новых если транзакция открыта.
     *
     * @throws ConnectionManagerException если не удалось
     */
    public function begin() {
        if ($this->_transactions++ == 0) {
            foreach($this->_connections as $key => $conn) {
                $this->_transactions_cache[$key] = $conn->beginTransaction();
            }
        }
        return true;
    }

    /**
     * Коммит
     */
    public function commit() {
        if ($this->_transactions > 0) {
            if (--$this->_transactions == 0) {
                foreach($this->_transactions_cache as $key => $trans) {
                    $trans->commit();
                }
                $this->_transactions_cache = array();
            }
        }
        return true;
    }

    /**
     * Откат транзакций
     */
    public function rollback() {
        if ($this->_transactions > 0) {
            $this->_transactions = 0;
            foreach ($this->_transactions_cache as $key => $trans) {
                $trans->rollback();
            }
            $this->_transactions_cache = array();
        }
        return true;
    }


    /**
     * Закрытие всех коннектов и коммит транзакций
     */
    public function finish() {
        foreach($this->_connections as $key => $conn) {
            if (!empty($this->_transactions_cache[$key])) {
                $this->_transactions_cache[$key]->commit();
            }
            $conn->closeConnection();
        }
        $this->_transactions = 0;
        $this->_connections = array();
        $this->_transactions_cache = array();
    }

    /**
     * В случае форка, нужно для нового процесса открыть новые коннекты
     * а все старые удалить
     */
    public function fork() {
        $this->_transactions = 0;
        $this->_connections = array();
        $this->_transactions_cache = array();
    }
}
