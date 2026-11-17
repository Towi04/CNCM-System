@component('mail::message')
# Hola {{ $usuario->fullname }}

Asignacion de asesoria

@component('mail::button', ['url' => route('asesorias.calendario-profesor.index'), 'color' => 'primary'])
    Ver asesoria
@endcomponent


Atentamente:

{{ config('app.name') }}
@endcomponent
