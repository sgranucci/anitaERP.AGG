<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionLiquidacionSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\ClienteCuentacorrienteAplicacionFilaSupport;
use App\Support\Ventas\ClienteCuentacorrienteAplicacionMatcherSupport;
use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Aplica NC y cobranzas a cuenta contra facturas de clientes.
 * Subledger siempre; asiento de DC solo si las cotizaciones difieren.
 * Después del commit MySQL espeja a Anita: aplmov + climov.cliv_t_cobrado.
 */
class ClienteCuentacorrienteAplicacionService
{
    public function __construct(
        private ClienteCuentacorrienteAplicacionAsientoService $asientoDcService,
        private ClienteCuentacorrienteAplicacionAnitaSyncService $anitaSyncService,
    ) {}

    /**
     * @param  list<array{credito_id:int,deuda_id:int,monto:float,cotizacion_liquidacion?:float|null}>  $lineas
     * @return array{aplicadas:int, monto:float, dc:float, asientos_dc:int, ids: list<int>, avisos_reclasificacion: list<int>}
     */
    public function aplicar(int $clienteId, string $fecha, array $lineas): array
    {
        $fecha = substr($fecha, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new RuntimeException('Fecha de aplicación inválida.');
        }

        $resultado = DB::transaction(function () use ($clienteId, $fecha, $lineas) {
            $ids = [];
            foreach ($lineas as $linea) {
                $ids[] = (int) ($linea['credito_id'] ?? 0);
                $ids[] = (int) ($linea['deuda_id'] ?? 0);
            }
            $ids = array_values(array_unique(array_filter($ids)));
            if ($ids === []) {
                throw new RuntimeException('Indique al menos una línea a aplicar.');
            }

            /** @var \Illuminate\Support\Collection<int, Cliente_Cuentacorriente> $bloqueados */
            $bloqueados = Cliente_Cuentacorriente::query()
                ->with([
                    'ventas.tipotransacciones',
                    'cobranzas.tipotransaccioncajas',
                    'clientes',
                    'monedas',
                    'empresas',
                ])
                ->where('cliente_id', $clienteId)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $aplicados = $this->sumasAplicadas($ids);

            $creditosById = [];
            $deudasById = [];
            foreach ($bloqueados as $cc) {
                $fila = ClienteCuentacorrienteAplicacionFilaSupport::desdeModelo(
                    $this->conAplicado($cc, (float) ($aplicados[$cc->id] ?? 0))
                );
                if ($fila['lado'] === 'credito') {
                    $creditosById[$fila['id']] = $fila;
                } else {
                    $deudasById[$fila['id']] = $fila;
                }
            }

            $errores = ClienteCuentacorrienteAplicacionMatcherSupport::validarLineas(
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
            $avisosReclasificacion = [];

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

                $etiquetaCredito = ClienteCuentacorrienteAplicacionFilaSupport::etiqueta(
                    $credito,
                    ClienteCuentacorrienteAplicacionFilaSupport::tipo($credito, 'credito')
                );
                $etiquetaDeuda = ClienteCuentacorrienteAplicacionFilaSupport::etiqueta(
                    $deuda,
                    ClienteCuentacorrienteAplicacionFilaSupport::tipo($deuda, 'deuda')
                );

                $aplDeuda = Cliente_Cuentacorriente_Aplicacion::query()->create([
                    'fecha' => $fecha,
                    'cliente_cuentacorriente_id' => $deuda->id,
                    'total' => -$liq['monto_deuda'],
                    'moneda_id' => $deuda->moneda_id,
                    'cotizacion' => $deuda->cotizacion,
                    'cotizacion_liquidacion' => $liq['cruzada'] ? $liq['cotizacion_liquidacion'] : null,
                    'diferencia_cambio' => $dc,
                    'asiento_id' => $asientoId,
                    'ventaaplicado_id' => $credito->venta_id,
                    'cobranza_id' => null,
                    'comprobanteaplicado' => $etiquetaCredito,
                    'empresa_id' => $deuda->empresa_id,
                    'cliente_cuentacorriente_aplicado_id' => $credito->id,
                ]);
                $aplCredito = Cliente_Cuentacorriente_Aplicacion::query()->create([
                    'fecha' => $fecha,
                    'cliente_cuentacorriente_id' => $credito->id,
                    'total' => $liq['monto_credito'],
                    'moneda_id' => $credito->moneda_id,
                    'cotizacion' => $credito->cotizacion,
                    'cotizacion_liquidacion' => $liq['cruzada'] ? $liq['cotizacion_liquidacion'] : null,
                    'diferencia_cambio' => $dc,
                    'asiento_id' => $asientoId,
                    'ventaaplicado_id' => $deuda->venta_id,
                    'cobranza_id' => null,
                    'comprobanteaplicado' => $etiquetaDeuda,
                    'empresa_id' => $credito->empresa_id,
                    'cliente_cuentacorriente_aplicado_id' => $deuda->id,
                ]);

                $idsAplicacion[] = (int) $aplDeuda->id;
                $idsAplicacion[] = (int) $aplCredito->id;
                $creadas++;
                $montoTotal += $monto;
                $dcTotal += $dc;
                if ($asientoId) {
                    $asientosDc++;
                    if ($this->asientoDcService->esReclasificacionNoAnticipo($deuda, $credito)) {
                        $avisosReclasificacion[] = (int) $aplDeuda->id;
                        Log::info('aplicacion_cc_cliente.reclasificacion', [
                            'aplicacion_id' => (int) $aplDeuda->id,
                            'cliente_id' => $clienteId,
                            'deuda_id' => $deuda->id,
                            'credito_id' => $credito->id,
                        ]);
                    }
                }
            }

            return [
                'aplicadas' => $creadas,
                'monto' => round($montoTotal, 4),
                'dc' => round($dcTotal, 4),
                'asientos_dc' => $asientosDc,
                'ids' => $idsAplicacion,
                'avisos_reclasificacion' => $avisosReclasificacion,
            ];
        });

        try {
            $this->anitaSyncService->syncPorIdsAplicacion($resultado['ids']);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'La aplicación quedó grabada en anitaERP pero no se reflejó en Anita (climov/aplmov): '.$e->getMessage()
            );
        }

