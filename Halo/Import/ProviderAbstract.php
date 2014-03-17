<?php

namespace Halo\Import;

abstract class ProviderAbstract {

    protected $_login, $_password;

    abstract protected function _auth();

    abstract public function fetchContacts();

    public function __construct($login, $password) {
        $this->_login = $login;
        $this->_password = $password;
    }

    public function auth() {
        $this->_auth();
    }
}
