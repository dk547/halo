<?php
namespace Halo;
class Stats extends \CComponent {
    public function init() {}

    /**
     * Записывает строчку данных (преобразуя eе в json) в лог статистики для
     * последующей обработки.
     * Директории в имени файла создаются автоматически
     *
     * @param $info array
     * @param $filename string
     * @return bool
     */
    public function log(array $info, $filename) {
        $path = STATSLOG_PATH.'/'.$filename;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            // попробуем создать нужные директории
            if (!mkdir($dir, 0755, true)) {
                Script::log("Could not write to stats log $path");
                return false;
            }
        }

        $info['_ts'] = time();

        error_log(json_encode($info)."\n", 3, $path);
        return true;
    }

}
