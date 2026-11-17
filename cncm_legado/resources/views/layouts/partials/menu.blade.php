<nav class="navbar-default navbar-static-side" role="navigation">
        <div class="sidebar-collapse">
            <ul class="nav metismenu" id="side-menu">
                <li class="nav-header">
                    <div class="dropdown profile-element">
                        {{-- <img alt="image" class="rounded-circle" src="img/profile_small.jpg"/> --}}
                        <a href="{{url('/')}}">
                            <center><img src="{{asset('img/logo-negativo.png')}}" alt="{{config('app.name')}}" class="img-fluid" width="50%"></center>
                        </a>
                        {{-- <ul class="dropdown-menu animated fadeInRight m-t-xs">
                            <li><a class="dropdown-item" href="profile.html">Profile</a></li>
                            <li><a class="dropdown-item" href="contacts.html">Contacts</a></li>
                            <li><a class="dropdown-item" href="mailbox.html">Mailbox</a></li>
                            <li class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="login.html">Logout</a></li>
                        </ul> --}}
                    </div>
                    <div class="logo-element">
                        {{config('app.name')}}
                    </div>
                </li>
                @role('administrador')
                <li class="{{UrlActive::active('admin/*', 'active')}}">
                    <a href=""><i class="fa fa-th-large"></i> <span class="nav-label">Administración</span> <span class="fa arrow"></span></a>
                    <ul class="nav nav-second-level">
                        <li class="{{UrlActive::active('admin/users/*', 'active')}}"><a href="{{route('admin.usuarios.index')}}">Usuarios</a></li>
                        {{-- <li class="{{UrlActive::active('admin/areas/*', 'active')}}"><a href="{{route('admin.areas.index')}}">Áreas de eventos</a></li>
                        <li class="{{UrlActive::active('admin/conceptos_areas/*', 'active')}}"><a href="{{route('admin.conceptos_areas.index')}}">Conceptos cotizaciones</a></li>  --}}

                    </ul>
                </li>
                @endrole

                @role(['administrador'])
                <li class="{{UrlActive::active('clientes', 'active')}}{{UrlActive::active('clientes/*', 'active')}}">
                    <a href="{{route('clientes.index')}}"><i class="fas fa-address-book"></i> <span class="nav-label">Clientes</span></a>
                </li>

                <li class="{{UrlActive::active('seguimiento', 'active')}}{{UrlActive::active('seguimiento/*', 'active')}}">
                    <a href="{{route('seguimiento.index')}}"><i class="fas fa-user-friends"></i> <span class="nav-label">Seguimiento</span></a>
                </li>

                @endrole
                @role(['administrador'])
                {{-- <li class="{{UrlActive::active('salones', 'active')}}{{UrlActive::active('salones/*', 'active')}}">
                    <a href="{{route('salones.index')}}"><i class="fas fa-place-of-worship"></i> <span class="nav-label">Salones</span></a>
                </li> --}}
                @endrole
                {{-- <li class="{{UrlActive::active('eventos', 'active')}}{{UrlActive::active('eventos/*', 'active')}}{{UrlActive::active('evento/*', 'active')}}">
                    <a href=""><i class="fas fa-calendar-alt"></i> <span class="nav-label">Eventos</span><span class="fa arrow"></span></a>
                    <ul class="nav nav-second-level">
                            <li class="{{UrlActive::active('admin/eventos/*', 'active')}}"><a href="{{route('eventos.index')}}">Calendario</a></li>
                            <li class="{{UrlActive::active('evento/seguimiento', 'active')}}"><a href="{{route('eventos.seguimiento')}}">Seguimiento</a></li>
                    </ul>
                </li> --}}
                @role(['administrador'])
                {{-- <li class="{{UrlActive::active('reportes', 'active')}}{{UrlActive::active('reportes/*', 'active')}}">
                    <a href="{{route('reportes.index')}}"><i class="fas fa-chart-pie"></i> <span class="nav-label">Reportes</span></a>
                </li> --}}
                @endrole
                <li class="{{UrlActive::active('soporte_tecnico', 'active')}}">
                    <a href="{{route('soporte')}}"><i class="fa fa-headset"></i> <span class="nav-label">Soporte</span></a>
                </li>

                <li class="{{UrlActive::active('videotutoriales/*', 'active')}}{{UrlActive::active('videotutoriales', 'active')}}">
                    <a href="{{route('videotutoriales.index')}}"><i class="fa fa-video"></i> <span class="nav-label">Video Tutoriales </span></a>
                </li>
                <li>
                        <a href="{{ route('logout') }}"
                        onclick="event.preventDefault();
                                      document.getElementById('logout-form').submit();"><i class="fas fa-sign-out-alt"></i>
                            {{ __('Logout') }}
                        </a>
                </li>

            </ul>

        </div>
    </nav>
