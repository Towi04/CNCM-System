<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarIdDescuentoApoyosEspeciales extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('apoyos_especiales', function (Blueprint $table) {
            $table->unsignedBigInteger('id_descuento')->nullable();

            $table->foreign('id_descuento')->references('id')->on('descuentos')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('apoyos_especiales', function (Blueprint $table) {
            $table->dropForeign('apoyos_especiales_id_descuento_foreign');

            $table->dropColumn('id_descuento');
            
        });
    }
}
