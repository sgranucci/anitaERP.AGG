<?php

namespace App\Services\Compras;

use App\Mail\Compras\ProveedorCuentacorrienteConciliacionDiaria;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Contable\Asiento;
use App\Support\Compras\ProveedorCuentaContableMonedaSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionLiquidacionSupport;
use App\Support\Compras\ProveedorCuentacorrienteConciliacionSupport;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Robot diario: ficha CC ↔ deuda abierta ↔ integridad de pares/DC ↔ mayor AP.
 */
class ProveedorCuentacorrienteConciliacionDiariaService
{
    /**
     * @return array<string, mixed>
     */
    public function ejecutar(?string $desde = null, bool $enviarMail = true): array
    {
        $config = config('compras.conciliacion_cc_proveedor', []);
        $ventana = max(1, (int) ($config['ventana_dias'] ?? 45));
        $desde = $desde ?: Carbon::today()->subDays($ventana - 1)->toDateString();
        $tolerancia = (float) ($config['tolerancia'] ?? 0.05);
        $toleranciaGl = (float) ($config['tolerancia_gl'] ?? 1.00);
        $limite = (int) ($config['limite_filas_mail'] ?? 80);

        $informe = [
            'fecha_calendario' => $desde.' → '.Carbon::today()->toDateString(),
            'fecha_desde' => $desde,
            'alertas' => [],
            'resumen' => [
                'pares_rotos' => 0,
                'cruzada_sin_cotizacion' => 0,
                'dc_descuadrada' => 0,
                'dc_sin_asiento' => 0,
                'sobreaplicadas' => 0,
                'cc_dc_huerfanas' => 0,
                'ficha_vs_deuda' => 0,
                'subledger_vs_gl' => 0,
            ],
            'requiere_alerta' => false,
        ];

        $this->auditarPares($informe, $desde, $tolerancia);
        $this->auditarSaldosYHuerfanas($informe, $tolerancia);
        $this->auditarFichaVsDeuda($informe, $tolerancia);
        $this->auditarSubledgerVsGl($informe, $toleranciaGl);

        foreach ($informe['resumen'] as $n) {
            if ((int) $n > 0) {
                $informe['requiere_alerta'] = true;
                break;
            }
        }

        $informe['alertas'] = array_slice($informe['alertas'], 0, $limite);

        $debeEnviar = $informe['requiere_alerta']
            || filter_var($config['mail_siempre'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($enviarMail && $debeEnviar) {
            $informe = array_merge($informe, $this->enviarMail($informe, $config));
        }

        return $informe;
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function auditarPares(array &$informe, string $desde, float $tolerancia): void
    {
        $filas = Proveedor_Cuentacorriente_Aplicacion::query()
            ->whereDate('fecha', '>=', $desde)
            ->where('total', '<', 0)
            ->orderBy('id')
            ->get();

        foreach ($filas as $apl) {
            $par = Proveedor_Cuentacorriente_Aplicacion::query()
                ->where('proveedor_cuentacorriente_id', (int) ($apl->proveedor_cuentacorriente_aplicado_id ?? 0))
                ->where('proveedor_cuentacorriente_aplicado_id', (int) $apl->proveedor_cuentacorriente_id)
                ->where('id', '!=', (int) $apl->id)
                ->orderByDesc('id')
                ->first();

            if ($par === null) {
                $informe['resumen']['pares_rotos']++;
                $informe['alertas'][] = [
                    'tipo' => 'par_roto',
                    'detalle' => 'Aplicación #'.$apl->id.' sin espejo en el otro lado de la CC.',
                ];

                continue;
            }

            $cruzada = (int) $apl->moneda_id !== (int) $par->moneda_id;
            if ($cruzada && (! $apl->cotizacion_liquidacion || (float) $apl->cotizacion_liquidacion <= 0)
                && (int) ($apl->pagoproveedor_id ?? 0) === 0) {
                $informe['resumen']['cruzada_sin_cotizacion']++;
                $informe['alertas'][] = [
                    'tipo' => 'cruzada_sin_cotizacion',
                    'detalle' => 'Aplicación #'.$apl->id.' cruza monedas sin cotización de liquidación.',
                ];
            }

            $esperada = ProveedorCuentacorrienteConciliacionSupport::dcEsperada(
                [
                    'total' => (float) $apl->total,
                    'moneda_id' => (int) $apl->moneda_id,
                    'cotizacion' => (float) ($apl->cotizacion ?? 1),
                ],
                [
                    'total' => (float) $par->total,
                    'moneda_id' => (int) $par->moneda_id,
                    'cotizacion' => (float) ($par->cotizacion ?? 1),
                ]
            );
            $dcGuardada = (float) ($apl->diferencia_cambio ?? 0);
            if (ProveedorCuentacorrienteConciliacionSupport::desvia($esperada, $dcGuardada, $tolerancia)
                && (abs($esperada) >= $tolerancia || abs($dcGuardada) >= $tolerancia)) {
                $informe['resumen']['dc_descuadrada']++;
                $informe['alertas'][] = [
                    'tipo' => 'dc_descuadrada',
                    'detalle' => sprintf(
                        'Aplicación #%d DC guardada %s vs esperada %s.',
                        (int) $apl->id,
                        number_format($dcGuardada, 2, ',', '.'),
                        number_format($esperada, 2, ',', '.')
                    ),
                ];
            }

            if (ProveedorCuentacorrienteAplicacionLiquidacionSupport::esCruzada((int) $apl->moneda_id, (int) $par->moneda_id)
                || abs($dcGuardada) >= 0.01
                || abs($esperada) >= 0.01) {
                if ((int) ($apl->asiento_id ?? 0) <= 0 && abs($esperada) >= 0.01) {
                    $informe['resumen']['dc_sin_asiento']++;
                    $informe['alertas'][] = [
                        'tipo' => 'dc_sin_asiento',
                        'detalle' => 'Aplicación #'.$apl->id.' tiene DC y no tiene asiento.',
                    ];
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function auditarSaldosYHuerfanas(array &$informe, float $tolerancia): void
    {
        $aplicados = Proveedor_Cuentacorriente_Aplicacion::query()
            ->selectRaw('proveedor_cuentacorriente_id, SUM(total) as aplicado')
            ->groupBy('proveedor_cuentacorriente_id')
            ->pluck('aplicado', 'proveedor_cuentacorriente_id');

        Proveedor_Cuentacorriente::query()
            ->select(['id', 'total', 'proveedor_id', 'empresa_id', 'comprobante_proveedor_id', 'pagoproveedor_id', 'moneda_id'])
            ->orderBy('id')
            ->chunkById(500, function ($filas) use (&$informe, $aplicados, $tolerancia) {
                foreach ($filas as $cc) {
                    $aplicado = (float) ($aplicados[$cc->id] ?? 0);
                    $saldo = ProveedorCuentacorrienteGrillaSupport::saldoPendiente((float) $cc->total, $aplicado);
                    if (abs((float) $cc->total) + 0.0001 < abs($aplicado) - $tolerancia) {
                        $informe['resumen']['sobreaplicadas']++;
                        $informe['alertas'][] = [
                            'tipo' => 'sobreaplicada',
                            'detalle' => sprintf(
                                'CC #%d proveedor %d sobreaplicada (total %s / aplicado %s).',
                                (int) $cc->id,
                                (int) $cc->proveedor_id,
                                $cc->total,
                                $aplicado
                            ),
                        ];
                    }

                    $huerfanaDc = (int) ($cc->pagoproveedor_id ?? 0) > 0
                        && (int) ($cc->comprobante_proveedor_id ?? 0) === 0
                        && (int) $cc->moneda_id === ProveedorCuentacorrienteAplicacionLiquidacionSupport::monedaLocalId()
                        && abs((float) $cc->total) >= 0.01
                        && abs($saldo) >= $tolerancia
                        && abs($aplicado) < $tolerancia;
                    if ($huerfanaDc) {
                        $informe['resumen']['cc_dc_huerfanas']++;
                        $informe['alertas'][] = [
                            'tipo' => 'cc_dc_huerfana',
                            'detalle' => sprintf(
                                'CC #%d OP %d: ítem de DC en pesos sin aplicar (descalce típico Anita).',
                                (int) $cc->id,
                                (int) $cc->pagoproveedor_id
                            ),
                        ];
                    }
                }
            });
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function auditarFichaVsDeuda(array &$informe, float $tolerancia): void
    {
        $grupos = [];
        Proveedor_Cuentacorriente::query()
            ->select(['id', 'proveedor_id', 'empresa_id', 'moneda_id', 'total', 'comprobante_proveedor_id'])
            ->where('total', '>', 0)
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->orderBy('id')
            ->chunkById(500, function ($filas) use (&$grupos) {
                foreach ($filas as $cc) {
                    $pend = ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                        (float) $cc->total,
                        (float) ($cc->aplicado ?? 0)
                    );
                    if ($pend < 0.01) {
                        continue;
                    }
                    $key = $cc->proveedor_id.'|'.$cc->empresa_id.'|'.$cc->moneda_id;
                    if (! isset($grupos[$key])) {
                        $grupos[$key] = ['toda' => 0.0, 'comprobante' => 0.0, 'proveedor_id' => (int) $cc->proveedor_id, 'empresa_id' => (int) $cc->empresa_id, 'moneda_id' => (int) $cc->moneda_id];
                    }
                    $grupos[$key]['toda'] += $pend;
                    if ((int) ($cc->comprobante_proveedor_id ?? 0) > 0) {
                        $grupos[$key]['comprobante'] += $pend;
                    }
                }
            });

        foreach ($grupos as $g) {
            if (ProveedorCuentacorrienteConciliacionSupport::desvia($g['toda'], $g['comprobante'], $tolerancia)) {
                $informe['resumen']['ficha_vs_deuda']++;
                $informe['alertas'][] = [
                    'tipo' => 'ficha_vs_deuda',
                    'detalle' => sprintf(
                        'Proveedor %d empresa %d moneda %d: deuda abierta en ficha %s y la pantalla de deuda (con comprobante) muestra %s.',
                        $g['proveedor_id'],
                        $g['empresa_id'],
                        $g['moneda_id'],
                        number_format($g['toda'], 2, ',', '.'),
                        number_format($g['comprobante'], 2, ',', '.')
                    ),
                ];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function auditarSubledgerVsGl(array &$informe, float $toleranciaGl): void
    {
        $cuentas = [];
        Proveedor::query()
            ->select(['id', 'cuentacontable_id', 'cuentacontableme_id'])
            ->orderBy('id')
            ->chunkById(200, function ($proveedores) use (&$cuentas) {
                foreach ($proveedores as $p) {
                    foreach ([(int) $p->cuentacontable_id, (int) $p->cuentacontableme_id] as $id) {
                        if ($id > 0) {
                            $cuentas[$id] = true;
                        }
                    }
                }
            });
        $cuentaIds = array_keys($cuentas);
        if ($cuentaIds === []) {
            return;
        }

        $gl = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->whereIn('m.cuentacontable_id', $cuentaIds)
            ->where(function ($q) {
                $q->whereNull('a.estado_aprobacion')
                    ->orWhere('a.estado_aprobacion', '!=', Asiento::ESTADO_APROBACION_RECHAZADO);
            })
            ->selectRaw('a.empresa_id, m.cuentacontable_id')
            ->selectRaw('SUM(m.monto * COALESCE(NULLIF(m.cotizacion, 0), 1)) as saldo_local')
            ->groupBy('a.empresa_id', 'm.cuentacontable_id')
            ->get()
            ->keyBy(fn ($r) => $r->empresa_id.'|'.$r->cuentacontable_id);

        $ccLocal = [];
        Proveedor_Cuentacorriente::query()
            ->with(['comprobante_proveedores.ordencompras.ordencompra_articulos', 'proveedores'])
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->orderBy('id')
            ->chunkById(300, function ($filas) use (&$ccLocal) {
                foreach ($filas as $cc) {
                    $saldo = ProveedorCuentacorrienteGrillaSupport::saldoPendiente(
                        (float) $cc->total,
                        (float) ($cc->aplicado ?? 0)
                    );
                    if (abs($saldo) < 0.01) {
                        continue;
                    }
                    $proveedor = $cc->proveedores;
                    $cuentaId = 0;
                    if ($cc->comprobante_proveedores) {
                        $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorDesdeComprobante(
                            $cc->comprobante_proveedores,
                            $proveedor
                        );
                    }
                    if ($cuentaId <= 0) {
                        $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorId(
                            $proveedor,
                            (int) ($cc->moneda_id ?: 1)
                        );
                    }
                    if ($cuentaId <= 0) {
                        continue;
                    }
                    $local = ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
                        $saldo,
                        (float) ($cc->cotizacion ?? 1),
                        (int) ($cc->moneda_id ?: 1)
                    );
                    if ($saldo < 0) {
                        $local = -$local;
                    }
                    $key = ((int) $cc->empresa_id).'|'.$cuentaId;
                    $ccLocal[$key] = ($ccLocal[$key] ?? 0) + $local;
                }
            });

        $keys = array_unique(array_merge(array_keys($ccLocal), $gl->keys()->all()));
        foreach ($keys as $key) {
            $sub = (float) ($ccLocal[$key] ?? 0);
            $mayor = -1 * (float) ($gl[$key]->saldo_local ?? 0);
            if (ProveedorCuentacorrienteConciliacionSupport::desvia($sub, $mayor, $toleranciaGl)) {
                [$empresaId, $cuentaId] = array_map('intval', explode('|', (string) $key));
                $informe['resumen']['subledger_vs_gl']++;
                $informe['alertas'][] = [
                    'tipo' => 'subledger_vs_gl',
                    'detalle' => sprintf(
                        'Empresa %d cuenta AP %d: CC abierta (local) %s ≠ mayor %s.',
                        $empresaId,
                        $cuentaId,
                        number_format($sub, 2, ',', '.'),
                        number_format($mayor, 2, ',', '.')
                    ),
                ];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     * @return array{mail_enviado?: bool, mail_destino?: string, mail_error?: string}
     */
    private function enviarMail(array $informe, array $config): array
    {
        $email = trim((string) ($config['email'] ?? ''));
        if ($email === '') {
            return [];
        }

        try {
            Mail::to($email)->send(new ProveedorCuentacorrienteConciliacionDiaria($informe));

            return [
                'mail_enviado' => true,
                'mail_destino' => $email,
            ];
        } catch (\Throwable $e) {
            Log::error('Conciliación CC proveedor: mail falló', ['error' => $e->getMessage()]);

            return ['mail_error' => $e->getMessage()];
        }
    }
}
