<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarAbonosDocumentos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('abonos_documentos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_sucursal')->unsigned()->nullable();
            $table->bigInteger('id_pago')->unsigned()->nullable();
            $table->bigInteger('id_documento')->unsigned()->nullable();
            $table->decimal('monto',15,2)->nullable();
            $table->boolean('venta_fiscal')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('id_sucursal')->references('id')->on('sucursales')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_pago')->references('id')->on('pagos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_documento')->references('id')->on('documentos')->onUpdate('cascade')->onDelete('cascade');

            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('abonos_documentos');
    }
}
