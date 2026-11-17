<?php

namespace App\Console\Commands;

use App\Models\Grupo;
use App\Models\GrupoDesercion;
use Illuminate\Console\Command;

use Illuminate\Support\Carbon;

class GenerarDesercionesGrupos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generar-deserciones:grupos {semana?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para generar el reporte semanal de deserciones por grupo';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Carbon::setWeekStartsAt(Carbon::SUNDAY);
        Carbon::setWeekEndsAt(Carbon::SATURDAY);

        $grupos = Grupo::where('status','=','Activo')->get();
        $fecha = Carbon::now();
        
        if($this->argument('semana')){
            $semana = $this->argument('semana');
        }else{
            $semana = $fecha->weekOfYear;
        }

        $year = $fecha->year;
        $semana_anterior = $semana-1;

        foreach ($grupos as $grupo) {
            
            #SE BUSCA LA DESERCION DE LA SEMANA ANTERIOR
            $desercion_anterior = GrupoDesercion::where('id_grupo','=',$grupo->id)
                                    ->where('semana','=',$semana_anterior)
                                    ->where('year','=',$fecha->year)
                                    ->first();
            
            if($desercion_anterior){
                $final_anterior = $desercion_anterior->total_final;
            }else{
                $final_anterior = $grupo->alumnos->count();
            }


            $grupo_desercion = GrupoDesercion::where('id_grupo','=',$grupo->id)
            ->where('semana','=',$semana)
            ->where('year','=',$year)
            ->first();

            if(!$grupo_desercion){
                $grupo_desercion = new GrupoDesercion();
            }
            $grupo_desercion->semana = $semana;
            $grupo_desercion->year = $year;
            $grupo_desercion->id_grupo = $grupo->id;
            $grupo_desercion->anterior = $final_anterior;
            $grupo_desercion->inicios = null;
            $grupo_desercion->reingresos = null;
            $grupo_desercion->cambios_horarios_altas = null;
            $grupo_desercion->bajas = null;
            $grupo_desercion->cambios_horarios_bajas = null;
            $grupo_desercion->fin_curso = null;
            $grupo_desercion->total_final = $final_anterior;
            $grupo_desercion->save();
        }

        $this->line('Deserciones creadas correctamente');

        return Command::SUCCESS;
    }
}
