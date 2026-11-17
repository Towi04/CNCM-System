@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Detalle del Pre-registro del alumno <small></small>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('pre-registro-alumnos.index') }}">Pre-Registro Alumnos</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Detalle Pre-registro</strong>
        </li>
    </ol>
@endsection

@section('contenido')

<style>
.contact-box:hover {
    transform: scale(1.05)
}

.borderless td,
.borderless th {
    border: none;
}

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

.content-box {
    padding: 0px !important;
}

</style>

<div class="row p-3">
    <div class="col-5 col-lg-5 col-sm-5 col-md-5 col-xs-12">
        <div class="user-profile compact">
            <div class="up-head-w"
                style="background-image: linear-gradient( rgb(24,41,72,0.9), 70%, rgb(24,41,72,0.9));">

                <div class="up-main-info " style="padding-bottom: 150px; padding-top:100px">
                    <h2 class="up-header">
                        Alumno
                    </h2>
                    <h6 class="up-sub-header">
                        {{ $alumno->nombres }} {{ $alumno->apellido_paterno }} {{ $alumno->apellido_materno }}
                    </h6>
                </div>
                <svg class="decor" width="842px" height="219px" viewBox="0 0 842 219"
                    preserveAspectRatio="xMaxYMax meet" version="1.1" xmlns="http://www.w3.org/2000/svg"
                    xmlns:xlink="http://www.w3.org/1999/xlink">
                    <g transform="translate(-381.000000, -362.000000)" fill="#FFFFFF">
                        <path class="decor-path"
                            d="M1223,362 L1223,581 L381,581 C868.912802,575.666667 1149.57947,502.666667 1223,362 Z">
                        </path>
                    </g>
                </svg>
            </div>

            <div class="up-controls">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="value-pair">
                            {{-- <div class="label">
                                Status:
                            </div>
                            <div class="value badge badge-pill badge-{{ $cliente->statusClass }}">
                                {{ $cliente->status }}
                            </div> --}}
                        </div>
                    </div>
                    <div class="col-sm-6 text-right">
                    </div>
                </div>
            </div>

            <div class="up-contents">
                <div class="m-b">
                    <div class="row m-b">
                        <div class="col-sm-12 b-b">

                        </div>
                    </div>
                    <div class="p-2">
                        @can(['editar_alumno'])
                            <a href="{{ route('alumnos.edit', $alumno) }}"
                                class="btn btn-info btn-sm btn-circle float-right text-white mb-2" data-toggle="tooltip"
                                data-placement="left" title="Editar informacion">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-7 col-lg-7 col-sm-7 col-md-7 col-xs-12">

        <div class="row">
            <div class="col-sm-12 col-xxxl-9">
                <div class="element-wrapper">
                    <div class="element-box">
                        <div class="os-tabs-w">
                            <div class="os-tabs-controls">
                                <ul class="nav nav-tabs smaller">

                                    <li class="nav-item">
                                        <a class="nav-link active" data-toggle="tab" href="#tab-info-alumno">Información del alumno</a>
                                    </li>

                                </ul>
                                <ul class="nav nav-pills smaller d-none d-md-flex">
                                </ul>
                            </div>

                            <div class="tab-content">
                                <div class="tab-pane active" id="tab-info-alumno">
                                    @include('alumnos.partials._info_alumno')
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
    <script type="text/javascript">
        // $(document).ready(function() {
        // });
    </script>
@endsection
