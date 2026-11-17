<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarDocumentosAlumnos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('documentos');
        
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_alumno')->unsigned()->nullable();
            $table->bigInteger('id_grupo')->unsigned()->nullable();
            $table->string('concepto')->nullable();
            $table->decimal('monto',15,2)->nullable();
            $table->decimal('monto_apoyo_inscripcion',15,2)->nullable();
            $table->decimal('saldo',15,2)->nullable();
            $table->date('fecha_limite')->nullable();
            $table->string('status')->nullable();
            $table->string('tipo')->nullable();
            $table->integer('mes')->nullable();
            $table->integer('semana')->nullable();
            $table->integer('anio')->nullable();
            $table->string('modalidad')->nullable();
            $table->timestamps();

            $table->foreign('id_alumno')->references('id')->on('alumnos')->onUpdate('cascade')->onDelete('cascade');
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
        Schema::dropIfExists('documentos');
    }
}
