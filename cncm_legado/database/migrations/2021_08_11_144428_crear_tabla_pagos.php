<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPagos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('folio');
            $table->unsignedBigInteger('id_sucursal')->nullable();
            $table->unsignedBigInteger('id_alumno')->nullable();
            $table->decimal('monto',16,2)->default(0);
            $table->dateTime('fecha')->nullable();
            $table->unsignedBigInteger('id_recibio')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_alumno')->references('id')->on('alumnos');
            $table->foreign('id_sucursal')->references('id')->on('sucursales');
            $table->foreign('id_recibio')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pagos');
    }
}
