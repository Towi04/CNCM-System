
<div class="col-sm-12 no_print no-print">
    {!! Form::open([
        'route' => $route,
        'class' => 'form-inline',
        'method' => 'GET',
        'accept-charset'=>'UTF-8',
        'enctype'=>'multipart/form-data', 'onsubmit'=> 'wait.modal("show")']) !!}
            <div class="form-group" >
                <span class="text-center text-bold">Especialidad: &nbsp;&nbsp;</span>
            </div>
            
            <div class="form-group seccion_semana" >
                    <label for="semana" class="sr-only" >Especialidad</label>
                    {!! Form::select('id_especialidad', $especialidades->prepend('Selecciona una especialidad',''), @$_GET['id_especialidad'], ['class'=>'form-control','id'=>'select_id_especialidad']) !!}
            </div>

            <div class="form-group" >
                <span class="text-center text-bold"> &nbsp;&nbsp; Semana: &nbsp;&nbsp;</span>
            </div>
            
            <div class="form-group seccion_semana" >
                    <label for="semana" class="sr-only" >Semana</label>
                    @if(isset($_GET['semana']))
                        <input id="semana" class="form-control" style="width:100%" name="semana" type="number" min="1" max="52" value="{{@$_GET['semana']}}" step="1">
                    @else 
                        <input id="semana" class="form-control" style="width:100%" name="semana" type="number" min="1" max="52" value="{{@$semana}}" step="1">
                    @endif
            </div>
            <div class="form-group mar-lft seccion_semana" >
                <label class="">&nbsp;&nbsp;Año: &nbsp;&nbsp;</label>
                    {!! Form::select('year', $years, @$year, ['class' => 'form-control','id'=>'year']); !!}
            </div>
            &nbsp;&nbsp;
            <button class="btn btn-info  pull-right" id="btn_search" type="submit"> Filtrar</button>

    {!! Form::close() !!}
</div>