<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarRfcASucursales extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('telefono')->nullable()->after('direccion');
            $table->string('rfc')->nullable()->after('telefono');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['telefono','rfc']);
        });
    }
}
