<!DOCTYPE html>
<html lang="es">

<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js"></script>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <link href="{{ asset('icon.jpeg') }}" rel="shortcut icon">
    <link href="{{ asset('icon.jpeg') }}" rel="apple-touch-icon">
    <title>{{ config('app.name') }}</title>
    <!-- CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600" rel="stylesheet" type="text/css">
    <link href="{{ asset('template-kineticpro-horizontal/assets/vendors/material-icons/material-icons.css') }}"
        rel="stylesheet" type="text/css">
    <link href="{{ asset('template-kineticpro-horizontal/assets/vendors/mono-social-icons/monosocialiconsfont.css') }}"
        rel="stylesheet" type="text/css">
    <link href="{{ asset('template-kineticpro-horizontal/assets/vendors/feather-icons/feather.css') }}" rel="stylesheet"
        type="text/css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/1.4.0/css/perfect-scrollbar.min.css"
        rel="stylesheet" type="text/css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.css" rel="stylesheet"
        type="text/css">
    <link href="{{ asset('template-kineticpro-horizontal/assets/css/style.css') }}" rel="stylesheet" type="text/css">
    <!-- Head Libs -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/modernizr/2.8.3/modernizr.min.js"></script>
    <script src="https://kit.fontawesome.com/1f556c46ab.js"></script>
</head>

<body class="body-bg-full profile-page">
    <div id="wrapper" class="wrapper">
        <div class="row container-min-full-height">
            <div
                class="col-lg-6 login-right d-lg-flex d-none pos-fixed pos-right text-inverse container-min-full-height">
                <div class="login-content px-3 w-75 text-center">
                    <img src="{{ asset('img/logo-negativo.png') }}">
                </div>
            </div>
            <div class="col-lg-6 login-left" style="background-color:#182948">
                <div class="w-75">
                    <h3 class="mb-4 text-center text-white">¡Bienvenido!</h3>
                    <form class="text-center" method="POST" action="{{ route('login') }}">
                        {{ csrf_field() }}
                        <div class="form-group {{ $errors->has('email') ? ' has-error' : '' }}">
                            <label class="text-muted" for="email">Correo Electrónico</label>
                            <input type="email" class="form-control form-control-line text-white" id="email"
                                name="email" value="{{ old('email') }}" required autofocus>
                            @if ($errors->has('email'))
                                <span class="help-block">
                                    <strong class="text-danger">{{ $errors->first('email') }}</strong>
                                </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label class="text-muted" for="password">Contraseña</label>
                            <input type="password" class="form-control form-control-line text-white" id="password"
                                name="password" required>
                            @if ($errors->has('password'))
                                <span class="help-block">
                                    <strong class="text-danger">{{ $errors->first('password') }}</strong>
                                </span>
                            @endif
                        </div>
                        <div class="form-group no-gutters mb-5 text-center">
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" id="to-recover" class="text-muted fw-700 text-uppercase heading-font-family fs-12">
                                    {{ __('Forgot Your Password?') }}
                                </a>
                            @endif
                        </div>
                        <div class="form-group mr-b-20">
                            <button class="btn btn-block btn-rounded btn-md btn-secondary text-uppercase fw-600 ripple"
                                type="submit">Iniciar sesión</button>
                        </div>
                    </form>
                </div>
                <!-- /.w-75 -->
            </div>

            <!-- /.login-right -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.wrapper -->
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl"crossorigin="anonymous"></script>
    <script src="{{ asset('template-kineticpro-horizontal/assets/js/material-design.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.js"></script>

</body>

</html>
