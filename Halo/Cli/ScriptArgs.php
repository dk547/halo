<?php
namespace Halo\Cli;

class ScriptsArgsException extends \Exception {};

/**
 * Command line arguments helper
 */
class ScriptArgs {

    protected $_options;

    public function __construct() {
        $this->_options = getopt("", array(
            "process-amount:", // total processes cli
            "process-number:", // number of current process cli
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
