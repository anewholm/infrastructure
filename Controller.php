<?php namespace Acorn;

use BackendMenu;
use Backend\Classes\Controller as BackendController;
use Backend\Behaviors\ListController;
use Backend\Classes\FormTabs;
use Illuminate\Support\Facades\Event;
use System\Classes\PluginManager;
use DB;
use File;
use Form;
use Request;
use ReflectionClass;
use Flash;
use \Exception;
use Str;

use Acorn\User\Models\User;
use Acorn\Events\DataChange;
use Acorn\Events\UserNavigation;
use Acorn\ServiceProvider;

/**
 * Computer Product Backend Controller
 */
class Controller extends BackendController
{
    use Traits\PathsHelper;

    // These can appear in Lists and Forms
    // wherever the model is displayed
    // see config_form.yaml
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
            if ($url = $leaf->controllerUrl($this->action, $leaf->id())) {
                header("Location: $url");
                exit(0);
            }
        }
    }

    // ------------------------------------------ Event Handlers
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
            $result['id'] = (method_exists($model, 'id') ? $model->id() : $model->id);
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
        $unqualifiedModelName      = $model?->unqualifiedClassName();
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

    public function onActionFunction(): string
    {
        $results     = array();
        $fnName      = post('name');
        $closeName   = $this->transBackend('close');
        $eventJs     = 'popup';
        $initJs      = "$('body > .control-popup').trigger('$eventJs');";
        $refresh     = 'document.location.reload()';
        $fnNameParts = explode('_', $fnName);
        $nameParts   = array_slice($fnNameParts, 5);
        $title       = e(trans(Str::title(implode(' ', $nameParts))));
        $modelName   = $this->unqualifiedClassName();

        if ($fnName) {
            if ($parameters = post('parameters')) {
                if ($id = post('id')) {
                    if ($user = User::authUser()) {
                        // TODO: Dynamic parameters. Popup?
                        foreach ($parameters as $name => $type) {
                        }

                        $results = DB::select("select $fnName(?, ?)", array($id, $user->id));
                    } else throw new \Exception("onActionFunction() requires logged in user with associated User::user");
                } else throw new \Exception("onActionFunction() had no POST id");
            } else throw new \Exception("onActionFunction() had no POST parameters");
        } else throw new \Exception("onActionFunction() had no POST name");

        return <<<HTML
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
        $eventData  = post('event');
        $eventClass = $eventData['eventClass']; // Fully Qualified
        $event      = $eventClass::fromArray($eventData); // DataChange, UserNavigation
        $context    = (object) post('context'); // [acorn, data, change]
        $handled    = FALSE;

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
                            if ($record->id() == $event->id()) {
                                return 'ajax-new';
                            }
                        });
                    }
                }

                // Flash
                $unqualifiedClassName = $eventModel->unqualifiedClassName();
                $operation            = $event->operation();
                $updateUrl            = ''; // $eventModel->controllerUrl('update', $event->id());
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

        return $handled;
    }

    // -------------------------------------- Config & Display
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
        $id     = ($model && method_exists($model, 'id') ? $model->id() : NULL);
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
