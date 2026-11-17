<?php
use Illuminate\Support\Facades\Route;

if (!function_exists('routeIs')) {
    /**
     * Regresa una clase css , si la ruta especificada esta siendo visitada.
     *
     * @param string|array $route Nombre que se le asigno a la ruta
     * @param string $class Clase css que se utiliza para resaltar el link
     * @return string Regresa el nombre de la clase
     * @see Illuminate\Routing\Router::is
     */
    function routeIs($route, string $class = 'active text-info'): string
    {
        return !Route::is($route) ?: $class;
    }
}


if(!function_exists('activeLink')){
    /**
     * Helper Active
     * Este helper revisa la url actual, si esta en la ruta especificada,
     * retorna una cadena, la cual represnta la clase active.
     *
     * Por defecto, se le agrega la clase active, pero se puede especificar
     * la clase active, dependiendo del componente
     *
     * @param string|array $path
     * @param string $class
     * @return string
     */
    function active( $path, string $class = 'active'):string
    {
        return request()->is($path) ? $class:'';
    }
}
