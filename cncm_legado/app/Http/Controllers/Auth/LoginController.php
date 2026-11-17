<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

use App\Notifications\UsuarioFueraHorario;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view('auth.template-'.config('settings.template').'.login');
    }

     /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        $sucursales_usuario = $user->sucursales;

        # NOTE: ASIGNO LAS SUCURSALES DEL USUARIO AUTENTICADO
        Session::put('sucursales',$sucursales_usuario);




        if(!$user->id_ultima_sucursal){
            Session::put('sucursal',$sucursales_usuario->first());
        }else{
            # SE OBTIENE LA ULTIMA SUCURSAL EN LA QUE ESTUVO TRABAJANDO ANTES DE CERRAR SESIÓN. SI ESTA ENTRE SUS SUCURSALES SE ABRE EN ESA
            if($sucursales_usuario->where('id',$user->id_ultima_sucursal)->first()){
                Session::put('sucursal', $sucursales_usuario->where('id',$user->id_ultima_sucursal)->first());
            }else{
                Session::put('sucursal',$sucursales_usuario->first());
            }
        }

        // VALIDACION DEL HORARIO
        $dentro_horario = DB::select(DB::raw('select count(*) as contador from users a 
        left join usuarios_dias b on a.id = b.id_usuario
        Where DAYOFWEEK("'.date('Y-m-d H:i:s').'") = b.dayofweek and a.id = '.auth()->user()->id.'
        and "'.date('Y-m-d H:i:s').'" BETWEEN CONCAT("'.date('Y-m-d').' ",DATE_SUB(b.hora_inicio,INTERVAL 15 MINUTE) ) and CONCAT("'.date('Y-m-d').' ",DATE_ADD(b.hora_final,INTERVAL 15 MINUTE));'))[0];


        if(!$dentro_horario->contador){
            $users = User::permission('notificacion_usuario_fuera_horario')->get();

            foreach($users as $user){
                try {
                    $user->notify(new UsuarioFueraHorario(auth()->user()));
                } catch (\Exception $e) {
                    // Manejo de excepciones si la notificación falla
                    Log::error('Error al enviar notificación: ' . $e->getMessage());
                }
            }
        }

        
    }
}
