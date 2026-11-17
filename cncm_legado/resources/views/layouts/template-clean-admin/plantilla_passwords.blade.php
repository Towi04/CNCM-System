<!DOCTYPE html>
<html lang="es">
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js"></script>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <link href="{{ asset('img/logo.png') }}" rel="shortcut icon">
    <title>{{ config('app.name' )}} | @yield('titulo')</title>

    <!-- CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600" rel="stylesheet" type="text/css">
    <link href="{{ asset('template-kineticpro-horizontal/assets/vendors/material-icons/material-icons.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('template-kineticpro-horizontal/assets/vendors/mono-social-icons/monosocialiconsfont.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('template-kineticpro-horizontal/assets/vendors/feather-icons/feather.css') }}" rel="stylesheet" type="text/css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/1.4.0/css/perfect-scrollbar.min.css" rel="stylesheet" type="text/css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.css" rel="stylesheet" type="text/css">
    <link href="{{ asset('template-kineticpro-horizontal/assets/css/style.css') }}" rel="stylesheet" type="text/css">
    <!-- Head Libs -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/modernizr/2.8.3/modernizr.min.js"></script>
    <script src="https://kit.fontawesome.com/1f556c46ab.js"></script>
</head>

<body class="body-bg-full profile-page">
    <div id="wrapper" class="wrapper">
        <div class="row container-min-full-height">

            {{-- logotipo --}}
            <div class="col-lg-6 login-right d-lg-flex d-none pos-fixed pos-right text-inverse container-min-full-height" >
                <div class="login-content px-3 w-75 text-center">
                    @yield('logotipo')
                </div>
            </div>
            <div class="col-lg-6 login-left">
                <div class="w-75">
                    @yield('form')
                </div>
            </div>
            <!-- /.login-right -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.wrapper -->
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>


    <script src="{{ asset('template-kineticpro-horizontal/assets/js/material-design.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.js"></script>
</body>
</html>

