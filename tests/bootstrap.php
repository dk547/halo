<?php

date_default_timezone_set('Europe/Moscow');

require dirname(__FILE__).'/../vendor/autoload.php';

define('LOCKS_PATH', dirname(__FILE__).'/locks');
\Halo\HaloBase::getInstance()->setScriptLockPath(LOCKS_PATH);
