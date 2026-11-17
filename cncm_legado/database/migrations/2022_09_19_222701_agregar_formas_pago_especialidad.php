<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarFormasPagoEspecialidad extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('especialidades', function (Blueprint $table) {
            $table->string('formas_pago')->nullable()->default('["mensual","semanal"]');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('especialidades', function (Blueprint $table) {
            $table->dropColumn('formas_pago');
        });
    }
}
