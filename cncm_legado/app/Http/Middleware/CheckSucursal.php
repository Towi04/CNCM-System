<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Sucursal;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckSucursal
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (empty($user)) {
            return $next($request);
        }

        // dd(Str::contains($request->path(), 'admin/sucursales'));
        if ($request->method() == 'POST' || $request->path() == 'logout' || Str::contains($request->path(), 'admin/sucursales') || $request->path() == '/') {
            return $next($request);
        }

        $sucursal = session('sucursal');

        if (empty($sucursal)) {
            if (Sucursal::query()->exists()) {
                if ($user->can('asignar_varias_sucursales')) {
                    return redirect()->route('admin.sucursales.asignar-sucursal');
                } else {
                    return redirect()->route('home');
                }
            } else {
                if ($user->can('gestionar_sucursales')) {
                    return redirect()->route('admin.sucursales.index');
                } else {
                    return redirect()->route('home');
                }
            }
        }

        return $next($request);
    }
}
