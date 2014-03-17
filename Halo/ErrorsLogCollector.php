<?php

namespace Halo;
define('SQL_CREATE_ERROR_LOG', "CREATE TABLE #tablename# (
  `created_ts` timestamp NOT NULL default '0000-00-00 00:00:00',
  `pid` integer,
  `user` varchar(50),
  `host` varchar(255),
  `code` varchar(255),
  `request` text,
  `message` text,
  `trace` text,
  KEY `code` (`code`),
  KEY `created_ts` (`created_ts`, `code`)
) ENGINE=InnoDB");


class ErrorsLogCollector extends StatsLogCollector {

    protected $_graphite_prefix = 'collectors.errors';

    protected function _insert(DB $Conn, $table_name, $insert_sql, $create_sql) {
        $Cmd = $Conn->createCommand($insert_sql);
        while(1) {
            try {
                $res = $Cmd->execute();
            } catch (CDbException $e) {
                if (isset($e->errorInfo[1]) &&  $e->errorInfo[1] == 1146) {
                    // таблицы нет
                    $Create = $Conn->createCommand(str_replace('#tablename#', $table_name, $create_sql));
                    $Create->execute();
                    continue;
                } else {
                    throw $e;
                }
            }
            break;
        }
    }

    protected function _collect($data) {

        // key = mail.errors.host.code
        $messages = [];
        foreach($data as $item) {
            $messages[] = new GraphiteElement([
                'key' =>  sprintf("errors.%s.%s",
                    str_replace('.', '_', $item['host']),
                    strtolower($item['code'])
                ),
                'value' => 1,
                'ts' => $item['_ts'],
            ]);
        }

        if (!Yii::app()->graphite->send($messages)) {
            Script::log("Error sending data to Graphite", Script::ER_ERR);
            return false;
        }

        // Сохраняем сами ошибки в базе данных
        // одна таблица в день
        $table_name = str_replace('#date#', date('Ymd'), DB_TABLE_ERROR_LOG);

        $Conn = Yii::app()->connectionManager->get(SQL_NAME_LOGS);

        $insert_sql = "INSERT INTO ".$table_name." (created_ts, pid, `user`, host, code, request, message, trace)
            VALUES (".implode("),(", array_map(function($e) use ($Conn) {
                $data = [
                    'FROM_UNIXTIME('.intval($e['_ts']).')',
                    intval($e['pid']),
                    $Conn->quoteValue($e['user']),
                    $Conn->quoteValue($e['host']),
                    $Conn->quoteValue($e['code']),
                    $Conn->quoteValue($e['request']),
                    $Conn->quoteValue($e['message']),
                    $Conn->quoteValue($e['trace']),
                ];
                return implode(',', $data);
            }, $data)).")";

        $this->_insert($Conn, $table_name, $insert_sql, SQL_CREATE_ERROR_LOG);

        return true;
    }

}