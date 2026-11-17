<style>
   @media(max-width:1025px) {
        .d-lg-block {
            display: none !important;
        }
    }
</style>

<ul class="main-menu no_print">
    <li class="sub-header d-none d-sm-none d-md-none d-lg-block d-xl-block">
      <span >Menú principal</span>
    </li>

    @canany(['gestionar_usuarios','gestionar_roles','gestionar_permisos','gestionar_sucursales','listar_productos'])
        <li class="selected has-sub-menu">
            <a href="#">
                <div class="icon-w">
                    <div class="fa fa-cogs"></div>
                </div>
                <span>Administración</span>
            </a>
            <div class="sub-menu-w">
                <div class="sub-menu-header d-none d-sm-none d-md-none d-lg-block d-xl-block">
                    Administración
                </div>
                <div class="sub-menu-i">
                    <ul class="sub-menu">
                        @can(['gestionar_configuraciones'])
                            <li>
                                <a href="{{ route('configuraciones.index') }}">Configuraciones</a>
                            </li>
                        @endcan

                        @can(['gestionar_usuarios'])
                            <li>
                                <a href="{{ route('admin.usuarios.index') }}">Usuarios</a>
                            </li>
                        @endcan

                        @can(['gestionar_roles'])
                            <li>
                                <a href="{{ route('admin.roles.index') }}">Roles</a>
                            </li>
                        @endcan

                        @can(['gestionar_permisos'])
                            <li>
                                <a href="{{ route('admin.permisos.index') }}">Permisos</a>
                            </li>
                        @endcan

                        @can(['gestionar_sucursales'])
                            <li>
                                <a href="{{ route('admin.sucursales.index') }}">Sucursal</a>
                            </li>
                        @endcan
                    </ul>
                    <ul class="sub-menu">
                        @can(['listar_especialidades'])
                            <li>
                                <a href="{{ route('admin.especialidades.index') }}">Especialidades</a>
                            </li>
                        @endcan

                        @can(['listar_cuentas_bancarias'])
                            <li>
                                <a href="{{ route('admin.cuentas-bancarias.index') }}">Cuentas Bancarias</a>
                            </li>
                        @endcan

                        @can(['listar_productos'])
                            <li>
                                <a href="{{ route('admin.productos.index') }}">Productos</a>
                            </li>
                        @endcan
                        @can(['ver_horarios_profesores'])
                        <li>
                            <a href="{{ route('admin.horarios-profesores.index') }}">Horarios Profesores</a>
                        </li>
                        @endcan
                        @can(['gestionar_descuentos'])
                        <li>
                            <a href="{{ route('descuentos.index') }}">Descuentos</a>
                        </li>
                        @endcan

                    </ul>
                </div>
            </div>
        </li>
    @endcanany

    @canany(['consultar_preregistros_alumnos','realizar_pre_registro','convertir_pre_registro_alumno'])
        <li class="">
            <a href="{{ route('pre-registro-alumnos.index') }}">
                <div class="icon-w">
                    <div class="fa fa-bookmark"></div>
                </div>
                <span>Pre-Registro Alumnos</span></a>
        </li>
    @endcanany

    @can('listar_alumnos')
        <li class="">
            <a href="{{ route('alumnos.index') }}">
                <div class="icon-w">
                <div class="fa fa-user"></div>
                </div>
                <span>Alumnos</span></a>
        </li>
    @endcan

    @can('registrar_asistencias')
        <li class="">
            <a href="{{ route('asistencias.index') }}">
                <div class="icon-w">
                <div class="fas fa-check"></div>
                </div>
                <span>Registrar asistencias</span></a>
        </li>
    @endcan

    @can('listar_materias')
        <li class="selected has-sub-menu">
            <a href="#">
                <div class="icon-w">
                    <div class="fa fa-cogs"></div>
                </div>
                <span>Especialidades</span>
            </a>
            <div class="sub-menu-w">
                <div class="sub-menu-header d-none d-sm-none d-md-none d-lg-block d-xl-block">
                    Especialidades
                </div>
                <div class="sub-menu-i">
                        @php
                            $especialidades = \App\Models\Especialidad::get();
                            $i=0;
                        @endphp
                        @foreach($especialidades as $especialidad)
                            @if($i==0)
                                <ul class="sub-menu">
                            @endif
                                <li>
                                    <a href="{{ route('materias.index', $especialidad->id) }}">{{$especialidad->nombre}}</a>
                                </li>
                                @if($i==7)
                                    </ul>
                                @php
                                    $i=-1;
                                @endphp
                                @endif
                                    @php
                                        $i=$i+1;
                                    @endphp
                        @endforeach
                </div>
            </div>
        </li>
    @endcan

    @canany(['listar_grupos','ver_todos_grupos'])
        <li class="">
            <a href="{{ route('grupos.index') }}">
                <div class="icon-w">
                <div class="fa fa-users"></div>
                </div>
                <span>Grupos</span></a>
        </li>
    @endcanany

    @can('ingresar_punto_venta')
        <li class="">
            <a href="{{ route('punto_de_venta.index') }}">
                <div class="icon-w">
                <div class="fas fa-cash-register"></div>
                </div>
                <span>Punto de venta</span></a>
        </li>
    @endcan

    @can('punto_de_venta_productos')
        <li class="">
            <a href="{{ route('punto_de_venta_productos.index') }}">
                <div class="icon-w">
                <div class="fas fa-cash-register"></div>
                </div>
                <span>Venta de productos</span></a>
        </li>
    @endcan

    <li class="selected has-sub-menu">
        <a href="#">
            <div class="icon-w">
                <div class="fa fa-cogs"></div>
            </div>
            <span>Reportes</span>
        </a>
        <div class="sub-menu-w">
            <div class="sub-menu-header d-none d-sm-none d-md-none d-lg-block d-xl-block">
                Reportes
            </div>
            <div class="sub-menu-i">
                <ul class="sub-menu">
                    @can('ver_reporte_ventas')
                    <li>
                        <a href="{{ route('reportes.reporte-ventas.index') }}">Reporte de Ventas</a>
                    </li>
                    @endcan
                    @can('ver_reporte_ventas_productos')
                    <li>
                        <a href="{{ route('reportes.reporte-ventas.index_productos') }}">Reporte de Ventas (Productos)</a>
                    </li>
                    @endcan
                    @can('ver_reporte_vencimiento')
                    <li>
                        <a href="{{ route('reportes.reporte-ventas.vencimientos') }}">Reporte de Vencimientos</a>
                    </li>
                    @endcan
                    @can('ver_reporte_proyeccion')
                    <li>
                        <a href="{{ route('reportes.reporte-ventas.proyeccion') }}">Reporte de Proyección</a>
                    </li>
                    @endcan
                    @can('ver_reporte_asesores')
                    <li>
                        <a href="{{ route('reportes.reporte-ventas.asesores') }}">Reporte de Asesores</a>
                    </li>
                    @endcan
                    @can('ver_reporte_asistencias_personal')
                    <li>
                        <a href="{{ route('reportes.asistencias_personal') }}">Reporte de Asistencias Personal</a>
                    </li>
                    @endcan
                    @can('ver_alertas')
                    <li>
                        <a href="{{ route('reportes.alertas') }}">Reporte de Alertas</a>
                    </li>
                    @endcan
                </ul>
                <ul class="sub-menu">
                    
                    
                    @can('ver_reporte_desercion')
                    <li>
                        <a href="{{ route('reportes.desercion') }}">Reporte de Retención</a>
                    </li>
                    @endcan
                    @can('ver_reporte_apoyos_inscripcion')
                    <li>
                        <a href="{{ route('reportes.apoyos_inscripcion') }}">Reporte de Apoyos a la inscripción</a>
                    </li>
                    @endcan
                    @can('ver_reporte_recomendados')
                    <li>
                        <a href="{{ route('reportes.recomendados') }}">Reporte de Recomendados</a>
                    </li>
                    @endcan
                    @can('ver_reporte_programados')
                    <li>
                        <a href="{{ route('reportes.programados') }}">Reporte de Programados</a>
                    </li>
                    @endcan
                    @can('ver_reporte_pagos_eliminados')
                    <li>
                        <a href="{{ route('reportes.pagos_eliminados') }}">Reporte de Pagos Eliminados</a>
                    </li>
                    @endcan
                    @can('ver_reporte_asesores')
                    <li>
                        <a href="{{ route('reportes.inscritos') }}">Reporte de Inscritos</a>
                    </li>
                    @endcan
                    
                </ul>
            </div>
        </div>
    </li>

    @can('entrar_calendario')
        <li class="">
            <a href="{{ route('agendar-asesoria.index') }}">
                <div class="icon-w">
                <div class="os-icon os-icon-calendar"></div>
                </div>
                <span>Calendario asesorias</span></a>
        </li>
    @endcan

    <li class="sub-header d-none d-sm-none d-md-none d-lg-block d-xl-block">
        <span>Opciones</span>
    </li>

    <li class="">
        <a href="{{ route('soporte.index') }}">
            <div class="icon-w">
            <div class="fa fa-headphones"></div>
            </div>
            <span>Soporte Técnico</span></a>
    </li>

    <li class=" ">
        <a href="{{ route('logout') }}" onclick="event.preventDefault();document.getElementById('logout-form').submit();">
        <div class="icon-w">
            <div class="os-icon os-icon-signs-11"></div>
        </div>
        <span>Cerrar Sesión</span></a>
    </li>
</ul>
