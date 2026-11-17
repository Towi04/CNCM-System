<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\GrupoDia;

class AgregarDayofweekGruposDias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('grupos_dias', function (Blueprint $table) {
            $table->integer('dayofweek')->nullable()->after('id_grupo');
        });

        $dias = [
            'domingo' => '1',
            'lunes' => '2',
            'martes' => '3',
            'miercoles' => '4',
            'jueves' => '5',
            'viernes' => '6',
            'sabado' => '7',
        ];

        foreach(GrupoDia::get() as $dia){
            $dia->dayofweek = $dias[$dia->dia];
            $dia->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('grupos_dias', function (Blueprint $table) {
            $table->dropColumn('dayofweek');
        });
    }
}
