<?php namespace Acorn;

use Yaml;
use Str;
use Lang;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Model;
use File;
use Log;
use Exception;
use Storage;
use DOMDocument;
use DOMNode;
use DOMXPath;

class PdfTemplate {
    protected $templateFilePath;
    protected $templateDOM, $xpath;
    protected $formControls, $textBoxes;
    protected $xQrCodeNode, $qrCodeHeightPX;

    public $comment;
    public $identifier;
    public $type;
    public $title;

    public function __construct(string $templateFilePath = NULL, string $dir = 'media')
    {
        if ($templateFilePath) $this->loadTemplate($templateFilePath, $dir);
    }

    protected function getSingleNode(string $xpath, DOMNode $xStartNode = NULL): DOMNode|null
    {
        $xNode     = NULL;
        $xNodeList = $this->xpath->query($xpath, $xStartNode);
        if (count($xNodeList)) $xNode = $xNodeList[0];

        return $xNode;
    }

    protected function getNodeValue(string $xpath, DOMNode $xStartNode = NULL): string|null
    {
        $nodeValue = NULL;
        if ($xNode = $this->getSingleNode($xpath, $xStartNode))
            $nodeValue = $xNode->nodeValue;

        return $nodeValue;
    }

    public function label(bool $plural = FALSE): string
    {
        $label    = NULL;
        $labelSet = ($plural ? 'labels-plural' : 'labels');
        $failover = 'en';

        if (isset($this->comment[$labelSet])) {
            $labels = $this->comment[$labelSet];
            $locale = Lang::getLocale();
            if      (isset($labels[$locale])) $label = $labels[$locale];
            else if (isset($labels[$failover]))    $label = $labels[$failover];
        } 
        
        if (is_null($label)) {
            $templateFileName = preg_replace('/^.*\/|\..*$/', '', $this->templateFilePath);
            $label            = Str::headline(str_ireplace('template', '', $templateFileName));
            if ($plural) $label = Str::plural($label);
            $label = trans($label);
        }

        return $label;
    }

    public function loadTemplate(string $templateFilePath, string $dir = 'media'): DOMDocument
    {
        // Load template
        $this->templateFilePath = $templateFilePath;
        $storageTemplatePath    = "$dir/$templateFilePath";
        if (!Storage::exists($storageTemplatePath)) {
            Log::error("[$storageTemplatePath] template not found");
            throw new Exception("[$storageTemplatePath] template not found");
        }
        $templateContents    = Storage::get($storageTemplatePath);
        if (!$templateContents) {
            Log::error("[$storageTemplatePath] template empty");
            throw new Exception("[$storageTemplatePath] template empty");
        }
        $this->templateDOM   = new DOMDocument();
        if (!$this->templateDOM->loadXML($templateContents)) {
            Log::error("[$storageTemplatePath] failed to loadXML()");
            throw new Exception("[$storageTemplatePath] failed to loadXML()");
        }

        // META info
        $this->xpath = new DOMXPath($this->templateDOM);
        if ($xOfficeMETA = $this->getSingleNode('/office:document/office:meta')) {
            $comment = $this->getNodeValue('dc:description', $xOfficeMETA);
            $this->comment    = ($comment ? Yaml::parse($comment) : array());
            $this->identifier = $this->getNodeValue('dc:identifier', $xOfficeMETA);
            $this->type       = $this->getNodeValue('dc:type', $xOfficeMETA);
            $this->title      = $this->getNodeValue('dc:title', $xOfficeMETA);
        }

        // Locate form controls
        //   <form:textarea form:name="scores.kurdish" form:control-implementation="ooo:com.sun.star.form.component.TextField" xml:id="control3" form:id="control3" form:input-required="false" form:convert-empty-to-null="true">
        // and text boxes
        //   <draw:frame text:anchor-type="paragraph" draw:z-index="0" draw:name="student_code" draw:style-name="gr3" draw:text-style-name="P10" svg:width="3.028cm" svg:height="1.848cm" svg:x="2.469cm" svg:y="3.454cm">
        //     <draw:text-box>
        //       <text:p>test</text:p>
        //     </draw:text-box>
        //     <svg:title>student_code</svg:title>
        //   </draw:frame>
        $xPageNode      = $this->getSingleNode('/office:document/office:body/office:text');
        $xFormNode      = $this->getSingleNode('office:forms/form:form', $xPageNode);
        $xFormControls  = $this->xpath->query('form:textarea', $xFormNode);
        $xDrawTextBoxes = $this->xpath->query('text:p/draw:frame', $xPageNode);

        // QRCode
        // <draw:frame draw:name="QRCode" svg:height="2.968cm" ...>
        //   <draw:image draw:mime-type="image/svg+xml">
        //     <office:binary-data>...
        if ($this->xQrCodeNode = $this->getSingleNode('.//draw:frame[@draw:name="QRCode"]/draw:image/office:binary-data', $xPageNode)) {
            $xQrCodeDrawImage  = $this->getSingleNode('..', $this->xQrCodeNode);
            $xQrCodeDrawFrame  = $this->getSingleNode('../..', $this->xQrCodeNode);
            $heightCM          = $xQrCodeDrawFrame->getAttribute('svg:height');
            $mimeType          = $xQrCodeDrawImage->getAttribute('draw:mime-type');
            $this->qrCodeHeightPX = floor((double) str_replace('cm', '', $heightCM) / 3.0 * 64);
            if ($mimeType != 'image/png') {
                Log::info("Changing mime-type to PNG");
                $xQrCodeDrawImage->setAttribute('draw:mime-type', 'image/png');
            }
        }

        $this->formControls = array();
        foreach ($xFormControls as $xFormControl) {
            $objectName = $xFormControl->getAttribute('form:name');
            if (!$objectName) {
                Log::error("Nameless control");
                throw new Exception("Nameless control");
            }
            $this->formControls[$objectName] = $xFormControl;
            Log::info("$objectName form control found");
        }

        $this->textBoxes = array();
        foreach ($xDrawTextBoxes as $xDrawTextBox) {
            $objectName = $xDrawTextBox->getAttribute('draw:name');
            if (!$objectName) {
                Log::error("Nameless control");
            }
            // <text:p text:style-name="P15"><text:span text:style-name="T3"><text:s/></text:span></text:p>
            $xTextP  = $this->xpath->query('draw:text-box/text:p', $xDrawTextBox)[0];
            if (!$xTextP) {
                Log::error("draw:frame without text:p");
            }
            // A child <text:span ...> allows paragraph and character formatting
            $xTextSP = $this->xpath->query('text:span', $xTextP);
            if (count($xTextSP)) $xTextP = $xTextSP[0];
            $this->textBoxes[$objectName] = $xTextP;
            Log::info("$objectName text-box found");
        }

        return $this->templateDOM;
    }

