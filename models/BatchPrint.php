<?php

namespace Acorn\Models;

use Acorn\PdfTemplate;
use \Backend\Models\ExportModel;
use File;
use DB;
use Log;
use Exception;
use Winter\Storm\Filesystem\Zip;

class BatchPrint extends ExportModel
{
    public $attachOne = [
        'template' => \System\Models\File::class, 
    ];

    // Injected by the controller
    public $config; 

    public $fillable = [
        'query',
        'conditions',
        'template',
        'output_mode',
        'compression',
        'prepend_uniqid'
    ];

    public function exportData($columns, $sessionKey = null)
    {
        static $first = TRUE;
        if ($first) Log::info('BatchPrint::exportData()');

        $cursor = NULL;
        if ($this->query) {
            if ($first) Log::info('  BatchPrint::query mode');
            $cursor = $this->query->cursor();
        } else if (isset($this->config['query'])) {
            if ($first) Log::info('  BatchPrint::config query mode');
            $cursor = DB::cursor($this->config['query']);
        } else {
            if ($first) Log::info('  BatchPrint::dataModel mode');
            if (!isset($this->config['dataModel'])) {
                Log::error("Data Model or query not defined for export process");
                throw new Exception("Data Model or query not defined for export process");
            }
            $dataModel = $this->config['dataModel'];
            $groupBy   = (isset($this->config['groupBy']) ? $this->config['groupBy'] : 'filename');
            $builder   = $dataModel::orderBy($groupBy, 'asc');
            if ($this->conditions) {
                // Simple conditions
                // this = 6, that = 'test'
                foreach (explode(',', $this->conditions) as $condition) {
                    $conditionParts = explode('=', $condition);
                    $columnName     = trim($conditionParts[0]);
                    $columnValue    = trim($conditionParts[1], '\n\r\t\v\x00 \'"');
                    $builder        = $builder->where($columnName, $columnValue);
                }
            }
            $cursor = $builder->cursor();
        }
        $first = FALSE;
        
        // cursor() & yield used to reduce memory usage
        foreach ($cursor as $record) {
            if ($record instanceof Model) {
                $record->addVisible($columns);
                $record = $record->toArray();
            }
            yield $record;
        }
    }

    protected function updatedPrintedArray($model, $pdfTemplate): void {
        // Record that this template has been printed
        $storageTemplatePath = $pdfTemplate->storageTemplatePath();
        
        $modelClass = get_class($model);
        if (!$model->readOnly && $model->hasAttribute('printed')) {
            $printed = $model->printed;
            if (is_null($printed)) $printed = array();
            if (is_array($printed)) {
                array_push($printed, $storageTemplatePath);
                $model->printed = $printed;
                $model->save();
                Log::info("$modelClass::printed array updated ($storageTemplatePath)");
            } else if ($model->printed instanceof Collection) {
                $model->printed->add($storageTemplatePath);
                $model->save();
                Log::info("$modelClass::printed collection updated ($storageTemplatePath)");
            } else {
                Log::error("$modelClass::printed is not an array");
            }
        }

        foreach ($model->belongsTo as $name => $config) {
            if ($relatedModel = $model->$name) {
                if ($relatedModel instanceof \Winter\Storm\Database\Model) {
                    $relatedModelClass = get_class($relatedModel);
                    if (!$relatedModel->readOnly && $relatedModel->hasAttribute('printed')) {
                        $printed = $relatedModel->printed;
                        if (is_null($printed)) $printed = array();
                        if (is_array($printed)) {
                            array_push($printed, $storageTemplatePath);
                            $relatedModel->printed = $printed;
                            $relatedModel->save();
                            Log::info("$relatedModelClass::printed array updated ($storageTemplatePath)");
                        } else if ($relatedModel->printed instanceof Collection) {
                            $relatedModel->printed->add($storageTemplatePath);
                            $relatedModel->save();
                            Log::info("$relatedModelClass::printed collection updated ($storageTemplatePath)");
                        } else {
                            $type = \gettype($relatedModel->printed);
                            Log::error("$relatedModelClass::printed is not an array: $type");
                            Log::error($relatedModel->printed);
                        }
                    }
                }
            } else {
                Log::error("Failed to retrieve valid $name relation");
            }
        }
    }

