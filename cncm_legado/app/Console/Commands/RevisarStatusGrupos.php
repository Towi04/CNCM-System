<?php

namespace App\Console\Commands;

use App\Models\Grupo;
use Illuminate\Console\Command;

class RevisarStatusGrupos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'revisar-status:grupos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revisa que las fechas de los grupos programados cambien a activo';

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
        # 👉 OBTENGO LOS GRUPOS DE TODAS LAS SUCURSALES Y LOS GUARDO EN  UN CURSOR
        $grupos = Grupo::query()->where('status',config('grupos.status.values.Programado'))->cursor();

        $now = now();

        $grupos->each(function($grupo)use($now){
            $status = $grupo->fecha_inicio->lt($now)
            ? config('grupos.status.values.Activo')
            : config('grupos.status.values.Programado');

            $grupo->update([
                'status' => $status
            ]);
        });

        return Command::SUCCESS;
    }
}
