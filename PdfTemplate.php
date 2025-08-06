<?php namespace Acorn;

use Yaml;
use Str;
use Lang;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App;
use Model;
use File;
use Log;
use Exception;
use Storage;
use DOMDocument;
use DOMNode;
use DOMXPath;

class PdfTemplate {
    protected $mediaDir;
    protected $templateFilePath;
    protected $templateDOM, $xpath;
    protected $textBoxes, $xQrCodeNode, $qrCodeHeightPX;
    protected $boxWarnings = array();

    public $comment;
    // Limit to a course, if relevant
    public $courseCode;
    public $courseName;
    // Where the action should appear, like fields.yaml
    // create, update, index
    public $contexts = array(); 
    public $title;
    public $templateLocale, $localeFallback;

    public function __construct(string $templateFilePath = NULL, string $mediaDir = 'media')
    {
        if ($templateFilePath) $this->loadTemplate($templateFilePath, $mediaDir);
    }

    public function storageTemplatePath(): string
    {
        return "$this->mediaDir/$this->templateFilePath";
    }

    protected function getSingleNode(string $xpath, DOMNode $xStartNode = NULL, bool $throwIfMultiple = FALSE): DOMNode|null
    {
        $xNode     = NULL;
        $xNodeList = $this->xpath->query($xpath, $xStartNode);
        if (count($xNodeList)) $xNode = $xNodeList[0];

        if ($throwIfMultiple && count($xNodeList) > 1)
            throw new Exception("Multiple nodes returned during [$xpath] request");

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

        if (isset($this->comment[$labelSet])) {
            $labels = $this->comment[$labelSet];
            if      (isset($labels[$this->templateLocale]))         $label = $labels[$this->templateLocale];
            else if (isset($labels[$this->localeFallback])) $label = $labels[$this->localeFallback];
        } 
        
        if (is_null($label)) {
            $templateFileName = preg_replace('/^.*\/|\..*$/', '', $this->templateFilePath);
            $label            = Str::headline(str_ireplace('template', '', $templateFileName));
            if ($plural) $label = Str::plural($label);
            $label = trans($label);
        }

        return $label;
    }

    public function boxNames(): array
    {
        return array_keys($this->textBoxes);
    }

    public function forContext(string $context): bool
    {
        return (!$this->contexts || in_array($context, $this->contexts));
    }

    public function forUpdateContext(): bool
    {
        return $this->forContext('update');
    }

    public function forIndexContext(): bool
    {
        return $this->forContext('index');
    }

    public function details(): array
    {
        return array(
            'acorn::lang.models.pdftemplate.title'       => $this->label(),
            'acorn::lang.models.pdftemplate.coursecode'  => $this->courseCode,
            'acorn::lang.models.pdftemplate.coursename'  => $this->courseName,
            'acorn::lang.models.pdftemplate.locale'      => $this->templateLocale,
            'acorn::lang.models.pdftemplate.boxnames'    => implode(', ', $this->boxNames()),
            'acorn::lang.models.pdftemplate.boxwarnings' => implode(', ', $this->boxWarnings),
        );
    }

