<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarIdEspecialidadInscritos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->bigInteger('id_especialidad')->unsigned()->nullable()->after('id_grupo');
            
            
            $table->foreign('id_especialidad')->references('id')->on('especialidades')->onUpdate('cascade')->onDelete('cascade');
            
        });

        DB::select(DB::raw('update inscripciones a
        inner join grupos b on a.id_grupo = b.id 
        inner join especialidades c on b.id_especialidad = c.id
        set a.id_especialidad = c.id'));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->dropForeign('inscripciones_id_especialidad_foreign');

            $table->dropColumn('id_especialidad');
        });
    }
}
