<?php

use Winter\Storm\Database\Schema\Blueprint;
use AcornAssociated\Migration;

class DbPluginMenuControl extends Migration
{
    public function up()
    {
        Schema::table('system_plugin_versions', function(Blueprint $table) {
            if (!Schema::hasColumn($table->getTable(), 'acornassociated_infrastructure')) 
                $table->boolean('acornassociated_infrastructure')->default(false);
        });
    }

    public function down()
    {}
}


