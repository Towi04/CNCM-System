<?php

/**
 * Catálogo de campos para certificaciones (columnas del Excel institucional).
 * Cada producto-certificación elige qué campos aplican y quién los llena.
 */

/** @return list<array{clave:string, etiqueta:string, tipo:string, categoria:string}> */
function certificacion_campos_catalogo_definicion(): array
{
    $text = 'text';
    $date = 'date';
    $time = 'time';
    $email = 'email';
    $phone = 'phone';
    $bool = 'bool';

    return [
        ['clave' => 'folio', 'etiqueta' => 'Folio', 'tipo' => $text, 'categoria' => 'examen'],
        ['clave' => 'apellido_paterno', 'etiqueta' => 'Apellido P', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'apellido_materno', 'etiqueta' => 'Apellido M', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'email', 'etiqueta' => 'e-mail', 'tipo' => $email, 'categoria' => 'alumno'],
        ['clave' => 'telefono', 'etiqueta' => 'Teléfono', 'tipo' => $phone, 'categoria' => 'alumno'],
        ['clave' => 'certificacion', 'etiqueta' => 'Certificación', 'tipo' => $text, 'categoria' => 'examen'],
        ['clave' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => $date, 'categoria' => 'examen'],
        ['clave' => 'hora', 'etiqueta' => 'Hora', 'tipo' => $time, 'categoria' => 'examen'],
        ['clave' => 'folio_id', 'etiqueta' => 'Folio / ID', 'tipo' => $text, 'categoria' => 'examen'],
        ['clave' => 'clave', 'etiqueta' => 'Clave', 'tipo' => $text, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'data', 'etiqueta' => 'Data', 'tipo' => $text, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'cc', 'etiqueta' => 'CC', 'tipo' => $text, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'c_pago', 'etiqueta' => 'C. Pago', 'tipo' => $text, 'categoria' => 'examen'],
        ['clave' => 'zoom', 'etiqueta' => 'Zoom', 'tipo' => $text, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'pedir_uks_glcc', 'etiqueta' => 'Pedir UKS/GLCC', 'tipo' => $bool, 'categoria' => 'examen'],
        ['clave' => 'fecha2', 'etiqueta' => 'Fecha2', 'tipo' => $date, 'categoria' => 'examen'],
        ['clave' => 'hora2', 'etiqueta' => 'Hora2', 'tipo' => $time, 'categoria' => 'examen'],
        ['clave' => 'itep_results', 'etiqueta' => 'iTEP Results', 'tipo' => $text, 'categoria' => 'examen'],
        ['clave' => 'canceled', 'etiqueta' => 'Canceled', 'tipo' => $bool, 'categoria' => 'examen'],
        ['clave' => 'reglamento', 'etiqueta' => 'Reglamento', 'tipo' => $bool, 'categoria' => 'alumno'],
        ['clave' => 'user', 'etiqueta' => 'user', 'tipo' => $text, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'password', 'etiqueta' => 'password', 'tipo' => $text, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'nombre_completo', 'etiqueta' => 'Nombre Completo', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'nombre_m', 'etiqueta' => 'Nombre M', 'tipo' => $text, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'email_m', 'etiqueta' => 'e-mail M', 'tipo' => $email, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'moodle', 'etiqueta' => 'moodle', 'tipo' => $text, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'solicitud', 'etiqueta' => 'Solicitud', 'tipo' => $text, 'categoria' => 'examen'],
        ['clave' => 'ine', 'etiqueta' => 'INE', 'tipo' => $bool, 'categoria' => 'alumno'],
        ['clave' => 'curp', 'etiqueta' => 'CURP', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'fecha_nacimiento', 'etiqueta' => 'F. Nacimiento', 'tipo' => $date, 'categoria' => 'alumno'],
        ['clave' => 'direccion_completa', 'etiqueta' => 'Dirección Completa', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'direccion', 'etiqueta' => 'Dirección', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'num_int', 'etiqueta' => '# Int', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'colonia', 'etiqueta' => 'Colonia', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'municipio', 'etiqueta' => 'Municipio', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'estado', 'etiqueta' => 'Estado', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'cp', 'etiqueta' => 'CP', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'recoger', 'etiqueta' => 'Recoger', 'tipo' => $text, 'categoria' => 'examen'],
        ['clave' => 'a_las', 'etiqueta' => 'a las', 'tipo' => $time, 'categoria' => 'examen'],
        ['clave' => 'cfdi', 'etiqueta' => 'CFDI', 'tipo' => $text, 'categoria' => 'fiscal'],
        ['clave' => 'fecha_linguaskill', 'etiqueta' => 'Fecha Linguaskill', 'tipo' => $date, 'categoria' => 'linguaskill'],
        ['clave' => 'hora_linguaskill', 'etiqueta' => 'Hora Linguaskill', 'tipo' => $time, 'categoria' => 'linguaskill'],
        ['clave' => 'ine_linguaskill', 'etiqueta' => 'INE Linguaskill', 'tipo' => $bool, 'categoria' => 'linguaskill'],
        ['clave' => 'reglamento_linguaskill', 'etiqueta' => 'Reglamento Linguaskill', 'tipo' => $bool, 'categoria' => 'linguaskill'],
        ['clave' => 'token', 'etiqueta' => 'TOKEN', 'tipo' => $text, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'ej_token', 'etiqueta' => 'Ej. TOKEN', 'tipo' => $text, 'categoria' => 'acceso_supervisor'],
        ['clave' => 'sexo', 'etiqueta' => 'Sexo', 'tipo' => $text, 'categoria' => 'alumno'],
        ['clave' => 'nacionalidad', 'etiqueta' => 'Nacionalidad', 'tipo' => $text, 'categoria' => 'alumno'],
    ];
}

