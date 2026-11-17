@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')

@endsection

@section('breadcrumb')

    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Alumnos</strong>
        </li>
    </ol>

@endsection

@section('contenido')

   <div class="row m-4">
       <div class="col-16">
           <div class="element-box">
               <div class="element-header">Alumnos con inicio de grupo muy antes (30 días) que la fecha de su primer pago</div>
                {!! $table !!}
           </div>
           <div class="element-box">
            <div class="element-header">Alumnos con inicio de grupo muy despues (30 días) que la fecha de su primer pago</div>
            {!! $table2 !!}
       </div>
       </div>
   </div>

@endsection

@section('scripts') 
<link rel="stylesheet" href="{{ asset('plugins/xeditable/css/bootstrap-editable.css') }}">
<script src="{{ asset('plugins/xeditable/js/bootstrap-editable.min.js') }}"></script>

    <script type="text/javascript">
        $('.editable_fecha_inicio_grupo').editable({
            emptytext: 'Vacio',
            onblur: 'ignore',
        });

    </script>

@endsection
