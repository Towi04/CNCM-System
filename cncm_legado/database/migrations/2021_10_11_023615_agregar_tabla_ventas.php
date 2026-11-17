<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarTablaVentas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::dropIfExists('partidas_ventas');
        // Schema::dropIfExists('ventas');

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->integer('folio');
            $table->bigInteger('id_alumno')->unsigned()->nullable();
            $table->bigInteger('id_sucursal')->unsigned();
            $table->date('fecha')->nullable();
            $table->decimal('total',12,2)->nullable();
            $table->string('status');
            $table->string('forma_pago')->nullable();
            $table->bigInteger('id_recibio')->unsigned()->nullable();
            $table->timestamps();
            
            $table->foreign('id_alumno')->references('id')->on('alumnos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_sucursal')->references('id')->on('sucursales')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_recibio')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('partidas_ventas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_venta')->unsigned();
            $table->bigInteger('id_producto')->unsigned();
            $table->decimal('cantidad',12,2);
            $table->decimal('precio',12,2);
            $table->decimal('total',12,2);
            $table->timestamps();

            $table->foreign('id_venta')->references('id')->on('ventas')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_producto')->references('id')->on('productos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('partidas_ventas');
        Schema::dropIfExists('ventas');
    }
}
