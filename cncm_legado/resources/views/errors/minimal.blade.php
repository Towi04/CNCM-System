<!DOCTYPE html>
<html lang="es">
    <head>
        <title>@yield('title')</title>
        <meta charset="utf-8">
        <meta content="ie=edge" http-equiv="x-ua-compatible">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="{{ config('settings.company.autor') }}" name="author">
        <meta content="{{ config('settings.company.description') }}"  name="description">
        <link href="{{ asset('img/logo.png') }}" rel="shortcut icon">
        <link href="apple-touch-icon.png" rel="apple-touch-icon">
        <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700" rel="stylesheet" type="text/css">
        <link href="{{ asset('template-clean-admin/css/main.css?version=4.3.0') }}" rel="stylesheet">
        <script src="https://kit.fontawesome.com/1f556c46ab.js" crossorigin="anonymous"></script>
    </head>
    <body>

            <div class="content-box">
                <div class="big-error-w">
                    <div class="text-center pb-2">
                        <center>
                            <img src="{{ asset('img/logo.png') }}" style="width:250px;">
                        </center>
                    </div>
                <h1>
                    @yield('code')
                </h1>
                <h5>
                    @yield('message')
                </h5>


                    <div class="row">
                        <div class="col-md-12">
                        <a class="btn btn-primary btn-block" href="{{ app('router')->has('home') ? route('home') : url('/') }}">
                            <i class="btn-label fas fa-home"></i> {{ __('ir a Inicio') }}
                        </a>
                        </div>
                    </div>
                </div>

            </div>




        <script src="{{ asset('template-clean-admin/bower_components/jquery/dist/jquery.min.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/popper.js/dist/umd/popper.min.js')}}"></script>
        <script src="{{ asset('template-clean-admin/js/main.js?version=4.3.0') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/perfect-scrollbar/js/perfect-scrollbar.jquery.min.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/perfect-scrollbar/js/perfect-scrollbar.jquery.min.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/util.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/alert.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/button.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/carousel.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/collapse.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/dropdown.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/modal.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/tab.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/tooltip.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/popover.js') }}"></script>
        <script src="{{ asset('template-clean-admin/bower_components/bootstrap/js/dist/collapse.js') }}"></script>
    </body>
</html>
