<?php

namespace App\Console;

use App\Console\Commands\GenerarFoliosAlumnos;
use App\Console\Commands\GenerarPagoMensual;
use App\Console\Commands\GenerarPagoMensualSinProntoPago;
use App\Console\Commands\GenerarPagoSemanal;
use App\Console\Commands\RevisarStatusGrupos;
use App\Console\Commands\GenerarDesercionesGrupos;
use App\Console\Commands\EnviarCumplePersonal;
use App\Console\Commands\EnviarCumpleAlumnos;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        GenerarPagoMensual::class,
        GenerarPagoSemanal::class,
        GenerarFoliosAlumnos::class,
        RevisarStatusGrupos::class,
        GenerarPagoMensualSinProntoPago::class,
        GenerarDesercionesGrupos::class,
        EnviarCumplePersonal::class,
        EnviarCumpleAlumnos::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('generar-pago:mensual')->monthlyOn(1, '05:00');
        $schedule->command('generar-pago:semanal')->weeklyOn(7, '05:00');
        $schedule->command('revisar-status:grupos')->dailyAt('00:15');
        $schedule->command('alumnos:enviar_cumple')->dailyAt('8:05');
        $schedule->command('personal:enviar_cumple')->dailyAt('8:00');
        $schedule->command('generar-pago:mensual-sin-pronto-pago')->dailyAt('5:00');
        $schedule->command('generar-deserciones:grupos')->weeklyOn(7, '00:10');
        
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
