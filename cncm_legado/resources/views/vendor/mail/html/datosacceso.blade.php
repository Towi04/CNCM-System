@component('mail::message')
# Hola {{$user->nombres}}

Se ha creado una cuenta para ti en <b>{{config('app.name')}}</b>:

@component('mail::table')
|                   |                   |
| ------------------|-------------------|
| **Correo**        | {{$user->email}}  |
| **Contraseña**    | {{$password}}     |
@endcomponent


@component('mail::button', ['url' => route('home')])
Ingresar al Sistema
@endcomponent

@endcomponent