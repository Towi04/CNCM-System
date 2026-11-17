<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarIdUsuarioAutoriza extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('apoyos_inscripciones', function (Blueprint $table) {
            $table->bigInteger('id_usuario_autoriza')->unsigned()->nullable();

            $table->foreign('id_usuario_autoriza')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('apoyos_inscripciones', function (Blueprint $table) {
            $table->dropForeign('apoyos_inscripcion_id_usuario_autoriza_foreign');
            $table->dropColumn('id_usuario_autoriza');

            
        });
    }
}
