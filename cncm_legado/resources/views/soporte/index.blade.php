@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo', 'Soporte Técnico')

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a>Soporte Técnico</a>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row white-bg dashboard-header p-3">
        <div class="col-md-12">
            <center>
                <a href="{{ config('settings.company.web') }}" target="_blank">
                    <img src="{{ asset('img/logo_adn.png') }}" width="30%" alt="Logotipo Corporativo">
                </a>
                <h2>¡Nuestro equipo de soporte esta para ayudarte!</h2>
            </center>
        </div>
        <div class="w-100 m-3"></div>
        <div class="col-md-8">
            <h4>Si tienes algún problema, dejános un mensaje:</h4>
            {!! Form::open(['route' => 'soporte.enviar-correo', 'method' => 'POST','onsubmit'=>'wait.modal("show")']) !!}
            <div class="form-group">
                {{ Form::label('nombre', 'Tu nombre') }}
                {{ Form::text('nombre', Auth::user()->nombres, ['placeholder' => 'Escribe aqui tu nombre', 'class' => 'form-control', 'required' => 'required']) }}
            </div>
            <div class="form-group">
                {{ Form::label('email', 'Tu correo') }}
                {{ Form::text('email', Auth::user()->email, ['placeholder' => 'Escribe aqui tu correo para ponernos en contacto contigo', 'class' => 'form-control', 'required' => 'required']) }}
            </div>
            <div class="form-group">
                {{ Form::label('mensaje', 'Tu mensaje') }}
                {{ Form::textarea('mensaje', null, ['placeholder' => 'Escribe aqui tu mensaje', 'class' => 'form-control', 'required' => 'required']) }}
            </div>
            <div>
                <button class="btn btn-sm btn-primary float-right m-t-n-xs" type="submit"><strong>Enviar</strong></button>
            </div>
            {!! Form::close() !!}
        </div>
        <div class="col-md-4">

            <center>
                <h2>Ó llamanos al: </h2>
            </center>

            <center>
                <h2 class="text-navy"><i class="fas fa-mobile-alt"></i> {{ config('settings.company.telefono') }}
                    <br><small style="color: #061E44"> Menciona que hablas de: {{ config('app.name') }}</small></h2>
            </center>

            <img src="{{ asset('img/soporte.png') }}" alt="Soporte Técnico" class="img-fluid">
        </div>
    </div>

@endsection