        return $resultado;
    }

    /**
     * Revierte un par de aplicaciones generado por este proceso (no cobranza).
     */
    public function desaplicar(int $aplicacionId, int $clienteId): void
    {
        $snapshot = null;

        DB::transaction(function () use ($aplicacionId, $clienteId, &$snapshot) {
            /** @var Cliente_Cuentacorriente_Aplicacion|null $apl */
            $apl = Cliente_Cuentacorriente_Aplicacion::query()
                ->lockForUpdate()
                ->find($aplicacionId);
            if ($apl === null) {
                throw new RuntimeException('Aplicación no encontrada.');
            }
            if ((int) ($apl->cobranza_id ?? 0) > 0) {
                throw new RuntimeException('Esta aplicación pertenece a una cobranza. Revierta la cobranza.');
            }

            $snapshot = $this->anitaSyncService->snapshotDesdeAplicacion($apl);

            $parId = (int) ($apl->cliente_cuentacorriente_aplicado_id ?? 0);
            $idsBorrar = [(int) $apl->id];
            $asientoIds = [];
            if ((int) ($apl->asiento_id ?? 0) > 0) {
                $asientoIds[] = (int) $apl->asiento_id;
            }

            if ($parId > 0) {
                $par = Cliente_Cuentacorriente_Aplicacion::query()
                    ->lockForUpdate()
                    ->where('cliente_cuentacorriente_id', $parId)
                    ->where('cliente_cuentacorriente_aplicado_id', $apl->cliente_cuentacorriente_id)
                    ->where(function ($q) {
                        $q->whereNull('cobranza_id')->orWhere('cobranza_id', 0);
                    })
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

            $ccIds = Cliente_Cuentacorriente_Aplicacion::query()
                ->whereIn('id', $idsBorrar)
                ->pluck('cliente_cuentacorriente_id');

            $ajenos = Cliente_Cuentacorriente::query()
                ->whereIn('id', $ccIds)
                ->where('cliente_id', '!=', $clienteId)
                ->exists();
            if ($ajenos) {
                throw new RuntimeException('La aplicación no pertenece al cliente indicado.');
            }

            $fechaReverso = now()->format('Y-m-d');
            foreach (array_values(array_unique($asientoIds)) as $asientoId) {
                $this->asientoDcService->revertirSiCorresponde($asientoId, $fechaReverso);
            }

            Cliente_Cuentacorriente_Aplicacion::query()
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
                'La aplicación se deshizo en anitaERP pero no se revirtió en Anita (climov/aplmov): '.$e->getMessage()
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

        $rows = Cliente_Cuentacorriente_Aplicacion::query()
            ->selectRaw('cliente_cuentacorriente_id, SUM(total) as aplicado')
            ->whereIn('cliente_cuentacorriente_id', $ccIds)
            ->groupBy('cliente_cuentacorriente_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->cliente_cuentacorriente_id] = (float) $row->aplicado;
        }

        return $out;
    }

    private function conAplicado(Cliente_Cuentacorriente $cc, float $aplicado): Cliente_Cuentacorriente
    {
        $cc->aplicado = $aplicado;

        return $cc;
    }

    public static function sqlLadoCredito(): string
    {
        return 'cliente_cuentacorriente.total < 0 AND '.SqlDialectSupport::sqlSaldoPendienteClienteCc();
    }

    public static function sqlLadoDeuda(): string
    {
        return 'cliente_cuentacorriente.total > 0 AND '.SqlDialectSupport::sqlSaldoPendienteClienteCc();
    }

    public static function saldoAbsoluto(Cliente_Cuentacorriente $fila): float
    {
        return ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
            (float) $fila->total,
            $fila->aplicado ?? null
        );
    }
}
