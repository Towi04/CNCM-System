<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\Grupo;

class PagosAlumnosService
{
    protected $alumno;

    public function setAlumno(Alumno $alumno)
    {
        $this->alumno = $alumno;

        return $this;
    }

    public function mensual(Grupo $grupo)
    {
        if (empty($this->alumno)) {
            throw new \Exception('Debes asignar a un alumno para generar sus pagos', 1);
        }

        # INSCRIPCION
        $this->alumno->pagos()->create([
            'id_grupo'      => $grupo->id,
            'concepto'      => config('alumnos.concepto.inscripcion'),
            'monto'         => $grupo->precio_inscripcion ?? 0,
            'fecha_limite'  => $grupo->fecha_inicio,
        ]);

        # CALUCULO DE PAGOS
        $colegiaturas = [];

        $fecha_inicio = $grupo->fecha_inicio->copy();

        foreach (range(1, config('grupos.duracion.cuatrimestre')) as $cuatrimeste) {

            $fecha_limite = ($cuatrimeste == 1) ?
                $fecha_inicio :
                $fecha_inicio->copy()->addMonth($cuatrimeste - 1);

            $colegiaturas[] = [
                'id_grupo'      => $grupo->id,
                'concepto'      => config('alumnos.concepto.colegiatura'),
                'monto'         => $grupo->precio_mensualidad ?? 0,
                'fecha_limite'  => $fecha_limite
            ];
        }

        $this->alumno->pagos()->createMany($colegiaturas);
    }

    public function semanal(Grupo $grupo)
    {
        if (empty($this->alumno)) {
            throw new \Exception('Debes asignar a un alumno para generar sus pagos', 1);
        }

        # INSCRIPCION
        $this->alumno->pagos()->create([
            'id_grupo'      => $grupo->id,
            'concepto'      => config('alumnos.concepto.inscripcion'),
            'monto'         => $grupo->precio_inscripcion ?? 0,
            'fecha_limite'  => $grupo->fecha_inicio,
        ]);

        # CALCULO DE PAGOS
        $fecha_inicio = $grupo->fecha_inicio->copy();
        $fecha_final = $grupo->fecha_inicio->copy()->addMonth(config('grupos.duracion.cuatrimestre'));
        $semanas = $fecha_inicio->diffInWeeks($fecha_final);
        $monto_total_pago = ($grupo->precio_semanal ?? 0) * config('grupos.duracion.cuatrimestre');
        $pago_semanal = ($semanas == 0) ? 0 :  $monto_total_pago / $semanas;

        $colegiaturas = [];
        foreach (range(1, $semanas) as $semana) {

            $fecha_limite = ($semana == 1) ?
                $fecha_inicio :
                optional($fecha_inicio)->copy()->addWeek($semana - 1);

            $colegiaturas[] = [
                'id_grupo'      => $grupo->id,
                'concepto'      => config('alumnos.concepto.colegiatura'),
                'monto'         => $pago_semanal,
                'fecha_limite'  => $fecha_limite
            ];
        }

        $this->alumno->pagos()->createMany($colegiaturas);
    }
}
