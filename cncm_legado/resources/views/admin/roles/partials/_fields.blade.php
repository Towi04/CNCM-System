
<div class="form-group">
    {!! Form::label('display_name', 'Nombre:*'); !!}
    {!! Form::text('display_name', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui el nombre del rol']); !!}
</div>
<div class="form-group">
    {!! Form::label('description', 'Descripción'); !!}
    {!! Form::textarea('description', null, ['class' => 'form-control', 'placeholder' => 'Escribe aqui la descripción','rows'=>'5']); !!}
</div>

<script type="text/javascript">
    window.addEventListener('DOMContentLoaded', (event) => {
    });
</script>
