<?php

namespace Acorn\Models;

use \Backend\Models\ImportModel;
use Acorn\Exam\Models\DataEntryScore;
use Exception;

class Import extends ImportModel
{
    /**
     * @var array The rules to be applied to the data.
     */
    public $rules = [];

    public $config; // Injected by the controller

    public function importData($results, $sessionKey = null)
    {
        foreach ($results as $row => $data) {
            try {
                // TODO: Get data Model
                $subscriber = new DataEntryScore;
                $subscriber->fill($data);
                $subscriber->save();

                $this->logCreated();
            }
            catch (Exception $ex) {
                $this->logError($row, $ex->getMessage());
            }

        }
    }
}