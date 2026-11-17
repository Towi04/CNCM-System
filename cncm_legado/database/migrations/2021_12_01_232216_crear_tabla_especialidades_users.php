<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaEspecialidadesUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('especialidades_users', function (Blueprint $table) {
            $table->unsignedBigInteger('id_especialidad');
            $table->unsignedBigInteger('id_usuario');

            $table->foreign('id_especialidad')
                ->references('id')
                ->on('especialidades')
                ->onDelete('cascade');

            $table->foreign('id_usuario')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->primary(['id_especialidad', 'id_usuario'], 'especialidades_users_primary');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('especialidades_users');
    }
}
