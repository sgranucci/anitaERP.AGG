<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Depmae;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Ventas\Ordentrabajo;
use Illuminate\Support\Facades\DB;

/**
 * Egreso manual Ferli: el campo «Lote de stock» graba articulo_movimiento.lote
 * y deja ordentrabajo_id vacío. Si ese número es el código de una OT con saldo
 * (lote 0), el reporte Stock por OT no lo netea y muestra una fila negativa.
 * Los ingresos se graban siempre como lote.
 */
final class MovimientoStockConsumoOtAvisoSupport
{
    public static function esDocumentoEgreso(?Tipotransaccion_Stock $tipo, ?string $signoCantidad): bool
    {
        if ($tipo === null) {
            return false;
        }

        $operacion = strtoupper(trim((string) ($tipo->operacion ?? '')));
        if ($operacion === 'E') {
            return false;
        }
        if ($operacion === 'S' || $operacion === 'C') {
            return true;
        }
        if ($operacion === 'T') {
            return MovimientoStockSalidaSaldoSupport::esSignoRestaStock($signoCantidad);
        }

        return MovimientoStockSalidaSaldoSupport::esSignoRestaStock($signoCantidad);
    }

    public static function lineaEsEgreso(
        ?Tipotransaccion_Stock $tipo,
        ?string $signoCantidad,
        float $cantidadFirmada,
        string $sentido = ''
    ): bool {
        if ($tipo === null) {
            return false;
        }

        $operacion = strtoupper(trim((string) ($tipo->operacion ?? '')));
        if ($operacion === 'E') {
            return false;
        }
        if ($operacion === 'S') {
            return true;
        }
        if ($operacion === 'T') {
            return MovimientoStockSalidaSaldoSupport::esSignoRestaStock($signoCantidad);
        }
        if ($operacion === 'C') {
            $sentido = strtoupper(trim($sentido));
            if ($sentido === MovimientoStockCanjeSupport::SENTIDO_SALE) {
                return true;
            }
            if ($sentido === MovimientoStockCanjeSupport::SENTIDO_ENTRA) {
                return false;
            }

            return $cantidadFirmada < 0;
        }

        return MovimientoStockSalidaSaldoSupport::esSignoRestaStock($signoCantidad);
    }

    /**
     * @param  list<array{articulo_id:int, combinacion_id:int, cantidad:float}>  $lineas
     * @return array{ordentrabajo_id:int, mensaje_confirm:string, mensaje_bloqueo:string}|null
     */
    public static function avisoSiLoteEsOtConStock(
        string $lote,
        int $depositoId,
        array $lineas,
        ?int $excluirMovimientoStockId = null
    ): ?array {
        $lote = trim($lote);
        if ($depositoId <= 0 || ! ReporteStockOtSituacionSupport::esLoteImportado($lote)) {
            return null;
        }

        $agrupadas = self::agruparLineas($lineas);
        if ($agrupadas === []) {
            return null;
        }

        $otIds = Ordentrabajo::query()
            ->where('codigo', $lote)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($otIds === []) {
            return null;
        }

        $deposito = Depmae::query()->find($depositoId);
        $codigoDep = trim((string) ($deposito->codigo ?? ''));
        $nombreDep = trim((string) ($deposito->nombre ?? ''));
        $depositoTxt = $codigoDep;
        if ($nombreDep !== '' && strcasecmp($nombreDep, $codigoDep) !== 0) {
            $depositoTxt = trim($codigoDep.' '.$nombreDep);
        }
        if ($depositoTxt === '') {
            $depositoTxt = 'depósito '.$depositoId;
        }

        /** @var array<int, list<string>> $detallePorOt */
        $detallePorOt = [];
        foreach ($agrupadas as $linea) {
            $saldoLote = self::saldoPares(
                $linea['articulo_id'],
                $linea['combinacion_id'],
                $depositoId,
                true,
                $lote,
                0,
                $excluirMovimientoStockId
            );
            if ($saldoLote + 0.0001 >= $linea['cantidad']) {
                continue;
            }

            $otElegida = 0;
            $saldoOtElegido = 0.0;
            foreach ($otIds as $otId) {
                $saldoOt = self::saldoPares(
                    $linea['articulo_id'],
                    $linea['combinacion_id'],
                    $depositoId,
                    false,
                    $lote,
                    $otId,
                    $excluirMovimientoStockId
                );
                if ($saldoOt > $saldoOtElegido + 0.0001) {
                    $saldoOtElegido = $saldoOt;
                    $otElegida = $otId;
                }
            }
            if ($otElegida <= 0 || $saldoOtElegido <= 0.0001) {
                continue;
            }

            $detallePorOt[$otElegida][] = self::etiquetaLinea($linea).' (OT '.self::fmtPares($saldoOtElegido).' pares, lote '.self::fmtPares($saldoLote).')';
        }

        if ($detallePorOt === []) {
            return null;
        }

        $partes = [];
        foreach ($detallePorOt as $otId => $detalles) {
            $partes[] = implode('; ', $detalles);
        }
        $detalle = implode('; ', $partes);
        $base = 'El '.$lote.' tiene stock en la OT, no como lote importado ('.$depositoTxt.'): '.$detalle
            .'. Si lo grabás como lote, esa OT sigue en el reporte y el egreso queda en una fila negativa.';

        if (count($detallePorOt) > 1) {
            return [
                'ordentrabajo_id' => 0,
                'mensaje_confirm' => $base.' No se puede aplicar a una sola OT.',
                'mensaje_bloqueo' => $base.' Separá el egreso: hay más de una OT con ese código.',
            ];
        }

        $otId = (int) array_key_first($detallePorOt);

        return [
            'ordentrabajo_id' => $otId,
            'mensaje_confirm' => $base.' ¿Aplicar el egreso a la OT '.$lote.'?',
            'mensaje_bloqueo' => $base.' Volvé a guardar y confirmá el aviso para aplicarlo a la OT '.$lote.'.',
        ];
    }

