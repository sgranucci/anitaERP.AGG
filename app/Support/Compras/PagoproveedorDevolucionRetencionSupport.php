<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;

/**
 * Cálculo de saldos disponibles para devolución parcial de retenciones (DEV RET).
 */
final class PagoproveedorDevolucionRetencionSupport
{
    public const PREFIJO_DETALLE = 'DEV RET OPP';

    public static function esDevolucion(Pagoproveedor $pago): bool
    {
        return preg_match('/^'.preg_quote(self::PREFIJO_DETALLE, '/').'\b/iu', trim((string) $pago->detalle)) === 1;
    }

    public static function leyenda(Pagoproveedor $origen, string $extra = ''): string
    {
        $nro = trim((string) ($origen->numerotransaccion ?? ''));
        $base = self::PREFIJO_DETALLE.' '.$nro;
        $extra = trim($extra);

        return $extra === '' ? $base : $base.': '.$extra;
    }

    /**
     * Clona retenciones positivas como líneas negativas en el pago destino
     * (AOP de reversión o OP de DEV RET). SICORE las exporta como NC (03).
     *
     * @param  iterable<Pagoproveedor_Retencion|array{retencion: Pagoproveedor_Retencion, importe?: float}>  $retencionesOrigen
     * @return list<Pagoproveedor_Retencion>
     */
    public static function clonarComoDevolucionEnPago(
        Pagoproveedor $destino,
        iterable $retencionesOrigen,
        string $motivo = 'reverso_op',
    ): array {
        $creadas = [];
        foreach ($retencionesOrigen as $item) {
            if (is_array($item)) {
                $origenRet = $item['retencion'] ?? null;
                $importeOverride = array_key_exists('importe', $item) ? (float) $item['importe'] : null;
            } else {
                $origenRet = $item;
                $importeOverride = null;
            }
            if (! $origenRet instanceof Pagoproveedor_Retencion) {
                continue;
            }
            $importe = round($importeOverride ?? (float) $origenRet->importe, 2);
            if ($importe <= 0.001) {
                continue;
            }

            $det = is_array($origenRet->detalle_calculo) ? $origenRet->detalle_calculo : [];
            $det['origen_retencion_id'] = (int) $origenRet->id;
            $det['devolucion'] = true;

            $creadas[] = Pagoproveedor_Retencion::query()->create([
                'pagoproveedor_id' => (int) $destino->id,
                'tiporetencion' => (string) $origenRet->tiporetencion,
                'retencionganancia_id' => $origenRet->retencionganancia_id,
                'retencioniva_id' => $origenRet->retencioniva_id,
                'retencionsuss_id' => $origenRet->retencionsuss_id,
                'provincia_id' => $origenRet->provincia_id,
                'codigo_regimen' => $origenRet->codigo_regimen,
                'codigo_retencion' => $origenRet->codigo_retencion,
                // SICORE NC: base = retención anulada, no la base de la operación.
                'base_calculo' => abs($importe),
                'alicuota' => (float) $origenRet->alicuota,
                'importe' => -abs($importe),
                'nro_certificado' => $origenRet->nro_certificado,
                'moneda_id' => (int) ($origenRet->moneda_id ?: $destino->moneda_id ?: 1),
                'cotizacion' => (float) ($origenRet->cotizacion ?: $destino->cotizacion ?: 1),
                'detalle_calculo' => $det,
                'motivo' => $motivo,
            ]);
        }

        return $creadas;
    }

