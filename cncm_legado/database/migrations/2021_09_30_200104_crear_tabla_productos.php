<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaProductos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_sucursal')->nullable();
            $table->string('nombre')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('clave_sat')->nullable();
            $table->string('clave_unidad_sat');
            $table->decimal('precio',15,2);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_sucursal')->references('id')->on('sucursales');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('productos');
    }
}
