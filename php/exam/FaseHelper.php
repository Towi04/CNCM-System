<?php

namespace HayExam;

use PDO;

class FaseHelper
{
    /** Niveles CEFR (prefijo antes de 1-4, 5-8, 9-12). */
    public const NIVELES = ['A1', 'A1+', 'A2', 'A2+', 'B1', 'B1+', 'B2', 'B2+'];

    public static function normalizar(string $fase): string
    {
        return trim($fase);
    }

    /**
     * Extrae el nivel de una fase completa (ej. "A1 1-4" → "A1", "B1+ 5-8" → "B1+").
     */
    public static function extraerNivel(string $fase): string
    {
        $fase = self::normalizar($fase);
        if ($fase === '') {
            return '';
        }
        foreach (array_reverse(self::NIVELES) as $n) {
            if (stripos($fase, $n) === 0) {
                return $n;
            }
        }
        return '';
    }

    /**
     * Opciones de fase/nivel al calificar según tipo de examen.
     *
     * @param array<string> $fasesCompletas
     * @return array<string>
     */
    public static function fasesParaRegistro(string $tipoExamen, array $fasesCompletas): array
    {
        $fasesCompletas = array_values(array_filter(array_map([self::class, 'normalizar'], $fasesCompletas)));
        if ($tipoExamen !== 'nivel') {
            return $fasesCompletas;
        }
        $niveles = [];
        foreach ($fasesCompletas as $f) {
            $n = self::extraerNivel($f);
            if ($n !== '') {
                $niveles[$n] = true;
            }
        }
        $out = array_keys($niveles);
        usort($out, function ($a, $b) {
            $ia = array_search($a, self::NIVELES, true);
            $ib = array_search($b, self::NIVELES, true);
            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
        });
        return $out;
    }

    public static function validar(string $fase): void
    {
        if (self::normalizar($fase) === '') {
            throw new \InvalidArgumentException('La fase es obligatoria (ej. "A1 1-4", "Windows").');
        }
    }

    /** @param array<string> $fases */
    public static function sqlIn(PDO $pdo, array $fases): string
    {
        $fases = array_values(array_unique(array_filter(array_map([self::class, 'normalizar'], $fases))));
        if (empty($fases)) {
            return ' AND 1=0';
        }
        $quoted = array_map(fn($f) => $pdo->quote($f), $fases);
        return ' AND fase IN (' . implode(',', $quoted) . ')';
    }

    public static function sqlEquals(PDO $pdo, string $fase): string
    {
        return 'fase = ' . $pdo->quote(self::normalizar($fase));
    }
}
