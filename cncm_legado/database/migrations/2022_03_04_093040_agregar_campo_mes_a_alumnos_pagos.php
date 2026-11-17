<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCampoMesAAlumnosPagos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alumnos_pagos', function (Blueprint $table) {
            $table->smallInteger('mes')->nullable()->after('tipo');
            $table->smallInteger('semana')->nullable()->after('mes');
            $table->smallInteger('anio')->nullable()->after('semana');
            $table->string('modalidad')->nullable()->after('anio');
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
            $table->dropColumn([
                'mes',
                'semana',
                'modalidad',
                'anio',
            ]);
        });
    }
}
