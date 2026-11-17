<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarFechaInicioTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->date('fecha_inicio_grupo')->after('fecha')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->dropColumn('fecha_inicio_grupo');
        });
    }
}
