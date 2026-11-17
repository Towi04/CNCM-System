<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarProntopagoNormal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->decimal('pronto_pago',12,2)->nullable()->after('monto');
            $table->decimal('normal_pago',12,2)->nullable()->after('pronto_pago');
            $table->date('fecha_limite_pronto_pago')->nullable()->after('normal_pago');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn('pronto_pago');
            $table->dropColumn('normal_pago');
            $table->dropColumn('fecha_limite_pronto_pago');
        });
    }
}
