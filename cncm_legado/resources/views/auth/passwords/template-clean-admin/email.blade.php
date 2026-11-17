@extends('layouts.template-clean-admin.plantilla_passwords')

@section('titulo', __('Reset Password'))
@section('logotipo')
    <img src="{{asset('img/logo-negativo.png')}}">
@endsection

@section('form')
    <h3 class="mb-5 text-center text-primary">{{ __('Reset Password') }}</h3>

    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="form-group {{ $errors->has('email') ? ' has-error' : '' }}">
            <label for="email" class="text-muted">{{ __('E-Mail Address') }}</label>
            <input id="email"
                type="email"
                class="form-control form-control-line"
                name="email"
                value="{{ old('email') }}"
                required>

            @if ($errors->has('email'))
                <span class="help-block">
                    <strong class="text-danger">{{ $errors->first('email') }}</strong>
                </span>
            @endif
        </div>

        <div class="form-group mr-b-20">
            <button type="submit" class="btn btn-block btn-rounded btn-md btn-primary text-uppercase fw-600 ripple">
                {{ __('Send Password Reset Link') }}
            </button>
        </div>
    </form>
@endsection
