<?php

/**
 * @author adn <a.aranza@aranzaycervantes.mx>
 *
 * Archivo de configuracion para los parametros de cada proyecto
 */
return [
    'template' => env('FRONT_TEMPLATE', 'clean-admin'),
    'company' => [
        'autor'         => env('APP_AUTOR', 'Aldo David Aranza Mora'),
        'description'   => env('APP_DESCRIPTION', "Página de administración de " . env('APP_NAME', 'Laravel')),
        'empresa'       => env('APP_EMPRESA', 'ADN - Administración Digital de Negocios'),
        'web'           => env('APP_WEB', 'https://adndigital.mx'),
        'telefono'      => env('APP_TELEFONO', '461 1567492'),
        'soporte_email' => env('APP_SOPORTE_EMAIL', 'a.aranza@aranzaycervantes.mx'),
    ],
    'site' => [
        'web'   => 'www.cncm.edu.mx'
    ],

    /**
     * Permisos de prueba (Puedes agregar permisos en esta seccion)
     * PARA ACTUALIZAR, EJECUTAR php artisan db:seed
     */
    'permissions' => [
        [
            'name'          => 'gestionar_usuarios',
            'display_name'  => 'Gestión de usuarios',
            'description'   => 'Permite la gestion de usuarios'
        ],
        [
            'name'          => 'gestionar_roles',
            'display_name'  => 'Gestión de roles',
            'description'   => 'Permite la gestion de roles'
        ],
        [
            'name'          => 'gestionar_permisos',
            'display_name'  => 'Gestión de permisos',
            'description'   => 'Permite la gestion de permisos'
        ],
        [
            'name'          => 'gestionar_sucursales',
            'display_name'  => 'Gestión de sucursales',
            'description'   => 'Permite la gestion de sucursales'
        ],
        [
            'name'          => 'asignar_varias_sucursales',
            'display_name'  => 'Asignar varias sucursales',
            'description'   => 'Permite la asignacion de multiples sucursales'
        ],
        [
            'name'          => 'listar_alumnos',
            'display_name'  => 'Listar de alumnos',
            'description'   => 'Permite mostrar la lista de alumnos'
        ],
        [
            'name'          => 'crear_alumno',
            'display_name'  => 'Crear alumno',
            'description'   => 'Permite crear a un alumno'
        ],
        [
            'name'          => 'consultar_alumno',
            'display_name'  => 'Consultar Alumno',
            'description'   => 'Permite consultar a un alumno'
        ],
        [
            'name'          => 'editar_alumno',
            'display_name'  => 'Editar alumno',
            'description'   => 'Permite editar un alumno'
        ],
        [
            'name'          => 'eliminar_alumno',
            'display_name'  => 'Eliminar alumno',
            'description'   => 'Permite eliminar un alumno'
        ],
        [
            'name'          => 'listar_materias',
            'display_name'  => 'Listar Materias',
            'description'   => 'Permite Mostrar las materias'
        ],
        [
            'name'          => 'crear_materia',
            'display_name'  => 'Crear Materia',
            'description'   => 'Permite Crear una materia'
        ],
        [
            'name'          => 'editar_materia',
            'display_name'  => 'Editar Materia',
            'description'   => 'Permite editar una materia'
        ],
        [
            'name'          => 'eliminar_materia',
            'display_name'  => 'Eliminar materia',
            'description'   => 'Permite eliminar una materia'
        ],
        [
            'name'          => 'listar_grupos',
            'display_name'  => 'Listar Grupos',
            'description'   => 'Permite mostrar la lista de grupos'
        ],
        [
            'name'          => 'crear_grupo',
            'display_name'  => 'Crear grupo',
            'description'   => 'Permite crear a un grupo'
        ],
        [
            'name'          => 'consultar_grupo',
            'display_name'  => 'Consultar grupo',
            'description'   => 'Permite consultar a un grupo'
        ],
        [
            'name'          => 'editar_grupo',
            'display_name'  => 'Editar grupo',
            'description'   => 'Permite editar un grupo'
        ],
        [
            'name'          => 'eliminar_grupo',
            'display_name'  => 'Eliminar alumno',
            'description'   => 'Permite eliminar un grupo'
        ],
        [
            'name'          => 'asignar_materias',
            'display_name'  => 'Asignar Materias',
            'description'   => 'Permite asignar una materia',
        ],
        [
            'name'          => 'asignar_alumnos',
            'display_name'  => 'Asignar alumnos',
            'description'   => 'Permite asignar alumnos a un grupo',
        ],
        [
            'name'          => 'realizar_pre_registro',
            'display_name'  => 'Realizar Pre Registro',
            'description'   => 'Permite realizar el pre-registro de los alumnos',
        ],

        [
            'name'          => 'convertir_pre_registro_alumno',
            'display_name'  => 'Convertir Pre-registro en Alunno',
            'description'   => 'Permite transformar un pre-registro en alumno',
        ],

        [
            'name'          => 'listar_especialidades',
            'display_name'  => 'Listar Especialidades',
            'description'   => 'Permite mostrar la lista de especialidades'
        ],
        [
            'name'          => 'crear_especialidad',
            'display_name'  => 'Crear especialidad',
            'description'   => 'Permite crear una especialidad'
        ],
        [
            'name'          => 'consultar_especialidad',
            'display_name'  => 'Consultar especialidad',
            'description'   => 'Permite consultar una especialidad'
        ],
        [
            'name'          => 'editar_especialidad',
            'display_name'  => 'Editar especialidad',
            'description'   => 'Permite editar una especialidad'
        ],
        [
            'name'          => 'eliminar_especialidad',
            'display_name'  => 'Eliminar especialidad',
            'description'   => 'Permite eliminar una especialidad'
        ],

        [
            'name'          => 'listar_cuentas_bancarias',
            'display_name'  => 'Listar cuentas bancarias',
            'description'   => 'Permite mostrar la lista de cuentas_bancarias'
        ],
        [
            'name'          => 'crear_cuenta_bancaria',
            'display_name'  => 'Crear cuenta bancaria',
            'description'   => 'Permite crear una cuenta bancaria'
        ],
        [
            'name'          => 'consultar_cuenta_bancaria',
            'display_name'  => 'Consultar cuenta_bancaria',
            'description'   => 'Permite consultar una cuenta bancaria'
        ],
        [
            'name'          => 'editar_cuenta_bancaria',
            'display_name'  => 'Editar cuenta bancaria',
            'description'   => 'Permite editar una cuenta bancaria'
        ],
        [
            'name'          => 'eliminar_cuenta_bancaria',
            'display_name'  => 'Eliminar cuenta bancaria',
            'description'   => 'Permite eliminar una cuenta bancaria'
        ],
        [
            'name'          => 'ingresar_punto_venta',
            'display_name'  => 'Ingresar al punto de venta',
            'description'   => 'Permite ingresar al punto de venta y realizar cobros a los alumnos'
        ],
        [
            'name'          => 'editar_datos_fiscales',
            'display_name'  => 'Editar Datos Fiscales',
            'description'   => 'Permiso para editar los datos fiscales del alumno'
        ],
        [
            'name'          => 'consultar_preregistros_alumnos',
            'display_name'  => 'Consultar Pre-registros Alumnos',
            'description'   => 'Permiso para mostrar los pre-registros de alumnos'
        ],
        [
            'name'          => 'inscribir_a_otros_grupos',
            'display_name'  => 'Inscribir a alumnos a otros grupos',
            'description'   => 'Inscribir alumnos a otros grupos'
        ],
        [
            'name'          => 'gestionar_configuraciones',
            'display_name'  => 'Gestionar configuraciones',
            'description'   => 'Actualizar y modificar las configuraciones globales del sistema'
        ],
        [
            'name'          => 'convertir_no_fiscales_a_fiscales',
            'display_name'  => 'Convertir ventas no fiscales a fiscales',
            'description'   => 'Permite convertir las ventas no fiscales a fiscales de acuerdo al porcentaje de la configuracion'
        ],

        # PRODUCTOS
        [
            'name'          => 'listar_productos',
            'display_name'  => 'Listar Productos',
            'description'   => 'Permite Mostrar los productos'
        ],
        [
            'name'          => 'crear_producto',
            'display_name'  => 'Crear producto',
            'description'   => 'Permite crear un producto'
        ],
        [
            'name'          => 'editar_producto',
            'display_name'  => 'Editar Producto',
            'description'   => 'Permite editar un producto'
        ],
        [
            'name'          => 'eliminar_producto',
            'display_name'  => 'Eliminar producto',
            'description'   => 'Permite eliminar un producto'
        ],
        [
            'name'          => 'gestionar_compras',
            'display_name'  => 'Gestionar compras',
            'description'   => 'Permite gestionar compras'
        ],
        [
            'name'          => 'punto_de_venta_productos',
            'display_name'  => 'Entrar al punto de venta de productos',
            'description'   => 'Permite generar ventas en el punto de ventas de productos'
        ],
        [
            'name'          => 'registrar_asistencias',
            'display_name'  => 'Registrar asistencias',
            'description'   => 'Permite registrar asistencias de los alumnos'
        ],

        # ASESORIAS
        [
            'name'          => 'impartir_asesorias',
            'display_name'  => 'Impartir asesorias',
            'description'   => 'Permite Impartir asesorias'
        ],

        [
            'name'          => 'ver_horarios_profesores',
            'display_name'  => 'Ver horario de profesores',
            'description'   => 'Permite mostrar los horarios de profesores'
        ],

        [
            'name'          => 'entrar_calendario',
            'display_name'  => 'Entrar al calendario',
            'description'   => 'Permite entrar al calendario'
        ],

        [
            'name'          => 'agendar_asesoria',
            'display_name'  => 'Agendar asesoria',
            'description'   => 'Permite agendar una asesoria'
        ],

        [
            'name'          => 'editar_asesoria',
            'display_name'  => 'Editar Asesorias',
            'description'   => 'Permite editar una asesoria'
        ],

        [
            'name'          => 'cancelar_asesorias',
            'display_name'  => 'Cancelar asesorias',
            'description'   => 'Permite cancelar una asesoria'
        ],
        [
            'name'          => 'registrar_pago_manual',
            'display_name'  => 'Registrar pago manual',
            'description'   => 'Permite registrar un pago de forma manual'
        ],
        [
            'name'          => 'finalizar_grupo',
            'display_name'  => 'Finalizar grupo',
            'description'   => 'Permite finalizar grupos'
        ],
        [
            'name'          => 'ver_reporte_ventas',
            'display_name'  => 'Ver reporte de ventas',
            'description'   => 'Permite ver el reporte de ventas'
        ],
        [
            'name'          => 'editar_reporte_ventas',
            'display_name'  => 'Editar reporte de ventas',
            'description'   => 'Permite editar informacion en el reporte de ventas'
        ],
        [
            'name'          => 'ver_reporte_ventas_productos',
            'display_name'  => 'Ver reporte de ventas por productos',
            'description'   => 'Permite ver el reporte de ventas por productos'
        ],
        [
            'name'          => 'ver_reporte_vencimiento',
            'display_name'  => 'Ver reporte de vencimientos',
            'description'   => 'Permite ver el reporte de vencimientos'
        ],
        [
            'name'          => 'ver_reporte_proyeccion',
            'display_name'  => 'Ver reporte de proyección',
            'description'   => 'Permite ver el la proyeccion'
        ],
        [
            'name'          => 'ver_reporte_asesores',
            'display_name'  => 'Ver reporte de asesores',
            'description'   => 'Permite ver el la asesores'
        ],
        [
            'name'          => 'ver_todos_grupos',
            'display_name'  => 'Ver todos los grupos',
            'description'   => 'Permite ver todos los grupos'
        ],
        [
            'name'          => 'eliminar_movimiento_reporte_ventas',
            'display_name'  => 'Eliminar Movimiento reporte ventas',
            'description'   => 'Permite eliminar un movimiento del reporte de ventas'
        ],
        [
            'name'          => 'generar_corte_caja',
            'display_name'  => 'Generar Corte de caja',
            'description'   => 'Permite generar el corte de caja'
        ],
        [
            'name'          => 'ocultar_ventas_no_fiscales',
            'display_name'  => 'Ocultar ventas no fiscales',
            'description'   => 'Permite mostrar/ocultar ventas no fiscales'
        ],
        [
            'name'          => 'descargar_excel_bd',
            'display_name'  => 'Descargar exceles de bases de datos',
            'description'   => 'Permite descargar informacion de bases de datos'
        ],
        [
            'name'          => 'asignar_apoyos_especiales',
            'display_name'  => 'Asignar apoyos especiales',
            'description'   => 'Permite asignar apoyos especiales a los alumnos'
        ],
        [
            'name'          => 'asignar_apoyos_especiales_en_inscripcion',
            'display_name'  => 'Asignar apoyos especiales en inscripcion',
            'description'   => 'Permite asignar apoyos especiales en la inscripción del alumno'
        ],
        [
            'name'          => 'autorizar_apoyos_especiales_en_inscripcion',
            'display_name'  => 'Autorizar apoyos especiales en inscripcion',
            'description'   => 'Permite autorizar apoyos especiales en la inscripción del alumno'
        ],
        [
            'name'          => 'reimprimir_ticket_reporte_ventas',
            'display_name'  => 'Reimprimir Ticket Reporte Ventas',
            'description'   => 'Permite reimprimir ticket desde el reporte de ventas'
        ],

        [
            'name'          => 'editar_reporte_ventas_productos',
            'display_name'  => 'Editar Reporte Ventas Productos',
            'description'   => 'Permite editar la informacion desde el reporte de ventas de productos'
        ],
        [
            'name'          => 'eliminar_movimiento_reporte_ventas_productos',
            'display_name'  => 'Eliminar Movimiento Reporte Ventas Productos',
            'description'   => 'Permite eliminar un movimiento desde el reporte de ventas de productos',
        ],
        [
            'name'          => 'reimprimir_ticket_reporte_ventas_productos',
            'display_name'  => 'Reimprimir Ticket Reporte Ventas Productos',
            'description'   => 'Permite reimprimir el ticket desde el reporte de ventas de productos',
        ],
        [
            'name'          => 'ver_reporte_asistencias_personal',
            'display_name'  => 'Ver el reporte de asistencias del personal',
            'description'   => 'Ver el reporte de asistencias del personal',
        ],
        [
            'name'          => 'ver_reporte_desercion',
            'display_name'  => 'Ver el reporte de desercion',
            'description'   => 'Ver el reporte de desercion',
        ],
        [
            'name'          => 'baja_grupo',
            'display_name'  => 'Dar de baja a un alumno de un grupo',
            'description'   => 'Permite dar de baja a un alumno de un grupo',
        ],
        [
            'name'          => 'cambio_horario_grupo',
            'display_name'  => 'Cambio horario grupo',
            'description'   => 'Permite realizar un cambio de horario de un grupo a un alumno',
        ],
        [
            'name'          => 'ver_reporte_apoyos_inscripcion',
            'display_name'  => 'Ver reporte de apoyos a inscripcion',
            'description'   => 'Permite ver el reporte de apoyos a inscripción',
        ],
        [
            'name'          => 'subir_foto_alumnos',
            'display_name'  => 'Subir foto de los alumnos',
            'description'   => 'Subir foto de los alumnos',
        ],
        [
            'name'          => 'ver_reporte_pagos_eliminados',
            'display_name'  => 'Ver reporte pagos eliminados',
            'description'   => 'Ver reporte pagos eliminados',
        ],
        [
            'name'          => 'cambiar_fecha_inicio_grupo',
            'display_name'  => 'Cambiar fecha de inicio en grupo',
            'description'   => 'Cambiar fecha de inicio en grupo',
        ],
        [
            'name'          => 'crear_notas_alumnos',
            'display_name'  => 'Crear notas en los alumnos',
            'description'   => 'Crear notas en los alumnos',
        ],
        [
            'name'          => 'ver_cumpleaños_personal',
            'display_name'  => 'Ver cumpleaños de personal',
            'description'   => 'Ver cumpleaños personal',
        ],
        [
            'name'          => 'ver_cumpleaños_alumnos',
            'display_name'  => 'Ver cumpleaños de alumnos',
            'description'   => 'Ver cumpleaños de alumnos',
        ], 
        [
            'name'          => 'ver_alertas',
            'display_name'  => 'Ver alertas',
            'description'   => 'Ver alertas',
        ],
        [
            'name'          => 'cambiar_especialidad_pagos_alumno',
            'display_name'  => 'Cambiar especialidad en pagos del alumno',
            'description'   => 'Cambiar especialidad en pagos del alumno',
        ],
        [
            'name'          => 'cambiar_fecha_inicio_especialidad',
            'display_name'  => 'Cambiar fecha inicio de especialidad',
            'description'   => 'Cambiar fecha inicio de especialidad',
        ],
        [
            'name'          => 'asignar_apoyos_especiales_en_inscripcion_preregistro',
            'display_name'  => 'Asignar apyoyo especial (Pre registro)',
            'description'   => 'Asignar apyoyo especial (Pre registro)',
        ],
        [
            'name'          => 'actualizar_asesor_alumno',
            'display_name'  => 'Actualizar asesor del alumno',
            'description'   => 'Actualizar asesor del alumno',
        ],
        [
            'name'          => 'ver_reporte_recomendados',
            'display_name'  => 'Ver reporte de recomendados',
            'description'   => 'Permite ver el reporte de recomendados',
        ],
        [
            'name'          => 'ver_reporte_programados',
            'display_name'  => 'Ver reporte de alumnos programados',
            'description'   => 'Permite ver el reporte de alumnos programados a iniciar',
        ],
        [
            'name'          => 'ver_reporte_programados',
            'display_name'  => 'Ver reporte de alumnos programados',
            'description'   => 'Permite ver el reporte de alumnos programados a iniciar',
        ],
        [
            'name'          => 'notificacion_usuario_fuera_horario',
            'display_name'  => 'Notificacion de usuario fuera de horario',
            'description'   => 'Permite recibir notificación de usuario fuera de horario',
        ],
        [
            'name'          => 'poner_monto_manual',
            'display_name'  => 'Poner monto manual',
            'description'   => 'Permite poder poner el monto manual en los pagos',
        ],
        [
            'name'          => 'gestionar_descuentos',
            'display_name'  => 'Gestionar descuentos',
            'description'   => 'Gestionar descuentos',
        ],
        [
            'name'          => 'dar_fin_de_curso',
            'display_name'  => 'Dar fin de curso',
            'description'   => 'Dar fin de curso',
        ],
        [
            'name'          => 'cambio_forma_pago',
            'display_name'  => 'Realizar cambio forma pago',
            'description'   => 'Realizar cambio de forma de pago',
        ],
        
    ],

    # Roles del sistema (Puedes agregar mas roles de prueba)
    'roles' => [
        [
            'name'          => 'administrador',
            'display_name'  => 'Administrativo',
            'description'   => 'Usuario con permisos de administrativo',
        ],
    ],

    /**
     * Usuarios de prueba
     */
    'users' => [
        [
            'nombres'           => 'Aldo David',
            'apellido_paterno'  => 'Aranza',
            'apellido_materno'  => 'Mora',
            'email'             => 'a.aranza@aranzaycervantes.mx',
            'password'          => '26227426',
            'celular'           => '4611567492',
            'remember_token'    => null,
        ]
    ],

    'sucursales' => [
        [
            'nombre'            => 'Plantel Celaya',
            'direccion'         => 'Andador Gongora 101 Zona Centro',
            'municipio'         => 'Celaya',
            'estado'            => 'Guanajuato',
        ],
        [
            'nombre'            => 'Plantel Irapuato',
            'direccion'         => 'Av. Guerrero #378, Col. Centro.',
            'municipio'         => 'Irapuato',
            'estado'            => 'Guanajuato',
        ],
        [
            'nombre'            => 'Plantel Salamanca',
            'direccion'         => 'Portal de los Bravo #121, Col. Centro.',
            'municipio'         => 'Salamnca',
            'estado'            => 'Guanajuato',
        ],
        [
            'nombre'            => 'Plantel San Luis Potosí',
            'direccion'         => 'Zaragoza #875, Col. Centro.',
            'municipio'         => 'San Luis Potosí',
            'estado'            => 'Guanajuato',
        ],

    ],

    'especialidades' => [
        [
            'nombre'             => 'Ingles',
            'id_sucursal'        => 1,
            'descripcion'        => null,
            'precio_inscripcion' => null,
            'precio_mensualidad' => null,
            'precio_mensualidad_pronto_pago' => null,
            'precio_semanal'     => null,
        ],
        [
            'nombre'             => 'Computacion',
            'id_sucursal'        => 1,
            'descripcion'        => null,
            'precio_inscripcion' => null,
            'precio_mensualidad' => null,
            'precio_mensualidad_pronto_pago' => null,
            'precio_semanal'     => null,
        ],
        [
            'nombre'             => 'Computacion 2021',
            'id_sucursal'        => 1,
            'descripcion'        => null,
            'precio_inscripcion' => null,
            'precio_mensualidad' => null,
            'precio_mensualidad_pronto_pago' => null,
            'precio_semanal'     => null,
        ],
        [
            'nombre'             => 'Prepa Abierta',
            'id_sucursal'        => 1,
            'descripcion'        => null,
            'precio_inscripcion' => null,
            'precio_mensualidad' => null,
            'precio_mensualidad_pronto_pago' => null,
            'precio_semanal'     => null,
        ],
        [
            'nombre'             => 'Prepa Abierta',
            'id_sucursal'        => 1,
            'descripcion'        => null,
            'precio_inscripcion' => null,
            'precio_mensualidad' => null,
            'precio_mensualidad_pronto_pago' => null,
            'precio_semanal'     => null,
        ],
        [
            'nombre'             => 'Prepa escoloarizada',
            'id_sucursal'        => 1,
            'descripcion'        => null,
            'precio_inscripcion' => null,
            'precio_mensualidad' => null,
            'precio_mensualidad_pronto_pago' => null,
            'precio_semanal'     => null,
        ],
        [
            'nombre'             => 'Otros',
            'id_sucursal'        => 1,
            'descripcion'        => null,
            'precio_inscripcion' => null,
            'precio_mensualidad' => null,
            'precio_mensualidad_pronto_pago' => null,
            'precio_semanal'     => null,
        ],
        
    ],
    # Configuraciones del sistema
    'configuraciones' => [
        [
            'nombre'          => 'mostrar_solo_fiscales',
            'descripcion'  => '¿Mostrar solo la información de ventas fiscales?',
            'valor'   => 'No',
        ],
        [
            'nombre'          => 'porcentaje_fiscal',
            'descripcion'  => 'Porcentaje de referencia para convertir ventas no fiscales en fiscales',
            'valor'   => '20',
        ],
    ],
];
