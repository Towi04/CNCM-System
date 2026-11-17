<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'productos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_sucursal',
        'nombre',
        'descripcion',
        'clave_sat',
        'clave_unidad_sat',
        'precio',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];


    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class,'id_sucursal','id')
            ->withDefault([
                'nombre' => ''
            ]);
    }

    /**
     * Get all of the partidas_compras for the Producto
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function partidas_compras()
    {
        return $this->hasMany(Compra::class, 'id_producto', 'id');
    }

    /**
     * Get all of the partidas_compras for the Producto
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function partidas_ventas()
    {
        return $this->hasMany(PartidaVenta::class, 'id_producto', 'id');
    }



    public function getExistenciasAttribute(){
        
        
        $partidas_compras = $this->partidas_compras->sum('cantidad');
        $partidas_ventas = $this->partidas_ventas->filter(function($partida){
            return $partida->venta->status == 'Cerrada';
        })->sum(function($partida){
            return -1* $partida->cantidad;
        });

        
        // AQUI SE VA A PONER LO DE LAS VENTAS


      
        

        return [

            'compras' => $partidas_compras, 
            'ventas' => $partidas_ventas,
            
        ];

}


public function getExistencias(){
    $suma = 0;
    $existencias = $this->existencias;

    // dd($existencias);
 
    if(isset($existencias['compras'])){
        $suma +=$existencias['compras'];
    }
    // dd($suma);

    if(isset($existencias['ventas'])){
        $suma +=$existencias['ventas'];
    }
   
  
    

    return  $suma;

}

    



}