    protected function processExportData($columns, $results, $options)
    {
        static $first = TRUE;
        if ($first) {
            Log::info('BatchPrint::processExportData()');
            // Log::info($options);
            // Log::info(\Session::all());
        }
        $first = FALSE;

        // Output details
        $tempPath = temp_path();          // Absolute: /var/www/university/storage/temp
        $outName  = uniqid('oc'); // oc68247db892897
        $groupBy  = (isset($this->config['groupBy']) ? $this->config['groupBy'] : 'filename');

        // TODO: Support options compression and output_mode
        // TODO: Check for LibreOffice binary
        if ($this->compression != 'zip') {
            Log::error("Compression mode [$this->compression] is not supported yet. Only ZIP");
            throw new Exception("Compression mode [$this->compression] is not supported yet. Only ZIP");
        }
        if ($this->output_mode != 'multi') {
            Log::error("Only multi output mode is supported, not [$this->output_mode]");
            throw new Exception("Only multi output mode is supported, not [$this->output_mode]");
        }
        if (!$this->template) {
            Log::error("Template required");
            throw new Exception("Template required");
        }
        if (!$results) {
            Log::error("No records");
            throw new Exception("No records");
        }

        $pdfTemplate = new PdfTemplate($this->template);

        // TODO: output_mode:single Add new page copy
        // Look for / create a new-page style:
        //   <style:paragraph-properties fo:break-before="page"/>
        // $xPageBreakStyles = $xpath->query('/office:document/office:automatic-styles/style:style[style:paragraph-properties/@fo:break-before="page"]');
        // if (!$xPageBreakStyles->count())
        //     throw new Exception('No automatic page break styles');
        // $pageBreakStyle = $xPageBreakStyles[0]->getAttribute('style:name'); 
        
        // Generate multi Libre Office document => ZIP
        $lastGroupBy = NULL;
        $first       = TRUE;
        $filename    = NULL;
        $files       = array();
        foreach ($results as $model) {
            // Group by collects records with same id together
            // before writing the PDF
            if (!$first) {
                if (!$groupBy || !$model->$groupBy || $lastGroupBy != $model->$groupBy) {
                    Log::info("---------------------------------- Finishing");
                    $this->updatedPrintedArray($model, $pdfTemplate);
                    
                    // Save generated FODT into the storage/temp directory
                    $pdfLocation = $pdfTemplate->writePDF($outName, $filename, $this->prepend_uniqid);
                    array_push($files, $pdfLocation);

                    // Reset all form values
                    $pdfTemplate->resetTemplate();
                }
            }

            $filename = ($model->hasAttribute('filename') ? $model->filename : $model->name);
            $pdfTemplate->writeAttributes($model);
            
            if ($groupBy) $lastGroupBy = $model->$groupBy;
            $first = FALSE;
        }
        // Save last generated FODT into the storage/temp directory
        if (!$first) {
            Log::info("---------------------------------- Finishing");
            $this->updatedPrintedArray($model, $pdfTemplate);
            $pdfLocation = $pdfTemplate->writePDF($outName, $filename, $this->prepend_uniqid);
            array_push($files, $pdfLocation);
        }

        // Compression
        ZIP::make("$tempPath/$outName", $files);

        // Tidy up
        foreach ($files as $file) File::delete($file);

        // Move the ZIP to the correct position for the download() action
        // config_import_export.yaml fileName: print.pdf
        // ImportExportController::download($name, $outputName = null)
        //   => ExportModel::download($name, $outputName = null)
        //     => $csvPath = temp_path() . '/' . $name
        //     => storage/temp/oc68248694e0ac3
        // >> storage/temp/oc68248694e0ac3
        //File::move("$tempPath/$outName.zip", "$tempPath/$outName");
        
        // ImportExportController::onExport()
        //   => download/<reference>/<exportFileName>
        //   => download/oc68248694e0ac3/print.zip
        return $outName; 
    }
}