    public function loadTemplate(string $templateFilePath, string $mediaDir = 'media'): DOMDocument
    {
        // Load template
        $this->mediaDir         = $mediaDir;
        $this->templateFilePath = trim($templateFilePath, '/');
        $storageTemplatePath    = $this->storageTemplatePath();

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
            // Usually contains labels
            $this->comment    = ($comment ? Yaml::parse($comment) : array());
            // Limiting to a Course, if relevant
            $this->courseCode = $this->getNodeValue('dc:identifier', $xOfficeMETA);
            $this->courseName = $this->getNodeValue('dc:type', $xOfficeMETA);
            // English Name, not really used
            $this->title      = $this->getNodeValue('dc:title', $xOfficeMETA);
            // WinterCMS Model contexts: create, update, preview
            $this->contexts   = array_filter(preg_split('/ *, */', $this->getNodeValue('dc:coverage', $xOfficeMETA)));
            // Overall default language, if applicable
            //
            // Also can be set on each text element
            // Language information, set with the Character... dialog is stored separately
            // in an associated <style> element in <office:automatic-styles>
            // @fo:language="en" and @fo:country="US"
            //   <style:style style:name="T5" style:family="text">
            //     <style:text-properties fo:color="#808080" loext:opacity="100%" fo:language="ast" fo:country="ES" fo:font-weight="bold" style:font-weight-asian="bold" style:font-weight-complex="bold"/>
            //   </style:style>
            $this->templateLocale = $this->getNodeValue('dc:relation', $xOfficeMETA);

            // Older LibreOffice does not support the comments above
            // in this case we check the user defined values
            if ($value = $this->getNodeValue("meta:user-defined[@meta:name='comment']", $xOfficeMETA))
                $this->comment = Yaml::parse($comment);
            if ($value = $this->getNodeValue("meta:user-defined[@meta:name='dc:identifier']", $xOfficeMETA))
                $this->courseCode = $value;
            if ($value = $this->getNodeValue("meta:user-defined[@meta:name='dc:type']", $xOfficeMETA))
                $this->courseName = $value;
            if ($value = $this->getNodeValue("meta:user-defined[@meta:name='dc:title']", $xOfficeMETA))
                $this->title = $value;
            if ($value = $this->getNodeValue("meta:user-defined[@meta:name='dc:coverage']", $xOfficeMETA))
                $this->contexts = array_filter(preg_split('/ *, */', $value));
            if ($value = $this->getNodeValue("meta:user-defined[@meta:name='dc:relation']", $xOfficeMETA))
                $this->templateLocale = $value;

            // Set global language
            if ($this->templateLocale) {
                Lang::setLocale($this->templateLocale);
                Log::info("Template locale: $this->templateLocale");
            }
        }
        if (!$this->templateLocale) Log::warning("Template locale not stated");
        $this->localeFallback = Lang::getFallback();

        Log::info("---------------------------------- Searching for dynamic elements");
        $xPageNode      = $this->getSingleNode('/office:document/office:body/office:text');
        $xDrawTextBoxes = $this->xpath->query('.//text:p/draw:frame', $xPageNode);

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

        // Locate text boxes
        //  <text:p>
        //   <draw:frame text:anchor-type="paragraph" draw:z-index="0" draw:name="student_code" draw:style-name="gr3" draw:text-style-name="P10" svg:width="3.028cm" svg:height="1.848cm" svg:x="2.469cm" svg:y="3.454cm">
        //     <draw:text-box>
        //       <text:p>[<text:span text:style-name="T5">]test</text:p>
        //     </draw:text-box>
        //     <svg:title>student_code</svg:title>
        //   </draw:frame>
        //  </text:p>
        //
        // Language information, set with the Character... dialog is stored separately
        // in an associated <style> element in <office:automatic-styles>
        // referenced from text:span/text:style-name
        //   <style:style style:name="T5" style:family="text">
        //     <style:text-properties fo:color="#808080" loext:opacity="100%" fo:language="ast" fo:country="ES" fo:font-weight="bold" style:font-weight-asian="bold" style:font-weight-complex="bold"/>
        //   </style:style>
        $this->textBoxes = array();
        foreach ($xDrawTextBoxes as $xDrawTextBox) {
            if ($objectName = $xDrawTextBox->getAttribute('draw:name')) {
                // text-box names are unique in LibreOffice
                // allow for box.name 1 auto renaming in LibreOffice
                $objectName = preg_replace('/ [0-9]+$/', '', $objectName);

                // <text:p text:style-name="P15"><text:span text:style-name="T3"><text:s/></text:span></text:p>
                if ($xTextP = $this->getSingleNode('draw:text-box/text:p', $xDrawTextBox)) {
                    // A child <text:span ...> allows paragraph and character formatting
                    // and language setting through the <style> link
                    $language = NULL;
                    $xTextSPs = $this->xpath->query('text:span', $xTextP);
                    if (count($xTextSPs) == 1) {
                        $xTextP = $xTextSPs[0];

                        if ($styleName = $xTextP->getAttribute('text:style-name')) {
                            if ($xStyle = $this->getSingleNode(".//style:style[@style:name='$styleName']")) {
                                if ($xTextProperties = $this->getSingleNode("./style:text-properties", $xStyle)) {
                                    // @style:language-complex, @style:country-complex 
                                    // & @fo:language, @fo:country can exist together
                                    // language complex takes precedence
                                    $country  = $xTextProperties->getAttribute('style:country-complex');
                                    $language = $xTextProperties->getAttribute('style:language-complex');
                                    if ($language && $country != 'none') {
                                        // Set the language directly on the text-box for easy access later
                                        $xTextP->setAttribute('fo:language', $language);
                                    } else {
                                        $country  = $xTextProperties->getAttribute('fo:country');
                                        $language = $xTextProperties->getAttribute('fo:language');
                                        if ($language && $country != 'none') {
                                            // Set the language directly on the text-box for easy access later
                                            $xTextP->setAttribute('fo:language', $language);
                                        }
                                    }
                                } else {
                                    Log::error("<style:style style:name=$styleName> without style:text-properties");
                                }
                            } else {
                                Log::error("<style:style style:name=$styleName> not found");
                            }
                        }
                    } else if (count($xTextSPs) > 1) {
                        array_push($this->boxWarnings, "Multiple <text:span>s in <text:p> box [$objectName]");
                    }

                    if (isset($this->textBoxes[$objectName])) array_push($this->textBoxes[$objectName], $xTextP);
                    else $this->textBoxes[$objectName] = array($xTextP);
                    Log::info("'$objectName' text-box found ($language)");
                } else {
                    Log::warning("draw:frame without text:p");
                }
            } else {
                Log::error("Nameless control");
            }
        }

