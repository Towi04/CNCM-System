<!DOCTYPE html>
<html>

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{config('app.name')}}</title>

    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet">
    <link href="{{asset('css/bootstrap-glyphicons.min.css')}}" rel="stylesheet">
    <link href="{{asset('font-awesome/css/font-awesome.css')}}" rel="stylesheet">

    <link href="{{asset('css/animate.css')}}" rel="stylesheet">
    <link href="{{asset('css/style.css')}}" rel="stylesheet">
    <link href="{{ asset('icon.jpeg') }}" rel="shortcut icon">
    <link href="{{ asset('icon.jpeg') }}" rel="apple-touch-icon">

</head>

<body class="gray-bg">

    <div class="loginColumns animated fadeInDown">
        <div class="row">

            <div class="col-md-6 align-self-center">
               

                <center><img src="{{asset('img/logo.png') }}" class="img-fluid" width="60%" alt="{{config('app.name')}}" ></center>

            </div>
            <div class="col-md-6 align-self-center">
                <h2 class="font-bold">{{ __('Login') }}</h2>
                <div class="widget-body">
                        <form method="POST" action="{{ route('login') }}">
                                @csrf
                        <div class="form-group">
                            <input name="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" placeholder="{{ __('E-Mail Address') }}" required="" value="{{ old('email') }}" autofocus>
                            @if ($errors->has('email'))
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                @endif
                        </div>
                        <div class="form-group">
                            <input name="password" type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" placeholder="{{ __('Password') }}" required="">
                            @if ($errors->has('password'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('password') }}</strong>
                            </span>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-primary block full-width m-b">{{ __('Login') }}</button>

                        @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}">
                            <small>{{ __('Forgot Your Password?') }}</small>
                        </a>
                        @endif

                        {{-- <p class="text-muted text-center">
                            <small>Do not have an account?</small>
                        </p> --}}
                        {{-- <a class="btn btn-sm btn-white btn-block" href="register.html">Create an account</a> --}}
                    </form>
                    {{-- <p class="m-t">
                        <small>Inspinia we app framework base on Bootstrap 3 &copy; 2014</small>
                    </p> --}}
                </div>
            </div>
        </div>
        <hr/>
        <div class="row">
            <div class="col-md-6">
                Derechos reservados {{config('app.name')}}
            </div>
            <div class="col-md-6 text-right">
               <small>2019</small>
            </div>
        </div>
    </div>

</body>

</html>
