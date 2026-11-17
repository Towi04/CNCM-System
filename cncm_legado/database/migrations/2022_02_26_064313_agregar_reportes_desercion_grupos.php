<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarReportesDesercionGrupos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('grupos_deserciones');

        Schema::create('grupos_deserciones', function (Blueprint $table) {
            $table->id();
            $table->integer('semana');
            $table->integer('year');
            $table->bigInteger('id_grupo')->unsigned();
            $table->integer('anterior')->nullable();
            $table->integer('inicios')->nullable();
            $table->integer('reingresos')->nullable();
            $table->integer('cambios_horarios_altas')->nullable();
            $table->integer('bajas')->nullable();
            $table->integer('cambios_horarios_bajas')->nullable();
            $table->integer('fin_curso')->nullable();
            $table->integer('total_final')->nullable();
            $table->timestamps();

            $table->foreign('id_grupo')->references('id')->on('grupos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grupos_deserciones');
    }
}
