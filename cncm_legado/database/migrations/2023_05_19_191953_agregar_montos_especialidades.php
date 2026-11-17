<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarMontosEspecialidades extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('descuentos', function (Blueprint $table) {
            $table->decimal('monto_1',14,2)->nullable()->after('id_especialidad_1');
            $table->string('forma_pago_1')->nullable()->after('monto_1');
            $table->decimal('monto_2',14,2)->nullable()->after('id_especialidad_2');
            $table->string('forma_pago_2')->nullable()->after('monto_2');

            $table->dropColumn('porcentaje_descuento');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('descuentos', function (Blueprint $table) {
            $table->dropColumn('monto_1');
            $table->dropColumn('monto_2');
            $table->dropColumn('forma_pago_1');
            $table->dropColumn('forma_pago_2');

            $table->decimal('porcentaje_descuento',14,2)->after('id_especialidad_2');
        });
    }
}
