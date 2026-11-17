<div class="modal inmodal fade animated" id="modal-inscripcion" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content animated bounceInRight">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inscripción de alumno <small>(*) Campos requeridos</small></h5>
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">
                        &times;</span><span class="sr-only">Close</span>
                    </button>
                </div>
                {!! Form::open(['id' => 'form-inscripcion']) !!}
                <div class="modal-body">

                    <div class="row">
                        <div class="col-12">
                            <p class="form-desc font-weight-bold p-0 m-0 border-0" id="inscripcion-detalle"></p>
                        </div>
                    </div>

                    <fieldset class="form-group m-0">
                        <legend><span>Forma de pago</span></legend>
                        <div class="row">
                            <div class="col-12">
                                {!! Form::label('tipo_pago','Tipo pago:*') !!}
                                {!! Form::select('tipo_pago', [
                                    'Efectivo'              => 'Efectivo',
                                    'Tarjeta de debito'     => 'Tarjeta de debito',
                                    'Tarjeta de crédito'    => 'Tarjeta de crédito',
                                    'Transferencia'         => 'Transferencia'
                                ],null, ['class' => 'form-control form-control-sm','form-selector'=> '','placeholder' =>'Selecciona una forma de pago' ,'required' => true]) !!}
                            </div>
                        </div>

                        <div class="row pt-3">
                            <div class="col-12">
                                {!! Form::label('fecha_inicio', 'Fecha Inicio:*'); !!}
                                {!! Form::text('fecha_inicio', null, ['class' => 'form-control form-control-sm', 'placeholder' => 'Escribe la fecha de inicio','autocomplete' => 'off','required' => true]); !!}
                            </div>
                        </div>

                        @can('asignar_apoyos_especiales_en_inscripcion_preregistro')
                            <div class="row pt-3">
                                <div class="col-12">
                                    <label>
                                        <input id="ckb-apoyo-especial" type="checkbox" name="apoyo_especial" value="1" class="i-checks">
                                        ¿Tiene apoyo especial?
                                    </label>
                                </div>
                                <div class="col-12">
                                    <label>
                                        <input id="ckb-apoyo-recomendacion" type="checkbox" name="apoyo_por_recomendacion" value="1" class="i-checks">
                                        ¿Tiene apoyo por recomendación?
                                    </label>
                                </div>
                            </div>

                            <div class="row pt-3" id="apoyo-especial" style="display: none;">
                                
                                <div class="col-12">
                                    {!! Form::label('precio_inscripcion','Precio Original de Inscripción:*') !!}
                                    {!! Form::number('precio_inscripcion',null, ['class' => 'form-control form-control-sm','form-selector'=> '','placeholder' =>'Escribe el monto de la inscripcion','step' => '0.01','data-apoyo']) !!}
                                </div>
                            </div>

                            <div class="row pt-3" id="apoyo-recomendacion" style="display: none;">
                                <div class="col-12">
                                    <div class="form-group">
                                        {!! Form::label('id_alumno_recomendo', 'Selecciona al alumno que recomendo:*'); !!}
                                        {!! Form::select('id_alumno_recomendo',[], null, ['class' => 'form-control','style' => 'width:100%','data-alumno']) !!}
                                    </div>

                                </div>
                            </div>
                            <div class="msje_cupo text-danger"></div>
                            <div class="row mt-2" id="apoyo-autorizacion"  style="display: none;">
                                <div class="col-12">
                                    {!! Form::label('id_usuario_autoriza','Selecciona el usuario que autoriza:*') !!}
                                    {!! Form::select('id_usuario_autoriza',$usuarios_autorizados,null, ['class' => 'form-control form-control-sm','form-selector'=> '','data-usuario']) !!}
                                </div>
                                <div class="col-12" >
                                    {!! Form::label('password','Ingresa el password:*') !!}
                                    {!! Form::password('password', ['class' => 'form-control form-control-sm','form-selector'=> '','data-password']) !!}
                                </div>
                            </div>
                               
                            <div class="row" id="apoyo-motivo"  style="display: none;">
                                <div class="col-12">
                                    {!! Form::label('motivo','Escribe el motivo del apoyo:*') !!}
                                    {!! Form::textarea('motivo',null, ['class' => 'form-control form-control-sm','placeholder' =>'Escribe el motivo del apoyo','data-motivo','rows'=>'3']) !!}
                                </div>
                            </div>

                        @endcan
                    </fieldset>
                </div>

                <div class="modal-footer">
                    <input type="hidden" name="cupo" id="cupo" value="">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Inscribir</button>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</div>
