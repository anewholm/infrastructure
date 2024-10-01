<?php namespace Acorn;

use BackendMenu;
use Backend\Classes\Controller as BackendController;
use Backend\Behaviors\ListController;
use Illuminate\Support\Facades\Event;
use System\Classes\PluginManager;
use File;
use Form;
use Request;
use ReflectionClass;
use Flash;
use \Exception;

use Acorn\Events\DataChange;
use Acorn\Events\UserNavigation;

/**
 * Computer Product Backend Controller
 */
class Controller extends BackendController
{
    use Traits\PathsHelper;

    public function __construct()
    {
        parent::__construct();

        $this->addViewPath('modules/acorn/partials/');

        Event::listen('backend.page.beforeDisplay', function($controller, $action, $params) {
            $this->addJs('/modules/acorn/assets/js/acorn.js');
            $this->addJs('/modules/acorn/assets/js/acorn.websocket.js', array('type' => 'module'));

            // Forms
            $this->addJs('/modules/acorn/assets/js/html5-qrcode.js');
            $this->addJs('/modules/acorn/assets/js/findbyqrcode.js');
            $this->addJs('/modules/acorn/assets/js/forms.js');
            $this->addJs('/modules/acorn/assets/js/tabbing.js');
            $this->addCss('/modules/acorn/assets/css/tabbing.css');
            $this->addCss('/modules/acorn/assets/css/menus.css');
            $this->addCss('/modules/acorn/assets/css/forms.css');
            $this->addCss('/modules/acorn/assets/css/lists.css');
            $this->addCss('/modules/acorn/assets/css/qrcode-printing.css');
            $this->addCss('/modules/acorn/assets/css/html5-qrcode.css');

            // TODO: Make this global and a plugin asset

            // Include general plugin CSS/JS for this controller
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
        if ($leaf = $model->getLeafTypeModel()) {
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
        $fieldName   = post('fieldName');

        $popupParams = explode(',', post('params'));
        list($controllerClass, $action) = explode('@', $popupRoute);
        if (!$action) $action = 'create';

        // ------------------------------- Form behavior
        // FormController::create() => FormController::initForm($model) prepares:
        //   $this->formWidget
        //   $this->prepareVars($model);
        //   $this->model = $model;
        $fullyQualifiedControllerClass = $this->qualifyClassName($controllerClass);
        $controller = new $fullyQualifiedControllerClass;
        $controller->$action(...$popupParams);
        $form    = &$controller->widget->form; // Backend\Widgets\Form
        $model   = &$form->model;
        $unqualifiedControllerName = $controller->unqualifiedClassName();
        $unqualifiedModelName      = $model?->unqualifiedClassName();
        $fullyQualifiedModelClass  = $model?->fullyQualifiedClassName();

        // Inject, hide and control formFields from post request
        if (is_array(post('Fields'))) {
            foreach (post('Fields') as $fieldName => $fieldDirectivesArray) {
                $formField = $form->getField($fieldName);
                if (!$formField) throw new Exception("Fields directive $fieldName has no target");

                foreach ($fieldDirectivesArray as $directiveName => $directiveStringValue) {
                    if (substr($directiveName, 0, 1) == '@') {
                        $directiveName = substr($directiveName, 1);
                        if ($directiveStringValue == 'id')  $directiveValue = last(explode('/', Request::url()));
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
        }

        // ------------------------------- In case of translatable fields
        $this->addJs('/plugins/winter/translate/assets/js/multilingual.js?v2.1.6');
        $this->addCss('/plugins/winter/translate/assets/css/multilingual.css?v2.1.6');

        // ------------------------------- Render
        $postUrl = $controller->controllerUrl($action); // /backend/acorn/finance/invoices/create
        $closeName      = $this->transBackend('close');
        $actionName     = $this->transBackend($action);
        $modelTitle     = (method_exists($this, 'transModel') ? $this->transModel('label', $model) : last(explode('\\', get_class($model))));
        $title          = "$actionName $modelTitle";
        $breadcrumbHTML = "";
        if ($breadcrumb) $breadcrumbs = explode(',', $breadcrumb);
        else             $breadcrumbs = array($unqualifiedControllerName, $title);
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
            'action'                => $action,
            'field_name'            => $fieldName, // Form field to refresh
            'redirect'              => 0, // IMPORTANT: This prevents the onSave() handler issuing a redirect
        );
        $dataRequestDataString = substr(json_encode($dataRequestData), 1, -1);

        return <<<HTML
            <div class="modal-header">
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
                    data-request='onSave'
                    data-request-form='.modal-body form'
                    data-request-data='$dataRequestDataString'
                    data-hotkey='ctrl+s, cmd+s'
                    data-load-indicator='$title...'
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
}
