<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Tipotransaccion_Compra;

/**
 * Corrige líneas de concepto IVA según la OC: reubicación FNx/FIx o prorrateo multi-CC (FPB/…).
 *
 * Misma regla que recibe_comprobante / PDF-IA, para el circuito LEGAJO (formulario) y reparos.
 */
final class PrecargaProveedorIvaLineasDesdeOcSupport
{
    /**
     * @param  list<array<string, mixed>>  $lineas  cada una con concepto_ivacompra_id y monto
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   cambio: bool,
     *   modo: 'ninguno'|'reubicar'|'prorrateo',
     *   avisos: list<string>,
     *   tipos_origen: list<string>,
     *   pesos_por_fino: array<string, float>
     * }
     */
    public function corregir(array $lineas, ?Ordencompra $oc, string $abreviaturaTipo): array
    {
        $vacio = [
            'lineas' => $lineas,
            'cambio' => false,
            'modo' => 'ninguno',
            'avisos' => [],
            'tipos_origen' => [],
            'pesos_por_fino' => [],
        ];
        if ($lineas === [] || $oc === null) {
            return $vacio;
        }

        $abrev = strtoupper(trim($abreviaturaTipo));
        if ($abrev === '') {
            return $vacio;
        }

        if (PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado($abrev)) {
            return $this->corregirProrrateo($lineas, $oc, $abrev);
        }

        return $this->corregirReubicar($lineas, $abrev);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   cambio: bool,
     *   modo: 'ninguno'|'reubicar'|'prorrateo',
     *   avisos: list<string>,
     *   tipos_origen: list<string>,
     *   pesos_por_fino: array<string, float>
     * }
     */
    private function corregirProrrateo(array $lineas, Ordencompra $oc, string $abrev): array
    {
        $tipoItem = PrecargaProveedorAbreviaturaTipoSupport::tipoItemDesdeOrdencompra($oc);
        $centros = PrecargaProveedorProrrateoMultiCcSupport::centrosConPesoDesdeOrdencompraErp($oc);
        $familia = PrecargaProveedorProrrateoMultiCcSupport::familiaDesdeAbreviatura($abrev);
        $prorrateo = app(PrecargaProveedorProrrateoMultiCcSupport::class)
            ->resolverDesdeCentros($familia, $tipoItem, $centros);

        if (! $prorrateo['activo'] || count($prorrateo['tipos_origen']) < 2) {
            return [
                'lineas' => $lineas,
                'cambio' => false,
                'modo' => 'ninguno',
                'avisos' => [],
                'tipos_origen' => $prorrateo['tipos_origen'],
                'pesos_por_fino' => $prorrateo['pesos_por_fino'],
            ];
        }

        $out = app(PrecargaProveedorProrrateoMultiCcSupport::class)->prorratearLineasIva(
            $lineas,
            $prorrateo['pesos_por_fino'],
            $prorrateo['tipos_origen'],
        );

        $cambio = ! $this->mismasLineasIva($lineas, $out);

        return [
            'lineas' => $out,
            'cambio' => $cambio,
            'modo' => $cambio ? 'prorrateo' : 'ninguno',
            'avisos' => $cambio
                ? ['IVA prorrateado multi-CC ('.implode('/', $prorrateo['centros']).') → '.$abrev
                    .' desde '.implode('+', $prorrateo['tipos_origen']).'.']
                : [],
            'tipos_origen' => $prorrateo['tipos_origen'],
            'pesos_por_fino' => $prorrateo['pesos_por_fino'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   cambio: bool,
     *   modo: 'ninguno'|'reubicar'|'prorrateo',
     *   avisos: list<string>,
     *   tipos_origen: list<string>,
     *   pesos_por_fino: array<string, float>
     * }
     */
    private function corregirReubicar(array $lineas, string $abrev): array
    {
        $tipoId = (int) (Tipotransaccion_Compra::query()->where('abreviatura', $abrev)->value('id') ?? 0);
        if ($tipoId <= 0) {
            return [
                'lineas' => $lineas,
                'cambio' => false,
                'modo' => 'ninguno',
                'avisos' => [],
                'tipos_origen' => [],
                'pesos_por_fino' => [],
            ];
        }

        $res = PrecargaProveedorConceptosTipoImputadoSupport::reubicar($lineas, $tipoId, $abrev);
        $cambio = (bool) ($res['reubico'] ?? false);

        return [
            'lineas' => $res['lineas'],
            'cambio' => $cambio,
            'modo' => $cambio ? 'reubicar' : 'ninguno',
            'avisos' => $cambio ? array_values($res['avisos'] ?? []) : [],
            'tipos_origen' => [],
            'pesos_por_fino' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $a
     * @param  list<array<string, mixed>>  $b
     */
    private function mismasLineasIva(array $a, array $b): bool
    {
        $norm = static function (array $lineas): array {
            $out = [];
            foreach ($lineas as $l) {
                $id = (int) ($l['concepto_ivacompra_id'] ?? 0);
                $monto = round((float) ($l['monto'] ?? 0), 2);
                if ($id <= 0 || abs($monto) < 0.0001) {
                    continue;
                }
                $out[] = $id.':'.number_format($monto, 2, '.', '');
            }
            sort($out);

            return $out;
        };

        return $norm($a) === $norm($b);
    }
}
