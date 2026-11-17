<?php

namespace App\Imports;

use App\Models\Alumno;
use App\Models\Pago;
use App\Models\Abono;
use App\Models\AbonoDocumento;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class PagosImport implements 
    ToCollection,
    WithHeadingRow
    ,SkipsOnError
    ,WithValidation
    ,WithBatchInserts
    ,WithChunkReading
{
    use SkipsErrors,Importable;

    protected $sucursal;

    public function __construct($sucursal)
    {   

        $this->sucursal = $sucursal;
    }

    public function collection(Collection $collection)
    {

        $alumnos_borrar = [];
        foreach ($collection as $row)
        {
            $alumno = Alumno::with(['pagos'])
            ->where('nuevo_numero_control','=',$row['numero_control'])->where('id_sucursal','=',$this->sucursal->id)->first();
            
            // Si existe el alumno se realiza el cambio
            if($alumno){
                #Se borran los pagos del alumno

                // if(!in_array($alumno->id, $alumnos_borrar)){
                //     array_push($alumnos_borrar,$alumno->id);
                // }
                
                // Se obtiene primero las sexistenicas actualies. 
                $pago = new Pago();
                $pago -> folio = $row['folio'];
                $pago -> id_sucursal = $this->sucursal->id;
                $pago -> id_alumno = $alumno->id;
                $pago -> fecha = @\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['fecha']);
                $pago -> forma_pago =  'Efectivo';
                #EMMANUEL
                $pago -> id_recibio = 31;
                $pago -> monto = $row['cantidad'];
                $pago ->save();

            }
        }
        
    }

   

    public function rules():array {
        return [
            //  'id_number'                        => 'required',
            //  'numero_de_parte'           => 'required',
        ];
    }

    public function batchSize(): int
    {
        return 1000;
    }

     public function chunkSize(): int
    {
        return 1000;
    }

}