    /**
     * @param  list<array{articulo_id:int, combinacion_id:int, cantidad:float}>  $lineas
     * @return list<array{articulo_id:int, combinacion_id:int, cantidad:float}>
     */
    /**
     * @param  list<int|string|null>  $articulos
     * @param  list<int|string|null>  $combinaciones
     * @param  list<int|float|string|null>  $cantidades
     * @param  list<mixed>  $medidas
     * @param  list<int|string|null>  $sentidos
     * @return list<array{articulo_id:int, combinacion_id:int, cantidad:float}>
     */
    public static function lineasDesdePayload(
        array $articulos,
        array $combinaciones,
        array $cantidades,
        array $medidas,
        array $sentidos = [],
        bool $soloLineasQueSalen = false
    ): array {
        $lineas = [];
        foreach ($articulos as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            $combinacionId = (int) ($combinaciones[$i] ?? 0);
            $sumaMed = TransferenciaMercaderiaDetalleFerliSupport::sumaCantidadDesdeMedidas($medidas[$i] ?? '');
            $firmada = (float) str_replace(',', '.', (string) ($cantidades[$i] ?? 0));
            $cantidad = $sumaMed > 0.000001 ? $sumaMed : abs($firmada);
            if ($articuloId <= 0 || $combinacionId <= 0 || $cantidad < 0.000001) {
                continue;
            }
            if ($soloLineasQueSalen && ! self::lineaCanjeSale((string) ($sentidos[$i] ?? ''), $firmada)) {
                continue;
            }
            $lineas[] = [
                'articulo_id' => $articuloId,
                'combinacion_id' => $combinacionId,
                'cantidad' => $cantidad,
            ];
        }

        return $lineas;
    }

    private static function lineaCanjeSale(string $sentido, float $cantidadFirmada): bool
    {
        $sentido = strtoupper(trim($sentido));
        if ($sentido === MovimientoStockCanjeSupport::SENTIDO_SALE) {
            return true;
        }
        if ($sentido === MovimientoStockCanjeSupport::SENTIDO_ENTRA) {
            return false;
        }

        return $cantidadFirmada < 0;
    }

    /**
     * @param  list<array{articulo_id:int, combinacion_id:int, cantidad:float}>  $lineas
     * @return list<array{articulo_id:int, combinacion_id:int, cantidad:float}>
     */
    private static function agruparLineas(array $lineas): array
    {
        /** @var array<string, array{articulo_id:int, combinacion_id:int, cantidad:float}> $out */
        $out = [];
        foreach ($lineas as $linea) {
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            $combinacionId = (int) ($linea['combinacion_id'] ?? 0);
            $cantidad = abs((float) ($linea['cantidad'] ?? 0));
            if ($articuloId <= 0 || $combinacionId <= 0 || $cantidad < 0.000001) {
                continue;
            }
            $clave = $articuloId.'|'.$combinacionId;
            if (! isset($out[$clave])) {
                $out[$clave] = [
                    'articulo_id' => $articuloId,
                    'combinacion_id' => $combinacionId,
                    'cantidad' => 0.0,
                ];
            }
            $out[$clave]['cantidad'] += $cantidad;
        }

        return array_values($out);
    }

    private static function saldoPares(
        int $articuloId,
        int $combinacionId,
        int $depositoId,
        bool $comoLote,
        string $lote,
        int $ordentrabajoId,
        ?int $excluirMovimientoStockId
    ): float {
        $q = DB::table('articulo_movimiento as am')
            ->join('articulo_movimiento_talle as amt', 'amt.articulo_movimiento_id', '=', 'am.id')
            ->where('am.articulo_id', $articuloId)
            ->where('am.combinacion_id', $combinacionId)
            ->where('am.deposito_id', $depositoId);

        if ($comoLote) {
            $q->where('am.lote', $lote);
        } else {
            $q->where('am.ordentrabajo_id', $ordentrabajoId)
                ->where(function ($w) {
                    $w->whereNull('am.lote')
                        ->orWhere('am.lote', '')
                        ->orWhere('am.lote', '0')
                        ->orWhere('am.lote', 0);
                });
        }

        if ($excluirMovimientoStockId !== null && $excluirMovimientoStockId > 0) {
            $q->where(function ($w) use ($excluirMovimientoStockId) {
                $w->whereNull('am.movimientostock_id')
                    ->orWhere('am.movimientostock_id', '<>', $excluirMovimientoStockId);
            });
        }

        return (float) $q->sum('amt.cantidad');
    }

    /**
     * @param  array{articulo_id:int, combinacion_id:int, cantidad:float}  $linea
     */
    private static function etiquetaLinea(array $linea): string
    {
        $articulo = Articulo::query()->find($linea['articulo_id'], ['id', 'sku', 'descripcion']);
        $combinacion = Combinacion::query()->find($linea['combinacion_id'], ['id', 'codigo', 'nombre']);
        $artTxt = trim((string) (($articulo->sku ?? '').' '.($articulo->descripcion ?? '')));
        $combTxt = trim((string) (($combinacion->codigo ?? '').'-'.($combinacion->nombre ?? '')), '-');
        $txt = trim($artTxt.' '.$combTxt);
        if ($txt === '') {
            $txt = 'artículo '.$linea['articulo_id'];
        }

        return $txt.', '.self::fmtPares($linea['cantidad']).' pares';
    }

    private static function fmtPares(float $n): string
    {
        if (abs($n - round($n)) < 0.001) {
            return (string) (int) round($n);
        }

        return number_format($n, 2, ',', '.');
    }
}
