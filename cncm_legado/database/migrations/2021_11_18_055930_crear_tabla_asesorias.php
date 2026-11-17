<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaAsesorias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asesorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_sucursal')->nullable();
            $table->unsignedBigInteger('id_profesor')->nullable();
            $table->unsignedBigInteger('id_alumno')->nullable();
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_final')->nullable();
            $table->text('notas')->nullable();
            $table->enum('status',[
                "Espera de confirmacion",
                "Agendados",
                "Rechazados",
                "Cancelados"
            ])->default('Espera de confirmacion');
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
        Schema::dropIfExists('asesorias');
    }
}
