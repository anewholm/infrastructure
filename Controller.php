<?php namespace Acorn;

use BackendMenu;
use Backend\Classes\Controller as BackendController;
use Backend\Behaviors\ListController;
use Backend\Classes\FormTabs;
use Illuminate\Support\Facades\Event;
use System\Classes\PluginManager;
use App;
use DB;
use File;
use Form;
use Request;
use Redirect;
use Session;
use ReflectionClass;
use Flash;
use \Exception;
use Str;
use Winter\Storm\Html\Helper as HtmlHelper;

use Acorn\User\Models\User;
use Acorn\Events\DataChange;
use Acorn\Events\UserNavigation;
use Acorn\ServiceProvider;
use Illuminate\Http\RedirectResponse;

/**
 * Computer Product Backend Controller
 */
class Controller extends BackendController
{
    use Traits\PathsHelper;

    public const DENEST = TRUE;

    // These can appear in Lists and Forms
    // wherever the model is displayed
    // see config_form.yaml
    // TODO: Not implemented yet. Only Model->actionFunctions is implemented
    public $actionFunctions = array();

    public function __construct()
    {
        parent::__construct();

        $this->addViewPath('~/modules/acorn/partials');

        Event::listen('backend.page.beforeDisplay', function($controller, $action, $params) {
            // Files commonly get loaded in popups, so we always include this widget
            // TODO: attach the FileUpload widget instead
            $controller->addJs('~/modules/backend/formwidgets/fileupload/assets/js/fileupload.js');
            $controller->addCss('~/modules/backend/formwidgets/fileupload/assets/css/fileupload.css');

            // Include general plugin CSS/JS for this controller
            // TODO: This plugin.css should be done in the Plugin, with the same event name
            $reflection = new ReflectionClass($this);
            $absolutePluginPath = File::normalizePath(dirname(dirname($reflection->getFileName())));
            $relativePluginPath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $absolutePluginPath);
            $relativeAssetPath  = 'assets/css/plugin.css';
            if (file_exists("$absolutePluginPath/$relativeAssetPath"))
                $this->addCss("$relativePluginPath/$relativeAssetPath");
            $relativeAssetPath  = 'assets/js/plugin.js';
            if (file_exists("$absolutePluginPath/$relativeAssetPath"))
                $this->addJs("$relativePluginPath/$relativeAssetPath");
        });

