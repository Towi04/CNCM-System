<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ActualizarAgregarIdEspecialidadAbonos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('abonos_documentos', function (Blueprint $table) {
            $table->bigInteger('id_especialidad')->nullable()->unsigned();

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
        Schema::table('abonos_documentos', function (Blueprint $table) {
            $table->dropForeign('abonos_documentos_id_especialidad_foreign');

            $table->dropColumn('id_especialidad');
            
        });
    }
}
