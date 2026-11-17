<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarIdEspecialidadPrecios extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('precios', function (Blueprint $table) {
            $table->bigInteger('id_especialidad')->unsigned()->nullable()->after('id_grupo');

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
        Schema::table('precios', function (Blueprint $table) {
            $table->dropForeign('precios_id_especialidad_foreign');

            $table->dropColumn('id_especialidad');
        });
    }
}
