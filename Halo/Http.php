<?php
namespace Halo;

use Guzzle\Http\Client;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;

/**
 * Class Http
 *
 * Some useful wrapper over curl to work with imports
 *
 * @package Halo
 */
class Http {

    /** @var CookiePlugin */
    protected $_cookie;

    public function __construct() {
        $this->_cookie = new CookiePlugin(new ArrayCookieJar());
    }

    public function post($url, array $params) {
        $client = new Client();
        $client->addSubscriber($this->_cookie);

        $request = $client->post($url, [], $params['data']);

        $response = $request->send();
        return $response;
    }

    public function get($url) {
        $client = new Client();
        $client->addSubscriber($this->_cookie);

        $request = $client->get($url);

        $response = $request->send();
        return $response;
    }

}