<?php

namespace App\Services\Compras;

use App\Models\Compras\AplicacionCcProveedorComprobante;
use App\Models\Compras\AplicacionCcProveedorComprobanteLinea;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Comprobante de aplicación de cuenta corriente de proveedores.
 *
 * Un comprobante por empresa (la de la deuda) en cada confirmación del workbench.
 * Numerador propio por empresa: MAX(numero) bajo lock + índice único como respaldo.
 * Anular no borra: el comprobante queda ANULADO con sus líneas como copia histórica.
 */
class AplicacionCcProveedorComprobanteService
{
    /**
     * @param  list<array{
     *   empresa_id:int,
     *   aplicacion_deuda_id:?int,
     *   aplicacion_credito_id:?int,
     *   deuda_cc_id:?int,
     *   credito_cc_id:?int,
     *   deuda_etiqueta:?string,
     *   credito_etiqueta:?string,
     *   monto_deuda:float,
     *   moneda_deuda_id:?int,
     *   cotizacion_deuda:float|string|null,
     *   monto_credito:float,
     *   moneda_credito_id:?int,
     *   cotizacion_credito:float|string|null,
     *   cotizacion_liquidacion:float|string|null,
     *   valor_local_deuda:float,
     *   diferencia_cambio:float,
     *   asiento_id:?int
     * }>  $pares
     * @return list<AplicacionCcProveedorComprobante>
     */
    public function emitir(
        int $proveedorId,
        string $fecha,
        array $pares,
        string $origen = AplicacionCcProveedorComprobante::ORIGEN_ERP,
        ?int $usuarioId = null,
        ?string $observacion = null,
    ): array {
        if ($pares === []) {
            return [];
        }

        $usuarioId ??= auth()->id() ? (int) auth()->id() : null;
        $porEmpresa = [];
        foreach ($pares as $par) {
            $empresaId = (int) ($par['empresa_id'] ?? 0);
            if ($empresaId <= 0) {
                throw new RuntimeException('La aplicación no tiene empresa: no se puede numerar el comprobante.');
            }
            $porEmpresa[$empresaId][] = $par;
        }
        ksort($porEmpresa);

        return DB::transaction(function () use ($porEmpresa, $proveedorId, $fecha, $origen, $usuarioId, $observacion) {
            $out = [];
            foreach ($porEmpresa as $empresaId => $lineas) {
                $monedas = array_values(array_unique(array_map(
                    static fn ($l) => (int) ($l['moneda_deuda_id'] ?? 0),
                    $lineas
                )));
                $unaMoneda = count($monedas) === 1 && $monedas[0] > 0;

                $comprobante = AplicacionCcProveedorComprobante::query()->create([
                    'empresa_id' => $empresaId,
                    'proveedor_id' => $proveedorId,
                    'numero' => $this->proximoNumero($empresaId),
                    'fecha' => substr($fecha, 0, 10),
                    'estado' => AplicacionCcProveedorComprobante::ESTADO_EMITIDO,
                    'moneda_id' => $unaMoneda ? $monedas[0] : null,
                    'total_moneda' => $unaMoneda
                        ? round(array_sum(array_map(static fn ($l) => abs((float) $l['monto_deuda']), $lineas)), 4)
                        : null,
                    'total' => round(array_sum(array_map(static fn ($l) => abs((float) $l['valor_local_deuda']), $lineas)), 4),
                    'diferencia_cambio' => round(array_sum(array_map(static fn ($l) => (float) $l['diferencia_cambio'], $lineas)), 4),
                    'cantidad_lineas' => count($lineas),
                    'detalle' => $this->detalle($lineas),
                    'observacion' => $observacion,
                    'origen' => $origen,
                    'usuario_id' => $usuarioId,
                ]);

                foreach ($lineas as $l) {
                    AplicacionCcProveedorComprobanteLinea::query()->create([
                        'comprobante_id' => $comprobante->id,
                        'aplicacion_deuda_id' => $l['aplicacion_deuda_id'] ?? null,
                        'aplicacion_credito_id' => $l['aplicacion_credito_id'] ?? null,
                        'deuda_cc_id' => $l['deuda_cc_id'] ?? null,
                        'credito_cc_id' => $l['credito_cc_id'] ?? null,
                        'deuda_etiqueta' => $l['deuda_etiqueta'] ?? null,
                        'credito_etiqueta' => $l['credito_etiqueta'] ?? null,
                        'monto_deuda' => round(abs((float) $l['monto_deuda']), 4),
                        'moneda_deuda_id' => $l['moneda_deuda_id'] ?? null,
                        'cotizacion_deuda' => $l['cotizacion_deuda'] ?? null,
                        'monto_credito' => round(abs((float) $l['monto_credito']), 4),
                        'moneda_credito_id' => $l['moneda_credito_id'] ?? null,
                        'cotizacion_credito' => $l['cotizacion_credito'] ?? null,
                        'cotizacion_liquidacion' => $l['cotizacion_liquidacion'] ?? null,
                        'valor_local_deuda' => round(abs((float) $l['valor_local_deuda']), 4),
                        'diferencia_cambio' => round((float) $l['diferencia_cambio'], 4),
                        'asiento_id' => $l['asiento_id'] ?? null,
                    ]);
                }

                $out[] = $comprobante;
            }

            return $out;
        });
    }

