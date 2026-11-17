@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Editar Descuento <small></small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('descuentos.index') }}">Descuentos</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Editar Descuento</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::model($descuento, ['route' => ['descuentos.update', $descuento], 'method' => 'PUT', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                    <h5 class="form-header">
                        Llena el formulario
                    </h5>

                    @include('descuentos.partials._fields')

                    <div class="form-buttons-w text-right">
                        <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Guardar</button>
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>

    
    
@endsection


@section('scripts')

<link rel="stylesheet" href="{{ asset('plugins/xeditable/css/bootstrap-editable.css') }}">
<script src="{{ asset('plugins/xeditable/js/bootstrap-editable.min.js') }}"></script>

    <script src="{{ asset('template-clean-admin/bower_components/select2/dist/js/i18n/es.js') }}"></script>
    <script type="text/javascript">
        $(function(){
            

            $('.datepicker').datepicker({
                language: 'es',
                format: 'dd-mm-yyyy',
                ignoreReadonly: false,
                todayHighlight: true,
                todayBtn: true,
                minDate: new Date(),
            });

            options = {
                'emptytext':'Vacio',
                'onblur':'ignore',
                'showbuttons':false,
            }

          
        })
    </script>
@endsection
