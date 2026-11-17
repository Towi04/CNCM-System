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
                <li class="{{UrlActive::active('admin/*', 'active')}}">
                    <a target="_blank" href="http://www.rancholafragua.com.mx"><i class="fa fa-th-large"></i> <span class="nav-label">Página web</span> </a>
                </li>
                <li class="{{UrlActive::active('clientes', 'active')}}{{UrlActive::active('clientes/*', 'active')}}{{UrlActive::active('cotizaciones/*', 'active')}}">
                        <a target="_blank" href="http://www.rancholafragua.com.mx/promociones.html"><i class="fas fa-address-book"></i> <span class="nav-label">Promociones</span></a>
                </li>
                <li class="{{UrlActive::active('salones', 'active')}}{{UrlActive::active('salones/*', 'active')}}">
                    <a target="_blank" href="http://www.rancholafragua.com.mx/ubicacioacuten.html"><i class="fas fa-place-of-worship"></i> <span class="nav-label">Ubicación</span></a>
                </li>
               
            </ul>

        </div>
    </nav>
