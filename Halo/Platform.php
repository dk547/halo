<?php
namespace Halo;

class Platform extends \CComponent
{
    protected $dbServers = array();

    protected $spots = array();

    public function init()
    {
        $this->iniDbServers();
    }

    public function getDBServers()
    {
        $this->iniDbServers();
        return $this->dbServers;
    }

    protected function iniDbServers()
    {
        if (empty($this->dbServers))
        {
            $author = Author::get();
            if ($author === false) {
                $servers = require_once CONFIG_PATH . "/servers.php";
            } else {
                $servers = require_once CONFIG_PATH."/author/$author/servers.php";
            }
            $this->dbServers = $servers;
        }
    }

    public function getServerByName($server_name)
    {
        $this->iniDbServers();
        if (isset($this->dbServers[$server_name])) {
            $server = $this->dbServers[$server_name];
            $server['name'] = $server_name;
            // дефолтный пароль и юзер
            $server['pass'] = empty($server['pass'])?WEB_SQL_PASS:$server['pass'];
            $server['user'] = empty($server['user'])?WEB_SQL_USER:$server['user'];
            if (defined('WEB_SQL_PORT')) {
                $server['port'] = empty($server['port']) ? WEB_SQL_PORT : $server['port'];
            }
            return $server;
        }

        return array();
    }

    public function getServersByType($type) {
        $result = [];
        foreach($this->dbServers as $name => $data) {
            if (isset($data['type']) && $data['type'] == $type) {
                $result[$name] = $data;
            }
        }
        return $result;
    }
}
