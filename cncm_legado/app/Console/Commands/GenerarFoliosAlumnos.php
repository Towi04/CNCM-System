<?php

namespace App\Console\Commands;

use App\Models\Alumno;
use App\Models\Sucursal;
use Illuminate\Console\Command;

class GenerarFoliosAlumnos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generar-folios:alumnos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para generar los folios de alumnos del sistema';

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
        $sucursales = Sucursal::where('id','=','3')->toBase()->select('id')->pluck('id');
        $folio_alumno_inicial = config('alumnos.control_folio');
        $incremento_folio = 1;

        foreach ($sucursales as $sucursal) {
            $alumnos = Alumno::query()
                ->where('status',config('alumnos.status.Alumno'))
                ->where('id_sucursal',$sucursal)
                ->get();

            $control_incremento = 1;

            foreach ($alumnos as $alumno) {
                $alumno->update([
                    'nuevo_numero_control' => $folio_alumno_inicial + $control_incremento,
                ]);

                $control_incremento+= $incremento_folio;
            }
        }

        $this->line('Folios Generados correctamente');

        return Command::SUCCESS;
    }
}
