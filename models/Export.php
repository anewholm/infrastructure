<?php

namespace Acorn\Models;

use \Backend\Models\ExportModel;
use Exception;

class Export extends ExportModel
{
    public $config; // Injected by the controller

    public function exportData($columns, $sessionKey = null)
    {
        // cursor() & yield used to reduce memory usage
        if (!isset($this->config['dataModel']))
            throw new Exception("Data Model not defined for export process");
        $dataModel = $this->config['dataModel'];
        foreach ($dataModel::cursor() as $record) {
            $record->addVisible($columns);
            $line = $record->toArray();
            yield $line;
        }
    }
}