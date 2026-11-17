<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCorreccionesMontosSaldosAlumnosPagos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::select('update alumnos_pagos set saldo = monto where status = "Pendiente" and saldo = 0');
        DB::select('delete from alumnos_pagos where status = "Pendiente" and monto = 0 and saldo = 0');
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
