<?php
namespace Halo\QueueProcessor;

use Halo\QueueProcessor;

class Parallel extends QueueProcessor
{
    protected $_process_number = 0;
    protected $_process_amount = 1;

    public function __construct($params)
    {
        if (isset($params['process_number'])) {
            $this->_process_number = intval($params['process_number']);
        }
        if (isset($params['process_amount'])) {
            $this->_process_amount = intval($params['process_amount']);
        }

        parent::__construct($params);
    }

    public function process($record)
    {
        return parent::process($record);
    }

    public function shouldRecordBeSkipped($row)
    {
        if (empty($row) || !isset($row['id'])) {
            return false;
        }
        return !((($row['id'] % $this->_process_amount) === $this->_process_number));
    }
}
