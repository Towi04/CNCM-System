<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarPreciosAGrupos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('grupos', function (Blueprint $table) {
            $table->dropColumn(['colegiatura','inscripcion']);
            $table->decimal('precio_semanal',16,2)->nullable()->after('fecha_inicio');
            $table->decimal('precio_mensualidad_pronto_pago',16,2)->nullable()->after('fecha_inicio');
            $table->decimal('precio_mensualidad',16,2)->nullable()->after('fecha_inicio');
            $table->decimal('precio_inscripcion',16,2)->nullable()->after('fecha_inicio');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('grupos', function (Blueprint $table) {
            $table->dropColumn(['precio_semanal','precio_mensualidad_pronto_pago','precio_mensualidad','precio_inscripcion']);
            $table->decimal('inscripcion',12,2)->nullable()->after('fecha_inicio');
            $table->decimal('colegiatura',12,2)->nullable()->after('fecha_inicio');
        });
    }
}