function certificacion_campos_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS certificacion_campo_catalogo (
            clave VARCHAR(60) NOT NULL,
            etiqueta VARCHAR(120) NOT NULL,
            tipo ENUM(\'text\',\'date\',\'time\',\'email\',\'phone\',\'bool\') NOT NULL DEFAULT \'text\',
            categoria ENUM(\'alumno\',\'examen\',\'acceso_supervisor\',\'linguaskill\',\'fiscal\') NOT NULL DEFAULT \'alumno\',
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS producto_certificacion_campo (
            id_producto INT UNSIGNED NOT NULL,
            clave_campo VARCHAR(60) NOT NULL,
            obligatorio TINYINT(1) NOT NULL DEFAULT 0,
            llenado_por ENUM(\'asesor\',\'alumno\',\'supervisor\') NOT NULL DEFAULT \'asesor\',
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id_producto, clave_campo),
            KEY idx_pcc_orden (id_producto, orden)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column(
            $pdo,
            'certificacion_solicitudes',
            'datos_formulario',
            'JSON NULL COMMENT \'Valores capturados según plantilla del producto\'',
            'notas'
        );
    }

    certificacion_campos_seed_catalogo($pdo);
}

function certificacion_campos_seed_catalogo(PDO $pdo): void
{
    $st = $pdo->prepare(
        'INSERT INTO certificacion_campo_catalogo (clave, etiqueta, tipo, categoria, activo)
         VALUES (?,?,?,?,1)
         ON DUPLICATE KEY UPDATE etiqueta = VALUES(etiqueta), tipo = VALUES(tipo), categoria = VALUES(categoria)'
    );
    foreach (certificacion_campos_catalogo_definicion() as $c) {
        $st->execute([$c['clave'], $c['etiqueta'], $c['tipo'], $c['categoria']]);
    }
}

/** @return list<array<string, mixed>> */
function certificacion_campos_para_producto(PDO $pdo, int $idProducto, ?string $llenadoPor = null): array
{
    certificacion_campos_ensure_schema($pdo);
    $params = [$idProducto];
    $sql = 'SELECT pcc.*, c.etiqueta, c.tipo, c.categoria
            FROM producto_certificacion_campo pcc
            INNER JOIN certificacion_campo_catalogo c ON c.clave = pcc.clave_campo AND c.activo = 1
            WHERE pcc.id_producto = ?';
    if ($llenadoPor !== null && $llenadoPor !== '') {
        $sql .= ' AND pcc.llenado_por = ?';
        $params[] = $llenadoPor;
    }
    $sql .= ' ORDER BY pcc.orden, c.etiqueta';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @param list<array{clave_campo:string,obligatorio?:bool,llenado_por?:string,orden?:int}> $campos */
function certificacion_campos_guardar_producto(PDO $pdo, int $idProducto, array $campos): void
{
    certificacion_campos_ensure_schema($pdo);
    $pdo->prepare('DELETE FROM producto_certificacion_campo WHERE id_producto = ?')->execute([$idProducto]);
    $st = $pdo->prepare(
        'INSERT INTO producto_certificacion_campo (id_producto, clave_campo, obligatorio, llenado_por, orden)
         VALUES (?,?,?,?,?)'
    );
    $orden = 0;
    foreach ($campos as $c) {
        $clave = trim((string) ($c['clave_campo'] ?? ''));
        if ($clave === '') {
            continue;
        }
        $llenado = (string) ($c['llenado_por'] ?? 'asesor');
        if (!in_array($llenado, ['asesor', 'alumno', 'supervisor'], true)) {
            $llenado = 'asesor';
        }
        $st->execute([
            $idProducto,
            $clave,
            !empty($c['obligatorio']) ? 1 : 0,
            $llenado,
            (int) ($c['orden'] ?? $orden),
        ]);
        $orden++;
    }
}

/** Campos que ya captura el formulario base de pre-registro asesor. */
function certificacion_campos_claves_formulario_base(): array
{
    return [
        'nombre', 'nombres', 'apellido_paterno', 'apellido_materno',
        'telefono', 'email', 'nombre_completo', 'certificacion',
    ];
}

/** @param list<array<string, mixed>> $campos */
function certificacion_campos_filtrar_preregistro_asesor(array $campos): array
{
    $omit = array_flip(certificacion_campos_claves_formulario_base());

    return array_values(array_filter($campos, static function (array $c) use ($omit): bool {
        $clave = (string) ($c['clave_campo'] ?? '');
        if ($clave === '' || isset($omit[$clave])) {
            return false;
        }
        if (($c['categoria'] ?? '') === 'acceso_supervisor') {
            return false;
        }

        return true;
    }));
}
