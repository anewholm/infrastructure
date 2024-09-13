<?php

use Winter\Storm\Database\Schema\Blueprint;
use Acorn\Migration;

class DbBackendUsersUrl extends Migration
{
    public function up()
    {
        // Add extra namespaced fields in to the backend_users table
        Schema::table('backend_users', function(Blueprint $table) {
            if (!Schema::hasColumn($table->getTable(), 'acorn_url')) $table->string('acorn_url', 2048)->nullable();
        });
    }

    public function down()
    {
        Schema::table('backend_users', function(Blueprint $table) {
            if (Schema::hasColumn($table->getTable(), 'acorn_url')) $table->dropColumn('acorn_url');
        });
    }
}
