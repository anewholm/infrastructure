<?php

use Winter\Storm\Database\Schema\Blueprint;
use Acorn\Migration;

class DbServers extends Migration
{
    public function up()
    {
        $table = 'acorn_servers';
        Schema::create($table, function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->uuid('id')->unique()->primaryKey();
            $table->string('hostname', 1024)->default('hostname()')->unique();
            $table->uuid('location_id')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('created_at')->default('now()');
        });

        // Could not set a function for the default above
        $this->setFunctionDefault($table, 'id', 'gen_random_uuid');
    }

    public function down()
    {
        Schema::dropIfExists('acorn_servers');
    }
}


