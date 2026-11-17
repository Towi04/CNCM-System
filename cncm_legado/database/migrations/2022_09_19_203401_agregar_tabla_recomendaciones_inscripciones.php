<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarTablaRecomendacionesInscripciones extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('recomendaciones_inscripciones');

        Schema::create('inscripciones_recomendaciones', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->bigInteger('id_alumno_recomendado')->unsigned();
            $table->bigInteger('id_alumno_recomendo')->unsigned();
            $table->bigInteger('id_especialidad')->unsigned();
            $table->bigInteger('id_documento_aplicado')->unsigned()->nullable();
            $table->bigInteger('id_autorizo')->unsigned();
            $table->bigInteger('id_sucursal')->unsigned();
            $table->decimal('monto',15,2)->nullable();
            $table->timestamps();

            $table->foreign('id_alumno_recomendado')->references('id')->on('alumnos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_alumno_recomendo')->references('id')->on('alumnos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_especialidad')->references('id')->on('especialidades')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_documento_aplicado')->references('id')->on('documentos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_autorizo')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_sucursal')->references('id')->on('sucursales')->onUpdate('cascade')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inscripciones_recomendaciones');
    }
}
