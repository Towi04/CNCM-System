<?php
declare(strict_types=1);

namespace HayTutor;

use PDO;

/**
 * Contexto operativo del sistema HAY según permisos RBAC del usuario.
 * Tarifas, alumnos, podio, pre-registros — solo lo que su rol puede ver en el panel.
 */
final class InstitutionalSystemContextRetriever
{
    public function __construct(private PDO $pdo)
    {
    }

    public function buscar(string $pregunta, int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $bloques = [];
        $encabezado = $this->encabezadoUsuario($userId);
        if ($encabezado !== '') {
            $bloques[] = $encabezado;
        }

        if ($this->intencionTarifas($pregunta) && $this->puedeVerTarifas()) {
            $ctx = $this->contextoTarifas($pregunta);
            if ($ctx !== '') {
                $bloques[] = $ctx;
            }
        }

        if ($this->intencionPodio($pregunta) && $this->puedeVerPodio()) {
            $ctx = $this->contextoPodio();
            if ($ctx !== '') {
                $bloques[] = $ctx;
            }
        }

        $termino = $this->extraerTerminoBusqueda($pregunta);
        if ($termino !== null && $this->intencionPersona($pregunta)) {
            if ($this->puedeVerAlumnos()) {
                $ctx = $this->contextoAlumnos($termino);
                if ($ctx !== '') {
                    $bloques[] = $ctx;
                }
            } elseif ($this->puedeVerPreregistro()) {
                $ctx = $this->contextoPreregistros($termino);
                if ($ctx !== '') {
                    $bloques[] = $ctx;
                }
            }
        }

        if ($bloques === []) {
            return '';
        }

        return "[DATOS INSTITUCIONALES HAY — usar como fuente autorizada; no inventar cifras ni contactos]\n\n"
            . implode("\n\n---\n\n", $bloques);
    }

    private function cap(string $cap): bool
    {
        if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
            return true;
        }

