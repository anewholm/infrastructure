<?php

use Winter\Storm\Database\Schema\Blueprint;
use AcornAssociated\Migration;

class DbServers extends Migration
{
    public function up()
    {
        $table = 'acornassociated_servers';
        Schema::create($table, function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->uuid('id')->unique()->primaryKey();
            $table->string('hostname', 1024)->default('hostname()')->unique();
            $table->string('domain', 1024)->nullable();
            $table->text('response')->nullable();
            $table->timestamp('created_at')->default('now()');
        });
        $this->generated($table, 'name', 'hostname');

        // Could not set a function for the default above
        $this->setFunctionDefault($table, 'id', 'gen_random_uuid');
    }

    public function down()
    {
        Schema::dropIfExists('acornassociated_servers');
    }
}


