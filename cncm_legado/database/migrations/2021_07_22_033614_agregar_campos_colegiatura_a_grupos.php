<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCamposColegiaturaAGrupos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('grupos', function (Blueprint $table) {
            $table->decimal('inscripcion',12,2)->nullable()->after('fecha_inicio');
            $table->decimal('colegiatura',12,2)->nullable()->after('fecha_inicio');

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
            $table->dropColumn(['colegiatura','inscripcion']);
        });
    }
}
