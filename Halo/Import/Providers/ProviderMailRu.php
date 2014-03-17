<?php
namespace Halo\Import\Providers;

use Halo\Import\ProviderAbstract;
use Halo\Http;

class ProviderMailRu extends ProviderAbstract
{
    protected $_http;
    protected function _auth()
    {
        $this->_http = new Http();
        $res = $this->_http->post('http://win.mail.ru/cgi-bin/auth', [
            'data' => [
                'Login' => $this->_login,
                'Password' => $this->_password,
            ]
        ]);

        $entry = $res->getBody(true);


        if (preg_match('/action="[^"]+" name="Auth"/i', $entry, $m)) {
            \Script::log("mail.ru: Could not auth");
            return false;
        }

        return true;
    }

    public function fetchContacts()
    {
        $res = $this->_http->get('https://e.mail.ru/cgi-bin/abexport2?format=outlook&contact_id=');
        var_dump($res->getBody(true));

        return [];
    }
}

