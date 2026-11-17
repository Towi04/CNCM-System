<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarTipoApoyosEspecialesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('apoyos_especiales', function (Blueprint $table) {
            $table->string('tipo')->after('precio')->nullable()->default('Manual');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('apoyos_especiales', function (Blueprint $table) {
            $table->string('tipo')->after('precio')->nullable();
        });
    }
}
