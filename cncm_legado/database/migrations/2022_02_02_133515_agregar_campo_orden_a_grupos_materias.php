<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCampoOrdenAGruposMaterias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('grupos_materias', function (Blueprint $table) {
            $table->unsignedBigInteger('orden')->nullable()->index(); # 👉 SE AGREGA INDICE PARA MEJORAR LA VELOCIDAD DE ORDENAMIENTO EN CONSULTA
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('grupos_materias', function (Blueprint $table) {
            $table->dropIndex('grupos_materias_orden_index');
            $table->dropColumn('orden');
        });
    }
}
