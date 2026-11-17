<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaAlumnosPagos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('alumnos_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_alumno')->nullable();
            $table->unsignedBigInteger('id_grupo')->nullable();
            $table->string('concepto')->nullable(); #'(inscripcion|colegiatura)
            $table->decimal('monto',12,2)->nullable();
            $table->date('fecha_limite')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->foreign('id_alumno')
                ->references('id')
                ->on('alumnos');

            $table->foreign('id_grupo')
                ->references('id')
                ->on('grupos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('alumnos_pagos');
    }
}
