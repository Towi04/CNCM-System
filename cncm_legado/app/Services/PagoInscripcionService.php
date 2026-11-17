<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Grupo;
use App\Models\Alumno;
use Jenssegers\Date\Date;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PagoInscripcionService
{
    protected $alumno;

    protected $fecha_actual;

    protected $request;

    public function __construct()
    {
        $this->alumno = new Alumno();

        $this->fecha_actual = today();
    }


    public function setAlumno(Alumno $alumno)
    {
        $this->alumno = $alumno;

        return $this;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    public function mensual()
    {
        $grupos = $this->alumno->grupos;

        foreach ($grupos as $grupo) {
            $this->mensualPorGrupo($grupo);
        }
    }

    public function semanal()
    {
        $grupos = $this->alumno->grupos;

        foreach ($grupos as $grupo) {
            $this->semanalPorGrupo($grupo);
        }
    }

    
    public function mensualPorGrupo(Grupo $grupo)
    {
        # 👉 SE CALCULA EL PRECIO SEGUN EL EL NUMERO DE CLASES TRANSCURRIDAS
        $precio_inscripcion = $this->calcular_precio_mensual($grupo);
        $this->inscripcion($grupo,$precio_inscripcion);
    }

    public function semanalPorGrupo(Grupo $grupo)
    {
        $precio_inscripcion = optional($this->request)->input('precio_inscripcion') ?? $grupo->precio_inscripcion ?? 0;

        $this->inscripcion($grupo,$precio_inscripcion);

        $precio_semanal = $grupo->precio_semanal ?? 0;

        if ($grupo->fecha_inicio->greaterThan($this->fecha_actual)) {
            $fecha_inicio = $grupo->fecha_inicio->copy();
        } else {
            $fecha_inicio = $this->fecha_actual->copy();
        }

        $this->alumno->pagos()->create([
            'id_grupo'      => $grupo->id,
            'concepto'      => config('alumnos.concepto.colegiatura') . 'Semana: ' . $grupo->fecha_inicio->week . ' del ' . $grupo->fecha_inicio->year,
            'tipo'          => config('alumnos.concepto.colegiatura'),
            'monto'         => $precio_semanal,
            'fecha_limite'  => $fecha_inicio->endOfWeek(Carbon::SATURDAY),
            'status'        => config('pagos.status.Pendiente'),
        ]);
    }

    public function inscripcion(Grupo $grupo,$precio_inscripcion)
    {
        $monto_apoyo_inscripcion = optional($this->request)->has('precio_inscripcion') ? ($grupo->precio_inscripcion  - $this->request->input('precio_inscripcion')): null;
        $precio_inscripcion = optional($this->request)->has('precio_inscripcion')  ? $this->request->input('precio_inscripcion'): $precio_inscripcion;

        $pago_alumno = $this->alumno->pagos()->create([
            'id_grupo'                  => $grupo->id,
            'concepto'                  => config('alumnos.concepto.inscripcion') . ' del ' . $grupo->fecha_inicio->year,
            'monto'                     => $precio_inscripcion,
            'monto_apoyo_inscripcion'   => $monto_apoyo_inscripcion,
            'fecha_limite'              => $grupo->fecha_inicio,
            'tipo'                      => config('alumnos.concepto.inscripcion'),
            'status'                    => config('pagos.status.Pagado'),
        ]);

        if (!empty($this->request)) {

            # FOLIO Y VENTA FISCAL 😁
            # DETECTAR SI ES FISCAL O NO FISCAL:
            $venta_fiscal = ($this->request->input('tipo_pago', '') != 'Efectivo') ? true : $this->alumno->solicitud_factura;
            $folio_fiscal = Pago::query()->select('folio_fiscal')->where('id_sucursal', $this->request->input('id_sucursal'))->max('folio_fiscal') ?? 0;
            $folio = Pago::query()->select('folio')->where('id_sucursal', $this->request->input('id_sucursal'))->max('folio')?? 0;

            # CREO EL PAGO DEL ALUMNO 😏
            $pago = Pago::create([
                'folio'         => ($venta_fiscal) ? null:($folio + 1),  # 👉 SI NO ES UNA VENTA FISCAL, PONER EL FOLIO EN NULL,
                'folio_fiscal'  => ($venta_fiscal) ? $folio_fiscal + 1 : null,
                'id_sucursal'   => $this->request->input('id_sucursal'),
                'id_alumno'     => $this->alumno->id,
                'monto'         => $precio_inscripcion,
                'forma_pago'    => $this->request->input('tipo_pago'),
                'fecha'         => now(),
                'id_recibio'    => auth()->id(),
            ]);

            # GENERO EL ABONO 🙄
            $pago->abonos()->create([
                'id_sucursal'       => $this->request->input('id_sucursal'),
                'id_alumno_pago'    => $pago_alumno->id,
                'monto'             => $precio_inscripcion,
                'venta_fiscal'      => $venta_fiscal,
            ]);
        }
    }

    private function calcular_precio_mensual(Grupo $grupo)
    {
        $precio_inscripcion = optional($this->request)->input('precio_inscripcion') ?? $grupo->precio_inscripcion ?? 0;

        $dia_actual = Carbon::today();
        $mes_actual = $dia_actual->month;

        $lista_numero_dias = [
            'lunes'     => 1,
            'martes'    => 2,
            'miercoles' => 3,
            'jueves'    => 4,
            'viernes'   => 5,
            'sabado'    => 6,
            'domingo'   => 7,
        ];

        $total_dias = 0;
        $dias_pendientes = 0;

        $days = $grupo->days;

        foreach ($days as $grupodia) {
            $numero_dia =  $lista_numero_dias[$grupodia->dia] ?? 0;
            $total_dias += countDaysInMonth($mes_actual,$numero_dia);
            $dias_pendientes += countDaysInMonth($dia_actual,$numero_dia);
        }

        $precio = ($total_dias == 0) ? 0 : $dias_pendientes * $precio_inscripcion / $total_dias;


        return $precio;
    }
}
