@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Editar Especialidad <small></small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('admin.especialidades.index') }}">Especialidades</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Editar Especialidad</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row">
        <div class="col-md-12">
            <div class="element-box">
                {!! Form::model($especialidad, ['route' => ['admin.especialidades.update', $especialidad], 'method' => 'PUT', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                    <h5 class="form-header">
                        Llena el formulario
                    </h5>

                    @include('admin.especialidades.partials._fields')

                    <div class="form-buttons-w text-right">
                        <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Guardar</button>
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-4">
            <div class="element-box">
                {!! Form::open(['route' => 'admin.especialidades.guardar_precio', 'method' => 'POST', 'accept-charset' => 'UTF-8', 'enctype' => 'multipart/form-data','onsubmit' => "wait.modal('show')"]) !!}
                
                <h5 class="form-header">
                    Registro de precios
                </h5>

                @include('admin.especialidades.partials._fields_precios')

                <div class="form-buttons-w text-right">
                    <button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> Guardar cambios</button>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
        <div class="col-8">
            <div class="element-box">
        
                <h5 class="form-header">
                    Historico de precios
                </h5>
                @php
                    $precios = $especialidad->precios;
                @endphp

                @include('admin.especialidades.partials._historico_precios')

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
            const dom = {
                select2_cordinador: $("#select2_cordinador")
            }

            dom.select2_cordinador.select2({
                language: "es",
                placeholder:'Selecciona un profesor',
                ajax: {
                    method: 'POST',
                    data:
                    function (params) {
                        return {
                            term: params.term,
                            page: params.page || 1,
                            _token: '{{ csrf_token() }}'
                        }
                    },
                    url: '{{ route("admin.usuarios.traer_usuarios_select2") }}',
                    dataType: 'json',
                    cache: false,
                    delay:250,
                    beforeSend:function(xhr,type){
                        xhr.setRequestHeader('X-CSRF-Token',$('meta[name="csrf-token"]').attr('content'))
                    }
                },
                escapeMarkup: function (markup) { return markup; },
                minimumInputLength: 3,
                templateResult: function(option){
                    if (option.loading) {
                        return option.text;
                    }

                    if(!option.nombres || !option.apellido_paterno || !option.apellido_materno){
                        return option.text
                    }

                    return `${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                },
                templateSelection:function(option){
                    if(!option.nombres || !option.apellido_paterno || !option.apellido_materno){
                        return option.text
                    }

                    return `${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                }
            });

            @if($especialidad->cordinadores()->exists())
                dom.select2_cordinador.empty();

                @foreach ($especialidad->cordinadores as $cordinador)
                    dom.select2_cordinador.append(
                        new Option("{{ $cordinador->full_name }}", "{{ $cordinador->id }}", true,true)
                    );
                @endforeach

                dom.select2_cordinador.trigger('change')
            @endif

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

            $('.editable_fecha').editable(options)

        })
    </script>
@endsection
