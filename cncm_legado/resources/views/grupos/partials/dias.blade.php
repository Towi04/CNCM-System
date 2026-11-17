@foreach($dias_semana as $dia => $display_dia)
<div class="row">
    <div class="col-md-6 align-self-center">
    {!! Form::checkbox('dia[]', $dia, null, ['class'=>'form-control checkbox_dia','data-dia'=>$dia]) !!} {{$display_dia}} 
    </div>
    <div class="col-md-3 horas_{{$dia}}">
        <label for="">Inicio</label>
        <input class="form-control" id="inicio_{{$dia}}"  name="inicio_{{$dia}}" type="time" value="" >
    </div>
    <div class="col-md-3 horas_{{$dia}}">
        <label for="">Fin</label>
        <input class="form-control" id="fin_{{$dia}}" name="fin_{{$dia}}" type="time" value="">
    </div>
</div>
@endforeach