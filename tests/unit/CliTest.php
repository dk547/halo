<?php

class CliTest extends PHPUnit_Framework_TestCase {
    public function testA() {
        \Halo\HaloBase::getInstance()->setLogger(null);
    }
}