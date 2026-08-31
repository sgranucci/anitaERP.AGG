<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Concepto_Sueldos;
use App\Support\Sueldos\ConceptoTipo;
use App\Support\Sueldos\Lsd\LsdAnsiSupport;
use App\Support\Sueldos\Lsd\LsdConceptoAfipCatalogo;
use App\Support\Sueldos\Lsd\LsdConceptosExportMeta;
use App\Support\Sueldos\Lsd\LsdRegistroSupport;

class LsdExportadorConceptosService
{
    /**
     * @return array{contenido: string, cantidad: int, omitidos: int, nombre: string}
     */
    public function generar(): array
    {
        $conceptos = Concepto_Sueldos::query()
            ->where('activo', true)
            ->whereNotIn('tipo', ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES)
            ->orderBy('codigo')
            ->get();

        $lineas = [];
        $omitidos = 0;
        foreach ($conceptos as $c) {
            $afip = LsdConceptoAfipCatalogo::normalizarCodigo($c->concepto_afip);
            if ($afip === null || ! LsdConceptoAfipCatalogo::codigoValido($afip)) {
                $omitidos++;

                continue;
            }
            $codigoEmp = trim((string) ($c->codigo_lsd_empleador ?? ''));
            if ($codigoEmp === '') {
                $codigoEmp = LsdConceptoAfipCatalogo::codigoEmpleadorDesdeInterno($c->codigo);
            }
            $lineas[] = LsdRegistroSupport::registroConcepto([
                'concepto_afip' => $afip,
                'codigo_empleador' => $codigoEmp,
                'descripcion' => $c->descripcion,
                'repetible' => (bool) $c->lsd_repetible,
                'tipo' => LsdConceptoAfipCatalogo::tipoDesdeCodigo($afip),
                'subsistemas' => is_array($c->lsd_subsistemas) ? $c->lsd_subsistemas : null,
            ]);
        }

        $cantidad = count($lineas);
        LsdConceptosExportMeta::marcar($cantidad);

        return [
            'contenido' => LsdAnsiSupport::archivo($lineas),
            'cantidad' => $cantidad,
            'omitidos' => $omitidos,
            'nombre' => 'LSD_conceptos_'.date('Ymd_His').'.txt',
        ];
    }
}