    public function writeAttributes(Model $model): void
    {
        // Write QR Code
        if ($this->xQrCodeNode) {
            // Failovers for the link to the data model edit / view screen
            $qrCodeMode   = (property_exists($model, 'qrcodemode') ? $model->qrcodemode : 'update');
            $qrCodeObject = (property_exists($model, 'qrCodeObject') ? $model->{$model->qrCodeObject} : $model);
            $qrCode       = (property_exists($model, 'qrcode') 
                ? $model->qrcode
                : (method_exists($qrCodeObject, 'controllerUrl')
                    ? $qrCodeObject->absoluteControllerUrl($qrCodeMode)
                    : json_encode(array(
                        'class' => get_class($qrCodeObject), 
                        'id'    => $qrCodeObject->id,
                        'mode'  => $qrCodeMode
                    ))
                )
            );
            Log::info("Writing PNG QRCode ({$this->qrCodeHeightPX}px) $qrCode");
            $image  = QrCode::size($this->qrCodeHeightPX)->generate($qrCode); // PNG
            $base64 = base64_encode($image);
            $this->xQrCodeNode->nodeValue = $base64;
        }

        // Fill form values
        $attributes = $model->attributesToArray();
        foreach ($attributes as $name => $value) {
            if (is_array($value)) {
                // JSONable field
                // scores => geography|history|math => id|title|value
                $i = 1;
                foreach ($value as $subName => $subValues) {
                    $subValue = (is_array($subValues)
                        ? (isset($subValues['value']) ? $subValues['value'] : NULL)
                        : $subValues
                    );

                    // scores.geography|history|math
                    $embeddedName = "$name.$subName"; 
                    if (isset($this->formControls[$embeddedName])) {
                        Log::info("Form control attribute $embeddedName => $subValue");
                        $this->formControls[$embeddedName]->setAttribute('form:current-value', $subValue);
                    }
                    else if (isset($this->textBoxes[$embeddedName])) {
                        Log::info("Text box $embeddedName => $subValue");
                        $this->textBoxes[$embeddedName]->nodeValue = $subValue;
                    }
                    else {
                        Log::warning("Text box $embeddedName => $subValue not found");
                    }

                    // scores.@x
                    $embeddedName = "$name.@$i"; 
                    if (isset($this->formControls[$embeddedName])) {
                        Log::info("Form control attribute $embeddedName => $subValue");
                        $this->formControls[$embeddedName]->setAttribute('form:current-value', $subValue);
                    }
                    else if (isset($this->textBoxes[$embeddedName])) {
                        Log::info("Text box $embeddedName => $subValue");
                        $this->textBoxes[$embeddedName]->nodeValue = $subValue;
                    }
                    $i++;
                }
            } else {
                if (isset($this->formControls[$name])) {
                    // this => that
                    Log::info("Form control attribute $name => $value");
                    $this->formControls[$name]->setAttribute('form:current-value', $value);
                }
                else if (isset($this->textBoxes[$name])) {
                    Log::info("Text box $name => $value");
                    $this->textBoxes[$name]->nodeValue = $value;
                }
                else {
                    Log::warning("Text box $name => $value not found");
                }
            }
        }
    }
    
    public function resetTemplate(): void
    {
        foreach ($this->formControls as $xFormControl) $xFormControl->setAttribute('form:current-value', '');
        foreach ($this->textBoxes    as $xDrawTextBox) $xDrawTextBox->nodeValue = '';
    }

    public function writePDF(string $outName, string $filename, bool $prepend_uniqid = TRUE): string
    {
        $tempPath = temp_path();
        $filename = preg_replace('/[^a-zA-Z0-9-_]+/', '-', $filename);
        $filename = ($prepend_uniqid ? "$outName-$filename" : $filename);
        File::put("$tempPath/$filename.fodt", $this->templateDOM->saveXML());
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