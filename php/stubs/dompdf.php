<?php

/**
 * Stubs para análisis estático (IDE). Dompdf real: composer require dompdf/dompdf
 */
namespace Dompdf;

class Options
{
    public function set(string $key, $value): void
    {
    }
}

class Dompdf
{
    public function __construct(?Options $options = null)
    {
    }

    public function loadHtml(string $html, ?string $encoding = null): void
    {
    }

    /**
     * @param string|array<int, float|int> $size Nombre de papel o tamaño [x1, y1, x2, y2] en puntos
     */
    public function setPaper(string|array $size, ?string $orientation = null): void
    {
    }

    public function render(): void
    {
    }

    /** @return string */
    public function output(array $options = [])
    {
        return '';
    }
}