        return function_exists('rbac_cap') && rbac_cap($cap);
    }

    private function puedeVerTarifas(): bool
    {
        return $this->cap('menu_preregistro')
            || $this->cap('menu_consulta_adeudo')
            || $this->cap('admin_catalogo')
            || $this->cap('menu_punto_venta')
            || $this->cap('menu_venta_productos');
    }

    private function puedeVerAlumnos(): bool
    {
        return $this->cap('menu_alumnos') || $this->cap('menu_consulta_adeudo');
    }

    private function puedeVerContactoCompleto(): bool
    {
        return $this->cap('menu_alumnos') || $this->cap('menu_consulta_adeudo');
    }

    private function puedeVerPodio(): bool
    {
        return $this->cap('menu_podio_ventas');
    }

    private function puedeVerPreregistro(): bool
    {
        return $this->cap('menu_preregistro');
    }

    private function idPlantel(): int
    {
        return function_exists('plantel_scope_id') ? plantel_scope_id($this->pdo) : 0;
    }

    private function encabezadoUsuario(int $userId): string
    {
        $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';
        $labels = function_exists('rbac_roles_etiquetas') ? rbac_roles_etiquetas() : [];
        $rolLabel = $labels[$rol] ?? $rol;
        $st = $this->pdo->prepare(
            'SELECT CONCAT(u.nombre, \' \', u.apellido) AS nombre, p.nombre AS plantel
             FROM usuarios u
             LEFT JOIN planteles p ON p.id_plantel = u.id_plantel
             WHERE u.id_usuario = ? LIMIT 1'
        );
        $st->execute([$userId]);
        $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return '[USUARIO HAY] ' . trim((string) ($u['nombre'] ?? ''))
            . ' · Rol: ' . $rolLabel
            . ($u['plantel'] ?? '' ? ' · Plantel: ' . $u['plantel'] : '')
            . "\nResponda solo con datos del bloque institucional; si falta información, indíquelo.";
    }

    private function intencionTarifas(string $pregunta): bool
    {
        return (bool) preg_match(
            '/\b(tarifa|tarifas|costo|costos|precio|precios|inscripci[oó]n|colegiatura|mensualidad|cuatrimestre|semestre|pago|pagos|cu[aá]nto cuesta|precio de|costo de|apoyo educativo|referencia)\b/ui',
            $pregunta
        );
    }

    private function intencionPodio(string $pregunta): bool
    {
        return (bool) preg_match(
            '/\b(podio|ranking|top asesores|mejores asesores|captaci[oó]n|ventas del|inscritos de la semana)\b/ui',
            $pregunta
        );
    }

    private function intencionPersona(string $pregunta): bool
    {
        return (bool) preg_match(
            '/\b(alumno|alumna|estudiante|tel[eé]fono|correo|email|contacto|control|matr[ií]cula|pr[eé]-?registro|prospecto|inscrito)\b/ui',
            $pregunta
        );
    }

    private function extraerTerminoBusqueda(string $pregunta): ?string
    {
        $t = trim($pregunta);
        if ($t === '') {
            return null;
        }
        if (preg_match('/\b(?:n[uú]mero de control|no\.?\s*control|control|matr[ií]cula)\s*[:\-]?\s*([A-Za-z0-9\-]+)/ui', $t, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/(?:alumno|alumna|estudiante|tel[eé]fono de|contacto de|correo de|datos de|informaci[oó]n de)\s+(.+?)(?:\?|\.|$)/ui', $t, $m)) {
            $term = trim(preg_replace('/\s*(tel[eé]fono|correo|email).*$/ui', '', $m[1]) ?? $m[1]);

            return $term !== '' ? $term : null;
        }
        if (preg_match('/\b([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)+)\b/u', $t, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\b([a-záéíóúñ]{3,}(?:\s+[a-záéíóúñ]{3,})+)\b/ui', $t, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function filtroEspecialidadDesdePregunta(string $pregunta): ?string
    {
        $t = mb_strtolower($pregunta);
        if (preg_match('/\b(ingl[eé]s|english|ing)\b/u', $t)) {
            return 'ing';
        }
        if (preg_match('/\b(computaci[oó]n|comp)\b/u', $t)) {
            return 'comp';
        }
        if (preg_match('/\b(kids|infantil)\b/u', $t)) {
            return 'kids';
        }
        if (preg_match('/\b(preparatoria|prep)\b/u', $t)) {
            return 'prep';
        }

        return null;
    }

    private function contextoTarifas(string $pregunta): string
    {
        if (!function_exists('catalog_listar_especialidades') || !function_exists('catalog_tarifa_snapshot_row')) {
            return '';
        }
        catalog_ensure_schema($this->pdo);

        $filtroEsp = $this->filtroEspecialidadDesdePregunta($pregunta);
        $filtros = ['activo' => '1'];
        if ($filtroEsp !== null) {
            $filtros['q'] = $filtroEsp;
        }

        $rows = catalog_listar_especialidades($this->pdo, $filtros);
        if ($filtroEsp !== null) {
            $rows = array_values(array_filter($rows, function ($e) use ($filtroEsp) {
                $clave = strtoupper((string) ($e['clave'] ?? ''));
                $nombre = mb_strtolower((string) ($e['nombre'] ?? ''));
                if ($filtroEsp === 'ing') {
                    return str_starts_with($clave, 'ING') || str_contains($nombre, 'ingl');
                }
                if ($filtroEsp === 'comp') {
                    return str_starts_with($clave, 'COMP') || str_contains($nombre, 'comput');
                }
                if ($filtroEsp === 'kids') {
                    return ($e['modalidad'] ?? '') === 'kids' || str_contains($nombre, 'kids');
                }
                if ($filtroEsp === 'prep') {
                    return in_array($e['modalidad'] ?? '', ['prep_abierta', 'prep_escolarizada'], true)
                        || str_contains($nombre, 'prep');
                }

                return true;
            }));
        }

        if ($rows === []) {
            return '[TARIFAS CNCM] No se encontraron especialidades activas para esa consulta.';
        }

        $modalidades = function_exists('catalog_modalidades_etiquetas')
            ? catalog_modalidades_etiquetas()
            : [];
        $lineas = ['[TARIFAS OFICIALES CNCM — catálogo vigente]'];
        foreach (array_slice($rows, 0, 8) as $e) {
            $snap = catalog_tarifa_snapshot_row($e);
            $mod = $modalidades[$e['modalidad'] ?? 'regular'] ?? ($e['modalidad'] ?? '');
            $lineas[] = '• ' . ($e['clave'] ?? '') . ' — ' . ($e['nombre'] ?? '')
                . ($mod !== '' ? ' (' . $mod . ')' : '');
            $lineas[] = '  Inscripción referencia: '
                . catalog_format_mxn((float) ($snap['costo_inscripcion_referencia'] ?? 0))
                . ' · Apoyo educativo: '
                . catalog_format_mxn((float) ($snap['costo_inscripcion_apoyo'] ?? 0));
            if ((float) ($snap['costo_mensualidad_apoyo'] ?? 0) > 0) {
                $lineas[] = '  Mensualidad ref/apoyo: '
                    . catalog_format_mxn((float) ($snap['costo_mensualidad_referencia'] ?? 0))
                    . ' / '
                    . catalog_format_mxn((float) ($snap['costo_mensualidad_apoyo'] ?? 0));
            }
            if ((float) ($snap['costo_semanal_apoyo'] ?? 0) > 0) {
                $lineas[] = '  Semanal ref/apoyo: '
                    . catalog_format_mxn((float) ($snap['costo_semanal_referencia'] ?? 0))
                    . ' / '
                    . catalog_format_mxn((float) ($snap['costo_semanal_apoyo'] ?? 0));
            }
            if ((float) ($snap['costo_pronto_pago_apoyo'] ?? 0) > 0) {
                $lineas[] = '  Pronto pago (1-6 del mes) ref/apoyo: '
                    . catalog_format_mxn((float) ($snap['costo_pronto_pago_referencia'] ?? 0))
                    . ' / '
                    . catalog_format_mxn((float) ($snap['costo_pronto_pago_apoyo'] ?? 0));
            }
            if ($snap['costo_cuatrimestre'] !== null && (float) $snap['costo_cuatrimestre'] > 0) {
                $lineas[] = '  Cuatrimestre: ' . catalog_format_mxn((float) $snap['costo_cuatrimestre']);
            }
            if ($snap['costo_anual'] !== null && (float) $snap['costo_anual'] > 0) {
                $lineas[] = '  Anual: ' . catalog_format_mxn((float) $snap['costo_anual']);
            }
            if (function_exists('catalog_colegiatura_resumen')) {
                $lineas[] = '  Resumen colegiatura: ' . catalog_colegiatura_resumen($e);
            }
        }
        $lineas[] = 'Nota: al inscribir se congelan tarifas en el expediente del alumno; estos son precios de catálogo.';

        return implode("\n", $lineas);
    }

    private function contextoPodio(): string
    {
        if (!function_exists('gerente_podio_asesores')) {
            return '';
        }
        $idPlantel = $this->idPlantel();
        $data = gerente_podio_asesores($this->pdo, $idPlantel > 0 ? $idPlantel : null);
        $items = $data['items'] ?? [];
        if ($items === []) {
            return '[PODIO ASESORES] Sin actividad registrada en el periodo '
                . ($data['desde'] ?? '') . ' — ' . ($data['hasta'] ?? '') . '.';
        }

        $lineas = ['[PODIO ASESORES CNCM — semana ' . ($data['desde'] ?? '') . ' a ' . ($data['hasta'] ?? '') . ']'];
        $pos = 1;
        foreach (array_slice($items, 0, 10) as $row) {
            $lineas[] = $pos . '. ' . trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? ''))
                . ' (' . ($row['plantel'] ?? '') . ')'
                . ' — Entrevistas: ' . (int) ($row['entrevistas'] ?? 0)
                . ', Pre-registros: ' . (int) ($row['preregistros'] ?? 0)
                . ', Inscritos: ' . (int) ($row['inscritos'] ?? 0)
                . ', Puntos: ' . (int) ($row['puntos'] ?? 0);
            $pos++;
        }

        return implode("\n", $lineas);
    }

    private function contextoAlumnos(string $termino): string
    {
        $idPlantel = $this->idPlantel();
        if ($idPlantel <= 0) {
            return '';
        }

        $alumno = null;
        if (function_exists('pago_buscar_alumno_control')) {
            $alumno = pago_buscar_alumno_control($this->pdo, $termino, $idPlantel);
        }
        $lista = [];
        if ($alumno === null && function_exists('alumno_listar')) {
            $lista = alumno_listar($this->pdo, $idPlantel, ['q' => $termino, 'estado' => 'todos']);
            if ($lista !== []) {
                $alumno = $lista[0];
            }
        }

        if ($alumno === null && $lista === []) {
            return '[ALUMNOS] No se encontró alumno activo con: ' . $termino;
        }

        $mostrarContacto = $this->puedeVerContactoCompleto();
        $lineas = ['[EXPEDIENTE ALUMNO — datos del sistema HAY]'];

        $aMostrar = $alumno !== null ? [$alumno] : array_slice($lista, 0, 3);
        foreach ($aMostrar as $a) {
            $nombre = function_exists('alumno_nombre_completo')
                ? alumno_nombre_completo($a)
                : trim(($a['nombres'] ?? $a['nombre'] ?? '') . ' ' . ($a['apellido_paterno'] ?? $a['apellido'] ?? ''));
            $lineas[] = '• ' . $nombre
                . ' · Control: ' . ($a['numero_control'] ?? '—')
                . ' · Estado: ' . ($a['estado'] ?? '—');
            $lineas[] = '  Especialidad: ' . ($a['especialidad_nombre'] ?? '—');
            if (!empty($a['grupos_txt'])) {
                $lineas[] = '  Grupos: ' . $a['grupos_txt'];
            }
            if (!empty($a['asesor_nombre'])) {
                $lineas[] = '  Asesor: ' . $a['asesor_nombre'];
            }
            if ($mostrarContacto) {
                if (!empty($a['telefono'])) {
                    $lineas[] = '  Teléfono: ' . $a['telefono'];
                }
                if (!empty($a['email'])) {
                    $lineas[] = '  Email: ' . $a['email'];
                }
                if (!empty($a['forma_pago'])) {
                    $lineas[] = '  Forma de pago: ' . $a['forma_pago'];
                }
            } else {
                $lineas[] = '  (Contacto restringido para su rol; consulte recepción si necesita teléfono/email.)';
            }
        }

        return implode("\n", $lineas);
    }

    private function contextoPreregistros(string $termino): string
    {
        if (!function_exists('preregistro_listar')) {
            return '';
        }
        $idPlantel = $this->idPlantel();
        if ($idPlantel <= 0) {
            return '';
        }
        preregistro_ensure_schema($this->pdo);
        $rows = preregistro_listar($this->pdo, $idPlantel, ['q' => $termino]);
        if ($rows === []) {
            return '[PRE-REGISTROS] No se encontró prospecto con: ' . $termino;
        }

        $lineas = ['[PRE-REGISTROS CNCM — prospectos]'];
        foreach (array_slice($rows, 0, 3) as $p) {
            $nombre = trim(($p['nombres'] ?? '') . ' ' . ($p['apellido_paterno'] ?? '') . ' ' . ($p['apellido_materno'] ?? ''));
            $lineas[] = '• ' . $nombre . ' · Estado: ' . ($p['estado'] ?? '—');
            $lineas[] = '  Especialidad: ' . ($p['especialidad_nombre'] ?? '—');
            if (!empty($p['telefono'])) {
                $lineas[] = '  Teléfono: ' . $p['telefono'];
            }
            if (!empty($p['telefono2'])) {
                $lineas[] = '  Teléfono 2: ' . $p['telefono2'];
            }
            if (!empty($p['email'])) {
                $lineas[] = '  Email: ' . $p['email'];
            }
            if (!empty($p['asesor_nombre'])) {
                $lineas[] = '  Asesor registro: ' . $p['asesor_nombre'];
            }
        }

        return implode("\n", $lineas);
    }
}
