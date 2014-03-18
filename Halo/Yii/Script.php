<?php
namespace Halo\Yii;

/**
 * Wrapper for cli Script for Yii
 *
 * @package Halo\Yii
 */
abstract class Script extends \Halo\Cli\Script {
    protected function _getCliLockDir() {
        return realpath(\Yii::app()->params['cliLockDir']);
    }
}
