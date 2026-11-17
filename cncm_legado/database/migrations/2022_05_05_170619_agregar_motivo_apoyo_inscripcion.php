<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarMotivoApoyoInscripcion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('apoyos_inscripciones', function (Blueprint $table) {
            $table->text('motivo')->nullable();
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
            $table->text('motivo')->nullable();
        });
    }
}
