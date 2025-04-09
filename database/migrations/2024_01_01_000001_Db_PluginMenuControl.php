<?php

use Winter\Storm\Database\Schema\Blueprint;
use Acorn\Migration;

class DbPluginMenuControl extends Migration
{
    public function up()
    {
        Schema::table('system_plugin_versions', function(Blueprint $table) {
            if (!Schema::hasColumn($table->getTable(), 'acorn_infrastructure')) 
                $table->boolean('acorn_infrastructure')->default(false);
        });
    }

    public function down()
    {}
}


