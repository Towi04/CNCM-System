<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarNotasAlumnos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->datetime('fecha');
            $table->text('nota');
            $table->bigInteger('id_alumno')->unsigned();
            $table->bigInteger('id_usuario')->unsigned();
            $table->timestamps();

            $table->foreign('id_alumno')->references('id')->on('alumnos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_usuario')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notas');
    }
}
