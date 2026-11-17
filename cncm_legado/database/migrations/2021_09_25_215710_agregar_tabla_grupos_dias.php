<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarTablaGruposDias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('grupos_dias', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_grupo')->unsigned();
            $table->string('dia');
            $table->time('hora_inicio');
            $table->time('hora_final');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grupos_dias');
    }
}
