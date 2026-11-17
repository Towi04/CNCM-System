<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarComprasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::dropIfExists('partidas_compras');
        Schema::dropIfExists('partidas_compras');
        
        Schema::create('partidas_compras', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_producto')->nullable()->unsigned();
            $table->decimal('cantidad')->nullable();
            $table->string('status')->default('Emitida');
            $table->date('fecha')->nullable();
            $table->bigInteger('id_usuario')->nullable()->unsigned();
            $table->timestamps();

            $table->foreign('id_producto')->references('id')->on('productos')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_usuario')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('partidas_compras');
    }
}
