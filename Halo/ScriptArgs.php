<?php
namespace Halo;

class ScriptsArgsException extends \Exception {};

namespace Halo;

/**
 * Класс для работы с опциями командной строки для CLI скриптов
 */
class ScriptArgs {

    protected $_options;

    public function __construct() {
        $this->_options = getopt("", array(
            "process-amount:", // всего процессов cli
            "process-number:", // номер процесса cli
            "debug-mode",
            "shadow",
        ));

        if ($this->getProcessNumber() >= $this->getProcessAmount()) {
            throw new ScriptsArgsException("--process-amount should be greater than --process-number");
        }

    }

    public function getProcessNumber() {
        if (isset($this->_options['process-number'])) {
            return intval($this->_options['process-number']);
        }

        return 0;
    }

    public function getProcessAmount() {
        if (isset($this->_options['process-amount'])) {
            return intval($this->_options['process-amount']);
        }

        return 1;
    }

    public function isDebugMode() {
        return isset($this->_options['debug-mode']);
    }

    public function isShadowMode() {
        return isset($this->_options['shadow']);
    }
}
