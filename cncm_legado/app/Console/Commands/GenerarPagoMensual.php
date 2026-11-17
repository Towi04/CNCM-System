<?php

namespace App\Console\Commands;

use App\Models\Alumno;
use App\Services\PagoColegiaturaDocumentosService;
use Illuminate\Console\Command;
use App\Models\User;
use App\Notifications\ErrorProcesoAutomatico;
use App\Notifications\ConfirmacionProcesoAutomatico;

class GenerarPagoMensual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generar-pago:mensual';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera los pagos mensuales de los alumnos inscritos';

    /**
     * Servicio para calcular la colegiatura
     *
     * @var  App\Services\PagoColegiaturaService
     */
    protected $pcs;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(PagoColegiaturaDocumentosService $pcds)
    {
        parent::__construct();

        $this->pcds = $pcds;

    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        # NOTE: LAZYCOLLECTION https://laravel.com/docs/8.x/collections#lazy-collection-methods


        try{


        Alumno::query()->alumno()->whereHas('especialidades', function($q){
            return $q->where('status','=','Activo')->where('alumnos_especialidades.forma_pago','=','mensual');
        })->with('alumno')
            ->cursor()
            ->each(function($alumno){
                
                foreach($alumno->especialidades as $index => $especialidad){
                    
                    $this->pcds->setAlumno($alumno);
                    $this->pcds->mensual($especialidad);
                }
                
            });


        $this->line('Pago Mensual generado correctamente');
        $users = User::whereIn('email',['aldo@adndigital.mx','zemarcial@cncm.edu.mx'])->get();

        foreach($users as $user){
            $user->notify(new ConfirmacionProcesoAutomatico('Creación de pagos mensuales'));
        }


        }catch(\Throwable $th){


            $users = User::whereIn('email',['aldo@adndigital.mx','zemarcial@cncm.edu.mx'])->get();

            foreach($users as $user){
                $user->notify(new ErrorProcesoAutomatico('Cargo mensual de documentos', $th));
            }
            $this->line('Ocurrio un error. Se envío email con la info.');
        }


        return 0;
    }
}
