<?php

namespace App\Console\Commands;

use App\Models\Alumno;
use App\Models\Grupo;
use App\Services\PagoColegiaturaService;
use App\Services\PagoInscripcionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerarPagoMensualSinProntoPago extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generar-pago:mensual-sin-pronto-pago';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permite actualizar el precio de pronto pago al precio normal asignado para el grupo';

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
        $dia_actual = today();

        Alumno::query()->select(['id','numero_control'])->alumno()->mensual()
        ->whereHas('especialidades', function($q){
            return $q->where('status','=','Activo')->where('alumnos_especialidades.forma_pago','=','mensual');
        })->with(['especialidades'])
        ->each(function($alumno) use ($dia_actual){
            $especialidades = $alumno->especialidades;

            foreach ($especialidades as $especialidad) {
                # OBTENGO LA DIFERENCIA ENTRE EL PRECIO DE LA MENSUALIDAD Y EL PRONTO PAGO
                $precio_mensualidad = $especialidad->pivot->monto ?? 0;
                $precio_pronto_pago = $especialidad->pivot->monto_pronto_pago ?? 0;
                $precio_cargo = ($precio_mensualidad == 0) ? 0 : ($precio_mensualidad - $precio_pronto_pago);

                $documentos = $alumno->documentos()
                    ->where('id_especialidad',$especialidad->id)
                    ->where('status','Pendiente')
                    ->where('tipo', config('alumnos.concepto.colegiatura'))
                    ->where('especial','!=',1)
                    ->whereDate('fecha_limite','<',$dia_actual)
                    ->get();

                foreach ($documentos as $pago) {
                    # SUMO ESA DIFERENCIA AL MONTO Y AL SALDO
                    $monto = $pago->monto + $precio_cargo;
                    $pago->monto = $monto;
                    $pago->saldo = $monto;
                    $pago->save();
                }
            }
        });

        $this->line('Mensualidad actualizada correctamente');
    }
}
