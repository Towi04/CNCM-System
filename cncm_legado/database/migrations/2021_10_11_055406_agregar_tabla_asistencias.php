<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarTablaAsistencias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::dropIfExists('asistencias');

        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_alumno')->unsigned();
            $table->datetime('fecha');
            $table->timestamps();

            $table->foreign('id_alumno')->references('id')->on('alumnos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asistencias');
    }
}
