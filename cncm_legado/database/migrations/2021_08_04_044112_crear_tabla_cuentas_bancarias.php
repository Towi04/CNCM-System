<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCuentasBancarias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('banco')->nullable();
            $table->string('no_cuenta')->nullable();
            $table->unsignedInteger('id_sucursal');
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
        Schema::dropIfExists('cuentas_bancarias');
    }
}
