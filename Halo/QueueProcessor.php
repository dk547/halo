<?php
namespace Halo;
use \Halo\Cli\Script;

abstract class QueueProcessor {

    const RESULT_OK = 1; // обработка пакета прошла успешно
    const RESULT_TEMP_FAIL = 2; // временно неуспешно
    const RESULT_FAIL = 3; // пакет совсем не может быть обработан
    const RESULT_OK_DELETED = 4; // как и RESULT_OK, только запись вручную удалена в process

    /// настройки
    // сервер куда коннектится
    protected $_server_name = '';

    // таблица очереди
    protected $_table = '';

    // имя поля в таблице, которое содержит время обновления записи
    protected $_ts_field = 'ts';

    // имя поля в таблице, которое содержит счетчик ошибок
    protected $_errcnt_field = 'errcnt';

    // время в секундах: пауза перед следующей попыткой обработать эту запись
    protected $_error_timeout = 600;

    // удалять автоматически обработанные записи или нет
    protected $_manual_delete = false;

    // сколько записей обрабатываем за запуск
    protected $_limit = 5000;

    // сколько записей выбирается из базы за раз
    protected $_limit_packet = 500;

    // обновлять ли счетчик ошибок в записи
    // отключение также отключит перенос ошибочных записей
    protected $_errors_update = true;

    // Максимальное число ошибочных обработок 1 записи
    // 0 - неограничено
    protected $_errors_limit = 5;

    // пауза в секундах до следующей обработки ошибочной записи
    protected $_errors_timeout = 600;

    // через сколько секунд удалять запись из очереди
    // 0 - никогда
    protected $_purge_timeout = 86400;

    // максимум подряд идущих ошибок при обработке блока записей
    protected $_errors_max = 0;

    // статистика работы
    protected $_stats = [
        'total' => 0,
        'processed' => 0,
        'skipped' => 0,
        'deleted' => 0,
    ];

    ///
    protected  $_db = null;

    public function __construct() {

    }

    /**
     * Проверяет нужно ли обрабатывать запись в зависимости от кол-ва предыдущих ошибок
     *
     * @param array $row
     * @return bool
     */
    protected function _shouldBeSkippedByErrors(array $row) {
        if ($this->_errors_timeout > 0 && $row[$this->_errcnt_field] > 0
            && (time() - strtotime($row[$this->_ts_field])) < $this->_errors_timeout) {
            return true;
        }
       return false;
    }

    protected function _updateErrorCounter(array $row) {
        $sql = $this->_getSqlUpdateErrors([$row['id']]);
        $cmd = $this->getDbConnection()->createCommand($sql);
        $result = $cmd->query();
        return $result->getRowCount();
    }

    public function run()
    {
        do {
            if (false === ($data = $this->_getNextData($this->_stats['total'], $this->_limit_packet))) {
                return false;
            }

            $errors = 0;
            $this->_stats['total'] += count($data);

            foreach ($data as $row) {

                if ($this->_shouldBeSkippedByErrors($row)) {
                    $this->_stats['skipped']++;
                    continue;
                }

                if ($this->shouldRecordBeSkipped($row)) {
                    $this->_stats['skipped']++;
                    continue;
                }

                $result = $this->process($row);

                if (self::RESULT_OK === $result || self::RESULT_OK_DELETED === $result) {
                    if ($errors) {
                        $errors = 0;
                    }
                    $this->_stats['processed']++;
                    if (self::RESULT_OK === $result) {
                        $this->_delete([$row['id']]);
                    }
                    continue;
                }

                if ($this->_errors_update) {
                    $this->_updateErrorCounter($row);
                }

                if ($this->_purge_timeout > 0 && (time() - strtotime($row[$this->_ts_field])) > $this->_purge_timeout) {
                    $this->_moveToErrors($row, true);
                    $this->_stats['processed']++;
                    continue;
                }

                if ($this->_errors_update) {
                    if (($row[$this->_errcnt_field] >= $this->_errors_limit && $this->_errors_limit > 0) || self::RESULT_FAIL === $result) {
                        $this->_moveToErrors($row);
                        $this->_stats['processed']++;
                    }
                }

                $errors++;

                if ($this->_errors_max > 0 && $errors > $this->_errors_max) {
                    Script::log("Aborted. Too many errors without a break.", Script::ER_ERR);
                    break;
                }

                $this->_stats['skipped']++;
            }

        } while (count($data) > 0 && $this->_stats['processed'] < $this->_limit);

        return true;
    }

    public function getDbConnection()
    {
        if (!$this->_db) {
            $this->_db = \Yii::app()->connectionManager->get($this->_server_name);
        }
        return $this->_db;
    }

    public function shouldRecordBeSkipped($row)
    {
        return false;
    }

    /**
     * Обработка записи
     *
     * Возвращает результат операции: одна из констант RESULT_xxx
     */
    abstract function process($record);

    /**
     * Запрос для выборки записей из очереди
     *
     * @param $offset
     * @param $limit
     * @return string
     */
    protected function _getSqlSelect($offset, $limit)
    {
        return "SELECT * FROM " . $this->_table . " WHERE
            (" . $this->_errcnt_field . " = 0 OR " . $this->_ts_field . " < (NOW() - INTERVAL " . $this->_error_timeout . " SECOND))
            LIMIT $offset, $limit";
    }

    /**
     * Запрос для удаления обработанных записей из таблицы
     *
     * @param array $ids Список id записей
     * @return string SQL statement
     */
    protected function _getSqlDelete(array $ids)
    {
        return "DELETE FROM " . $this->_table . " WHERE id IN (" . implode(',', $ids) . ")";
    }

    /**
     * Запрос для обновления счетчика ошибок
     *
     * @param array $ids
     * @return string
     */
    protected function _getSqlUpdateErrors(array $ids)
    {
        return " UPDATE " . $this->_table . " SET " . $this->_errcnt_field . " = " . $this->_errcnt_field . " + 1, " . $this->_ts_field . " = NOW() WHERE id IN (" . implode(',', $ids) . ")";
    }

    /**
     * Получаем следующий пакет данных для обработки
     *
     * @param int $from
     * @param bool $limit
     * @return array
     */
    protected function _getNextData($from = 0, $limit = false)
    {
        if (false === $limit) {
            $limit = $this->_limit;
        }

        $sql = $this->_getSqlSelect($from, $limit);
        $cmd = $this->getDbConnection()->createCommand($sql);
        $res = $cmd->queryAll(true);
        Script::log("fetched next data from=$from limit=$limit, rows=".count($res));
        return $res;
    }

    // Удаляет обработанные ид записей
    protected function _delete(array $ids, $check_manual = true)
    {
        if(($this->_manual_delete && $check_manual) || empty($ids)) {
            return true;
        }

        $sql = $this->_getSqlDelete($ids);
        $Conn = $this->getDbConnection();

        $cmd = $Conn->createCommand($sql);
        $result = $cmd->query();

        $this->_stats['deleted'] += $result->getRowCount();
        return count($ids) == $result->getRowCount();
    }

    protected function _moveToErrors(array $row, $purged = false)
    {
        if (!$purged) {
            Script::log("Record could not be processed. Removed. ".print_r($row, true), Script::ER_WRN);
            $this->_delete([$row['id']]);
        } else {
            Script::log("Record is removing because purge timeout. ".print_r($row, true), Script::ER_WRN);
            $this->_delete([$row['id']], false);
        }
    }

    public function getStats() {
        return $this->_stats;
    }
}