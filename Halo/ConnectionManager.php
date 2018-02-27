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
    protected $_callbacks = [];
    protected $_charset; // default charset SET NAMES xxx

    /** @var Platform */
    private $_platform = null;

    // имя класса Наследника CDbConnection
    // если не указано используется \Halo\DB
    public $db_class = false;

    const EVENT_AFTER_BEGIN_TRANSACTION = 'begin';
    const EVENT_AFTER_COMMIT_TRANSACTION = 'commit';
    const EVENT_AFTER_ROLLBACK_TRANSACTION = 'rollback';
    const EVENT_AFTER_EACH_BEGIN_TRANSACTION = 'each_begin';
    const EVENT_AFTER_EACH_COMMIT_TRANSACTION = 'each_commit';
    const EVENT_AFTER_EACH_ROLLBACK_TRANSACTION = 'each_rollback';

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

        $protocol = HaloBase::getInstance()->getSqlProtocol();
        if (!empty($connection_params['protocol'])) {
            $protocol = $connection_params['protocol'];
        }

        $dsn = $protocol.':host='.$connection_params['host'];

        if (empty($connection_params['port']) && defined('WEB_SQL_PORT')) {
            $connection_params['port'] = WEB_SQL_PORT;
        }

        if (!empty($connection_params['port'])) {
            $dsn .= ';port='.$connection_params['port'];
        }

        if (!empty($connection_params['dbname'])) {
            $dsn .= ';dbname='.$connection_params['dbname'];
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

        $charset = null;
        if (!is_null($this->_charset)) {
            $charset = $this->_charset;
        }
        if (isset($connection_params['charset'])) {
            if (empty($connection_params['charset'])) {
                $charset = null;
            } else {
                $charset = $connection_params['charset'];
            }
        }

        if (!is_null($charset)) {
            $this->_connections[$key]->charset = $charset;
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
                $begin_ts = microtime(true);
                $this->_transactions_cache[$key] = $conn->beginTransaction();
                $end_ts = microtime(true);
                $this->_processEvent(self::EVENT_AFTER_EACH_BEGIN_TRANSACTION, [
                    'conn' => $conn,
                    'seconds' => $end_ts - $begin_ts,
                ]);
            }
            $this->_processEvent(self::EVENT_AFTER_BEGIN_TRANSACTION);
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
                    $begin_ts = microtime(true);
                    $trans->commit();
                    $end_ts = microtime(true);
                    $this->_processEvent(self::EVENT_AFTER_EACH_COMMIT_TRANSACTION, [
                        'conn' => $trans,
                        'seconds' => $end_ts - $begin_ts,
                    ]);
                }
                $this->_processEvent(self::EVENT_AFTER_COMMIT_TRANSACTION);
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
                $begin_ts = microtime(true);
                $trans->rollback();
                $end_ts = microtime(true);
                $this->_processEvent(self::EVENT_AFTER_EACH_ROLLBACK_TRANSACTION, [
                    'conn' => $trans,
                    'seconds' => $end_ts - $begin_ts,
                ]);
            }
            $this->_processEvent(self::EVENT_AFTER_ROLLBACK_TRANSACTION);
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

    /**
     * Close connection
     *
     * @param DB $conn
     */
    public function closeConnection($conn) {
        foreach($this->_connections as $key => $c) {
            if ($c == $conn) {
                $conn->closeConnection();
                unset($this->_connections[$key]);
            }
        }
    }

    public function getOpenTransactions() {
        return $this->_transactions;
    }

    public function reconnectAll() {
        if (empty($this->_connections)) {
            return;
        }
        foreach($this->_connections as $key => $conn) {
            $conn->setActive(false);
            $conn->setActive(true);
        }
    }

    /**
     * @param $event
     * @param callable $callback
     */
    public function registerCallback($event, callable $callback) {
        if (!isset($this->_callbacks[$event])) {
            $this->_callbacks[$event] = [];
        }
        $this->_callbacks[$event][] = $callback;
    }

    public function unregisterCallback(callable $callback) {
        foreach ($this->_callbacks as $name => $list) {
            if (is_array($list)) {
                foreach($list as $k => $func) {
                    if ($func == $callback) {
                        unset($this->_callbacks[$name][$k]);
                    }
                }
            }
        }
    }

    protected function _processEvent($name, array $params = []) {
        if (isset($this->_callbacks[$name])) {
            foreach($this->_callbacks[$name] as $func) {
                call_user_func_array($func, $params);
            }
        }
    }
}
