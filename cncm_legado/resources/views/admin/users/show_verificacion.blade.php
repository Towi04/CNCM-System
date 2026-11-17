@extends('layouts.template-'.config('settings.template').'.plantilla_publico')

@section('titulo')
    {{$user->fullname}}
@endsection

@section('breadcrumb')
   
@endsection

@section('contenido')
    <div class="row mt-2 pt-2 justify-content-center">
        <div class="col-md-8">
            <div class="element-box">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="profile-image">
                            <img src="{{ url('archivo/usuarios_foto/') }}/{{ $user->id }}/{{ $user->foto }}" class="rounded-circle circle-border m-b-md" alt="profile" width="150px">
                        </div>
                        <div class="profile-info">
                            <div class="">
                                <div>
                                    <h2 class="no-margins">
                                        {{ $user->nombres }} {{ $user->apellido_paterno }}
                                    </h2>
                                    <h4>
                                        @foreach ($user->roles as $role)
                                            {{ $role->display_name }}@if(!$loop->last),@endif
                                        @endforeach
                                    </h4>
                                    <h4>
                                        @foreach ($user->sucursales as $sucursal)
                                            {{ $sucursal->nombre }}@if(!$loop->last),@endif
                                        @endforeach
                                    </h4>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
