<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarIdEspecialidadAsistencias extends Migration
{
    public function up()
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->bigInteger('id_especialidad')->unsigned()->nullable();

            $table->foreign('id_especialidad')->references('id')->on('especialidades')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropColumn('id_especialidad');
        });
    }
}
