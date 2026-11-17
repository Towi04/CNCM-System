@extends('layouts.plantilla')

@section('titulo')
    Usuarios
@endsection

@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('admin.usuarios.index') }}">Usuarios</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>{{ $user->fullname }}</strong>
        </li>
    </ol>
@endsection

@section('contenido')
    <div class="row m-b-lg m-t-lg">
        <div class="col-md-6">

            <div class="profile-image">
                <img src="{{ url('archivo/usuarios_foto/') }}/{{ $user->id }}/{{ $user->foto }}"
                    class="rounded-circle circle-border m-b-md" alt="profile">
            </div>
            <div class="profile-info">
                <div class="">
                    <div>
                        <h2 class="no-margins">
                            {{ $user->fullname }}
                        </h2>
                        <h4>
                            @foreach ($user->roles as $role)
                                {{ $role->display_name }}@if (!$loop->last),@endif
                            @endforeach
                        </h4>
                        <a href="{{ route('admin.usuarios.edit', $user->id) }}"> <button class="btn btn-info btn-circle"><i
                                    class="fas fa-pencil-alt"></i></button></a>
                        <a href=""> <button class="btn btn-danger btn-circle"><i class="fas fa-trash-alt"></i></button></a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <table class="table small m-b-xs">
                <tbody>
                    <tr>
                        <td>
                            <strong>142</strong> clientes
                        </td>
                        <td>
                            <strong>22</strong> Ventas
                        </td>

                    </tr>
                    <tr>
                        <td>
                            <strong>61</strong> Otra cosa
                        </td>
                        <td>
                            <strong>54</strong> Otra cosa
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong>154</strong> Otra cosa
                        </td>
                        <td>
                            <strong>32</strong> Otra cosa
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="col-md-3">
            <small>Ventas del último mes</small>
            <h2 class="no-margins">$ 206,480.00</h2>
            {{-- <div id="sparkline1"></div> --}}
        </div>


    </div>

@endsection
