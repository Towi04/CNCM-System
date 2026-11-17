<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCampoFechaInicioAlumnosGrupos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alumnos_grupos', function (Blueprint $table) {
            $table->date('fecha_inicio')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alumnos_grupos', function (Blueprint $table) {
            $table->dropColumn('fecha_inicio');
        });
    }
}
