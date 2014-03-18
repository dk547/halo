<?php
/**
 * Base Interface for Halo Logger
 *
 * @author Naumov Aleksey
 */

namespace Halo;

interface iLoggerComponent
{
    const ER_OK  = 'OK';
    const ER_WRN = 'WRN';
    const ER_ERR = 'ERR';

    public function log($message, $level);
    public function showLogMessages();
    public function hideLogMessages();
}