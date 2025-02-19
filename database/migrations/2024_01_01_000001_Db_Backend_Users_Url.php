<?php

use Winter\Storm\Database\Schema\Blueprint;
use AcornAssociated\Migration;

class DbBackendUsersUrl extends Migration
{
    public function up()
    {
        // Add extra namespaced fields in to the backend_users table
        Schema::table('backend_users', function(Blueprint $table) {
            if (!Schema::hasColumn($table->getTable(), 'acornassociated_url')) $table->string('acornassociated_url', 2048)->nullable();
        });
    }

    public function down()
    {
        Schema::table('backend_users', function(Blueprint $table) {
            if (Schema::hasColumn($table->getTable(), 'acornassociated_url')) $table->dropColumn('acornassociated_url');
        });
    }
}
