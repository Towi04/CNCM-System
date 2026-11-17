<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarIdEspecialidadAAlumnos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alumnos', function (Blueprint $table) {
            // $table->dropColumn('especialidad');
            $table->unsignedBigInteger('id_especialidad')->after('tutor')->nullable();

            $table->integer('edad')->nullable()->change();
            $table->date('fecha_nacimiento')->nullable()->change();
            $table->text('domicilio')->nullable()->change();
            $table->string('colonia')->nullable()->change();
            $table->string('municipio')->nullable()->change();
            $table->string('telefono')->nullable()->change();
            $table->string('celular')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('codigo_postal')->nullable()->change();
            $table->string('ocupacion')->nullable()->change();
            $table->json('grado_estudios')->nullable()->change();
            $table->boolean('solicitud_factura')->nullable()->change();
            $table->unsignedBigInteger('id_asesor_educativo')->change();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->json('especialidad')->after('tutor');
            $table->dropColumn('id_especialidad');
        });
    }
}
