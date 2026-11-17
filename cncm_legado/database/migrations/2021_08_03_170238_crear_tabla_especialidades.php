<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaEspecialidades extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('especialidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_sucursal')->nullable();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->decimal('precio_inscripcion',16,2)->nullable();
            $table->decimal('precio_mensualidad',16,2)->nullable();
            $table->decimal('precio_mensualidad_pronto_pago',16,2)->nullable();
            $table->decimal('precio_semanal',16,2)->nullable();
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
        Schema::dropIfExists('especialidades');
    }
}
