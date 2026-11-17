<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaHorariosProfesores extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('horarios_profesores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_sucursal')->nullable();
            $table->unsignedBigInteger('id_profesor')->nullable();
            $table->string('dia')->nullable();
            $table->smallInteger('hora_inicio')->default(0);
            $table->smallInteger('hora_final')->default(0);
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
        Schema::dropIfExists('horarios_profesores');
    }
}
