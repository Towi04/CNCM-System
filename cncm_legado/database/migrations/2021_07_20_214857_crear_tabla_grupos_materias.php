<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaGruposMaterias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('grupos_materias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_materia')->nullable();
            $table->unsignedBigInteger('id_grupo')->nullable();
            $table->unsignedBigInteger('id_profesor')->nullable();
            $table->decimal('horas_semana')->nullable();

            $table->foreign('id_materia')
                ->references('id')
                ->on('materias');

            $table->foreign('id_grupo')
                ->references('id')
                ->on('grupos');

            $table->foreign('id_profesor')
                ->references('id')
                ->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grupos_materias');
    }
}
