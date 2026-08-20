<?php

namespace App\Services\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionDcSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionFilaSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionLiquidacionSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionMatcherSupport;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Aplica NC y pagos a cuenta contra facturas adeudadas.
 * Subledger siempre; asiento de DC solo si las cotizaciones difieren.
 * Después del commit MySQL espeja a Anita: aplmovp + promov.prov_t_pagado.
 */
class ProveedorCuentacorrienteAplicacionService
{
    public function __construct(
        private ProveedorCuentacorrienteAplicacionAsientoService $asientoDcService,
        private ProveedorCuentacorrienteAplicacionAnitaSyncService $anitaSyncService,
    ) {}

    /**
     * @param  list<array{credito_id:int,deuda_id:int,monto:float,cotizacion_liquidacion?:float|null}>  $lineas
     * @return array{aplicadas:int, monto:float, dc:float, asientos_dc:int, ids: list<int>}
     */
    public function aplicar(int $proveedorId, string $fecha, array $lineas): array
    {
        $fecha = substr($fecha, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new RuntimeException('Fecha de aplicación inválida.');
        }

        $resultado = DB::transaction(function () use ($proveedorId, $fecha, $lineas) {
            $ids = [];
            foreach ($lineas as $linea) {
                $ids[] = (int) ($linea['credito_id'] ?? 0);
                $ids[] = (int) ($linea['deuda_id'] ?? 0);
            }
            $ids = array_values(array_unique(array_filter($ids)));
            if ($ids === []) {
                throw new RuntimeException('Indique al menos una línea a aplicar.');
            }

            /** @var \Illuminate\Support\Collection<int, Proveedor_Cuentacorriente> $bloqueados */
            $bloqueados = Proveedor_Cuentacorriente::query()
                ->with([
                    'comprobante_proveedores.tipotransaccion_compras',
                    'comprobante_proveedores.ordencompras.ordencompra_articulos',
                    'comprobante_proveedores.proveedores',
                    'pagoproveedores',
                    'proveedores',
                    'monedas',
                    'empresas',
                ])
                ->where('proveedor_id', $proveedorId)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $aplicados = $this->sumasAplicadas($ids);

            $creditosById = [];
            $deudasById = [];
            foreach ($bloqueados as $cc) {
                $fila = ProveedorCuentacorrienteAplicacionFilaSupport::desdeModelo(
                    $this->conAplicado($cc, (float) ($aplicados[$cc->id] ?? 0))
                );
                if ($fila['lado'] === 'credito') {
                    $creditosById[$fila['id']] = $fila;
                } else {
                    $deudasById[$fila['id']] = $fila;
                }
            }

            $errores = ProveedorCuentacorrienteAplicacionMatcherSupport::validarLineas(
                $creditosById,
                $deudasById,
                $lineas,
                $fecha
            );
            if ($errores !== []) {
                throw new RuntimeException(implode(' ', $errores));
            }

            $creadas = 0;
            $montoTotal = 0.0;
            $dcTotal = 0.0;
            $asientosDc = 0;
            $idsAplicacion = [];

            foreach ($lineas as $linea) {
                $cid = (int) $linea['credito_id'];
                $did = (int) $linea['deuda_id'];
                $monto = round(abs((float) $linea['monto']), 4);
                $credito = $bloqueados->get($cid);
                $deuda = $bloqueados->get($did);
                if ($credito === null || $deuda === null) {
                    throw new RuntimeException('Movimiento de cuenta corriente no encontrado.');
                }

                $liq = ProveedorCuentacorrienteAplicacionLiquidacionSupport::liquidar(
                    [
                        'moneda_id' => (int) $deuda->moneda_id,
                        'cotizacion' => $deuda->cotizacion,
                    ],
                    [
                        'moneda_id' => (int) $credito->moneda_id,
                        'cotizacion' => $credito->cotizacion,
                    ],
                    $monto,
                    $linea['cotizacion_liquidacion'] ?? null
                );
                $dc = $liq['dc'];
                $asientoId = $this->asientoDcService->generarSiCorresponde(
                    $deuda,
                    $credito,
                    $dc,
                    $fecha,
                    $liq
                );

                $etiquetaCredito = ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
                    $credito,
                    ProveedorCuentacorrienteAplicacionFilaSupport::tipo($credito, 'credito')
                );
                $etiquetaDeuda = ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
                    $deuda,
                    ProveedorCuentacorrienteAplicacionFilaSupport::tipo($deuda, 'deuda')
                );

                $aplDeuda = Proveedor_Cuentacorriente_Aplicacion::query()->create([
                    'fecha' => $fecha,
                    'proveedor_cuentacorriente_id' => $deuda->id,
                    'total' => -$liq['monto_deuda'],
                    'moneda_id' => $deuda->moneda_id,
                    'cotizacion' => $deuda->cotizacion,
                    'cotizacion_liquidacion' => $liq['cruzada'] ? $liq['cotizacion_liquidacion'] : null,
                    'diferencia_cambio' => $dc,
                    'asiento_id' => $asientoId,
                    'comprobanteaplicado' => $etiquetaCredito,
                    'comprobante_proveedor_aplicado_id' => $credito->comprobante_proveedor_id,
                    'empresa_id' => $deuda->empresa_id,
                    'proveedor_cuentacorriente_aplicado_id' => $credito->id,
                ]);
                $aplCredito = Proveedor_Cuentacorriente_Aplicacion::query()->create([
                    'fecha' => $fecha,
                    'proveedor_cuentacorriente_id' => $credito->id,
                    'total' => $liq['monto_credito'],
                    'moneda_id' => $credito->moneda_id,
                    'cotizacion' => $credito->cotizacion,
                    'cotizacion_liquidacion' => $liq['cruzada'] ? $liq['cotizacion_liquidacion'] : null,
                    'diferencia_cambio' => $dc,
                    'asiento_id' => $asientoId,
                    'comprobanteaplicado' => $etiquetaDeuda,
                    'comprobante_proveedor_aplicado_id' => $deuda->comprobante_proveedor_id,
                    'empresa_id' => $credito->empresa_id,
                    'proveedor_cuentacorriente_aplicado_id' => $deuda->id,
                ]);

                $idsAplicacion[] = (int) $aplDeuda->id;
                $idsAplicacion[] = (int) $aplCredito->id;
                $creadas++;
                $montoTotal += $monto;
                $dcTotal += $dc;
                if ($asientoId) {
                    $asientosDc++;
                }
            }

            return [
                'aplicadas' => $creadas,
                'monto' => round($montoTotal, 4),
                'dc' => round($dcTotal, 4),
                'asientos_dc' => $asientosDc,
                'ids' => $idsAplicacion,
            ];
        });

        try {
            $this->anitaSyncService->syncPorIdsAplicacion($resultado['ids']);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'La aplicación quedó grabada en anitaERP pero no se reflejó en Anita (promov/aplmovp): '.$e->getMessage()
            );
        }

        return $resultado;
    }

    /**
     * Revierte un par de aplicaciones generado por este proceso (no OP).
     */
    public function desaplicar(int $aplicacionId, int $proveedorId): void
    {
        $snapshot = null;

        DB::transaction(function () use ($aplicacionId, $proveedorId, &$snapshot) {
            /** @var Proveedor_Cuentacorriente_Aplicacion|null $apl */
            $apl = Proveedor_Cuentacorriente_Aplicacion::query()
                ->lockForUpdate()
                ->find($aplicacionId);
            if ($apl === null) {
                throw new RuntimeException('Aplicación no encontrada.');
            }
            if ((int) ($apl->pagoproveedor_id ?? 0) > 0) {
                throw new RuntimeException('Esta aplicación pertenece a una orden de pago. Revierta la OP.');
            }

            $snapshot = $this->anitaSyncService->snapshotDesdeAplicacion($apl);

            $parId = (int) ($apl->proveedor_cuentacorriente_aplicado_id ?? 0);
            $idsBorrar = [(int) $apl->id];
            $asientoIds = [];
            if ((int) ($apl->asiento_id ?? 0) > 0) {
                $asientoIds[] = (int) $apl->asiento_id;
            }

            if ($parId > 0) {
                $par = Proveedor_Cuentacorriente_Aplicacion::query()
                    ->lockForUpdate()
                    ->where('proveedor_cuentacorriente_id', $parId)
                    ->where('proveedor_cuentacorriente_aplicado_id', $apl->proveedor_cuentacorriente_id)
                    ->whereNull('pagoproveedor_id')
                    ->where('id', '!=', $apl->id)
                    ->orderByDesc('id')
                    ->first();
                if ($par !== null) {
                    $idsBorrar[] = (int) $par->id;
                    if ((int) ($par->asiento_id ?? 0) > 0) {
                        $asientoIds[] = (int) $par->asiento_id;
                    }
                }
            }

            $ccIds = Proveedor_Cuentacorriente_Aplicacion::query()
                ->whereIn('id', $idsBorrar)
                ->pluck('proveedor_cuentacorriente_id');

            $ajenos = Proveedor_Cuentacorriente::query()
                ->whereIn('id', $ccIds)
                ->where('proveedor_id', '!=', $proveedorId)
                ->exists();
            if ($ajenos) {
                throw new RuntimeException('La aplicación no pertenece al proveedor indicado.');
            }

            $fechaReverso = now()->format('Y-m-d');
            foreach (array_values(array_unique($asientoIds)) as $asientoId) {
                $this->asientoDcService->revertirSiCorresponde($asientoId, $fechaReverso);
            }

            Proveedor_Cuentacorriente_Aplicacion::query()
                ->whereIn('id', $idsBorrar)
                ->delete();
        });

        if ($snapshot === null) {
            return;
        }

        try {
            $this->anitaSyncService->revertir($snapshot);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'La aplicación se deshizo en anitaERP pero no se revirtió en Anita (promov/aplmovp): '.$e->getMessage()
            );
        }
    }

    /**
     * @param  list<int>  $ccIds
     * @return array<int, float>
     */
    private function sumasAplicadas(array $ccIds): array
    {
        if ($ccIds === []) {
            return [];
        }

        $rows = Proveedor_Cuentacorriente_Aplicacion::query()
            ->selectRaw('proveedor_cuentacorriente_id, SUM(total) as aplicado')
            ->whereIn('proveedor_cuentacorriente_id', $ccIds)
            ->groupBy('proveedor_cuentacorriente_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->proveedor_cuentacorriente_id] = (float) $row->aplicado;
        }

        return $out;
    }

    private function conAplicado(Proveedor_Cuentacorriente $cc, float $aplicado): Proveedor_Cuentacorriente
    {
        $cc->aplicado = $aplicado;

        return $cc;
    }

    public static function sqlLadoCredito(): string
    {
        return 'proveedor_cuentacorriente.total < 0 AND '.SqlDialectSupport::sqlSaldoPendienteProveedorCc();
    }

    public static function sqlLadoDeuda(): string
    {
        return 'proveedor_cuentacorriente.total > 0 AND '.SqlDialectSupport::sqlSaldoPendienteProveedorCc();
    }

    public static function saldoAbsoluto(Proveedor_Cuentacorriente $fila): float
    {
        return ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
            (float) $fila->total,
            $fila->aplicado ?? null
        );
    }
}
