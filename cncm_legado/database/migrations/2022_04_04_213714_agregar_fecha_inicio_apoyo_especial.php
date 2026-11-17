<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarFechaInicioApoyoEspecial extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('apoyos_especiales', function (Blueprint $table) {
            $table->date('fecha_inicio')->nullable()->after('precio');
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
            $table->dropColumn('fecha_inicio');
        });
    }
}
