<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User;
use App\Notifications\EnviarCumplePersonalNotification;


class EnviarCumplePersonal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'personal:enviar_cumple';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía al personal la felicitación de cumpleaños';

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
        
        $usuarios_cumples = User::whereRaw("DATE_FORMAT(fecha_nacimiento,'%m-%d') = DATE_FORMAT(NOW(),'%m-%d')")->get();

        foreach($usuarios_cumples as $usuario){
            $usuario->notify(new EnviarCumplePersonalNotification());
        }

        return 0;
    }
}
