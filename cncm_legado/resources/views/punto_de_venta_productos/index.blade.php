@extends('layouts.template-'.config('settings.template').'.plantilla')

@section('titulo')
    Venta de productos
@endsection


@section('breadcrumb')
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ url('/') }}">Inicio</a>
        </li>
        <li class="breadcrumb-item active">
            <strong>Venta de productos</strong>
        </li>
    </ol>
@endsection

@section('contenido')

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('id_alumno', 'Selecciona al alumno que va a comprar:*'); !!}
                {!! Form::select('id_alumno',[], null, ['class' => 'form-control','style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('nombre', 'ó ingresa el nombre de la persona:*'); !!}
                {!! Form::text('nombre', null, ['id'=>'nombre_persona','class' => 'form-control','style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-xl-8 col-lg-8 col-md-8 col-sm-8 col-12">
            <div class="element-box">
                <h5 class="element-header">
                    Agregar productos al carrito
                </h5>
                <div class="row">
                    <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                        Selecciona el producto para agregarlo al carrito:
                        {!! Form::select('id_producto', [], null, ['id'=>'select_productos','class'=>'form-control']) !!}
                    </div>
                    <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                        <table class="table" id="tb-partidas" width="100%" style="clear:both">
                            <thead>
                                <tr>
                                    <th>Acciones</th>
                                    <th>Nombre</th>
                                    <th>Cantidad</th>
                                    <th>Precio unitario</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                        </table>
                        <h3 class="text-right">
                            TOTAL: $<span class="total_venta">0</span>
                        </h3>
                    </div>
                </div>

            </div>

        </div>
        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-4 col-12">
            <div class="element-box">
                <h5 class="element-header">
                    Cerrar venta
                </h5>

                {!! Form::open(['id' => 'form-recibir-abono','route' => 'punto_de_venta_productos.cerrar_venta']) !!}
                    <div class="row justify-content-end">
                        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                            <input type="hidden" name="id_venta" value="{{$venta->id}}">
                            <div class="form-group">
                                {!! Form::label('forma_pago','Forma Pago:') !!}
                                {!! Form::select('forma_pago', [
                                        'Efectivo'              => 'Efectivo',
                                        'Tarjeta de debito'     => 'Tarjeta de debito',
                                        'Tarjeta de crédito'    => 'Tarjeta de crédito',
                                        'Transferencia'         => 'Transferencia'
                                    ],null, ['class' => 'form-control form-control-sm','form-selector'=> '','disabled' => true]) !!}
                            </div>

                        </div>
                        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                            <button class="btn btn-success float-right btn-block " type="submit" disabled form-selector >Terminar venta</button>
                        </div>
                    </div>
                {!! Form::close() !!}
            </div>

        </div>
    </div>


    <div class="modal inmodal fade animated" id="modal-ticket" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content animated bounceInRight">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Ticket</h4>
                        {{-- <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">
                            &times;</span><span class="sr-only">Close</span>
                        </button> --}}
                    </div>

                    <div class="modal-body">
                        <div id="contenido-ticket"></div>
                    </div>

                    <div class="modal-footer">
                        <a onclick="wait.modal('show')" class="btn btn-primary" href="">Cerrar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('template-clean-admin/bower_components/select2/dist/js/i18n/es.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('plugins/xeditable/css/bootstrap-editable.css') }}">
    <script src="{{ asset('plugins/xeditable/js/bootstrap-editable.min.js') }}"></script>

    <script type="text/javascript">
        $(function() {
            var Helpers = function() {};

            Helpers.prototype.number_format = function(number,decimals) {
                return parseFloat(number).toFixed(decimals).replace(/(\d)(?=(\d{3})+\.)/g, "$1,").toString()
            }

            Helpers = new Helpers();

            const dom = {
                select_alumno: $("#id_alumno"),
                tb_partidas: $("#tb-partidas"),
                form_abonos: $("#form-recibir-abono"),
                select_productos: $("#select_productos"),
                nombre_persona: $('#nombre_persona'),

                tikets:{
                    contenido_ticket:$("#contenido-ticket"),
                    modal: $("#modal-ticket"),
                }
            };

            const disableForm = (disable = false) => {
                const $elements = dom.form_abonos[0].querySelectorAll('[form-selector]');
                Array.from($elements).forEach(formElement => formElement.disabled = disable);
            }

            // PRODUCTOS AGREGADOS AL CARRITO
            var dt_partidas = dom.tb_partidas.DataTable({
                dom: "<'row'<'col-6'><'col-6'>><'row'<'col-12'tr>><'row'<'col-5'><'col-7'>>",
                processing: true,
                serverSide: true,
                responsive: false,
                pageLength: -1,
                ajax: {
                    url: "{{ route('punto_de_venta_productos.datatables_partidas') }}",
                    type: "POST",
                    data: function (d) {
                        d.id_venta = {{$venta->id}};
                    },
                    complete: function(data) {
                        let response = data.responseJSON || {};

                        $('.total_venta').html(
                            Helpers.number_format(response.total_venta || 0,2)
                        );

                        if(response.data.length > 0){
                            disableForm(false);
                        }else{
                            disableForm(true);
                        }
                    },
                    beforeSend: function(xhr,type) {
                    if (!type.crossDomain) {
                            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
                        }
                    },
                },
                columns: [
                    {data: 'buttons', name: 'buttons', orderable: false, searchable: false},
                    {data: 'producto.nombre', name: 'producto.nombre'},
                    {data: 'cantidad', name: 'cantidad',className:'text-right'},
                    {data: 'precio', name: 'precio',className:'text-right'},
                    {data: 'total', name: 'total',className:'text-right'},
                ],
                order: false,
                language: {
                    "lengthMenu": "Mostrar _MENU_ registros por pagina",
                    "zeroRecords": "No se encontro ningún registro",
                    "info": "Mostrando del _START_ al _END_ de _TOTAL_ registros. (Página _PAGE_ de _PAGES_)",
                    "infoEmpty": "No hay registros disponibles",
                    "infoFiltered": "(Filtrado de un total de _MAX_ registros)",
                    "search": "Buscar:",
                    "paginate": {
                        "first": "Primera",
                        "last": "Última",
                        "previous": '<i class="fas fa-chevron-left"></i>',
                        "next": '<i class="fas fa-chevron-right"></i>'
                    },
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                },

                drawCallback: function (settings) {
                    $("[data-toggle='tooltip']").tooltip();

                    // $.fn.editable.defaults.mode = 'inline';

                    $('.editable').on('shown', function(e, editable) {
                        $('.editable-submit').html('<i class="fas fa-check fa-1x"></i>');
                        $('.editable-cancel').html('<i class="fas fa-times"></i>');
                        setTimeout(function() { editable.input.$input.select() }, 50);

                    });

                    $('.editable_cantidad').editable({
                        emptytext: 'Vacio',
                        onblur: 'ignore',
                        validate: function(value) {


                            if(value<=0) {
                                return 'Ingresa un numero mayor a 0';
                            }
                        },
                    });

                    $('.editable_cantidad').on('save', function(e, editable) {
                       dt_partidas.ajax.reload(null, false);

                    });
                },
            });

            // SELECCIONAR AL ALUMNO
            dom.select_alumno.select2({
                language: "es",
                placeholder:'Selecciona un alumnno',
                dropdownParent: dom.modal_asignar_alumno,
                ajax: {
                    method: 'POST',
                    data:function (params) {
                        return {
                            _token: '{{ csrf_token() }}',
                            term: params.term,
                            page: params.page || 1,
                            id_sucursal: "{{ optional(session('sucursal'))->id }}",
                            status:'Alumno'
                        }
                    },
                    url: '{{ route("alumnos.traer_alumnos_select2") }}',
                    dataType: 'json',
                    cache: false,
                    delay:250,
                    beforeSend:function(xhr,type){
                        xhr.setRequestHeader('X-CSRF-Token',$('meta[name="csrf-token"]').attr('content'))
                    }
                },
                escapeMarkup: function (markup) { return markup; },
                minimumInputLength: 2,
                templateResult: function(option){
                    if (option.loading) {
                        return option.text;
                    }

                    if(!option.nuevo_numero_control || !option.nombres || !option.apellido_paterno || !option.apellido_materno){
                        return option.text
                    }

                    return `No. Control: ${option.nuevo_numero_control} | Nombre: ${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                },
                templateSelection:function(option){
                    if(!option.nuevo_numero_control ||  !option.nombres || !option.apellido_paterno || !option.apellido_materno){
                        return option.text
                    }

                    return `No. Control: ${option.nuevo_numero_control} | Nombre: ${option.nombres} ${option.apellido_paterno} ${option.apellido_materno}`;
                }
            });

            dom.select_alumno.on('select2:select', function (e) {
                dom.nombre_persona.val('');
            });

            dom.nombre_persona.keyup(function (e) { 
                e.preventDefault()
                if($(this).val() !=''){
                    dom.select_alumno.val(null).trigger('change');
                }
                
            });

            // SELECCIONAR LOS PRODUCTOS
            dom.select_productos.select2({
                language: "es",
                placeholder:'Selecciona un producto',
                // dropdownParent: dom.modal_asignar_alumno,
                ajax: {
                    method: 'POST',
                    data:function (params) {
                        return {
                            _token: '{{ csrf_token() }}',
                            term: params.term,
                            page: params.page || 1,
                            id_sucursal: "{{ optional(session('sucursal'))->id }}",
                        }
                    },
                    url: '{{ route("admin.productos.traer_productos_select2") }}',
                    dataType: 'json',
                    cache: false,
                    delay:250,
                    beforeSend:function(xhr,type){
                        xhr.setRequestHeader('X-CSRF-Token',$('meta[name="csrf-token"]').attr('content'))
                    }
                },
                escapeMarkup: function (markup) { return markup; },
                minimumInputLength: 0,
                templateResult: function(option){
                    if (option.loading) {
                        return option.text;
                    }

                    if(!option.nombre || !option.descripcion ){
                        return option.text
                    }

                    return `${option.nombre} ${option.descripcion} `;
                },
                templateSelection:function(option){
                    if(!option.nombre || !option.descripcion){
                        return option.text
                    }

                    return `${option.nombre} ${option.descripcion} `;
                }
            });

            dom.select_productos.on('select2:select', function (e) {
                var data = e.params.data;
                $.ajax({
                    url: "{{route('punto_de_venta_productos.guardar_partida')}}",
                    type: 'POST',
                    // contentType: false,
                    // processData: false,
                    data: {
                        id_producto: data.id,
                        id_venta: {{$venta->id}},
                    },
                }).done(function(response){

                        dt_partidas.ajax.reload(null,false);
                        dom.select_productos.val(null).trigger('change');
                        setTimeout(() => {
                            wait.modal('hide');
                        }, 250);
                }).fail(function(error){
                    setTimeout(() => {
                        wait.modal('hide');
                        toastr.error('Error', 'Ocurrio un error inesperado');
                    }, 250);
                });

            });


            dom.form_abonos.submit(function(e)
            {
                e.preventDefault();

                wait.modal('show');

                const id_alumno = dom.select_alumno.val()

                if(!id_alumno && dom.nombre_persona.val() == ''){
                    setTimeout(function(){
                                wait.modal('hide');
                            }, 400);
                    toastr.error('Error', 'Debes seleccionar primero un alumno o escribir el nombre de la persona');
                    return false;
                }



                let formData = new FormData(this);
                if(id_alumno != null){
                    formData.append('id_alumno',id_alumno);
                }
                if(dom.nombre_persona.val() != ''){
                    formData.append('nombre',dom.nombre_persona.val());
                }

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    contentType: false,
                    processData: false,
                    data: formData
                }).done(function(response){


                        const venta = response.venta;
                        const route = "{{ url('punto_de_venta_productos/ticket/_ventaid') }}".replace('_ventaid',venta.id);

                        console.log(route);

                        dt_partidas.ajax.reload(function(){
                            dom.form_abonos[0].reset();

                            setTimeout(function(){
                                wait.modal('hide');
                            }, 200);


                            dom.tikets.modal.modal('show');
                            dom.tikets.contenido_ticket.html();
                            dom.tikets.contenido_ticket.html(`<iframe scrolling='auto' type='text/html' scroll='auto' src='${route}' width='100%' height='450px' align='center'></iframe>`);
                        },false);

                }).fail(function(error){
                    setTimeout(() => {
                        wait.modal('hide');
                        toastr.error('Error', 'Ocurrio un error inesperado');
                    }, 250);
                });
            });


            dom.tb_partidas.on('click',"a[data-action='delete']",function(e){
                e.preventDefault();

                var url = $(this).attr('href');

                swal({
                    title: "¿Estas seguro de eliminar el registro?",
                    type: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#ff3333",
                    cancelButtonColor: "#CDCDCD",
                    confirmButtonText: "Borrar",
                    cancelButtonText: "Cancelar",
                    showLoaderOnConfirm: false,
                }).then(function(result) {
                    if (!result.value) {
                        return;
                    }

                    wait.modal('show');

                    $.ajax({
                        url: url,
                        type: 'POST',
                        success: function (response){
                            dt_partidas.ajax.reload( function(){
                                setTimeout(function(){
                                    wait.modal('hide');
                                }, 200);
                                toastr.success('Éxito', 'Se borró con éxito el registro');
                            }, false )
                        },
                        error:function(error){
                            setTimeout(() => {
                                wait.modal('hide');
                                toastr.error('Error', 'Ocurrio un error inesperado');
                            }, 500);
                        }
                    });
                })
            });

        });
    </script>
@endsection
