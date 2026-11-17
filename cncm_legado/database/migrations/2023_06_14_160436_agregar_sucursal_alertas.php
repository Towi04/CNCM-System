<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarSucursalAlertas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_sucursal')->nullable();

            $table->foreign('id_sucursal')->references('id')->on('sucursales')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropForeign('alertas_id_sucursal_foreign');
            $table->dropColumn('id_sucursal');
        });
    }
}
