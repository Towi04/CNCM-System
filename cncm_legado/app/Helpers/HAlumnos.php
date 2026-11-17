<?php

use App\Models\Alumno;

if (!function_exists('generar_folio_alumno')) {

    /**
     * Permite calcular el siguiente numero_control del alumno dentro de la plataforma.
     *
     * @param int $id_sucursal Indica la sucursal de la que se quiere calcular el nuevo folio
     * @param int $incremento  Especifica el salto del siguiente folio
     * @return int Regresa el nuevo folio
     */
    function generar_folio_alumno($id_sucursal, $incremento = 1):int
    {
        $control_folio = config('alumnos.control_folio',0);

        $max_alumno = Alumno::query()
            ->where('status',config('alumnos.status.Alumno'))
            ->where('id_sucursal', $id_sucursal)
            ->max('nuevo_numero_control') ?? 0;

        # 👉 EN CASO DE NO HABER CORRIDO EL SCRIPT, ARRANCA CON EL FOLIO INICIAL
        if($max_alumno < $control_folio){
            $max_alumno = $control_folio;
        }

        return $max_alumno + $incremento;
    }
}
