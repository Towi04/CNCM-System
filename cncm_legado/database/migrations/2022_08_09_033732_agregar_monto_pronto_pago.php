<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarMontoProntoPago extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alumnos_especialidades', function (Blueprint $table) {
            $table->decimal('monto_pronto_pago',15,2)->after('monto')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alumnos_especialidades', function (Blueprint $table) {
            $table->dropColumn('monto_pronto_pago');
        });
    }
}