    public function proximoNumero(int $empresaId): int
    {
        $max = AplicacionCcProveedorComprobante::query()
            ->where('empresa_id', $empresaId)
            ->lockForUpdate()
            ->max('numero');

        return ((int) $max) + 1;
    }

    /**
     * Comprobante emitido que contiene la aplicación (lado deuda o crédito).
     */
    public function comprobanteVigenteDeAplicacion(int $aplicacionId): ?AplicacionCcProveedorComprobante
    {
        if ($aplicacionId <= 0) {
            return null;
        }

        $comprobanteId = AplicacionCcProveedorComprobanteLinea::query()
            ->where(function ($q) use ($aplicacionId) {
                $q->where('aplicacion_deuda_id', $aplicacionId)
                    ->orWhere('aplicacion_credito_id', $aplicacionId);
            })
            ->orderByDesc('id')
            ->value('comprobante_id');
        if (! $comprobanteId) {
            return null;
        }

        $comprobante = AplicacionCcProveedorComprobante::query()
            ->lockForUpdate()
            ->find((int) $comprobanteId);

        return $comprobante !== null && ! $comprobante->estaAnulado() ? $comprobante : null;
    }

    /**
     * Raíz (aplicación del lado deuda o, si falta, la del crédito) de cada par del comprobante.
     *
     * @return list<int>
     */
    public function raicesAplicacion(AplicacionCcProveedorComprobante $comprobante): array
    {
        return $comprobante->lineas()
            ->get(['aplicacion_deuda_id', 'aplicacion_credito_id'])
            ->map(static fn ($l) => (int) ($l->aplicacion_deuda_id ?: $l->aplicacion_credito_id))
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function marcarAnulado(AplicacionCcProveedorComprobante $comprobante, ?int $usuarioId = null): void
    {
        $comprobante->estado = AplicacionCcProveedorComprobante::ESTADO_ANULADO;
        $comprobante->anulado_at = now();
        $comprobante->anulado_usuario_id = $usuarioId ?? (auth()->id() ? (int) auth()->id() : null);
        $comprobante->save();
    }

    /**
     * @param  list<array{deuda_etiqueta?:?string,credito_etiqueta?:?string}>  $lineas
     */
    private function detalle(array $lineas): ?string
    {
        $partes = [];
        foreach ($lineas as $l) {
            $deuda = trim((string) ($l['deuda_etiqueta'] ?? ''));
            $credito = trim((string) ($l['credito_etiqueta'] ?? ''));
            if ($deuda === '' && $credito === '') {
                continue;
            }
            $partes[] = $deuda.' ← '.$credito;
        }
        if ($partes === []) {
            return null;
        }

        return mb_strimwidth(implode('; ', $partes), 0, 500, '…');
    }
}
