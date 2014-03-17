<?php
namespace Halo;

class Author {
    static protected $_author = null;

    /**
     * Return author name or false
     */
    static public function get() {
        if (!is_null(self::$_author)) {
            return self::$_author;
        }
        $mReturn = false;
        // Path to directory for author defenition
        $sPath = APPLICATION_PATH . DIRECTORY_SEPARATOR. 'protected' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'author' . DIRECTORY_SEPARATOR;
        $oDir = dir( $sPath );
        while (false !== ($sEntry = $oDir->read())) {
            if($sEntry != '.' && $sEntry != '..' && !is_dir($sPath.$sEntry)) {
                if ($sEntry[0] === '.') {
                    $mReturn = substr($sEntry, 1, strlen($sEntry));
                }
            }
        }
        unset($oDir);

        self::$_author = $mReturn;
        return self::$_author;
    }
}