    /**
     * @return list<array{
     *     id: int,
     *     tiporetencion: string,
     *     etiqueta: string,
     *     importe_original: float,
     *     importe_devuelto: float,
     *     disponible: float,
     *     nro_certificado: string,
     *     codigo_regimen: string|null,
     *     provincia_id: int|null
     * }>
     */
    public static function disponibles(Pagoproveedor $origen): array
    {
        $origen->loadMissing(['pagoproveedor_retenciones']);
        $devueltoPorOrigen = self::devueltoPorRetencionOrigenId($origen);

        $out = [];
        foreach ($origen->pagoproveedor_retenciones as $ret) {
            if (! $ret instanceof Pagoproveedor_Retencion) {
                continue;
            }
            $original = round((float) $ret->importe, 2);
            if ($original <= 0.001) {
                continue;
            }
            $devuelto = round((float) ($devueltoPorOrigen[(int) $ret->id] ?? 0), 2);
            $disponible = round(max(0, $original - $devuelto), 2);
            $out[] = [
                'id' => (int) $ret->id,
                'tiporetencion' => (string) $ret->tiporetencion,
                'etiqueta' => $ret->etiquetaTipo(),
                'importe_original' => $original,
                'importe_devuelto' => $devuelto,
                'disponible' => $disponible,
                'nro_certificado' => (string) ($ret->nro_certificado ?? ''),
                'codigo_regimen' => $ret->codigo_regimen !== null ? (string) $ret->codigo_regimen : null,
                'provincia_id' => $ret->provincia_id !== null ? (int) $ret->provincia_id : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, float> origen_retencion_id => importe ya devuelto (positivo)
     */
    public static function devueltoPorRetencionOrigenId(Pagoproveedor $origen): array
    {
        $hijas = Pagoproveedor::query()
            ->where('pagoproveedor_origen_id', (int) $origen->id)
            ->whereIn('estado', ['CONFIRMADA', 'PAGADA', 'CONCILIADA'])
            ->where('detalle', 'like', self::PREFIJO_DETALLE.'%')
            ->with('pagoproveedor_retenciones')
            ->get();

        $out = [];
        foreach ($hijas as $hija) {
            foreach ($hija->pagoproveedor_retenciones as $ret) {
                $det = is_array($ret->detalle_calculo) ? $ret->detalle_calculo : [];
                $origenId = (int) ($det['origen_retencion_id'] ?? 0);
                if ($origenId <= 0) {
                    continue;
                }
                $out[$origenId] = round(($out[$origenId] ?? 0) + abs((float) $ret->importe), 2);
            }
        }

        return $out;
    }

    /**
     * @param  list<array{id: int, importe: float|int|string}>  $seleccion
     * @param  list<array<string, mixed>>  $disponibles
     * @return list<array{retencion: Pagoproveedor_Retencion, importe: float}>
     */
    public static function validarSeleccion(Pagoproveedor $origen, array $seleccion, array $disponibles): array
    {
        $porId = [];
        foreach ($disponibles as $d) {
            $porId[(int) $d['id']] = $d;
        }

        if (! $origen->relationLoaded('pagoproveedor_retenciones')) {
            $origen->loadMissing(['pagoproveedor_retenciones']);
        }
        /** @var array<int, Pagoproveedor_Retencion> $rets */
        $rets = [];
        foreach ($origen->pagoproveedor_retenciones as $ret) {
            if ($ret instanceof Pagoproveedor_Retencion) {
                $rets[(int) $ret->getKey()] = $ret;
            }
        }

        $out = [];
        foreach ($seleccion as $item) {
            $id = (int) ($item['id'] ?? 0);
            $importe = round((float) ($item['importe'] ?? 0), 2);
            if ($id <= 0 || $importe < 0.001) {
                continue;
            }
            if (! isset($porId[$id], $rets[$id])) {
                throw new \RuntimeException('Retención #'.$id.' no pertenece a la OP.');
            }
            $disp = (float) $porId[$id]['disponible'];
            if ($importe - $disp > 0.009) {
                throw new \RuntimeException(
                    'El importe a devolver de '.$porId[$id]['etiqueta']
                    .' ($'.number_format($importe, 2, ',', '.').') supera el disponible'
                    .' ($'.number_format($disp, 2, ',', '.').').'
                );
            }
            $out[] = [
                'retencion' => $rets[$id],
                'importe' => $importe,
            ];
        }

        if ($out === []) {
            throw new \RuntimeException('Seleccione al menos una retención con importe a devolver.');
        }

        return $out;
    }
}
