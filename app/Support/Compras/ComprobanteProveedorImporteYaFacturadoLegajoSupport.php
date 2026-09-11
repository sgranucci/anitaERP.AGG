<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Importe ya facturado contra COM, para restar de la provisión disponible.
 *
 * La comparación de factura vs COM usa las recepciones **asignadas** (no todo el
 * legajo): una OC anual tipo Telefónica tiene una COM + una FC por mes; restar
 * el resto de facturas de la OC deja la provisión en 0.
 *
 * Cada factura previa se convierte a la moneda de la factura actual (motor de
 * moneda: manda la factura; cada documento usa su propia cotización).
 *
 * Anticipo 50/50: el descuento aplica cuando la 1ª mitad está vinculada a la
 * misma COM (pivot). Si era anticipada sin COM, no resta acá.
 */
final class ComprobanteProveedorImporteYaFacturadoLegajoSupport
{
    /**
     * Facturas contabilizadas del legajo (informativo en pantalla). No usar para
     * comparar provisión COM: eso va por {@see sumarComparableEnRecepciones()}.
     *
     * @return array{
     *     importe: float,
     *     cantidad: int,
     *     items: list<array{id: int, etiqueta: string, importe: float, signo: string}>
     * }
     */
    public static function sumarComparableEnLegajo(
        int $ordencompraId,
        ?int $excluirComprobanteId = null,
    ): array {
        $vacio = ['importe' => 0.0, 'cantidad' => 0, 'items' => []];
        if ($ordencompraId <= 0) {
            return $vacio;
        }

        $query = self::queryContabilizados()
            ->where('ordencompra_id', $ordencompraId);

        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        return self::acumular($query->orderBy('id')->get());
    }

