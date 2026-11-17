@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Usuarios
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('admin.usuarios.index') }}">Usuarios</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>{{ $user->fullname }}</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row mt-2 pt-2">
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
                                        {{ $user->fullname }}
                                    </h2>
                                    <h4>
                                        @foreach ($user->roles as $role)
                                            {{ $role->display_name }}@if(!$loop->last),@endif
                                        @endforeach
                                    </h4>
                                    <a href="{{ route('admin.usuarios.edit', $user->id) }}"> <button class="btn btn-info btn-circle"><i class="fas fa-pencil-alt"></i></button></a>
                                    <a href=""> <button class="btn btn-danger btn-circle"><i class="fas fa-trash-alt"></i></button></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="element-box text-center">
                <img src="{{asset('storage/usuarios_qrs/'.$user->id.'/qr.png')}}" alt="" >
                <a download="{{$user->fullname}}.png" class="btn btn-block btn-info text-white" href="{{asset('storage/usuarios_qrs/'.$user->id.'/qr.png')}}"><i class="fas fa-download    "></i> Descargar QR </a>
            </div>
        </div>
    </div>
@endsection
