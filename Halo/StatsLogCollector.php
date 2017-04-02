<?php
namespace Halo;

use Halo\Cli\Script;

class StatsLogCollector {

    // сколько записей вставляется в базу за один раз
    protected $_limit = 300;

    /** @var string Путь к директории с фаайлами stats logs */
    protected $_dir = null;

    /** автоматически конвертить строчки из лог из json в array */
    protected $_json_auto = true;

    /** Префикс ключа куда писать статистику по работе коллекторов */
    // например mail.collectors.frontend
    protected $_graphite_prefix = false;

    protected $_stats = [
        'processed' => 0,
    ];

    public function __construct($dir) {
        $this->_dir = $dir;
        $this->init();
    }

    public function init() {

    }

    /**
     * Функция вставляет в базу пришедший блок данных. Надо переопределить в наследнике
     *
     * @param mixed $data
     * @return bool
     */
    protected function _collect($data) {
        return true;
    }

    /**
     * Перед обработкой лога он ренеймится в лог с суффиксом .work
     * Здесь можно сделать доп. операции, типа послать сигнал SIGUSR1 в nginx
     *
     * @param $filename
     * @param $work_filename
     * @return bool
     */
    protected function _rename($filename, $work_filename) {
        return rename($filename, $work_filename);
    }

    /**
     * Обрабатывает директорию с логами
     *
     * @return bool
     */
    public function run() {
        if (!is_dir($this->_dir)) {
            Script::log('Incorrect directory '.$this->_dir, Script::ER_ERR);
            return false;
        }

        if (!($dh = opendir($this->_dir))) {
            Script::log('Could not open directory '.$this->_dir, Script::ER_ERR);
            return false;
        }

        // перед обработкой сначала ренеймим файл и ставим EX лок
        while (($file = readdir($dh))) {

            if ($file == '.' || $file == '..') {
                continue;
            }

            $work_filename = $this->_dir.$file;

            if (!is_readable($work_filename)) {
                Script::log('File '.$work_filename.' is not readable', Script::ER_ERR);
                continue;
            }

            if (!($fp = fopen($work_filename, 'r'))) {
                Script::log('Could not open file for reading '.$work_filename, Script::ER_ERR);
                continue;
            }

            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                fclose($fp);
                Script::log('Could not get EX lock for '.$work_filename);
                continue;
            }

            if (!preg_match('/\.work$/', $file)) {
                $new_filename = $work_filename.'.work';
                if (file_exists($new_filename)) {
                    fclose($fp);
                    continue;
                }
                if (!$this->_rename($work_filename, $new_filename)) {
                    fclose($fp);
                    Script::log('Could not rename file '.$work_filename.' to new '.$new_filename, Script::ER_ERR);
                    continue;
                }
            }

            Script::log("Processing file $work_filename , memory=".memory_get_peak_usage());

            $data = [];
            while(!feof($fp)) {
                $line = trim(fgets($fp));
                if (empty($line)) {
                    continue;
                }
                if ($this->_json_auto) {
                    $row_data = json_decode($line, true);
                    if (!$row_data) {
                        Script::log('Invalid JSON at '.$work_filename." line: ".$line, Script::ER_ERR);
                        continue;
                    }

                    $data[] = $row_data;
                } else {
                    $data[] = $line;
                }

                $this->_stats['processed']++;

                if (count($data) >= $this->_limit) {
                    if (!$this->_collect($data)) {
                        Script::log("Could not collect data block", Script::ER_ERR);
                        return false;
                    }
                    $data = [];
                }
            }

            fclose($fp);

            if (!empty($data) && !$this->_collect($data)) {
                Script::log("Could not collect data block 2", Script::ER_ERR);
                return false;
            }

            $i = 0;
            do {
                $res = unlink($work_filename);
                if (!$res) {
                    Script::log("Could not unlink file $work_filename, try $i", Script::ER_ERR);
                    sleep(1);
                }
            } while (!$res && $i++ < 5);

            unset($content);
        }

        // запишем статистику в графит если надо
        if ($this->_graphite_prefix) {
            if (!\Yii::app()->graphite->send([new GraphiteElement([
                'key' => $this->_graphite_prefix.'.processed',
                'value' => $this->_stats['processed'],
                'ts' => time(),
            ])])) {
                Script::log("Error sending data to Graphite", Script::ER_ERR);
            }
        }

        return true;
    }

    public function getStats() {
        return $this->_stats;
    }

}
