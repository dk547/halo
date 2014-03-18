<?php
namespace Halo\Yii;

class ConnectionManager extends \Halo\ConnectionManager implements \IApplicationComponent {

    protected $_initialized = false;

    public function init() {
        $this->_initialized = true;
    }

    public function getIsInitialized() {
        return $this->_initialized;
    }

    protected function _getPlatform() {
        return \Yii::app()->getComponent('platform');
    }
}

