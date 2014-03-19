<?php
namespace Halo\Import\Providers;

use Halo\Import\ProviderAbstract;
use Halo\Http;
use Halo\Cli\Script;

class ProviderMailRu extends ProviderAbstract
{
    protected $_http;
    protected function _auth()
    {
        Script::log("mail.ru: auth: login=".$this->_login);

        $this->_http = new Http();
        $res = $this->_http->post('http://win.mail.ru/cgi-bin/auth', [
            'data' => [
                'Login' => $this->_login,
                'Password' => $this->_password,
            ]
        ]);

        if ($res->getStatusCode() != 200) {
            Script::log("mail.ru: error auth ".$res->getMessage(), Script::ER_OK);
            return false;
        }

        $entry = $res->getBody(true);

        if (preg_match('/action="[^"]+" name="Auth"/i', $entry, $m)) {
            Script::log("mail.ru: Could not auth", Script::ER_OK);
            return false;
        }

        return true;
    }

    public function fetchContacts()
    {
        Script::log("mail.ru: fetchContacts: login=".$this->_login);
        $res = $this->_http->get('https://e.mail.ru/cgi-bin/abexport2?format=google&contact_id=');

        if ($res->getStatusCode() != 200) {
            Script::log("mail.ru: error fetchContacts ".$res->getMessage(), Script::ER_OK);
            return false;
        }

        $contacts = [];

        // 11 - name
        // 28 - email
        $lines = explode("\n", $res->getBody(true));
        foreach ($lines as $num => $line) {
            if (!$num) {
                continue;
            }
            $line = trim($line);
            $data = explode(",", $line);

            if (count($data) >= 29) {
                $contacts[] = ['name' => $data[11], 'email' => $data[28]];
            }
        }

        return $contacts;
    }
}

