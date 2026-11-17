<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaSucursalesUsuarios extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sucursales_usuarios', function (Blueprint $table) {
            $table->unsignedInteger('id_usuario');
            $table->unsignedBigInteger('id_sucursal');

            $table->primary(['id_usuario', 'id_sucursal'], 'usuario_sucursal_id_primary');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sucursales_usuarios');
    }
}
