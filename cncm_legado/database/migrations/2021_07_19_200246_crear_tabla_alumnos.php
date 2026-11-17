<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaAlumnos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_sucursal')->nullable();
            $table->string('foto')->nullable();
            $table->string('nombres');
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->integer('edad');
            $table->date('fecha_nacimiento');
            $table->text('domicilio');
            $table->string('colonia');
            $table->string('municipio');
            $table->string('telefono');
            $table->string('celular');
            $table->string('email');
            $table->string('codigo_postal');
            $table->string('ocupacion');

            $table->json('grado_estudios');
            $table->string('otro_grado_estudios')->nullable();

            $table->string('tutor')->nullable();
            $table->json('especialidad');
            $table->string('otra_especialidad')->nullable();
            $table->string('escuela_procedencia')->nullable();

            $table->text('objetivo_inscripcion')->nullable();
            $table->text('enfermedad_cronica')->nullable();
            $table->boolean('solicitud_factura');
            $table->unsignedBigInteger('id_asesor_educativo');

            # DATOS DE FACTURACION
            $table->string('rfc')->nullable();
            $table->string('cfdi')->nullable();
            $table->string('curp')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('telefono_general')->nullable();
            $table->string('correo_general')->nullable();
            $table->string('domicilio_fiscal')->nullable();

            $table->text('observaciones')->nullable();


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
        Schema::dropIfExists('alumnos');
    }
}
