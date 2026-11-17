<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarTablaDescuentos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('descuentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_especialidad_1');
            $table->unsignedBigInteger('id_especialidad_2');
            $table->decimal('porcentaje_descuento');
            $table->unsignedBigInteger('id_usuario');
            $table->timestamps();

            $table->foreign('id_especialidad_1')->references('id')->on('especialidades')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_especialidad_2')->references('id')->on('especialidades')->onUpdate('cascade')->onDelete('cascade');
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
        Schema::dropIfExists('descuentos');
    }
}
