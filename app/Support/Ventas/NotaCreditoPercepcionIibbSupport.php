<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Venta;
use App\Support\Caja\CobranzaDescuentoConfigSupport;
use App\Support\Configuracion\PercepcionNoCategorizadoSupport;
use App\Support\Configuracion\RegimenPercepcionSupport;

/**
 * Percepciones IIBB en nota de crédito de mostrador administración:
 * se heredan de la factura origen y se prorratean por neto gravado.
 *
 * Corre en AGG (y el resto) solo en facturación mostrador admin.
 * No entra en POS (gastronomía / estacionamiento / canje).
 * No aplica a facturas / ND (solo signo Resta + venta origen).
 */
final class NotaCreditoPercepcionIibbSupport
{
    public const FLAG_ES_NC = 'es_nota_credito';

    public const FLAG_VENTA_ORIGEN = 'venta_origen_id';

    /** Payload de NC de descuento en cobranza (NCP). */
    public const FLAG_NCP = 'origen_cobranza_ncp';

    /** @var array<int, array{filas: list<array<string, mixed>>, neto: float}|null> */
    private static array $origenCache = [];

    /**
     * @param  array<string, mixed>  $dataCliente
     */
    public static function corresponde(array $dataCliente): bool
    {
        if (empty($dataCliente[self::FLAG_ES_NC])) {
            return false;
        }

        return self::ventaOrigenId($dataCliente) > 0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function anexarOrigenSiCorresponde(array &$datosCliente, array $data, bool $esMostradorAdmin): void
    {
        if (! empty($data[self::FLAG_NCP])) {
            $datosCliente[self::FLAG_NCP] = true;
        }

        if (! $esMostradorAdmin) {
            return;
        }

        $origenId = (int) ($data['venta_origen_id'] ?? $data['venta_id'] ?? 0);
        if ($origenId <= 0 || ! self::payloadEsNotaCredito($data)) {
            return;
        }

        $datosCliente[self::FLAG_ES_NC] = true;
        $datosCliente[self::FLAG_VENTA_ORIGEN] = $origenId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function payloadEsNotaCredito(array $data): bool
    {
        if (isset($data['tipotransaccion_signo'])) {
            return (string) $data['tipotransaccion_signo'] === 'R';
        }

        return VentaNotaCreditoPrecioLiteralSupport::esNotaCreditoTipotransaccionId(
            (int) ($data['tipotransaccion_id'] ?? 0)
        );
    }

    /**
     * Percepciones a liquidar en la NC. null = no aplica (seguir recálculo).
     * Lista vacía = la factura origen no percibió; no inventar.
     *
     * @param  array<string, mixed>  $dataCliente
     * @return list<array<string, mixed>>|null
     */
    public static function paraNotaCredito(array $dataCliente, float $netoNc): ?array
    {
        if (self::ncpOmitePercepcionIibb($dataCliente)) {
            return [];
        }

        if (! self::corresponde($dataCliente)) {
            return null;
        }

        if (array_key_exists('percepciones_iibb_origen', $dataCliente)
            && is_array($dataCliente['percepciones_iibb_origen'])) {
            $filas = self::extraerFilasIibb($dataCliente['percepciones_iibb_origen']);

            return self::prorratearFilas($filas, $netoNc, self::netoOrigen($filas));
        }

        return self::desdeOrigen(self::ventaOrigenId($dataCliente), $netoNc);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public static function desdeOrigen(int $ventaOrigenId, float $netoNc): ?array
    {
        if ($ventaOrigenId <= 0) {
            return null;
        }

        $origen = self::cargarOrigen($ventaOrigenId);
        if ($origen === null) {
            return null;
        }

        return self::prorratearFilas($origen['filas'], $netoNc, $origen['neto']);
    }

    /**
     * @param  list<array<string, mixed>>  $filasOrigen
     * @return list<array<string, mixed>>
     */
    public static function prorratearFilas(array $filasOrigen, float $netoNc, float $netoOrigen): array
    {
        if ($filasOrigen === []) {
            return [];
        }

        $netoNc = round(max(0.0, $netoNc), 4);
        if ($netoNc < 0.00001) {
            return [];
        }

        $factor = self::factor($netoNc, $netoOrigen);
        if ($factor < 0.00001) {
            return [];
        }

        $esTotal = $factor >= 0.999;
        $out = [];
        foreach ($filasOrigen as $fila) {
            $importeOrigen = round((float) ($fila['importe'] ?? 0), 2);
            $baseOrigen = (float) ($fila['baseimponible'] ?? 0);
            $importe = $esTotal ? $importeOrigen : round($importeOrigen * $factor, 2);
            if (abs($importe) < 0.01) {
                continue;
            }
            $base = $esTotal
                ? ($baseOrigen > 0.00001 ? $baseOrigen : $netoNc)
                : round(($baseOrigen > 0.00001 ? $baseOrigen : $netoOrigen) * $factor, 2);

            $out[] = [
                'concepto' => (string) ($fila['concepto'] ?? ''),
                'tasa' => (float) ($fila['tasa'] ?? 0),
                'baseimponible' => $base,
                'jurisdiccion' => $fila['jurisdiccion'] ?? null,
                'provincia_id' => $fila['provincia_id'] ?? null,
                'importe' => $importe,
            ];
        }

        return $out;
    }

    public static function factor(float $netoNc, float $netoOrigen): float
    {
        if ($netoOrigen <= 0.00001) {
            return $netoNc > 0.00001 ? 1.0 : 0.0;
        }

        $factor = $netoNc / $netoOrigen;
        if ($factor < 0.0) {
            return 0.0;
        }
        if ($factor > 1.0) {
            return 1.0;
        }

        return $factor;
    }

    /**
     * @param  iterable<int, mixed>  $ventaImpuestos
     * @return list<array<string, mixed>>
     */
    public static function extraerFilasIibb(iterable $ventaImpuestos): array
    {
        $filas = [];
        foreach ($ventaImpuestos as $item) {
            $fila = self::filaComoArray($item);
            if ($fila === [] || ! self::esFilaIibb($fila)) {
                continue;
            }
            if (abs((float) ($fila['importe'] ?? 0)) < 0.01) {
                continue;
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function esFilaIibb(array $fila): bool
    {
        $concepto = trim((string) ($fila['concepto'] ?? ''));
        if ($concepto === '') {
            return false;
        }
        if (PercepcionNoCategorizadoSupport::esConcepto($concepto)) {
            return false;
        }
        if (RegimenPercepcionSupport::esConceptoPiva($concepto)
            || str_starts_with(mb_strtolower($concepto), 'perc. iva')) {
            return false;
        }

        $provinciaId = (int) ($fila['provincia_id'] ?? 0);
        if ($provinciaId > 0) {
            return true;
        }

        $c = mb_strtolower($concepto);

        return str_starts_with($c, 'perc.')
            || str_contains($c, 'percepcion iibb')
            || str_contains($c, 'perc. iibb');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public static function netoOrigen(array $filas): float
    {
        $max = 0.0;
        foreach ($filas as $fila) {
            $base = abs((float) ($fila['baseimponible'] ?? 0));
            if ($base > $max) {
                $max = $base;
            }
        }

        return $max;
    }

    public static function olvidarCache(): void
    {
        self::$origenCache = [];
    }

    /**
     * NCP de cobranza sin percepción IIBB (config o override de test).
     *
     * @param  array<string, mixed>  $dataCliente
     */
    public static function ncpOmitePercepcionIibb(array $dataCliente): bool
    {
        if (empty($dataCliente[self::FLAG_NCP])) {
            return false;
        }
        if (array_key_exists('nc_percepcion_iibb', $dataCliente)) {
            return empty($dataCliente['nc_percepcion_iibb']);
        }

        return ! CobranzaDescuentoConfigSupport::ncPercibeIibb();
    }

    /**
     * @param  array<string, mixed>  $dataCliente
     */
    private static function ventaOrigenId(array $dataCliente): int
    {
        return (int) ($dataCliente[self::FLAG_VENTA_ORIGEN] ?? $dataCliente['venta_id'] ?? 0);
    }

    /**
     * @return array{filas: list<array<string, mixed>>, neto: float}|null
     */
    private static function cargarOrigen(int $ventaOrigenId): ?array
    {
        if (array_key_exists($ventaOrigenId, self::$origenCache)) {
            return self::$origenCache[$ventaOrigenId];
        }

        $venta = Venta::query()
            ->with('venta_impuestos')
            ->find($ventaOrigenId);
        if (! $venta) {
            self::$origenCache[$ventaOrigenId] = null;

            return null;
        }

        $filas = self::extraerFilasIibb($venta->venta_impuestos);
        $filas = self::completarJurisdiccion($filas);
        self::$origenCache[$ventaOrigenId] = [
            'filas' => $filas,
            'neto' => self::netoOrigen($filas),
        ];

        return self::$origenCache[$ventaOrigenId];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private static function completarJurisdiccion(array $filas): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['provincia_id'] ?? 0);
            if ($id > 0 && ! isset($fila['jurisdiccion'])) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return $filas;
        }

        $juris = Provincia::query()
            ->whereIn('id', array_values($ids))
            ->pluck('jurisdiccion', 'id');

        foreach ($filas as $i => $fila) {
            $id = (int) ($fila['provincia_id'] ?? 0);
            if ($id > 0 && ! isset($fila['jurisdiccion']) && $juris->has($id)) {
                $filas[$i]['jurisdiccion'] = $juris->get($id);
            }
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaComoArray(mixed $fila): array
    {
        if (is_array($fila)) {
            return $fila;
        }
        if (is_object($fila) && method_exists($fila, 'toArray')) {
            return $fila->toArray();
        }

        return is_object($fila) ? (array) $fila : [];
    }
}
