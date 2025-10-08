<?php namespace Acorn\Behaviors;

use \Backend\Behaviors\ListController as BackendListController;
use \Exception;
use Input;
use Backend\Widgets\Search;
use Backend\Widgets\Filter;
use Backend\Widgets\Lists;
use \Winter\Storm\Database\Builder;

class ListController extends BackendListController
{
    use \Acorn\Traits\MorphConfig;

    public $readOnly = FALSE;
    
    public function __construct($controller)
    {
        parent::__construct($controller);

        $this->addViewPath('~/modules/backend/behaviors/listcontroller/partials');
        $this->addViewPath('~/modules/acorn/behaviors/listcontroller/partials');

        Search::extend(function ($widget) {
            $widget->addViewPath('~/modules/acorn/partials/');
            // Query string programmable search term
            if (Input::get('search')) $widget->setActiveTerm(Input::get('search'));
        });

        Lists::extend(function($widget){
            $widget->bindEvent('list.overrideColumnValue', function ($record, $column, $value) use($widget) {
                static $firstColumn, $lastColumnRecord, $previousRecord, $iGroup;
                // First column group
                if (is_null($iGroup)) $iGroup = 0;
                // Remember start column so we can detect when the row starts again
                if (!$firstColumn) $firstColumn = $column;
                // The row is starting again, remember the previous record for the whole row
                $isFirstColumn = ($firstColumn && $firstColumn->columnName == $column->columnName);
                if ($isFirstColumn) $previousRecord = $lastColumnRecord;

                $thisValue   = $widget->getColumnValueRaw($record, $column);
                $lastValue   = ($previousRecord ? $widget->getColumnValueRaw($previousRecord, $column) : NULL);
                $isDuplicate = ($thisValue == $lastValue);
                
                $class = 'theme-cell';
                if ($isDuplicate) $class .= ' duplicate';
                // If the first column changes then we update our row group
                else if ($isFirstColumn) {
                    $class .= ' heading';
                    $iGroup++;
                }
                $class .= " column1_group_$iGroup";
                if ($iGroup % 2) $class .= " column1_group_odd";

                $lastColumnRecord = $record;
                return "<div class='$class'>$value</div>";
            });

            // Themes
            if ($theme = get('theme')) {
                $this->controller->bodyClass .= " $theme";
                // TODO: Not having an effect
                $widget->showSorting = false;
                $widget->showSetup   = false;
            }

            $widget->bindEvent('list.extendQuery', function (Builder $query) {
                $query = $query->getQuery();
                foreach (get() as $getName => $fieldValue) {
                    if (substr($getName, 0, 7) == 'filter_') {
                        $fieldName = substr($getName, 7);
                        $query->where($fieldName, $fieldValue);
                    }
                    if (substr($getName, 0, 6) == 'order_') {
                        $fieldName = substr($getName, 6);
                        $direction = ($fieldValue == 'desc' ? 'desc' : 'asc');
                        $query->reorder($fieldName, $direction);
                    }
                }
            });
        });
    }
}
