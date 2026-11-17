<?php

namespace App\Console\Commands;

use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\Documento;
use App\Models\User;
use App\Notifications\ConfirmacionProcesoAutomatico;
use App\Notifications\ErrorProcesoAutomatico;
use App\Services\PagoColegiaturaService;
use App\Services\PagoInscripcionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


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
        $dia_actual = date('Y-m-d');

        try
        {
            DB::select(DB::raw('update documentos set monto = normal_pago, saldo = normal_pago - IFNULL((select sum(monto) from abonos_documentos where id_documento = documentos.id),0) where fecha_limite_pronto_pago < "'.$dia_actual.'" and saldo > 0'));

            $users = User::whereIn('email',['aldo@adndigital.mx','zemarcial@cncm.edu.mx'])->get();

            foreach($users as $user){
                $user->notify(new ConfirmacionProcesoAutomatico('Actualización de prontos pagos'));
            }

            $this->line('Documentos actualizados correctamente al pronto pago');
        }catch(\Throwable $th){
            $users = User::where('email','=','aldo@adndigital.mx')->get();
            foreach($users as $user){
                $user->notify(new ErrorProcesoAutomatico('Cargo semanal de documentos', $th));
            }
            $this->line('Ocurrio un error. Se envío email con la info.');

        }

    }
}