        return $this->templateDOM;
    }

    public function writeAttributes(Model $model): void
    {
        Log::info("---------------------------------- Writing attributes");

        // Write QR Code
        if ($this->xQrCodeNode) {
            // Failovers for the link to the data model edit / view screen
            $qrCodeMode   = ($model->hasAttribute('qrcodemode') ? $model->qrcodemode : 'update');
            $qrCodeObject = ($model->hasAttribute('qrCodeObject') ? $model->{$model->qrCodeObject} : $model);
            $qrCode       = ($model->hasAttribute('qrcode') 
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
        foreach ($this->textBoxes as $name => $xTextBoxes) {
            foreach ($xTextBoxes as $xTextBox) {
                $itemLocale  = $xTextBox->getAttribute('fo:language');
                $locale      = ($itemLocale ?: $this->templateLocale);
                
                $nameParts = explode('.', $name);
                if (count($nameParts) == 1) {
                    if ($model->hasAttribute($name)) {
                        $value = $model->getAttributeTranslated($name, $locale);
                        if ($value) {
                            $xTextBox->nodeValue = $value;
                            Log::info("Text box $name => $value ($locale)");
                        } else {
                            Log::warning("Text box $name NOT CHANGED because value was empty ($locale)");
                        }
                    }
                } else {
                    // JSONable field: scores.Kurdish
                    // scores => geography|history|math|... => id|title|value
                    // scores => @1|@2|@3|...               => id|title|value
                    $modelAttribute = $nameParts[0]; // scores
                    $arrayItem      = $nameParts[1]; // Kurdish
                    $content        = (count($nameParts) > 2 ? $nameParts[2] : 'value'); // title|value|minimum|...
                    if ($model->hasAttribute($modelAttribute)) {
                        $objectArray = $model->{$modelAttribute};
                        if ($arrayItem[0] == '@') {
                            $offset    = (int) substr($arrayItem, 1);
                            $keys      = array_keys($objectArray);
                            if (isset($keys[$offset])) $arrayItem = $keys[$offset];
                        }
                        if (isset($objectArray[$arrayItem])) {
                            $object = $objectArray[$arrayItem];
                            Log::info($objectArray);
                            $value  = (is_array($object) ? $object[$content] : $object);
                            if (is_array($value)) {
                                if      (isset($value[$locale]))               $value = $value[$locale];
                                else if (isset($value[$this->localeFallback])) $value = $value[$this->localeFallback];
                                else $value = implode(',', array_keys($value));
                            }
                            if ($value) {
                                $xTextBox->nodeValue = $value;
                                Log::info("Text box $name => $value ($locale)");
                            } else {
                                Log::warning("Text box $name NOT CHANGED because value was empty ($locale)");
                            }
                        } else {
                            Log::error("{$modelAttribute}[$arrayItem] not found");
                        }
                    }
                }
            }
        }
    }

    public function resetTemplate(): void
    {
        foreach ($this->textBoxes    as $xDrawTextBox) $xDrawTextBox->nodeValue = '';
    }

    public function getTemplateThumbnail(): string
    {
        $storageTemplatePath = $this->storageTemplatePath();
        $storagePngPath      = preg_replace('/\.[a-z]+$/', '.png', $storageTemplatePath);
        if (!Storage::exists($storagePngPath)) {
            $fullTemplatePath = Storage::path($storageTemplatePath);
            $storagePngDir    = dirname($fullTemplatePath);
            $execOutput = exec("libreoffice --headless --convert-to png $fullTemplatePath --outdir $storagePngDir");
        }
        return Storage::url($storagePngPath);
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