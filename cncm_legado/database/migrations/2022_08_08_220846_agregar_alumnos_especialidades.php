<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarAlumnosEspecialidades extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('alumnos_especialidades');

        Schema::create('alumnos_especialidades', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_alumno')->unsigned();
            $table->bigInteger('id_especialidad')->unsigned();
            $table->date('fecha_inicio');
            $table->string('forma_pago'); //mensual o semanal;
            $table->decimal('monto',12,2)->nullable();
            $table->integer('semanas_cursar');
            $table->integer('semanas_cursadas')->nullable();
            $table->integer('semanas_pagadas')->nullable();
            $table->string('status')->default('Activo');
            $table->timestamps();

            $table->foreign('id_alumno')->references('id')->on('alumnos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_especialidad')->references('id')->on('especialidades')->onUpdate('cascade')->onDelete('cascade');

        });
    }

    public function down()
    {
        Schema::dropIfExists('alumnos_especialidades');
    }
}
