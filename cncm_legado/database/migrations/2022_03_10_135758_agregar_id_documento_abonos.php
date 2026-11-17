<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarIdDocumentoAbonos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('abonos', function (Blueprint $table) {
            $table->bigInteger('id_documento')->after('id_alumno_pago')->nullable()->unsigned();

            $table->foreign('id_documento')->references('id')->on('documentos')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('abonos', function (Blueprint $table) {
            $table->dropForeign('abonos_id_documento_foreign');
            $table->dropColumn('id_documento');
            
        });
    }
}
