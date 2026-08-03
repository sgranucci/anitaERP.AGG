<?php

namespace App\Services\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Support\Sueldos\ConceptoAnitaMapeo;
use App\Support\Sueldos\ReciboBaseCalculoSupport;
use App\Support\Sueldos\RubroCostoLaboral;

/**
 * Reclasifica conceptos del rango papo–uapo según Anita hab_va_recibo:
 *   va al recibo → contribucion (+ rubro)
 *   no va → informativo (solo reportes / AS / bases)
 * También corrige va_recibo ERP de todo el catálogo según el bridge.
 */
class ConceptoPapoReclasificarService
{
    /**
     * @return array{
     *   leidos_anita: int,
     *   actualizados: int,
     *   a_contribucion: int,
     *   a_informativo: int,
     *   va_recibo_corregidos: int,
     *   unidad_medida_asignadas: int,
     *   errores: list<string>
     * }
     */
    public function reclasificarDesdeAnita(): array
    {
        $out = [
            'leidos_anita' => 0,
            'actualizados' => 0,
            'a_contribucion' => 0,
            'a_informativo' => 0,
            'va_recibo_corregidos' => 0,
            'unidad_medida_asignadas' => 0,
            'errores' => [],
        ];

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'tabla' => 'haberes',
            'campos' => 'hab_codigo,hab_desc,hab_va_recibo,hab_tipo',
            'where' => '1=1',
            'sistema' => 'sueldos',
        ]);
        $parsed = ApiAnita::parsearRespuestaLista($raw);
        if (! empty($parsed['error_lectura'])) {
            $out['errores'][] = (string) $parsed['error_lectura'];

            return $out;
        }

        /** @var array<int, object> $porCodigo */
        $porCodigo = [];
        foreach ($parsed['filas'] ?? [] as $row) {
            $codigo = (int) ($row->hab_codigo ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $porCodigo[$codigo] = $row;
        }
        $out['leidos_anita'] = count($porCodigo);

        Concepto_Sueldos::query()->orderBy('codigo')->chunkById(100, function ($chunk) use ($porCodigo, &$out) {
            foreach ($chunk as $concepto) {
                $codigo = (int) $concepto->codigo;
                $row = $porCodigo[$codigo] ?? null;
                if ($row === null) {
                    continue;
                }

                $habTipo = (int) ($row->hab_tipo ?? 1);
                $habVa = $row->hab_va_recibo ?? 0;
                $tipoNuevo = ConceptoAnitaMapeo::tipo($codigo, $habTipo, $habVa);
                $vaNuevo = ConceptoAnitaMapeo::vaRecibo($habVa);
                $rubro = $tipoNuevo === 'contribucion'
                    ? RubroCostoLaboral::inferirDesdeDescripcion((string) ($row->hab_desc ?? $concepto->descripcion))
                    : null;
                $sumaA = ConceptoAnitaMapeo::sumaA($tipoNuevo);

                $dirty = false;
                if ((string) $concepto->tipo !== $tipoNuevo) {
                    if ($tipoNuevo === 'contribucion') {
                        $out['a_contribucion']++;
                    }
                    if ($tipoNuevo === 'informativo' && (string) $concepto->tipo !== 'informativo') {
                        $out['a_informativo']++;
                    }
                    $concepto->tipo = $tipoNuevo;
                    $dirty = true;
                }
                if ((bool) $concepto->va_recibo !== $vaNuevo) {
                    $concepto->va_recibo = $vaNuevo;
                    $out['va_recibo_corregidos']++;
                    $dirty = true;
                }
                if (($concepto->suma_a ?? null) !== $sumaA) {
                    $concepto->suma_a = $sumaA;
                    $dirty = true;
                }
                if (($concepto->rubro_costo_laboral ?? null) !== $rubro) {
                    $concepto->rubro_costo_laboral = $rubro;
                    $dirty = true;
                }

                if (blank($concepto->unidad_medida)) {
                    $unidad = ReciboBaseCalculoSupport::inferirUnidad(
                        (string) ($row->hab_desc ?? $concepto->descripcion),
                        $concepto->factor !== null ? (float) $concepto->factor : null,
                        $tipoNuevo
                    );
                    if ($unidad !== null) {
                        $concepto->unidad_medida = $unidad;
                        $out['unidad_medida_asignadas']++;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $concepto->save();
                    $out['actualizados']++;
                }
            }
        });

        return $out;
    }

    /**
     * Precarga unidad_medida LSD en conceptos que aún no la tienen.
     *
     * @return array{asignadas: int, revisados: int}
     */
    public function precargarUnidadesMedida(): array
    {
        $out = ['asignadas' => 0, 'revisados' => 0];
        Concepto_Sueldos::query()
            ->where(function ($q) {
                $q->whereNull('unidad_medida')->orWhere('unidad_medida', '');
            })
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$out) {
                foreach ($chunk as $c) {
                    $out['revisados']++;
                    $u = ReciboBaseCalculoSupport::inferirUnidad(
                        (string) $c->descripcion,
                        $c->factor !== null ? (float) $c->factor : null,
                        (string) $c->tipo
                    );
                    if ($u === null) {
                        continue;
                    }
                    $c->unidad_medida = $u;
                    $c->save();
                    $out['asignadas']++;
                }
            });

        return $out;
    }
}
