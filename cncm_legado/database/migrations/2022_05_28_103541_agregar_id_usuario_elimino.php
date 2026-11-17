<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarIdUsuarioElimino extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->bigInteger('id_usuario_elimino')->unsigned()->nullable();

            $table->foreign('id_usuario_elimino')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign('pagos_id_usuario_elimino_foreign');

            $table->dropColumn('id_usuario_elimino');

        });
    }
}
