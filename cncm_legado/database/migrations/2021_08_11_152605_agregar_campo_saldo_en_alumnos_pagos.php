<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCampoSaldoEnAlumnosPagos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alumnos_pagos', function (Blueprint $table) {
            $table->decimal('saldo',16,2)->default(0)->after('monto');
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
            $table->dropColumn('saldo');
        });
    }
}
