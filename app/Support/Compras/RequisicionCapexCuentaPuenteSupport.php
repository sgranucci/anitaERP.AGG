<?php

namespace App\Support\Compras;

use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloCuentaCompraGastoSupport;

/**
 * Si el artículo imputa a la cuenta puente de bienes de uso, la línea de
 * requisición exige CAPEX. La cuenta es 123010030 «COMPRAS BS. DE USO -CTA.PUENTE»
 * (mismo código en cada empresa). Se toma la cuenta de compras y, si no hay, la de gastos.
 */
final class RequisicionCapexCuentaPuenteSupport
{
    public const CODIGO_CUENTA = '123010030';

    /**
     * @param  array{codigo?: string, nombre?: string}|null  $cuenta
     */
    public static function cuentaExigeCapex(?array $cuenta): bool
    {
        if ($cuenta === null) {
            return false;
        }

        return trim((string) ($cuenta['codigo'] ?? '')) === self::CODIGO_CUENTA;
    }

    /**
     * @param  array<string, mixed>  $payload  articulo_ids[], cantidades[], capex_ids[], empresa_id
     * @param  bool  $alEnviarArbol  true cuando el corte es el envío al árbol (el texto dice qué hacer después)
     *
     * @throws \InvalidArgumentException
     */
    public static function validar(array $payload, bool $alEnviarArbol = false): void
    {
        if (! OrdencompraUiConfigSupport::pedirPartidaCapex()) {
            return;
        }

        $articuloIds = $payload['articulo_ids'] ?? [];
        if (! is_array($articuloIds) || $articuloIds === []) {
            return;
        }

        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $ids = [];
        foreach ($articuloIds as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[] = $n;
            }
        }

        $cuentas = ArticuloCuentaCompraGastoSupport::etiquetasPorArticulos($ids, $empresaId);
        $articulos = $ids === []
            ? collect()
            : Articulo::query()->whereIn('id', $ids)->get(['id', 'sku', 'descripcion'])->keyBy('id');

        $faltan = [];
        $n = count($articuloIds);
        for ($i = 0; $i < $n; $i++) {
            $articuloId = (int) ($articuloIds[$i] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            $cantidad = (float) ($payload['cantidades'][$i] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }
            if (! self::cuentaExigeCapex($cuentas[$articuloId] ?? null)) {
                continue;
            }
            $capexId = $payload['capex_ids'][$i] ?? null;
            if ($capexId !== null && $capexId !== '' && (int) $capexId > 0) {
                continue;
            }
            $art = $articulos->get($articuloId);
            $sku = trim((string) ($art?->sku ?? ''));
            $desc = trim((string) ($art?->descripcion ?? ''));
            if (mb_strlen($desc) > 80) {
                $desc = mb_substr($desc, 0, 77).'…';
            }
            $etiqueta = $sku !== '' ? $sku : ('artículo '.$articuloId);
            if ($desc !== '') {
                $etiqueta .= ' '.$desc;
            }
            $faltan[] = 'la línea '.($i + 1).' ('.$etiqueta.')';
        }

        if ($faltan === []) {
            return;
        }

        throw new \InvalidArgumentException(self::mensajeCapexFaltante($faltan, $alEnviarArbol));
    }

    /**
     * @param  list<string>  $lineas  textos ya armados, p. ej. "la línea 1 (SKU descripción)"
     */
    public static function mensajeCapexFaltante(array $lineas, bool $alEnviarArbol = false): string
    {
        $lineas = array_values(array_filter($lineas, static fn ($linea) => trim((string) $linea) !== ''));
        $cuenta = self::CODIGO_CUENTA.' COMPRAS BS. DE USO - CTA. PUENTE';
        $una = count($lineas) === 1;
        $donde = $una ? $lineas[0] : implode('; ', $lineas);
        $verbo = $una ? 'imputa' : 'imputan';
        $esas = $una ? 'esa línea' : 'esas líneas';

        $tiene = $una ? 'no tiene' : 'no tienen';

        if ($alEnviarArbol) {
            return 'No se puede enviar al árbol: '.$donde.' '.$verbo
                .' a la cuenta puente de bienes de uso ('.$cuenta.') y '.$tiene.' CAPEX. '
                .'Edite la requisición, cargue el CAPEX de '.$esas.' y vuelva a enviarla.';
        }

        return 'No se puede guardar: '.$donde.' '.$verbo
            .' a la cuenta puente de bienes de uso ('.$cuenta.') y '.$tiene.' CAPEX. '
            .'Cargue el CAPEX de '.$esas.' para poder continuar.';
    }
}
