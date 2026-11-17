<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\UsuarioDia;
use Illuminate\Http\Request;
use App\Notifications\DatosAcceso;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

use App\Http\Requests\Admin\User\EditUserRequest;
use App\Http\Requests\Admin\User\CreateUserRequest;
use Symfony\Component\HttpFoundation\Response as HTTPMessages;
use SimpleSoftwareIO\QrCode\Facades\QrCode;


class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        abort_unless(Auth::user()->can('gestionar_usuarios'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        $users = User::with('roles')->get();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $roles = Role::query()->get();

        $sucursales = [];

        if (Auth::user()->can('asignar_varias_sucursales')) {
            $sucursales = Sucursal::query()->get();
        }

        $user = new User;

        $dias_semana = [
            'lunes'     => 'Lunes',
            'martes'    => 'Martes',
            'miercoles' => 'Miércoles',
            'jueves'    => 'Jueves',
            'viernes'   => 'Viernes',
            'sabado'    => 'Sábado',
            'domingo'   => 'Domingo',
        ];

        return view('admin.users.create', compact('user', 'roles', 'sucursales','dias_semana'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(CreateUserRequest $request)
    {
        $user = new User();

        $user->fill([
            'nombres'           => $request['nombres'],
            'apellido_paterno'  => $request['apellido_paterno'],
            'apellido_materno'  => $request['apellido_materno'],
            'email'             => $request['email'],
            'celular'           => $request['celular'],
            'password'          => $request['password'],
            'fecha_nacimiento'          => $request['fecha_nacimiento'],
            'email_verified_at' => date('Y-m-d')
        ]);

        $user->save();

        if (isset($request->role)) {
            $user->assignRole($request['role']);
        }

        if(Auth::user()->can('asignar_varias_sucursales')){
            if ($request->has('sucursales')) {
                $user->sucursales()->sync($request->input('sucursales',[]));
            }
        }else{
            // NOTE: AGREGAR SUCURSAL ACTUAL
            $sucursal_actual = session('sucursal');
            $user->sucursales()->sync([optional($sucursal_actual)->id]);
        }

        $file = $request->file('foto');

        if (isset($file)) {

            $image = \Image::make($file);
            $image->resize(150, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            

            $nombre_foto = $file->getClientOriginalName();

            if (!Storage::exists('usuarios_foto')) {
                Storage::makeDirectory('usuarios_foto');
            }

            if (!Storage::exists("usuarios_foto/{$user->id}")) {
                Storage::makeDirectory("usuarios_foto/{$user->id}");
            }

            $path = storage_path() . "/app/usuarios_foto/{$user->id}/";
            $image->save($path . $nombre_foto);

            $user->foto = $nombre_foto;
            $user->save();
        }

        if (isset($request->enviar_datos)) {
            try {
                $user->notify(new DatosAcceso($user, $request->password));
            } catch (\Throwable $th) {
                Log::error('No se pudo enviar datos de acceso.  Error: ' . $th->getMessage());
            }
        }

        $dias_number = [
            'lunes'     => '2',
            'martes'    => '3',
            'miercoles' => '4',
            'jueves'    => '5',
            'viernes'   => '6',
            'sabado'    => '7',
            'domingo'   => '1',
        ];
        // CREACION DE HORAS Y DIAS
        if(isset($request->dia)){
            foreach ($request->dia as $dia) {
                $usuario_dia = new UsuarioDia();
                $usuario_dia->id_usuario = $user->id;
                $usuario_dia->dia = $dia;
                $usuario_dia->dayofweek = $dias_number[$dia];
                $usuario_dia->hora_inicio = $request['inicio_' . $dia];
                $usuario_dia->hora_final = $request['fin_' . $dia];
                $usuario_dia->save();
            }
        }
        

        return redirect()->route('admin.usuarios.index')->with([
            'message' => "El usuario {$user->nombres} {$user->apellido_paterno} se guardó con éxito"
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(User $usuario)
    {
        $usuario->load(['roles']);

        $directory_cfdi = Storage::disk('local')->path("public/usuarios_qrs/".$usuario->id);

        if (!File::exists($directory_cfdi)) {
            File::makeDirectory($directory_cfdi, 0775, true);
        }


        // dd($usuario->link_verificacion);
        QrCode::format('png')->size('250px')->generate($usuario->link_verificacion, storage_path('app/public/usuarios_qrs/' . $usuario->id . '/qr.png'));
        
        return view('admin.users.show', [
            'user' => $usuario
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */

    public function edit(User $usuario)
    {
        $sucursales = [];

        if (Auth::user()->can('asignar_varias_sucursales')) {
            $sucursales = Sucursal::query()->get();
        }

        $dias_semana = [
            'lunes'     => 'Lunes',
            'martes'    => 'Martes',
            'miercoles' => 'Miércoles',
            'jueves'    => 'Jueves',
            'viernes'   => 'Viernes',
            'sabado'    => 'Sábado',
            'domingo'   => 'Domingo',
        ];

        return view('admin.users.edit', [
            'roles'         => Role::query()->get(),
            'user'          => $usuario->load(['roles']),
            'sucursales'    => $sucursales,
            'dias_semana'   => $dias_semana,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  User  $user
     * @return Response
     */
    public function update(User $usuario, EditUserRequest $request)
    {
        $usuario->fill([
            'nombres'           => $request['nombres'],
            'apellido_paterno'  => $request['apellido_paterno'],
            'apellido_materno'  => $request['apellido_materno'],
            'email'             => $request['email'],
            'celular'           => $request['celular'],
            'password'          => $request['password'],
            'fecha_nacimiento'  => $request['fecha_nacimiento'],
        ]);

        $usuario->save();

        $usuario->syncRoles($request->role);

        if (Auth::user()->can('asignar_varias_sucursales')) {
            if ($request->has('sucursales')) {
                $usuario->sucursales()->sync($request->input('sucursales',[]));
            }
        }else{
            $sucursal_actual = session('sucursal');
            $usuario->sucursales()->sync([optional($sucursal_actual)->id]);
        }

        # SI ES EL USUARIO ACTUAL QUE ESTA ACTUALIZANDO SU PROPIA INFORMACION
        if (Auth::user()->id == $usuario->id) {
            $usuario_sucursales = $usuario->sucursales;
            Session::put('sucursales',$usuario_sucursales);
            Session::put('sucursal',$usuario_sucursales->first());
        }

        if (isset($request->enviar_datos)) {
            try {
                $usuario->notify(new DatosAcceso($usuario, $request->password));
            } catch (\Throwable $th) {
                Log::error('No se pudo enviar datos de acceso.  Error: ' . $th->getMessage());
            }
        }

        $file = $request->file('foto');

        if (isset($file)) {

            $image = \Image::make($file);
            $image->resize(150, null, function ($constraint) {
                $constraint->aspectRatio();
            });

            $nombre_foto = $file->getClientOriginalName();

            if (!Storage::exists('usuarios_foto')) {
                Storage::makeDirectory('usuarios_foto');
            }

            if (!Storage::exists("usuarios_foto/{$usuario->id}")) {
                Storage::makeDirectory("usuarios_foto/{$usuario->id}");
            }

            $path = storage_path() . "/app/usuarios_foto/{$usuario->id}/";
            $image->save($path . $nombre_foto);

            $usuario->foto = $nombre_foto;
            $usuario->save();
        }

        // CREACION DE HORAS Y DIAS
        $usuario->days()->delete();

        $dias_number = [
            'lunes'     => '2',
            'martes'    => '3',
            'miercoles' => '4',
            'jueves'    => '5',
            'viernes'   => '6',
            'sabado'    => '7',
            'domingo'   => '1',
        ];

        if(isset($request->dia)){
            foreach ($request->dia as $dia) {
                $usuario_dia = new UsuarioDia();
                $usuario_dia->id_usuario = $usuario->id;
                $usuario_dia->dia = $dia;
                $usuario_dia->dayofweek = $dias_number[$dia];
                $usuario_dia->hora_inicio = $request['inicio_' . $dia];
                $usuario_dia->hora_final = $request['fin_' . $dia];
                $usuario_dia->save();
            }
        }

        return redirect()->back()->with([
            'message' => "El usuario se {$usuario->nombres} {$usuario->apellido_paterno} se actualizó con éxito"
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  User $user
     * @return Response
     */
    public function destroy(Request $request, User $usuario)
    {
        $usuario->email = $usuario->email . 'D' . date('dmYHis') . 'U' . Auth::user()->id;
        $usuario->save();
        $usuario->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'msg'     => "Usuario {$usuario->nombres} eliminado",
                'id'      => $usuario->id
            ]);
        }

        return redirect()
            ->route('admin.usuarios.index')
            ->with([
                'message' => "El usuario se {$usuario->nombres} {$usuario->apellido_paterno} se eliminó con éxito"
            ]);
    }

    public function traer_usuarios_select2(Request $request)
    {
        $term  = $request->input('term');
        $page = $request->input('page', 1);

        $resultCount = 10;
        $offset = ($page - 1) * $resultCount;

        $results = User::select(['id','nombres','apellido_paterno','apellido_materno'])
            ->where('nombres', 'like', "%{$term}%")
            ->orWhere('apellido_paterno', 'like', "%{$term}%")
            ->orWhere('apellido_materno', 'like', "%{$term}%")
            ->orderBy('nombres', 'asc')
            ->skip($offset)
            ->take($resultCount)
            ->get();

        $count = User::query()
            ->where('nombres', 'like', "%{$term}%")
            ->orWhere('apellido_paterno', 'like', "%{$term}%")
            ->orWhere('apellido_materno', 'like', "%{$term}%")
            ->count();

        $endCount = $offset + $resultCount;
        $morePages = $count > $endCount;

        if ($request->ajax()) {
            return response()->json([
                'results'       => $results,
                'pagination'    => [
                    'more' => $morePages
                ]
            ]);
        }

        return redirect()->back();
    }

   
    public function verificacion($id)
    {
        $usuario = User::find($id);
        

        return view('admin.users.show_verificacion', [
            'user' => $usuario
        ]);
    }

}
