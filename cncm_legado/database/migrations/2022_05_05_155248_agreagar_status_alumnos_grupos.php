<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgreagarStatusAlumnosGrupos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('alumnos_grupos', function (Blueprint $table) {
            $table->string('status')->default('Inscrito')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('alumnos_grupos', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}
