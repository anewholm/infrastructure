<?php namespace Acorn;

use BackendMenu;
use Backend\Classes\Controller as BackEndController;
use Backend\Behaviors\ListController;
use Illuminate\Support\Facades\Event;
use System\Classes\PluginManager;
use File;
use ReflectionClass;
use Flash;
use \Exception;

use Acorn\Events\DataChange;
use Acorn\Events\UserNavigation;

/**
 * Computer Product Backend Controller
 */
class Controller extends BackEndController
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
            $this->addCss('/modules/acorn/assets/css/forms.css');
            $this->addCss('/modules/acorn/assets/css/lists.css');
            $this->addCss('/modules/acorn/assets/css/qrcode-printing.css');
            $this->addCss('/modules/acorn/assets/css/html5-qrcode.css');


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

    public function onPopupRoute()
    {
        // ------------------------------- Inputs
        $popupRoute  = post('route');
        $breadcrumb  = post('breadcrumb');
        $fieldName   = post('fieldName');

        $popupParams = explode(',', post('params'));
        list($controllerClass, $action) = explode('@', $popupRoute);
        $fullyQualifiedControllerClass = $this->qualifyClassName($controllerClass);
        $controller = new $fullyQualifiedControllerClass;
        if (!$action)     $action     = 'create';

        $closeName  = $this->transBackend('close');
        $actionName = $this->transBackend($action);

        // ------------------------------- Form behavior
        // FormController::create() => FormController::initForm($model) prepares:
        //   $this->formWidget
        //   $this->prepareVars($model);
        //   $this->model = $model;
        $controller->$action(...$popupParams);
        $model = $controller->widget?->form->model;

        // ------------------------------- Render
        $breadcrumbHTML = "";
        $unqualifiedControllerName = $controller->unqualifiedClassName();
        $unqualifiedModelName      = $model?->unqualifiedClassName();
        $fullyQualifiedModelClass  = $model?->fullyQualifiedClassName();
        $modelTitle                = $this->transModel('label', $model);
        $title                     = "$actionName $modelTitle";

        if ($breadcrumb) $breadcrumbs = explode(',', $breadcrumb);
        else             $breadcrumbs = array($unqualifiedControllerName, $title);
        foreach ($breadcrumbs as $crumb) $breadcrumbHTML .= '<li>' . trans($crumb) . '</li>';
        $form    = $controller->widget?->form->render();
        $eventJs = 'popup';
        $initJs  = "$('body > .control-popup').trigger('$eventJs');";

        return "<div id='popup-container'>
                <div class='control-breadcrumb'><ul>$breadcrumbHTML</ul></div>
                <form id='popup-form' class='layout'>
                    $form
                    <input type='hidden' name='fully_qualified_model' value='$fullyQualifiedModelClass'></input>
                    <input type='hidden' name='action'                value='$action'></input>
                    <input type='hidden' name='field_name'            value='$fieldName'></input>
                    <div id='popup-buttons'>
                        <button type='button'
                            data-request='onPopupAction'
                            data-request-data=\"field_name:'$fieldName',action:'$action'\"
                            data-hotkey='ctrl+s, cmd+s'
                            data-load-indicator='$title...'
                            data-request-success='acorn_popupComplete(context, textStatus, jqXHR);'
                            data-dismiss='popup'
                            class='btn btn-primary'
                        >
                            $actionName
                        </button>
                        <button type='button' data-dismiss='popup' class='btn btn-default'>$closeName</button>
                    </div>
                </form>
                <script>$initJs</script>
            </div>";
    }

    public function onPopupAction()
    {
        $post         = post();
        $action       = $post['action'];
        $fieldName    = $post['field_name'];
        $fullyQualifiedModelClass = $post['fully_qualified_model'];
        $model        = new $fullyQualifiedModelClass;
        $fields       = $post[$model->unqualifiedClassName()];
        $translations = (isset($post['RLTranslate']) ? $post['RLTranslate'] : NULL);

        switch ($action) {
            case 'create':
                // TODO: This popup save() does not work for compound objects yet
                $model->fill($fields);
                $model->save();
                break;
        }

        return $model;
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
