<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCampoMontoApoyoInscripcionAAlumnosPagos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alumnos_pagos', function (Blueprint $table) {
            $table->decimal('monto_apoyo_inscripcion',12,2)->nullable()->after('monto');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alumnos_pagos', function (Blueprint $table) {
            $table->dropColumn('monto_apoyo_inscripcion');
        });
    }
}
