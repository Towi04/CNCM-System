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
            <a>Usuarios</a>
        </li>
    </ol>
@endsection

@section('contenido')
    <style>
        .contact-box:hover {
            transform: scale(1.05)
        }

        .activity-boxes-w .activity-box:before {
            position: absolute;
            top: 50%;
            left: -30px;
            content: "";
            width: 12px;
            height: 12px;
            border: 0px solid #60769f;
            background-color: #f2f4f8;
            border-radius: 20px;
            -webkit-transform: translateY(-50%);
            transform: translateY(-50%);
            z-index: 2;
        }

        .activity-title,
        .activity-role {
            text-transform: none !important;
        }

    </style>

    <div class="row justify-content-end pt-4 pr-5 ">
        <div class="col-md-2 ">
            <a href={{ route('admin.usuarios.create') }}>
                <button class="btn btn-success dim" type="button"><i class="fa fa-plus"></i> Agregar usuario</button>
            </a>
        </div>
    </div>

    <div class="row pb-5 pr-5 pl-5 activity-boxes-w">
        @foreach ($users as $user)
            <div class="col-lg-4 align-self-end mt-2" id="usuario-{{ $user->id }}">
                <div class="activity-box">
                    <div class="activity-avatar">
                        <img alt="" src="{{ url('archivo/usuarios_foto/') }}/{{ $user->id }}/{{ $user->foto }}">
                    </div>
                    <div class="activity-info">
                        <div class="activity-title">
                            <strong> {{ $user->fullname }}</strong><br>
                            {{ $user->email }}
                        </div>
                        <div class="activity-role" style="max-height: 100px; min-height: 50px; overflow-y:scroll">
                            Roles: {{ $user->nameRoleUser }}<br>
                        
                            Sucursales: {{ $user->sucursalesUser }}
                        </div>
                        <strong class="activity-title">
                            <a href="{{ route('admin.usuarios.show', $user->id) }}" class="btn btn-primary btn-sm mb-1"
                                data-toggle="tooltip" data-placement="top" data-original-title="Ver perfil"><i
                                    class="fas fa-search"></i></a>

                            <a href="{{ route('admin.usuarios.edit', $user->id) }}" data-toggle="tooltip"
                                data-placement="top" data-original-title="Editar usuario"
                                class="btn btn-success btn-sm mb-1"><i class="fas fa-pencil-alt"></i></a>

                            <a data-toggle="modal" data-target="#modalBorrar{{ $user->id }}"
                                class="btn btn-sm mb-1 btn-danger" style="color:white" data-toggle="tooltip"
                                data-placement="top" data-original-title="Enviar correo electrónico"><i
                                    class="fas fa-trash"></i></a>
                        </strong>
                    </div>
                </div>
            </div>
        @endforeach

        @foreach ($users as $user)
            <div class="onboarding-modal modal fade animated" id="modalBorrar{{ $user->id }}" tabindex="-1"
                role="dialog" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content animated bounceInRight">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span
                                    aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                            <i class="fas fa-exclamation-circle modal-icon"></i>
                            <h4 class="modal-title">¿Esta seguro que quiere borrar a {{ $user->fullname }}?</h4>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button>
                            <button type="button" data-id="{{ $user->id }}" class="btn btn-danger btn-delete"
                                data-dismiss="modal">Borrar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {!! Form::open(['route' => ['admin.usuarios.destroy', 'USER_ID'], 'method' => 'DELETE', 'role' => 'form', 'id' =>
        'form-delete']) !!}
        {!! Form::close() !!}
    </div>
@endsection

@section('scripts')
    <script type="text/javascript">
        $(function() {
            $('.btn-delete').click(function() {
                var id = $(this).data('id');
                var form = $('#form-delete');
                var action = form.attr('action').replace('USER_ID', id);
                var row = $('#usuario-' + id);

                row.fadeOut(1000);

                $.post(action, form.serialize(), function(result) {
                    if (result.success) {
                        toastr.success('Éxito', 'Se borró con éxito el usuario');
                    } else {
                        row.show();
                    }
                }, 'json');
            });
        })

    </script>
@endsection
