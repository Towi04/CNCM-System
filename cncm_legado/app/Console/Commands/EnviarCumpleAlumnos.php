<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Alumno;
use App\Notifications\EnviarCumpleAlumnosNotification;


class EnviarCumpleAlumnos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alumnos:enviar_cumple';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía a los alumnos la felicitación de cumpleaños';

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
        
        $alumnos_cumples = Alumno::whereRaw("DATE_FORMAT(fecha_nacimiento,'%m-%d') = DATE_FORMAT(NOW(),'%m-%d')")->get();

        foreach($alumnos_cumples as $alumno){
            $alumno->notify(new EnviarCumpleAlumnosNotification());
        }

        return 0;
    }
}