        // Files commonly get loaded in popups, so we always include this widget
        // TODO: Hardcoded testing to be removed
        /*
        if (class_exists('\Acorn\Justice\Models\ScannedDocument')) {
            // Users controller goes in to a loop for some reason
            if (!$this instanceof \Acorn\User\Controllers\Users) {
                $config = array(
                    'valueFrom' => 'document',
                    'model'     => new \Acorn\Justice\Models\ScannedDocument,
                );
                $pseudoUpload = new \Backend\Classes\FormField('ScannedDocument[document]', 'Document');
                $pseudoUpload->displayAs('text', $config);
                $this->widget->formDocument = new \Backend\FormWidgets\FileUpload($this, $pseudoUpload, $config);
            }
        }
        */
    }

    // ------------------------------------------ Leaf models
    public function update($id)
    {
        $this->redirectToLeaf($id);
        parent::update($id);
    }

    public function redirectToLeaf($id)
    {
        $modelClass = $this->modelFullyQualifiedClass();
        $model      = $modelClass::findOrFail($id);
        if (method_exists($model, 'getLeafTypeModel') && ($leaf = $model->getLeafTypeModel())) {
            if ($url = $leaf->controllerUrl($this->action, $leaf->id)) {
                header("Location: $url");
                exit(0);
            }
        }
    }

    // ------------------------------------------ Event Handlers
    public function onListActionTemplate()
    {
        // Options popup for multi-printing of this 
        // controller's List context
        $template    = post('template');
        $breadcrumb  = post('breadcrumb');
        $popupParams = (post('params') ?: array());
        $popupAction = 'export';
        $unqualifiedControllerName = $this->unqualifiedClassName();

        if (!property_exists($this->widget, 'list'))
            throw new Exception('onListActionTemplate requires a list widget');
        $list  = &$this->widget->list;
        $model = &$list->model;

        // ------------------------------- Render
        $postUrl        = $this->controllerUrl($popupAction); // /backend/acorn/finance/invoices/create
        $closeName      = $this->transBackend('close');
        $actionName     = $this->transBackend($popupAction);
        $modelTitle     = (method_exists($this, 'translateModelKey') && $model instanceof Model ? $this->translateModelKey('label', $model) : last(explode('\\', get_class($model))));
        $popupTitle     = "$actionName $modelTitle";
        $breadcrumbHTML = "";
        if ($breadcrumb) $breadcrumbs = explode(',', $breadcrumb);
        else             $breadcrumbs = array($unqualifiedControllerName, $popupTitle);
        foreach ($breadcrumbs as $crumb) $breadcrumbHTML .= '<li>' . trans($crumb) . '</li>';
        $eventJs   = 'popup';
        $initJs    = "$('body > .control-popup').trigger('$eventJs');";
        $form      = $this->makeFormWidget(\Backend\Widgets\Form::class, '$/modules/acorn/behaviors/batchprintcontroller/partials/fields_export.yaml');
        $formOpen  = Form::open(['class' => 'layout popup-form']); // Winter\Storm\Html\FormBuilder
        $formHtml  = $form->render();
        $formClose = Form::close();

        // Associated Field updates on success
        // We remove the braces for correct data-request-data format
        // NOTE: json_encode() will surround everything in double quotes
        $dataRequestData = array(
            'action'                => $popupAction,
            'template'              => $template,
            'params'                => $popupParams,
        );
        $dataRequestDataString = e(substr(json_encode($dataRequestData), 1, -1));

        // TODO: Encapsulate popup form display in a partial!
        return <<<HTML
            <div class="modal-header compact">
                <button type="button" class="close" data-dismiss="popup">&times;</button>
                <h4 class="modal-title">
                    <div class='control-breadcrumb'><ul>$breadcrumbHTML</ul></div>
                </h4>
            </div>
            <div class="modal-body">
                $formOpen
                $formHtml
                $formClose
            </div>
            <div class="modal-footer">
                <button
                    type='submit'
                    data-request-url='$postUrl'
                    data-request='onSomething'
                    data-request-form='.modal-body form'
                    data-request-data='$dataRequestDataString'
                    data-hotkey='ctrl+s, cmd+s'
                    data-load-indicator='$popupTitle...'
                    data-request-success='acorn_popupComplete(context, textStatus, jqXHR);'
                    data-dismiss='popup'
                    class='btn btn-primary'
                >
                    $actionName
                </button>
                <button type='button' data-dismiss='popup' class='btn btn-default'>$closeName</button>
                <script>$initJs</script>
            </div>
HTML;
    }

    public function onActionTemplate()
    {
        // Direct print template for single current model
        $template   = post('template');
        $modelClass = post('model');
        $modelId    = post('modelId');
        $outName    = uniqid('oc');
        $filename   = basename($template);

        if ($model = $modelClass::find($modelId)) {
            $pdfTemplate = new PdfTemplate($template);
            $pdfTemplate->writeAttributes($model);
            $reference   = $pdfTemplate->writePDF($outName, $filename);
            $fileUrl     = $this->actionUrl(
                'download',
                "$outName/$filename"
            );
        } else {
            throw new Exception("Model not found");
        }

        // TODO: This export_result_form does not work yet. The fileUrl is not a file, so it 404s
        return $this->makePartial('export_result_form', array(
            'returnUrl' => '.',
            'fileUrl'   => ''
        ));
    }
    
    // TODO: These were made for the list view _multi editing popups. Is there not another way?
    public function onRefreshField()
    {
        // Copied from Winter Form::onRefresh()
        // TODO: onRefreshField() doesn't work yet
        // TODO: FormController::formRenderField($name, $options = []) { return $this->formWidget->renderField($name, $options); }
        $result = [];

        if (($updateFields = post('fields')) && is_array($updateFields)) {
            foreach ($updateFields as $field) {
                if (!isset($this->allFields[$field])) {
                    continue;
                }

                /** @var FormWidgetBase $fieldObject */
                $fieldObject = $this->allFields[$field];
                $result['#' . $fieldObject->getId('group')] = $this->makePartial('field', ['field' => $fieldObject]);
            }
        }

        return $result;
    }

    public function create_onSave($context = NULL)
    {
        // Can return redirects from
        // protected function createRedirect($path, $status, $headers)
        $result = parent::create_onSave($context);

        // Include the new model id
        // $result can be a Illuminate\Http\RedirectResponse
        // creates normally redirect to updates
        if (is_null($result)) $result = array();
        if (is_array($result)) {
            $model = $this->widget->form->model;
            $result['id'] = $model->id;
        }

        return $result;
    }

    public function onPopupRoute()
    {
        // ------------------------------- Inputs
        $popupRoute  = post('route');
        $breadcrumb  = post('breadcrumb');
        $dependsOnFieldName = post('fieldName');
        $dataRequestUpdate  = post('dataRequestUpdate'); // Array

        $popupParams = (post('params') ?: array());
        $paramString = implode(',', $popupParams);
        list($controllerClass, $popupAction) = explode('@', $popupRoute);
        if (!$popupAction) $popupAction = 'create';

        // ------------------------------- Form behavior
        // Controller::create() is redirected to its implemented Form behavior:
        //   FormController::create() => FormController::initForm($model) with $model = formCreateModelObject() prepares:
        //     $this->formWidget = $this->makeWidget('Backend\Widgets\Form', $config);
        //     $this->formWidget->bindToController(); // $controller->widget->form = $this
        //     $this->prepareVars($model);
        //     $this->model = $model;
        // Use the relevant to controller to handle the form render
        $fullyQualifiedControllerClass = $this->qualifyClassName($controllerClass);
        if (!class_exists($fullyQualifiedControllerClass))  throw new \Exception("Controller [$fullyQualifiedControllerClass] does not exist");
        $controller = new $fullyQualifiedControllerClass;
        if (!is_callable(array($controller, $popupAction))) throw new \Exception("action method [$popupAction] does not exist on [$fullyQualifiedControllerClass]");
        $controller->$popupAction(...$popupParams);
        if (!property_exists($controller->widget, 'form'))  throw new \Exception("Failed to bind formWidget to controller [$fullyQualifiedControllerClass] during $popupAction($paramString)");

        $form    = &$controller->widget->form; // Backend\Widgets\Form
        $model   = &$form->model;
        $unqualifiedControllerName = $controller->unqualifiedClassName();
        $fullyQualifiedModelClass  = $model?->fullyQualifiedClassName();

        // Inject, hide and control formFields from post request
        // Fields: {legalcase_id: [@value:id]} will set the legalcase_id value to the URL id
        if (is_array(post('Fields'))) {
            foreach (post('Fields') as $fieldDirectiveName => $fieldDirectivesArray) {
                $formField = $form->getField($fieldDirectiveName);
                if (!$formField) throw new Exception("Fields directive [$fieldDirectiveName] has no target");

                foreach ($fieldDirectivesArray as $directiveName => $directiveStringValue) {
                    if (substr($directiveName, 0, 1) == '@') {
                        $directiveName = substr($directiveName, 1);
                        if ($directiveStringValue == 'id')  $directiveValue = end(explode('/', Request::url()));
                        else throw new Exception("Dynamic @Directive value not supported yet");
                    } else if (substr($directiveName, 0, 1) == '+') {
                        $directiveName  = substr($directiveName, 1);
                        $currentValue   = $formField->$directiveName;
                        $directiveValue = "$currentValue $directiveStringValue";
                    } else {
                        $directiveValue = json_decode($directiveStringValue);
                    }
                    $formField->$directiveName = $directiveValue;
                }
            }
        } else {
            // Auto match relation type fields to the parent page model
            // e.g. popup form legalcase_id => page form controller legalcases/update/9
            if ($this->action == 'update' && isset($this->params[0])) {
                $parentId = $this->params[0];
                $this->update($parentId);
                $parentModel      = $this->widget->form->config->model; // Legalcase
                $parentModelClass = get_class($parentModel);            // Acorn/Criminal/Models/Legalcase

                // We are looking for a popup model relation that points to our parent page Model
                $popupFieldNameToParentModel = NULL;
                foreach ($model->belongsTo as $belongsToName => $foreignModel) {
                    if (is_array($foreignModel)) $foreignModel = $foreignModel[0]; // Legalcase
                    if ($foreignModel == $parentModelClass) $popupFieldNameToParentModel = $belongsToName; // legalcase
                }

                if ($popupFieldNameToParentModel) {
                    foreach ($form->fields as $fieldName => &$fieldConfig) {
                        if ($fieldName == $popupFieldNameToParentModel) { // legalcase
                            $field = $form->getField($fieldName);
                            $field->value     = $parentId;
                            $field->cssClass .= ' hidden';
                        }
                    }
                }
            }
        }

        // ------------------------------- In case of translatable fields
        $this->addJs('/plugins/winter/translate/assets/js/multilingual.js?v2.1.6');
        $this->addCss('/plugins/winter/translate/assets/css/multilingual.css?v2.1.6');

        // ------------------------------- Render
        $postUrl = $controller->controllerUrl($popupAction); // /backend/acorn/finance/invoices/create
        $closeName      = $this->transBackend('close');
        $actionName     = $this->transBackend($popupAction);
        $modelTitle     = (method_exists($this, 'translateModelKey') && $model instanceof Model ? $this->translateModelKey('label', $model) : last(explode('\\', get_class($model))));
        $popupTitle     = "$actionName $modelTitle";
        $breadcrumbHTML = "";
        if ($breadcrumb) $breadcrumbs = explode(',', $breadcrumb);
        else             $breadcrumbs = array($unqualifiedControllerName, $popupTitle);
        foreach ($breadcrumbs as $crumb) $breadcrumbHTML .= '<li>' . trans($crumb) . '</li>';
        $eventJs   = 'popup';
        $initJs    = "$('body > .control-popup').trigger('$eventJs');";
        $formOpen  = Form::open(['class' => 'layout popup-form']); // Winter\Storm\Html\FormBuilder
        $formHtml  = $form->render();
        $formClose = Form::close();

        // Associated Field updates on success
        // We remove the braces for correct data-request-data format
        // NOTE: json_encode() will surround everything in double quotes
        $dataRequestData = array(
            'fully_qualified_model' => $fullyQualifiedModelClass,
            'action'                => $popupAction,
            'route'                 => $popupRoute,
            'field_name'            => $dependsOnFieldName, // Form field to refresh
            'redirect'              => 0, // IMPORTANT: This prevents the onSave() handler issuing a redirect
            'params'                => $popupParams,
        );
        $dataRequestDataString   = e(substr(json_encode($dataRequestData), 1, -1));
        $dataRequestUpdateString = e($dataRequestUpdate ? substr(json_encode($dataRequestUpdate), 1, -1) : '');

        return <<<HTML
            <div class="modal-header compact">
                <button type="button" class="close" data-dismiss="popup">&times;</button>
                <h4 class="modal-title">
                    <div class='control-breadcrumb'><ul>$breadcrumbHTML</ul></div>
                </h4>
            </div>
            <div class="modal-body">
                $formOpen
                $formHtml
                $formClose
            </div>
            <div class="modal-footer">
                <button
                    type='submit'
                    data-request-url='$postUrl'
                    data-request='onControllerSave'
                    data-request-form='.modal-body form'
                    data-request-data='$dataRequestDataString'
                    data-request-update='$dataRequestUpdateString'
                    data-hotkey='ctrl+s, cmd+s'
                    data-load-indicator='$popupTitle...'
                    data-request-success='acorn_popupComplete(context, textStatus, jqXHR);'
                    data-dismiss='popup'
                    class='btn btn-primary'
                >
                    $actionName
                </button>
                <button type='button' data-dismiss='popup' class='btn btn-default'>$closeName</button>
                <script>$initJs</script>
            </div>
HTML;
    }

    public function onControllerSave()
    {
        // Use the relevant to controller to handle the save
        $popupRoute  = post('route');
        $popupParams = (post('params') ?: array());
        list($controllerClass, $action) = explode('@', $popupRoute);

        $fullyQualifiedControllerClass  = $this->qualifyClassName($controllerClass);
        $controller = new $fullyQualifiedControllerClass;
        $onSave     = "${action}_onSave";

        return $controller->$onSave(...$popupParams);
    }

    public function onListEditableSave()
    {
        $changes = Model::listEditableSave();
        if ($changes) Flash::info(trans('acorn::lang.models.general.row_changes_saved'));
        else          Flash::warning(trans('acorn::lang.models.general.no_changes'));
        return ($changes ? Redirect::refresh() : NULL);
    }

    public function onSaveAndAddNew()
    {
        // Actually we do not refresh the page here
        // so the created values are left the same
        // to create more similar objects
        $response = $this->create_onSave();
        return ($response instanceof RedirectResponse
            ? ''
            : $response
        );
    }

    public function onActionFunction()
    {
        // Action functions can:
        //   - require user input
        //   - require verification
        //   - show results
        // If the parameters are all satisfied, then we run the function
        // otherwise we will present a form
        $response    = ''; 
        $modelId     = post('modelId');
        $modelClass  = post('model');
        $fnName      = post('name');
        $postParams  = post('parameters') ?? array();
        $user        = User::authUser();
        $closeName   = $this->transBackend('close');
        $eventJs     = 'popup';
        $initJs      = "$('body > .control-popup').trigger('$eventJs');";
        $refresh     = 'document.location.reload()';
        $fnNameParts = explode('_', $fnName);
        $nameParts   = array_slice($fnNameParts, 5);
        $title       = e(trans(Str::title(implode(' ', $nameParts))));
        $modelName   = $this->unqualifiedClassName();
        
        if (!$fnName)     throw new \Exception("onActionFunction() had no POST name");
        if (!$user)       throw new \Exception("onActionFunction() requires logged in user with associated User::user");
        if (!$modelClass) throw new \Exception("onActionFunction() had no POST Model class");

        // These will throw their own Exceptions
        $model          = ($modelId ? $modelClass::find($modelId) : new $modelClass());
        $actionFunctionDefinition = $model->actionFunctions(NULL, $fnName);
        $fnDatabaseName = $actionFunctionDefinition['fnDatabaseName'];
        $fnParams       = $actionFunctionDefinition['parameters'];
        $returnType     = $actionFunctionDefinition['returnType'];
        $title          = trans($actionFunctionDefinition['label']);
        $resultAction   = $actionFunctionDefinition['resultAction'] ?? NULL;
        $classParts     = explode('\\', get_class($model));
        $shortClass     = end($classParts);
        $classField     = Str::snake($shortClass); // academic_year
        $comment        = (isset($actionFunctionDefinition['comment']['en']) ? $actionFunctionDefinition['comment']['en'] : NULL);
        $commentIcon    = (isset($actionFunctionDefinition['commentIcon']) ? $actionFunctionDefinition['commentIcon'] : NULL);

        // TODO: SECURITY: Action Function Premissions

        // Parameter gathering
        $paramsMerged      = array();
        $unsatisfiedParams = array();
        foreach ($fnParams as $paramName => $paramType) {
            switch ($paramName) {
                case 'model_id':
                    $paramsMerged[$paramName] = array(
                        'value' => $model->id,
                        'type'  => $paramType,
                    );
                    break;
                case 'user_id':
                    $paramsMerged[$paramName] = array(
                        'value' => $user->id,
                        'type'  => $paramType,
                    );
                    break;
                default:
                    if (isset($postParams[$paramName])) $paramsMerged[$paramName] = array(
                        'value' => $postParams[$paramName],
                        'type'  => $paramType,
                    );
                    else {
                        $unsatisfiedParams[$paramName] = $paramType;
                    }
                    break;
            }
        }
            
        if (count($unsatisfiedParams)) {
            // ---------------------------------------------------- Show a form
            // Construct the form configuration
            $fieldsPath  = $this->modelDirectoryPathRelative('fields.yaml');
            $modelFields = $this->flattenFields($this->makeConfig($fieldsPath), self::DENEST);
            $formConfig = array(
                'model'  => $model,
                'fields' => array(),
            );

            // Adopt the main models form field configs if there is one
            foreach ($unsatisfiedParams as $paramName => $paramType) {
                $baseParamName       = preg_replace('/^p_|_id$/', '', $paramName);
                $fieldParametersName = "parameters[$paramName]";

                $config = array(
                    'label' => Str::headline($baseParamName),
                );

                if (isset($modelFields[$baseParamName])) {
                    $config = $modelFields[$baseParamName];
                    $config['comment'] = '';
                } else if ($baseParamName == $classField) {
                    $config = array_merge($config, array(
                        'type'    => 'dropdown',
                        'options' => "$modelClass::dropdownOptions",
                    ));
                } else {
                    switch ($paramType) {
                        case 'double precision':
                        case 'double':
                        case 'int':
                        case 'bigint':
                        case 'integer':
                            $config['type'] = 'number';
                            break;
                        case 'timestamp with time zone':
                        case 'timestamp without time zone':
                        case 'date':
                        case 'datetime':
                            $config['type'] = 'datepicker';
                            break;
                        case 'interval':
                            // TODO: Currently intervals are just presented as text
                            break;
                        case 'boolean':
                        case 'bool':
                            $config['type']    = 'switch';
                            $config['default'] = true;
                            break;
                        case 'char':
                            $config['length'] = 1;
                            break;
                        case 'text':
                            $config['type'] = 'richeditor';
                            break;
                        case 'path':
                            // File uploads are NOT stored in the actual column
                            $uploadDefinition = array(
                                'type'         => 'fileupload',
                                'mode'         => 'image',
                                'required'     => FALSE,
                                'imageHeight'  => 260,
                                'imageWidth'   => 260,
                                'thumbOptions' => array(
                                    'mode'      => 'crop',
                                    'offset'    => array(0,0),
                                    'quality'   => 90,
                                    'sharpen'   => 0,
                                    'interlace' => FALSE,
                                    'extension' => 'auto',
                                ),
                            );
                            $config = array_merge($config, $uploadDefinition);
                            break;
                    }
                }
                $formConfig['fields'][$fieldParametersName] = $config;
            }

            // Build a Form Widget
            $form       = new \Backend\Widgets\Form($this, $formConfig);
            $formOpen   = Form::open(['class' => 'layout popup-form']); // Winter\Storm\Html\FormBuilder
            $formHtml   = $form->render();
            $formClose  = Form::close();
            $cancelName = $this->transBackend('cancel');
                
            // Data-request
            $dataRequest           = __FUNCTION__;
            $dataRequestData       = post();
            $dataRequestDataString = e(substr(json_encode($dataRequestData), 1, -1));;

            // TODO: Translate comments
            $commentIconHtml = '';
            if ($commentIcon) {
                $imageBasePath   = '/modules/acorn/assets/images';
                $size            = 64;
                $commentIconHtml = "<img class='comment-icon' src='$imageBasePath/$commentIcon-$size.png'></img>";
            }
            $commentEscaped = e($comment);
            $commentHtml    = ($comment
                ? "<div class='help-block function-description'>$commentIconHtml$commentEscaped</div>"
                : NULL
            );

            // Render
            $response = <<<HTML
                <div class="modal-header compact">
                    <button type="button" class="close" data-dismiss="popup">&times;</button>
                    <h4 class="modal-title">
                        <div class='control-breadcrumb'><ul><li>$title</li></ul></div>
                    </h4>
                </div>
                <div class="modal-body">
                    $formOpen
                    $formHtml
                    $formClose
                    $commentHtml
                </div>
                <div class="modal-footer">
                    <button
                        type='submit'
                        data-request='$dataRequest'
                        data-request-form='.modal-body form'
                        data-request-data='$dataRequestDataString'
                        data-hotkey='ctrl+s, cmd+s'
                        data-load-indicator='$title...'
                        class='btn btn-primary'
                    >
                        $title
                    </button>
                    <button type='button' data-dismiss='popup' class='btn btn-default'>$cancelName</button>
                    <script>$initJs</script>
                </div>
HTML;
        } 
        
        else {
            // ---------------------------------------------------- Run the action function
            // The order is critical here as they are not named parameters
            $results      = array();
            $bindings     = array_pluck($paramsMerged, 'value');
            $placeholders = implode(',', array_fill(0, count($bindings), '?'));
            switch ($returnType) {
                case 'record':
                    // TODO: runFunction() 
                    $results = DB::select("select * from $fnDatabaseName($placeholders)", $bindings);
                    break;
                default:
                    $results = DB::select("select $fnDatabaseName($placeholders) as result", $bindings);
            }
            
            // TODO: Respond with an immediate refresh
            // TODO: configurable responses
            switch ($resultAction) {
                case 'model-uuid-redirect':
                    $newModelId = $results[0]->result;
                    $response   = Redirect::to($newModelId);
                    break;
                case 'refresh':
                    $response   = Redirect::refresh();
                    break;
                default:
                    $response   = <<<HTML
                    <div class="modal-header compact">
                        <button type="button" class="close" data-dismiss="popup">&times;</button>
                        <h4 class="modal-title">
                            <div class='control-breadcrumb'><ul><li>$modelName</li><li>$title</li>></ul></div>
                        </h4>
                    </div>
                    <div class="modal-body">
                        SUCCESS
                    </div>
                    <div class="modal-footer">
                        <button
                        type='button'
                        data-dismiss='popup'
                        class='btn btn-default'
                        onclick='$refresh'
                        >$closeName</button>
                        <script>$initJs</script>
                    </div>
HTML;
            }
        }

        return $response;
    }

    public function onLoadQrScanPopup()
    {
        // Post can include:
        //   actions:     array of string function names of the qrscan
        //   formFieldId: the form field in the main form to update with the value
        //   formClass:   the (unique) HTML class of a form to update with the scanned value
        //   formId:      the HTML @id of a form to update with the scanned value
        // The popup will JavaScript trigger(change) on the form field after inserting it into
        // the destsination form
        $actions        = post('actions');
        $eventJs        = 'popup';
        $actionsList    = '';
        $translationBaseKey = 'acorn::lang.models.general';
        foreach ($actions as $action) {
            $actionKey = str_replace('-', '_', $action);
            if ($actionsList) $actionsList .= ' | ';
            $actionsList .= e(trans("$translationBaseKey.$actionKey")); // str_replace('-', ' ', Str::title($action));
        }
        $scanQrCode     = e(trans('acorn::lang.models.general.scan_qrcode'));
        $breadcrumbHTML = "<li>$scanQrCode</li><li>$actionsList</li>";
        ///modal genreal scan_qrcode
        $closeName      = $this->transBackend('close');
        $initJs         = "$('body > .control-popup').trigger('$eventJs');";
        $qrScanPartial  = $this->makePartial('qrscan', post());

        return <<<HTML
            <div class="modal-header compact">
                <button type="button" class="close" data-dismiss="popup">&times;</button>
                <h4 class="modal-title">
                    <div class='control-breadcrumb'><ul>$breadcrumbHTML</ul></div>
                </h4>
            </div>
            <div class="modal-body">
                $qrScanPartial
            </div>
            <div class="modal-footer">
                <script>$initJs</script>
            </div>
HTML;
    }

    public function onWebSocket()
    {
        $handled    = FALSE;
        $eventData  = post('event');
        // Check if it is a WebSocket event that we understand
        if (is_array($eventData) && isset($eventData['eventClass'])) {
            $eventClass = $eventData['eventClass']; // Fully Qualified
            $event      = $eventClass::fromArray($eventData); // DataChange, UserNavigation
            $context    = (object) post('context'); // [acorn, data, change]

            // Actually only necessary for partials with 'list'
            // Request::header('X_WINTER_REQUEST_PARTIALS')
            if (array_search(ListController::class, $this->implement)) {
                // Returns 'list' => Backend\Widgets\Lists
                $this->makeLists();
            }

            // ALL websocket events come through to here
            // DataChange, UserNavigation, etc.
            switch (get_class($event)) {
                case DataChange::class:
                    // Backend\Behaviors\ListController
                    if (property_exists($this->widget, 'list')) {
                        // List config
                        $listWidget = $this->widget->list;
                        $listConfig = &$listWidget->config;
                        $listModel  = &$listConfig->model;
                        $eventModel = $event->model();

                        if (get_class($listModel) == get_class($eventModel)) {
                            // Highlight the new change in list displays
                            $listWidget->bindEvent('list.injectRowClass', function ($record) use (&$event) {
                                if ($record->id == $event->id) {
                                    return 'ajax-new';
                                }
                            });
                        }
                    }

                    // Flash
                    $unqualifiedClassName = $eventModel->unqualifiedClassName();
                    $operation            = $event->operation();
                    $updateUrl            = ''; // $eventModel->controllerUrl('update', $event->id);
                    $viewHTML             = ''; // TODO: "<a target='_blank' href='$updateUrl'>view</a>";
                    $flash                = "A new $unqualifiedClassName has been $operation. $viewHTML";
                    Flash::success($flash);

                    $handled = TRUE;
                    break;

                case UserNavigation::class:
                    $user = BackendAuth::user();
                    if ($event->isFor($user)) {
                        // TODO: Acorn\\Events\\UserNavigation return a Redirect command
                    }
                    $handled = TRUE;
                    break;
            }

            // ~/modules/backend/classes/Controller.php execAjaxHandlers() will now:
            // $partialList = Request::header('X_WINTER_REQUEST_PARTIALS')
            // foreach ($partialList as $partial) {
            //     $responseContents[$partial] = $this->makePartial($partial);
            // }
        }

        return $handled;
    }

    /*
    public function run($action = null, $params = []) {
        // TODO: Early attempt at popup controller redirection for relation managers: REMOVE
        // This is our first opportunity to intercept the BackendController processing
        // it has already selected this Controller using:
        // function BackendController::run($url = null)
        //   $controllerRequest = $this->getRequestedController($url);
        //   if (!is_null($controllerRequest)) {
        //       return $controllerRequest['controller']->run(
        //           $controllerRequest['action'],
        //           $controllerRequest['params']
        //       );
        //   }
        // getRequestedController($controller) uses:
        //   App::make($controller)
        $post = post();
        if (   isset($post['column_order']) 
            && isset($post['_parent_model'])
            && !isset($params['redirected_controller'])
        ) {
            // This is a columns config save request
            // Redirect to the appropriate controller
            $parentModel   = $post['_parent_model'];
            $parentModelId = $post['_parent_model_id'];
            $parentObj     = $parentModel::find($parentModelId);
            $controller    = $this->controllerFullyQualifiedClass($parentObj);
            $controllerObj = App::make($controller);
            $params['redirected_controller'] = TRUE;
            $params[0]     = $parentModelId;
            $response      = $controllerObj->run($action, $params);
        } else {
            unset($params['redirected_controller']);
            $response = parent::run($action, $params);
        }
        return $response;
    }
    */

    // -------------------------------------- Config & Display
    public function onGlobalScopeChange()
    {
        foreach (post() as $setting => $value) {
            $settingParts = explode('::', $setting);
            if (isset($settingParts[1]) && $settingParts[1] == 'globalScope') 
                Session::put($setting, $value);
        }
        return Redirect::refresh();
    }

    public function listExtendRecords($records)
    {
        // Custom relation scopes based on relations, not SQL
        // relationCondition => <the name of the relevant relation>, e.g. belongsTo['language']
        // Filters the listed models based on a filtered: of selected related models
        if (property_exists($this->widget, 'listFilter')) {
            foreach ($this->widget->listFilter->getScopes() as $name => $filterScope) {
                if (isset($filterScope->config['relationCondition']) && $filterScope->value) {
                    $relationCondition = $filterScope->config['relationCondition'];
                    foreach ($records as $i => &$record) {
                        // TODO: This is post SQL fetch so the pagination doesn't work
                        if ($relatedModels = $record->$relationCondition) {
                            $ids = $relatedModels->pluck('id')->toArray();
                            if (!array_intersect(array_keys($filterScope->value), $ids))
                                unset($records[$i]);
                        }
                    }
                }
            }
        }
        return parent::listExtendRecords($records);
    }

    public function listRender()
    {
        // Automatically listen for updates
        $html  = '<div id="ListWidgetContainer" websocket-listen="acorn" websocket-onacorn-data-change-update="\'list\': \'#ListWidgetContainer\'">';
        $html .= parent::listRender();
        $html .= '</div>';
        return $html;
    }

    public function formGetRedirectUrl($context = NULL, $model = NULL)
    {
        $action = post('action');
        $id     = ($model ? $model->id : NULL);
        return ($action && !is_null($id) ? "$action/$id" : parent::formGetRedirectUrl($context, $model));
    }

    public function formTertiaryTabs(): string
    {
        // NOTE: This combines with the AA\Module ServiceProvider Event backend.form.extendFields
        // form-with-sidebar layout sidebar
        $html = '';
        $form = &$this->widget->form;
        if ($tab = $form->getTab('tertiary')) {
            if ($fieldTabs = $tab->getFields())
                $html = $form->makePartial('form_fields', array('fields' => end($fieldTabs)));
        }

        return $html;
    }

    public function lastNestedFieldName(string $name): string
    {
        $names = HtmlHelper::nameToArray($name);
        return end($names);
    }

    public function flattenFields(\stdClass $config, bool $denest = FALSE): array
    {
        $flatFields = array();
        foreach ($config->fields as $name => $field) {
            if ($denest) $name = $this->lastNestedFieldName($name);
            $flatFields[$name] = $field;
        }
        if (property_exists($config, 'tabs')) {
            foreach ($config->tabs['fields'] as $name => $field) {
                if ($denest) $name = $this->lastNestedFieldName($name);
                $flatFields[$name] = $field;
            }
        }
        return $flatFields;
    }

    // -------------------------------------- ViewMaker overrides for debug
    // Copied from ViewMaker.php
    public function makePartial(string $partial, array $params = [], bool $throwException = true)
    {
        if (ServiceProvider::isDebug('partials')) {
            $partialOriginal = $partial;

            // Copied from ViewMaker.php
            $notRealPath = realpath($partial) === false || is_dir($partial) === true;
            if (!File::isPathSymbol($partial) && $notRealPath) {
                $folder = strpos($partial, '/') !== false ? dirname($partial) . '/' : '';
                $partial = $folder . '_' . strtolower(basename($partial));
            }

            $partialPath = $this->getViewPath($partial);

            return $this->debugWrap(parent::makePartial($partialOriginal, $params, $throwException), $partialPath, 'partials');
        }

        return parent::makePartial($partial, $params, $throwException);
    }

    public function makeView(string $view): string
    {
        if (ServiceProvider::isDebug('views')) {
            // Copied from ViewMaker.php
            $viewPath = $this->getViewPath(strtolower($view));
            return $this->debugWrap(parent::makeView($view), $viewPath, 'views');
        }

        return parent::makeView($view);
    }

    public function makeLayout(string $name = null, array $params = [], bool $throwException = true): string|bool
    {
        if (ServiceProvider::isDebug('layouts')) {
            // Copied from ViewMaker.php
            $layout = $name ?? $this->layout;
            $layoutPath = $this->getViewPath($layout, $this->layoutPath);

            return $this->debugWrap(parent::makeLayout($name, $params, $throwException), $layoutPath, 'layouts');
        }

        return parent::makeLayout($name, $params, $throwException);
    }

    public function makeLayoutPartial(string $partial, array $params = []): string
    {
        if (ServiceProvider::isDebug('layouts')) {
            $partialOriginal = $partial;

            // Copied from ViewMaker.php
            if (!File::isLocalPath($partial) && !File::isPathSymbol($partial)) {
                $folder = strpos($partial, '/') !== false ? dirname($partial) . '/' : '';
                $partial = $folder . '_' . strtolower(basename($partial));
            }

            return $this->debugWrap(parent::makeLayoutPartial($partialOriginal, $params), $partial, 'layouts');
        }

        return parent::makeLayoutPartial($partial, $params);
    }

    protected function debugWrap(string $content, string $debugHtml, string $type, bool $removeAbsolutePaths = TRUE): string
    {
        // Ignore CSS class and HTML attribute rendering
        if (   strstr($debugHtml, 'icon-classes')     === FALSE
            && strstr($debugHtml, 'browser_detector') === FALSE
        ) {
            if ($removeAbsolutePaths) $debugHtml = str_replace($_SERVER['DOCUMENT_ROOT'], '', $debugHtml);
            // Add .debug-relative to the first element class
            // TODO: What if it does not have a @class
            $debugPane = "<div class='debug debug-$type'>$debugHtml</div>";
            $debugPaneEscaped = htmlentities($debugPane);
            $content   = preg_replace('/^(\s*<[^ >]+) /', "\\1 debug-pane='$debugPaneEscaped' ", $content);
        }

        return $content;
    }
}
