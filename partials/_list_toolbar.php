<div data-control="toolbar">
    <?php // TODO: This should maybe be the toolbar.extend event instead?
    $user              = BackendAuth::user();
    $model             = $this->widget?->list?->model;
    $controllerListUrl = $this->actionUrl('');
    $isReadOnly        = ((property_exists($this, 'readOnly') && $this->readOnly) 
        || ($model && property_exists($model, 'readOnly') && $model->readOnly));
    $modelIsCreateSystem = ($model instanceof \Acorn\Model);

    if (!$isReadOnly): ?>
        <?php if (!$modelIsCreateSystem || $user->hasPermission($model->permissionFQN('create'))): ?>
        <a
            href="<?= $this->controllerUrl('create'); ?>"
            class="btn btn-primary wn-icon-plus">
            <?= e(trans('backend::lang.form.create_title', ['name' => trans($model?->translateModelKey())])); ?>
        </a>
        <?php endif ?>

        <?php if (!$modelIsCreateSystem || $user->hasPermission($model->permissionFQN('delete'))): ?>
        <button
            class="btn btn-danger wn-icon-trash-o"
            disabled="disabled"
            onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
            data-request="onDelete"
            data-request-confirm="<?= e(trans('backend::lang.list.delete_selected_confirm')); ?>"
            data-trigger-action="enable"
            data-trigger=".control-list input[type=checkbox]"
            data-trigger-condition="checked"
            data-request-success="$(this).prop('disabled', 'disabled');"
            data-stripe-load-indicator>
            <?= e(trans('backend::lang.list.delete_selected')); ?>
        </button>
        <?php endif ?>
    <?php endif ?>

    <?php if ($model && method_exists($model, 'isListEditable') && $model->isListEditable()): 
        $save   = e(trans('backend::lang.form.save'));
        $fields = implode(', ', array_keys($model->listEditable));
        ?>
        <button
            disabled="disabled"
            data-request="onListEditableSave"
            data-request-form="#list-editable-form"
            data-request-success="$(this).prop('disabled', 'disabled');  $('.control-list tr.dirty').removeClass('dirty')"
            data-hotkey="ctrl+s, cmd+s"
            data-load-indicator="<?= e(trans('backend::lang.form.saving_name', ['name' => trans('{{ model_lang_key }}.label')])); ?>"
            class="btn btn-primary">
            <?= "$save $fields"; ?>
        </button>
    <?php endif ?>
    
    <?php 
        // --------------------------------- Actions
        if ($model && property_exists($model, 'actionFunctions')) {
            $modelArrayName  = $model->unqualifiedClassName();
            foreach ($model->actionFunctions('list') as $name => $definition) {
                $label = e(trans($definition['label']));
                $dataRequestData = e(substr(json_encode(array(
                    'name'       => $name, // SECURITY: We do not want to reveal the full function name
                    'arrayname'  => $modelArrayName,
                    'model'      => get_class($model),
                )), 1,-1));
                $icon = (isset($definition['icon']) ? '' : '');
                $dataLoadIndicator = e(trans('backend::lang.form.saving_name', ['name' => trans('{{ model_lang_key }}.label')]));;

                print(<<<HTML
                    <button
                        data-control="popup"
                        data-request-data='$dataRequestData'
                        data-handler="onActionFunction"
                        data-load-indicator="$dataLoadIndicator"
                        class="btn">
                        $label
                    </button>
HTML
                );
            }
        } 
    
        // --------------------------------- PDF ActionTemplates
        if ($model) {
            $ml       = System\Classes\MediaLibrary::instance();
            $class    = get_class($model);
            $location = "ActionTemplates\\$class";
            // MediaLibraryItem s
            $mlis        = $ml->listFolderContents($location, 'title', NULL, TRUE);
            $useDropDown = (count($mlis) > 2);
            $print       = e(trans('acorn::lang.models.general.batch_print'));
            $dataLoadIndicator = e(trans('backend::lang.form.saving_name', ['name' => trans('{{ model_lang_key }}.label')]));;
            if ($useDropDown) {
                print(<<<HTML
                    <form class="inline-block"
                        data-control="popup"
                        data-size="huge"
                        data-handler="onListActionTemplate"
                        data-load-indicator="$dataLoadIndicator"
                    >
                        <input type="submit" value="submit" class="hidden"/>
                        <div class="form-group dropdown-field select-and-go-clear" data-field-name="template">
                            <select name="template" 
                                class="form-control select-no-search custom-select select2-hidden-accessible" 
                                autocomplete="off" 
                                required="required" 
                                data-placeholder="$print" 
                                data-disposable="data-disposable"
                                tabindex="-1" 
                            >
                                <option value="" selected="selected">$print</option>
HTML
                );
            }
            
            foreach ($mlis as $mli) {
                if ($mli->getFileType() == "document") {
                    $pdfTemplate = new \Acorn\PdfTemplate($mli->path);
                    $printName   = e($pdfTemplate->label(TRUE)); // From FODT comment
                    $dataRequestData = e(substr(json_encode(array(
                        'template'   => $mli->path,
                    )), 1,-1));

                    if ($pdfTemplate->forContext($this->action)) {
                        if ($useDropDown) {
                            print("<option value='$mli->path'>$print $printName...</option>");
                        } else              print(<<<HTML
                            <button
                                data-control="popup"
                                data-size="huge"
                                data-request-data='$dataRequestData'
                                data-handler="onListActionTemplate"
                                data-load-indicator="$dataLoadIndicator"
                                class="btn">
                                $print $printName...
                            </button>
HTML
                        );
                    }
                }
            } 
            if ($useDropDown) print('</select></div></form>');
        }
    ?>

    <a
        href="javascript:print()"
        class="btn btn-primary wn-icon-print">
        <?= e(trans('acorn::lang.models.general.print')); ?>
    </a>

    <?php if (property_exists($this->widget, 'importUploadForm')): ?>
        <a
            href="<?= $controllerListUrl ?>/import"
            class="btn wn-icon-import">
            <?= e(trans('acorn::lang.models.general.import')); ?>
        </a>
    <?php endif ?>

    <?php 
    // TODO: Re-enable an export system
    if (FALSE && property_exists($this->widget, 'exportUploadForm')): 
        $classParts = explode('\\', get_class($this->widget->exportUploadForm->model));
        $label      = Str::snake(end($classParts));
    ?>
        <a
            href="<?= $controllerListUrl ?>/export"
            class="btn wn-icon-export">
            <?= e(trans("acorn::lang.models.export.$label")); ?>
        </a>
    <?php endif ?>
</div>
