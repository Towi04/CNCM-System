<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPrecios extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('precios', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->bigInteger('id_grupo')->unsigned()->nullable();
            $table->datetime('fecha_inicio');
            $table->datetime('fecha_final')->nullable();
            $table->decimal('precio_pronto_pago',15,2)->nullable();
            $table->decimal('precio_normal',15,2);
            $table->bigInteger('id_usuario')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('id_usuario')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_grupo')->references('id')->on('grupos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('precios');
    }
}
