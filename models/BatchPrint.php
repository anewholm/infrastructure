<?php

namespace Acorn\Models;

use \Backend\Models\ExportModel;
use File;
use DB;
use Log;
use Exception;
use Storage;
use DOMDocument;
use DOMXPath;
use Winter\Storm\Filesystem\Zip;

class BatchPrint extends ExportModel
{
    public $attachOne = [
        'template' => \System\Models\File::class, 
    ];

    public $config; // Injected by the controller

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
        $cursor = NULL;
        if ($this->query) {
            $cursor = DB::cursor($this->query);
        } else if (isset($this->config['query'])) {
            $cursor = DB::cursor($this->config['query']);
        } else {
            if (!isset($this->config['dataModel']))
                throw new Exception("Data Model or query not defined for export process");
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
        
        // cursor() & yield used to reduce memory usage
        foreach ($cursor as $record) {
            if ($record instanceof Model) {
                $record->addVisible($columns);
                $record = $record->toArray();
            }
            yield $record;
        }
    }

    protected function processExportData($columns, $results, $options)
    {
        // Output details
        $tempPath = temp_path();          // Absolute: /var/www/university/storage/temp
        $outName  = uniqid('oc'); // oc68247db892897
        $groupBy  = (isset($this->config['groupBy']) ? $this->config['groupBy'] : 'filename');

        // TODO: Support options compression and output_mode
        // TODO: Check for LibreOffice binary
        if ($this->compression != 'zip')
            throw new Exception("Compression mode [$this->compression] is not supported yet. Only ZIP");
        if ($this->output_mode != 'multi')
            throw new Exception("Only multi output mode is supported, not [$this->output_mode]");
        if (!$this->template)
            throw new Exception("Template required");
        if (!$results)
            throw new Exception("No records");

        // Load template
        $storageTemplatePath = "media/$this->template";
        if (!Storage::exists($storageTemplatePath))
            throw new Exception("[$storageTemplatePath] template not found");
        $templateContents    = Storage::get($storageTemplatePath);
        if (!$templateContents)
            throw new Exception("[$storageTemplatePath] template empty");
        $templateDOM         = new DOMDocument();
        if (!$templateDOM->loadXML($templateContents))
            throw new Exception("[$storageTemplatePath] failed to loadXML()");

        // Locate form controls
        //   <form:textarea form:name="scores.kurdish" form:control-implementation="ooo:com.sun.star.form.component.TextField" xml:id="control3" form:id="control3" form:input-required="false" form:convert-empty-to-null="true">
        // and text boxes
        //   <draw:frame text:anchor-type="paragraph" draw:z-index="0" draw:name="student_code" draw:style-name="gr3" draw:text-style-name="P10" svg:width="3.028cm" svg:height="1.848cm" svg:x="2.469cm" svg:y="3.454cm">
        //     <draw:text-box>
        //       <text:p>test</text:p>
        //     </draw:text-box>
        //     <svg:title>student_code</svg:title>
        //   </draw:frame>
        $xpath               = new DOMXPath($templateDOM);
        $xPageNode           = $xpath->query('/office:document/office:body/office:text')[0];
        $xFormNode           = $xpath->query('office:forms/form:form', $xPageNode)[0];
        $xFormControls       = $xpath->query('form:textarea', $xFormNode);
        $xDrawTextBoxes      = $xpath->query('text:p/draw:frame', $xPageNode);

        $formControls = array();
        foreach ($xFormControls as $xFormControl) {
            $objectName = $xFormControl->getAttribute('form:name');
            if (!$objectName) throw new Exception("Nameless control");
            $formControls[$objectName] = $xFormControl;
            Log::info("$objectName form control found");
        }

        $textBoxes = array();
        foreach ($xDrawTextBoxes as $xDrawTextBox) {
            $objectName = $xDrawTextBox->getAttribute('draw:name');
            if (!$objectName) throw new Exception("Nameless control");
            $xTextP = $xpath->query('draw:text-box/text:p', $xDrawTextBox)[0];
            if (!$xTextP) throw new Exception("draw:frame without text:p");
            $textBoxes[$objectName] = $xTextP;
            Log::info("$objectName text-box found");
        }

        // TODO: output_mode:single Add new page copy
        // Look for / create a new-page style:
        // <style:paragraph-properties fo:break-before="page"/>
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
                    // Save generated FODT into the storage/temp directory
                    array_push($files, $this->writePDF($templateDOM, $outName, $filename));
                    // Reset all form values
                    foreach ($xFormControls as $xFormControl) $xFormControl->setAttribute('form:current-value', '');
                }
            }

            // filename
            $filename = ($model->filename ?: $model->name);

            // Fill form values
            foreach ($model->attributesToArray() as $name => $value) {
                if (is_array($value)) {
                    // JSONable field
                    // scores => geography|history|math => id|title|value
                    foreach ($value as $subName => $subValues) {
                        $embeddedName = "$name.$subName"; // scores.geography|history|math
                        $subValue     = (is_array($subValues)
                            ? (isset($subValues['value']) ? $subValues['value'] : NULL)
                            : $subValues
                        );
                        if (isset($formControls[$embeddedName])) {
                            Log::info("Form control attribute $embeddedName => $subValue");
                            $xFormControl = $formControls[$embeddedName];
                            $xFormControl->setAttribute('form:current-value', $subValue);
                        }
                        if (isset($textBoxes[$embeddedName])) {
                            Log::info("Text box $embeddedName => $subValue");
                            $xDrawTextBox = $textBoxes[$embeddedName];
                            $xDrawTextBox->nodeValue = $subValue;
                        }
                    }
                } else {
                    if (isset($formControls[$name])) {
                        // this => that
                        Log::info("Form control attribute $name => $value");
                        $xFormControl = $formControls[$name];
                        $xFormControl->setAttribute('form:current-value', $value);
                    }
                    if (isset($textBoxes[$name])) {
                        Log::info("Text box $name => $value");
                        $xDrawTextBox = $textBoxes[$name];
                        $xDrawTextBox->nodeValue = $value;
                    }
                }
            }
            
            if ($groupBy) $lastGroupBy = $model->$groupBy;
            $first = FALSE;
        }
        // Save last generated FODT into the storage/temp directory
        if (!$first) array_push($files, $this->writePDF($templateDOM, $outName, $filename));

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

    protected function writePDF(DOMDocument &$templateDOM, string $outName, string $filename): string
    {
        $tempPath = temp_path();
        $filename = preg_replace('/[^a-zA-Z0-9-_]+/', '-', $filename);
        $filename = ($this->prepend_uniqid ? "$outName-$filename" : $filename);
        File::put("$tempPath/$filename.fodt", $templateDOM->saveXML());
        // Generate PDF out to the storage/temp directory
        // will have name $outName-$id.pdf
        $execOutput = exec("libreoffice --headless --convert-to pdf:writer_pdf_Export $tempPath/$filename.fodt --outdir $tempPath");
        Log::info("LibreOffice PDF generator of [$filename] reported [$execOutput]");

        $pdfPath = "$tempPath/$filename.pdf";
        if (!File::exists($pdfPath))
            throw new Exception("LibreOffice PDF output at [$pdfPath] does not exist with [$execOutput]");
        File::delete("$tempPath/$filename.fodt");

        return $pdfPath;
    }
}