    /**
     * Facturas contabilizadas imputadas a las COM indicadas, en moneda destino.
     *
     * Un comprobante ligado a más de una COM seleccionada se cuenta una sola vez.
     *
     * @param  list<int|string>  $recepcionIds
     * @return array{
     *     importe: float,
     *     cantidad: int,
     *     items: list<array{id: int, etiqueta: string, importe: float, signo: string}>
     * }
     */
    public static function sumarComparableEnRecepciones(
        array $recepcionIds,
        ?int $excluirComprobanteId = null,
        int $monedaDestinoId = 1,
        mixed $cotizacionDestino = 1.0,
        mixed $fechaDestino = null,
    ): array {
        $vacio = ['importe' => 0.0, 'cantidad' => 0, 'items' => []];
        $ids = self::normalizarIds($recepcionIds);
        if ($ids === [] || ! self::hayTablaPivot()) {
            return $vacio;
        }

        $cpIds = Comprobante_Proveedor_Recepcion::query()
            ->whereIn('recepcion_proveedor_id', $ids)
            ->pluck('comprobante_proveedor_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();
        if ($cpIds === []) {
            return $vacio;
        }

        $query = self::queryContabilizados()->whereIn('id', $cpIds);
        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        return self::acumular(
            $query->orderBy('id')->get(),
            $monedaDestinoId,
            $cotizacionDestino,
            $fechaDestino,
            true,
        );
    }

    /**
     * Importe ya facturado por cada COM (misma moneda destino).
     * Un CP ligado a dos COM se suma en ambas al evaluar cada una por separado.
     *
     * @param  list<int|string>  $recepcionIds
     * @return array<int, float>
     */
    public static function importePorRecepcion(
        array $recepcionIds,
        ?int $excluirComprobanteId = null,
        int $monedaDestinoId = 1,
        mixed $cotizacionDestino = 1.0,
        mixed $fechaDestino = null,
    ): array {
        $ids = self::normalizarIds($recepcionIds);
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = 0.0;
        }
        if ($ids === [] || ! self::hayTablaPivot()) {
            return $out;
        }

        $pivotes = Comprobante_Proveedor_Recepcion::query()
            ->whereIn('recepcion_proveedor_id', $ids)
            ->get(['comprobante_proveedor_id', 'recepcion_proveedor_id']);
        $cpIds = $pivotes->pluck('comprobante_proveedor_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();
        if ($cpIds === []) {
            return $out;
        }

        $query = self::queryContabilizados()->whereIn('id', $cpIds);
        if ($excluirComprobanteId !== null && $excluirComprobanteId > 0) {
            $query->where('id', '!=', $excluirComprobanteId);
        }

        $cps = $query->get()->keyBy('id');
        foreach ($pivotes as $pivote) {
            $cp = $cps->get((int) $pivote->comprobante_proveedor_id);
            if (! $cp) {
                continue;
            }
            $rid = (int) $pivote->recepcion_proveedor_id;
            if (! isset($out[$rid])) {
                continue;
            }
            $out[$rid] = round(
                $out[$rid] + self::comparableDeComprobante(
                    $cp,
                    $monedaDestinoId,
                    $cotizacionDestino,
                    $fechaDestino,
                    true,
                ),
                2
            );
        }

        return $out;
    }

    /**
     * Provisión COM aún disponible para la factura actual (no negativa).
     */
    public static function provisionDisponible(float $provisionCom, float $yaFacturadoComparable): float
    {
        return max(0.0, round($provisionCom - $yaFacturadoComparable, 2));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Comprobante_Proveedor>
     */
    private static function queryContabilizados()
    {
        return Comprobante_Proveedor::query()
            ->with([
                'comprobante_proveedor_conceptos.concepto_ivacompras',
                'tipotransaccion_compras:id,signo,abreviatura',
                'proveedores:id,condicioniva_id',
            ])
            ->where(function ($q) {
                $q->where('estado', ComprobanteProveedorEstados::CONTABILIZADO)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('asiento_id')->where('asiento_id', '>', 0);
                    });
            })
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', [ComprobanteProveedorEstados::ANULADO]);
            });
    }

    /**
     * @param  Collection<int, Comprobante_Proveedor>  $rows
     * @return array{
     *     importe: float,
     *     cantidad: int,
     *     items: list<array{id: int, etiqueta: string, importe: float, signo: string}>
     * }
     */
    private static function acumular(
        Collection $rows,
        int $monedaDestinoId = 1,
        mixed $cotizacionDestino = 1.0,
        mixed $fechaDestino = null,
        bool $convertirADestino = false,
    ): array {
        $vacio = ['importe' => 0.0, 'cantidad' => 0, 'items' => []];
        if ($rows->isEmpty()) {
            return $vacio;
        }

        $importe = 0.0;
        $items = [];
        foreach ($rows as $cp) {
            $monto = self::comparableDeComprobante(
                $cp,
                $monedaDestinoId,
                $cotizacionDestino,
                $fechaDestino,
                $convertirADestino,
            );
            $signo = (string) ($cp->tipotransaccion_compras->signo ?? 'S');
            $esNc = ComprobanteProveedorImputacionApSupport::esNotaCredito($signo);
            $importe += $monto;
            $items[] = [
                'id' => (int) $cp->id,
                'etiqueta' => trim(sprintf(
                    '%s %04d-%08d',
                    $cp->letra ?: 'FC',
                    (int) ($cp->sucursal ?? 0),
                    (int) ($cp->numerocomprobante ?? 0)
                )),
                'importe' => $monto,
                'signo' => $esNc ? 'R' : 'S',
            ];
        }

        return [
            'importe' => round($importe, 2),
            'cantidad' => count($items),
            'items' => $items,
        ];
    }

    private static function comparableDeComprobante(
        Comprobante_Proveedor $cp,
        int $monedaDestinoId,
        mixed $cotizacionDestino,
        mixed $fechaDestino,
        bool $convertirADestino,
    ): float {
        $signo = (string) ($cp->tipotransaccion_compras->signo ?? 'S');
        $esNc = ComprobanteProveedorImputacionApSupport::esNotaCredito($signo);
        $condicionIva = isset($cp->proveedores)
            ? (int) ($cp->proveedores->condicioniva_id ?? 0)
            : null;
        if ($condicionIva !== null && $condicionIva <= 0) {
            $condicionIva = null;
        }

        $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            (string) ($cp->letra ?? ''),
            $condicionIva,
            (float) ($cp->total ?? 0),
            (float) ($cp->subtotal ?? 0),
            $cp->comprobante_proveedor_conceptos ?? [],
        );
        $monto = round((float) $meta['importe'], 2);
        if ($esNc) {
            $monto = -abs($monto);
        } else {
            $monto = abs($monto);
        }

        if (! $convertirADestino) {
            return $monto;
        }

        return ComprobanteProveedorMonedaMotor::convertirTolerante(
            $monto,
            (int) ($cp->moneda_id ?: 1),
            (float) ($cp->cotizacion ?: 0),
            $cp->fechacomprobante ?? null,
            $monedaDestinoId,
            $cotizacionDestino,
            $fechaDestino,
            'factura ya imputada a la COM',
            'la factura del proveedor',
        );
    }

    /** @param  list<int|string>  $recepcionIds */
    private static function normalizarIds(array $recepcionIds): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $recepcionIds),
            static fn (int $id) => $id > 0
        )));
    }

    private static function hayTablaPivot(): bool
    {
        return Schema::hasTable('comprobante_proveedor_recepcion');
    }
}
