<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaAbonos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('abonos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_sucursal')->nullable();
            $table->unsignedBigInteger('id_pago')->nullable();
            $table->unsignedBigInteger('id_alumno_pago')->nullable();
            $table->decimal('monto',16,2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_pago')->references('id')->on('pagos');
            $table->foreign('id_alumno_pago')->references('id')->on('alumnos_pagos');
            $table->foreign('id_sucursal')->references('id')->on('sucursales');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('abonos');
    }
}
