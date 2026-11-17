<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaApoyosEspeciales extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('apoyos_especiales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_alumno')->nullable();
            $table->unsignedBigInteger('id_grupo')->nullable();
            $table->unsignedBigInteger('id_sucursal')->nullable();
            $table->decimal('precio',16,2)->default(0);
            $table->date('fecha_final')->nullable();
            $table->timestamps();

            $table->foreign('id_alumno')->references('id')
                ->on('alumnos')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('id_grupo')->references('id')
                ->on('grupos')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('id_sucursal')->references('id')
                ->on('sucursales')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('apoyos_especiales');
    }
}
