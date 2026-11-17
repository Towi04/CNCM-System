<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CorregirRelacionGrupos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alumnos_grupos', function (Blueprint $table) {
            $table->dropForeign('alumnos_grupos_id_grupo_foreign');
            $table->foreign('id_grupo')->references('id')->on('grupos')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::table('alumnos_pagos', function (Blueprint $table) {
            $table->dropForeign('alumnos_pagos_id_grupo_foreign');
            $table->foreign('id_grupo')->references('id')->on('grupos')->onUpdate('cascade')->onDelete('cascade');

        });

        Schema::table('abonos', function (Blueprint $table) {
            $table->dropForeign('abonos_id_alumno_pago_foreign');
            $table->foreign('id_alumno_pago')->references('id')->on('alumnos_pagos')->onUpdate('cascade')->onDelete('cascade');

        });

        Schema::table('grupos_materias', function (Blueprint $table) {
            $table->dropForeign('grupos_materias_id_grupo_foreign');
            $table->foreign('id_grupo')->references('id')->on('grupos')->onUpdate('cascade')->onDelete('cascade');

            $table->dropForeign('grupos_materias_id_materia_foreign');
            $table->foreign('id_materia')->references('id')->on('materias')->onUpdate('cascade')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alumnos_grupos', function (Blueprint $table) {
            $table->dropForeign('alumnos_grupos_id_grupo_foreign');
            $table->foreign('id_grupo')->references('id')->on('grupos')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::table('alumnos_pagos', function (Blueprint $table) {
            $table->dropForeign('alumnos_pagos_id_grupo_foreign');
            $table->foreign('id_grupo')->references('id')->on('grupos')->onUpdate('cascade')->onDelete('cascade');

        });

        Schema::table('abonos', function (Blueprint $table) {
            $table->dropForeign('abonos_id_alumno_pago_foreign');
            $table->foreign('id_alumno_pago')->references('id')->on('alumnos_pagos')->onUpdate('cascade')->onDelete('cascade');

        });

        Schema::table('grupos_materias', function (Blueprint $table) {
            $table->dropForeign('grupos_materias_id_grupo_foreign');
            $table->foreign('id_grupo')->references('id')->on('grupos')->onUpdate('cascade')->onDelete('cascade');

            $table->dropForeign('grupos_materias_id_materia_foreign');
            $table->foreign('id_materia')->references('id')->on('materias')->onUpdate('cascade')->onDelete('cascade');

        });
    }
}
