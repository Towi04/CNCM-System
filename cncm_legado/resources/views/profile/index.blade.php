@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Mi Pefil
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Mi Perfil</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row mt-2 pt-2">
        <div class="col-md-12">
            <div class="card text-center">
                <div class="card-body">
                    <div class="profile-image">
                        <img src="{{ url('archivo/usuarios_foto/') }}/{{ $user->id }}/{{ $user->foto }}" width="150px" class="rounded-circle circle-border m-b-md" alt="profile">
                    </div>
                </div>
                <div class="profile-info">
                    <div class="">
                        <div>
                            <h2 class="no-margins">
                                {{ $user->fullname }}
                            </h2>
                            <h4>
                                {{ $user->nameRoleUser }}
                            </h4>
                            <p>
                                <b>Email:</b> {{ $user->email }}<br>
                                <b>Celular:</b> {{ $user->celular }}
                            </p>
                            <a href="{{ route('profile.editar') }}"> <button class="btn btn-primary btn-circle">Editar perfil <i class="fas fa-pencil-alt"></i></button></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
