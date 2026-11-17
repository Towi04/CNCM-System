<?php

namespace App\Http\Middleware;

use Closure;

class CheckServidor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(env('STATUS_SERVICIO') =='inactivo' ){
            echo 'Lo sentimos, este servicio no está disponible. Contacte a su proveedor de servicios para más información.';
            exit();
        }
        
        return $next($request);
    }
}
