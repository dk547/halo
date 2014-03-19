<?php

namespace Halo\Import;

abstract class ProviderAbstract {

    protected $_login, $_password;

    abstract protected function _auth();

    /**
     * Fetch contacts from mail provider
     *
     * @return array|boolean Format: [{name: , email: }, ..]
     */
    abstract public function fetchContacts();

    public function __construct($login, $password) {
        $this->_login = $login;
        $this->_password = $password;
    }

    public function auth() {
        return $this->_auth();
    }
}
