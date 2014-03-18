<?php
namespace Halo;
class GraphiteElement {

    protected $_key;
    protected $_value;
    protected $_ts;

    public function __construct(array $params = []) {
        $this->_key = isset($params['key']) ? $params['key'] : null;
        $this->_value = isset($params['value']) ? $params['value'] : null;
        $this->_ts = isset($params['ts']) ? $params['ts'] : null;
    }

    public function toArray() {
        return [
            'key' => $this->_key,
            'value' => $this->_value,
            'ts' => $this->_ts,
        ];
    }

    public function getKey() {
        return $this->_key;
    }

    public function getValue() {
        return $this->_value;
    }

    /**
     * @return int timestamp
     */
    public function getTs() {
        return is_null($this->_ts) ? time() : $this->_ts;
    }
}