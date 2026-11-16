<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

/**
 * Entidad que no corre el reporte pesado dentro del explorador CI:
 * arma filtros y abre el reporte nativo (mayor, etc.).
 */
interface EntidadSemanticaPuenteReporte
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function urlReporte(array $filtros): string;

    /** Texto de ayuda en el explorador CI. */
    public function ayudaPuente(): string;
}
