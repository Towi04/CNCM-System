@extends('layouts.template-clean-admin.plantilla_passwords')

@section('titulo', __('Reset Password'))

@section('logotipo')
    <img src="{{asset('img/logo-negativo.png')}}">
@endsection

@section('form')
    <h3 class="mb-5 text-center">{{ __('Reset Password') }}</h3>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group {{ $errors->has('email') ? ' has-error' : '' }}">
            <label for="email" class="text-muted">{{ __('E-Mail Address') }}</label>

            <input id="email"
                type="email"
                class="form-control form-control-line"
                name="email"
                value="{{ $email ?? old('email') }}"
                required
                autofocus>

            @if ($errors->has('email'))
                <span class="help-block">
                    <strong class="text-danger">{{ $errors->first('email') }}</strong>
                </span>
            @endif
        </div>

        <div class="form-group {{ $errors->has('password') ? ' has-error' : '' }}">
            <label for="password" class="text-muted">{{ __('Password') }}</label>
            <input id="password"
                type="password"
                class="form-control form-control-line"
                name="password"
                required>

            @if ($errors->has('password'))
                <span class="help-block">
                    <strong class="text-danger">{{ $errors->first('password') }}</strong>
                </span>
            @endif
        </div>

        <div class="form-group">
            <label for="password-confirm" class="text-muted">{{ __('Confirm Password') }}</label>
            <input id="password-confirm"
                type="password"
                class="form-control form-control-line"
                name="password_confirmation"
                required>
        </div>

        <div class="form-group mr-b-20">
            <button type="submit" class="btn btn-block btn-rounded btn-md btn-primary text-uppercase fw-600 ripple">
                {{ __('Reset Password') }}
            </button>
        </div>
    </form>
@endsection
