<?php

use \Halo\Cli\Script;

class CliTest extends PHPUnit_Framework_TestCase {
    public function testLogOutput() {
        ob_start();
        \Halo\HaloBase::getInstance()->setSendErrorsToStats(false);
        Script::log("test message", Script::ER_OK, 'context.context');
        $result = ob_get_clean();

        $this->assertTrue(boolval(preg_match('/\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d :: \d+? \[OK\] \[context.context\] test message/', $result)));
    }
}