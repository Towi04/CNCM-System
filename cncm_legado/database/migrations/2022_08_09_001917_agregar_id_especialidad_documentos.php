<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarIdEspecialidadDocumentos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       

        Schema::table('documentos', function (Blueprint $table) {
            $table->bigInteger('id_especialidad')->unsigned()->nullable()->after('id_alumno');

            $table->foreign('id_especialidad')->references('id')->on('especialidades')->onUdpate('cascade')->onDelete('cascade');
        });

        DB::select(DB::raw('update documentos d
        inner join grupos g on d.id_grupo = g.id 
        inner join especialidades e on g.id_especialidad = e.id
        set d.id_especialidad = e.id'));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign('documentos_id_especialidad_foreign');
            $table->dropColumn('id_especialidad');
        });
    }
}
