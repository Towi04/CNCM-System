<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaInscripciones extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_alumno')->unsigned();
            $table->bigInteger('id_grupo')->unsigned();
            $table->bigInteger('id_sucursal')->unsigned();
            $table->bigInteger('id_asesor')->unsigned()->nullable();
            $table->datetime('fecha');
            $table->timestamps();

            $table->foreign('id_alumno')->references('id')->on('alumnos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_grupo')->references('id')->on('grupos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_sucursal')->references('id')->on('sucursales')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_asesor')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inscripciones');
    }
}
