<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Models\Caja\Cheque;
use App\Support\Caja\ChequeListadoFiltros;
use App\Support\Configuracion\ParametroSistemaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Señales operativas para el catálogo de Consulta Inteligente (sin warehouse).
 * Umbrales y flags vienen de parametro_sistema (grupo Consulta Inteligente).
 */
final class ConsultaInteligenteAlertaSupport
{
    public const CLAVE_CHEQUES_DEPOSITAR_ACTIVA = 'ci_alerta_cheques_depositar_activa';

    public const CLAVE_CHEQUES_DEPOSITAR_ALTA = 'ci_alerta_cheques_depositar_alta';

    public const CLAVE_CHEQUES_VENCEN_ACTIVA = 'ci_alerta_cheques_vencen_activa';

    public const CLAVE_CHEQUES_VENCEN_DIAS = 'ci_alerta_cheques_vencen_dias';

    public const CLAVE_FACTURAS_VAR_ACTIVA = 'ci_alerta_facturas_var_activa';

    public const CLAVE_FACTURAS_VAR_PCT = 'ci_alerta_facturas_var_pct';

    public const CLAVE_VENTA_SIN_REMITO_ACTIVA = 'ci_alerta_venta_sin_remito_activa';

    public const CLAVE_VENTA_SIN_REMITO_ALTA = 'ci_alerta_venta_sin_remito_alta';

    public const CLAVE_REMITOS_HOY_ACTIVA = 'ci_alerta_remitos_hoy_activa';

    public const CLAVE_CHEQUES_SUMA_ACTIVA = 'ci_alerta_cheques_suma_activa';

    public const CLAVE_CHEQUES_SUMA_UMBRAL = 'ci_alerta_cheques_suma_umbral';

    public const CLAVE_FACTURAS_SUMA_VAR_ACTIVA = 'ci_alerta_facturas_suma_var_activa';

    public const CLAVE_FACTURAS_SUMA_VAR_PCT = 'ci_alerta_facturas_suma_var_pct';

    public const CLAVE_COBRANZAS_VAR_ACTIVA = 'ci_alerta_cobranzas_var_activa';

    public const CLAVE_COBRANZAS_VAR_PCT = 'ci_alerta_cobranzas_var_pct';

    /**
     * @return list<string>
     */
    public static function clavesParametro(): array
    {
        return [
            self::CLAVE_CHEQUES_DEPOSITAR_ACTIVA,
            self::CLAVE_CHEQUES_DEPOSITAR_ALTA,
            self::CLAVE_CHEQUES_VENCEN_ACTIVA,
            self::CLAVE_CHEQUES_VENCEN_DIAS,
            self::CLAVE_CHEQUES_SUMA_ACTIVA,
            self::CLAVE_CHEQUES_SUMA_UMBRAL,
            self::CLAVE_FACTURAS_VAR_ACTIVA,
            self::CLAVE_FACTURAS_VAR_PCT,
            self::CLAVE_FACTURAS_SUMA_VAR_ACTIVA,
            self::CLAVE_FACTURAS_SUMA_VAR_PCT,
            self::CLAVE_COBRANZAS_VAR_ACTIVA,
            self::CLAVE_COBRANZAS_VAR_PCT,
            self::CLAVE_VENTA_SIN_REMITO_ACTIVA,
            self::CLAVE_VENTA_SIN_REMITO_ALTA,
            self::CLAVE_REMITOS_HOY_ACTIVA,
        ];
    }

    /**
     * @return array<string, array{grupo: string, etiqueta: string, ayuda: string, tipo: string, orden: int}>
     */
    public static function definicionesParametro(): array
    {
        return [
            self::CLAVE_CHEQUES_DEPOSITAR_ACTIVA => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Alerta: cheques para depositar',
                'ayuda' => 'Mostrar chip cuando hay cheques listos para depósito.',
                'tipo' => 'boolean',
                'orden' => 900,
            ],
            self::CLAVE_CHEQUES_DEPOSITAR_ALTA => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Umbral alta: cheques para depositar',
                'ayuda' => 'A partir de esta cantidad la alerta se marca como alta.',
                'tipo' => 'entero',
                'orden' => 901,
            ],
            self::CLAVE_CHEQUES_VENCEN_ACTIVA => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Alerta: cheques propios por vencer',
                'ayuda' => 'Mostrar chip de cheques emitidos con fecha de pago próxima.',
                'tipo' => 'boolean',
                'orden' => 910,
            ],
            self::CLAVE_CHEQUES_VENCEN_DIAS => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Días ventana cheques por vencer',
                'ayuda' => 'Cantidad de días hacia adelante desde hoy.',
                'tipo' => 'entero',
                'orden' => 911,
            ],
            self::CLAVE_FACTURAS_VAR_ACTIVA => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Alerta: variación facturas del mes',
                'ayuda' => 'Comparar cantidad de facturas de proveedor vs mes anterior.',
                'tipo' => 'boolean',
                'orden' => 920,
            ],
            self::CLAVE_FACTURAS_VAR_PCT => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Umbral % variación facturas',
                'ayuda' => 'Disparar alerta si la variación absoluta alcanza este porcentaje.',
                'tipo' => 'entero',
                'orden' => 921,
            ],
            self::CLAVE_VENTA_SIN_REMITO_ACTIVA => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Alerta: facturas de venta sin remito',
                'ayuda' => 'Mostrar chip con comprobantes de venta pendientes de remito (hoy).',
                'tipo' => 'boolean',
                'orden' => 930,
            ],
            self::CLAVE_VENTA_SIN_REMITO_ALTA => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Umbral alta: sin remito',
                'ayuda' => 'A partir de esta cantidad la alerta se marca como alta.',
                'tipo' => 'entero',
                'orden' => 931,
            ],
            self::CLAVE_REMITOS_HOY_ACTIVA => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Alerta: remitos de hoy',
                'ayuda' => 'Mostrar chip con remitos emitidos en la fecha de hoy.',
                'tipo' => 'boolean',
                'orden' => 940,
            ],
            self::CLAVE_CHEQUES_SUMA_ACTIVA => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Alerta: suma cheques para depositar',
                'ayuda' => 'Avisar cuando el monto a depositar supera el umbral (no solo la cantidad).',
                'tipo' => 'boolean',
                'orden' => 950,
            ],
            self::CLAVE_CHEQUES_SUMA_UMBRAL => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Umbral $ cheques para depositar',
                'ayuda' => 'Monto mínimo (en moneda local) para disparar la alerta de suma.',
                'tipo' => 'entero',
                'orden' => 951,
            ],
            self::CLAVE_FACTURAS_SUMA_VAR_ACTIVA => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Alerta: variación $ facturas del mes',
                'ayuda' => 'Comparar suma de totales de facturas proveedor vs mes anterior.',
                'tipo' => 'boolean',
                'orden' => 960,
            ],
            self::CLAVE_FACTURAS_SUMA_VAR_PCT => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Umbral % variación $ facturas',
                'ayuda' => 'Disparar si la variación absoluta de la suma alcanza este porcentaje.',
                'tipo' => 'entero',
                'orden' => 961,
            ],
            self::CLAVE_COBRANZAS_VAR_ACTIVA => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Alerta: variación cobranzas del mes',
                'ayuda' => 'Comparar cantidad de cobranzas vs mes anterior.',
                'tipo' => 'boolean',
                'orden' => 970,
            ],
            self::CLAVE_COBRANZAS_VAR_PCT => [
                'grupo' => 'Consulta Inteligente',
                'etiqueta' => 'Umbral % variación cobranzas',
                'ayuda' => 'Disparar si la variación absoluta alcanza este porcentaje.',
                'tipo' => 'entero',
                'orden' => 971,
            ],
        ];
    }

    /**
     * @return list<array{titulo: string, detalle: string, nivel: string, url: string, color: string}>
     */
    public static function paraUsuario(): array
    {
        $out = [];

        if (
            self::bool(self::CLAVE_CHEQUES_DEPOSITAR_ACTIVA, true)
            && can('listar-cheque', false)
            && EntidadSemanticaCatalogo::existe('cheque')
        ) {
            $paraDepositar = self::contarCheques(['para_depositar' => 1]);
            $umbralAlta = self::entero(self::CLAVE_CHEQUES_DEPOSITAR_ALTA, 10);
            if ($paraDepositar > 0) {
                $out[] = [
                    'titulo' => $paraDepositar.' cheques para depositar',
                    'detalle' => 'Cartera lista para depósito',
                    'nivel' => $paraDepositar >= $umbralAlta ? 'alta' : 'media',
                    'url' => EntidadSemanticaCatalogo::urlExplorar('cheque', [
                        'para_depositar' => 1,
                        'consultar' => 1,
                    ]),
                    'color' => '#B7950B',
                ];
            }
        }

        if (
            self::bool(self::CLAVE_CHEQUES_VENCEN_ACTIVA, true)
            && can('listar-cheque', false)
            && EntidadSemanticaCatalogo::existe('cheque')
        ) {
            $dias = max(1, self::entero(self::CLAVE_CHEQUES_VENCEN_DIAS, 7));
            $vencen = self::chequesPropiosVencenEnDias($dias);
            if ($vencen > 0) {
                $out[] = [
                    'titulo' => $vencen.' cheques propios vencen en '.$dias.' días',
                    'detalle' => 'Emitidos con fecha de pago próxima',
                    'nivel' => 'alta',
                    'url' => EntidadSemanticaCatalogo::urlExplorar('cheque', [
                        'origen' => 'E',
                        'consultar' => 1,
                    ]),
                    'color' => '#C0392B',
                ];
            }
        }

        if (
            self::bool(self::CLAVE_FACTURAS_VAR_ACTIVA, true)
            && can('listar-comprobante-proveedor', false)
            && EntidadSemanticaCatalogo::existe('comprobante_proveedor')
        ) {
            $mes = self::contarComprobantesMes();
            $ant = self::contarComprobantesMesAnterior();
            $umbralPct = max(1, self::entero(self::CLAVE_FACTURAS_VAR_PCT, 25));
            if ($ant > 0 && $mes > 0) {
                $var = (($mes - $ant) / $ant) * 100;
                if (abs($var) >= $umbralPct) {
                    $out[] = [
                        'titulo' => sprintf('Facturas del mes %s%.0f%% vs mes anterior', $var > 0 ? '+' : '', $var),
                        'detalle' => $mes.' este mes · '.$ant.' mes anterior',
                        'nivel' => abs($var) >= ($umbralPct * 2) ? 'alta' : 'media',
                        'url' => EntidadSemanticaCatalogo::urlExplorar('comprobante_proveedor', [
                            'consultar' => 1,
                            'filtro_modo' => 'qbe',
                            'qbe' => [
                                'entre_grupos' => 'and',
                                'grupos' => [[
                                    'logic' => 'and',
                                    'not' => 0,
                                    'criterios' => [[
                                        'campo' => 'fechacomprobante',
                                        'op' => 'este_mes',
                                        'valor' => '',
                                        'valor_hasta' => '',
                                    ]],
                                ]],
                            ],
                        ]),
                    'color' => $var > 0 ? '#1A5276' : '#117A65',
                ];
                }
            }
        }

        if (
            self::bool(self::CLAVE_VENTA_SIN_REMITO_ACTIVA, true)
            && can('listar-factura', false)
            && EntidadSemanticaCatalogo::existe('factura')
        ) {
            $hoy = Carbon::today()->toDateString();
            $sinRemito = (int) DB::table('venta')
                ->whereNull('remito_id')
                ->whereDate('fecha', $hoy)
                ->count();
            $umbralAlta = self::entero(self::CLAVE_VENTA_SIN_REMITO_ALTA, 5);
            if ($sinRemito > 0) {
                $out[] = [
                    'titulo' => $sinRemito.' facturas sin remito (hoy)',
                    'detalle' => 'Comprobantes de venta del día pendientes de remito',
                    'nivel' => $sinRemito >= $umbralAlta ? 'alta' : 'media',
                    'url' => EntidadSemanticaCatalogo::urlExplorar('factura', [
                        'consultar' => 1,
                        'empresa_todas' => 1,
                        'solo_sin_remito' => 1,
                        'fecha_desde' => $hoy,
                        'fecha_hasta' => $hoy,
                    ]),
                    'color' => '#AF601A',
                ];
            }
        }

        if (
            self::bool(self::CLAVE_REMITOS_HOY_ACTIVA, true)
            && can('listar-remitos', false)
            && EntidadSemanticaCatalogo::existe('remito')
        ) {
            $hoy = Carbon::today()->toDateString();
            $remitosHoy = (int) DB::table('remito')->whereDate('fecha', $hoy)->count();
            if ($remitosHoy > 0) {
                $out[] = [
                    'titulo' => $remitosHoy.' remitos hoy',
                    'detalle' => 'Remitos emitidos en la fecha de hoy',
                    'nivel' => 'media',
                    'url' => EntidadSemanticaCatalogo::urlExplorar('remito', [
                        'consultar' => 1,
                        'filtro_modo' => 'qbe',
                        'qbe' => [
                            'entre_grupos' => 'and',
                            'grupos' => [[
                                'logic' => 'and',
                                'not' => 0,
                                'criterios' => [[
                                    'campo' => 'fecha',
                                    'op' => 'hoy',
                                    'valor' => '',
                                    'valor_hasta' => '',
                                ]],
                            ]],
                        ],
                    ]),
                    'color' => '#117A65',
                ];
            }
        }

        if (
            self::bool(self::CLAVE_CHEQUES_SUMA_ACTIVA, true)
            && can('listar-cheque', false)
            && EntidadSemanticaCatalogo::existe('cheque')
        ) {
            $umbral = max(0, self::entero(self::CLAVE_CHEQUES_SUMA_UMBRAL, 5000000));
            $suma = self::sumaChequesParaDepositar();
            if ($suma >= $umbral && $umbral > 0) {
                $out[] = [
                    'titulo' => 'Cheques a depositar '.ConsultaInteligenteConsultaMetricasSupport::fmtImporte($suma),
                    'detalle' => 'Suma ≥ umbral '.ConsultaInteligenteConsultaMetricasSupport::fmtImporte((float) $umbral),
                    'nivel' => $suma >= ($umbral * 2) ? 'alta' : 'media',
                    'url' => EntidadSemanticaCatalogo::urlExplorar('cheque', [
                        'para_depositar' => 1,
                        'consultar' => 1,
                    ]),
                    'color' => '#B7950B',
                ];
            }
        }

        if (
            self::bool(self::CLAVE_FACTURAS_SUMA_VAR_ACTIVA, true)
            && can('listar-comprobante-proveedor', false)
            && EntidadSemanticaCatalogo::existe('comprobante_proveedor')
        ) {
            $mes = self::sumaComprobantesMes();
            $ant = self::sumaComprobantesMesAnterior();
            $umbralPct = max(1, self::entero(self::CLAVE_FACTURAS_SUMA_VAR_PCT, 25));
            if ($ant > 0 && $mes > 0) {
                $var = (($mes - $ant) / $ant) * 100;
                if (abs($var) >= $umbralPct) {
                    $out[] = [
                        'titulo' => sprintf(
                            'Suma facturas %s%.0f%% vs mes ant. (%s)',
                            $var > 0 ? '+' : '',
                            $var,
                            ConsultaInteligenteConsultaMetricasSupport::fmtImporte($mes)
                        ),
                        'detalle' => 'Mes ant. '.ConsultaInteligenteConsultaMetricasSupport::fmtImporte($ant),
                        'nivel' => abs($var) >= ($umbralPct * 2) ? 'alta' : 'media',
                        'url' => EntidadSemanticaCatalogo::urlExplorar('comprobante_proveedor', [
                            'consultar' => 1,
                            'filtro_modo' => 'qbe',
                            'qbe' => [
                                'entre_grupos' => 'and',
                                'grupos' => [[
                                    'logic' => 'and',
                                    'not' => 0,
                                    'criterios' => [[
                                        'campo' => 'fechacomprobante',
                                        'op' => 'este_mes',
                                        'valor' => '',
                                        'valor_hasta' => '',
                                    ]],
                                ]],
                            ],
                        ]),
                        'color' => $var > 0 ? '#1A5276' : '#117A65',
                    ];
                }
            }
        }

        if (
            self::bool(self::CLAVE_COBRANZAS_VAR_ACTIVA, true)
            && can('listar-cobranza', false)
            && EntidadSemanticaCatalogo::existe('cobranza')
        ) {
            $mes = self::contarCobranzasMes();
            $ant = self::contarCobranzasMesAnterior();
            $umbralPct = max(1, self::entero(self::CLAVE_COBRANZAS_VAR_PCT, 25));
            if ($ant > 0 && $mes > 0) {
                $var = (($mes - $ant) / $ant) * 100;
                if (abs($var) >= $umbralPct) {
                    $out[] = [
                        'titulo' => sprintf('Cobranzas del mes %s%.0f%% vs mes anterior', $var > 0 ? '+' : '', $var),
                        'detalle' => $mes.' este mes · '.$ant.' mes anterior',
                        'nivel' => abs($var) >= ($umbralPct * 2) ? 'alta' : 'media',
                        'url' => EntidadSemanticaCatalogo::urlExplorar('cobranza', [
                            'consultar' => 1,
                            'filtro_modo' => 'qbe',
                            'qbe' => [
                                'entre_grupos' => 'and',
                                'grupos' => [[
                                    'logic' => 'and',
                                    'not' => 0,
                                    'criterios' => [[
                                        'campo' => 'fecha',
                                        'op' => 'este_mes',
                                        'valor' => '',
                                        'valor_hasta' => '',
                                    ]],
                                ]],
                            ],
                        ]),
                        'color' => $var > 0 ? '#1A5276' : '#117A65',
                    ];
                }
            }
        }

        return $out;
    }

    private static function bool(string $clave, bool $fallback): bool
    {
        return ParametroSistemaSupport::boolean($clave, $fallback);
    }

    private static function entero(string $clave, int $fallback): int
    {
        return (int) ParametroSistemaSupport::decimal($clave, (float) $fallback);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private static function contarCheques(array $extra): int
    {
        $q = Cheque::query();
        ChequeListadoFiltros::aplicar($q, array_merge([
            'para_depositar' => ! empty($extra['para_depositar']),
            'para_depositar_hasta' => Carbon::today()->toDateString(),
        ], $extra));

        return (int) $q->count();
    }

    private static function chequesPropiosVencenEnDias(int $dias): int
    {
        $hasta = Carbon::today()->addDays($dias)->toDateString();
        $desde = Carbon::today()->toDateString();

        return (int) Cheque::query()
            ->where('origen', 'E')
            ->whereDate('fechapago', '>=', $desde)
            ->whereDate('fechapago', '<=', $hasta)
            ->count();
    }

    private static function contarComprobantesMes(): int
    {
        $inicio = Carbon::now()->startOfMonth()->toDateString();
        $fin = Carbon::now()->endOfMonth()->toDateString();

        return (int) DB::table('comprobante_proveedor')
            ->whereDate('fechacomprobante', '>=', $inicio)
            ->whereDate('fechacomprobante', '<=', $fin)
            ->count();
    }

    private static function contarComprobantesMesAnterior(): int
    {
        $inicio = Carbon::now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $fin = Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        return (int) DB::table('comprobante_proveedor')
            ->whereDate('fechacomprobante', '>=', $inicio)
            ->whereDate('fechacomprobante', '<=', $fin)
            ->count();
    }

    private static function sumaChequesParaDepositar(): float
    {
        $q = Cheque::query();
        ChequeListadoFiltros::aplicar($q, [
            'para_depositar' => true,
            'para_depositar_hasta' => Carbon::today()->toDateString(),
        ]);

        return (float) $q->sum('monto');
    }

    private static function sumaComprobantesMes(): float
    {
        $inicio = Carbon::now()->startOfMonth()->toDateString();
        $fin = Carbon::now()->endOfMonth()->toDateString();

        return (float) DB::table('comprobante_proveedor')
            ->whereDate('fechacomprobante', '>=', $inicio)
            ->whereDate('fechacomprobante', '<=', $fin)
            ->sum('total');
    }

    private static function sumaComprobantesMesAnterior(): float
    {
        $inicio = Carbon::now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $fin = Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        return (float) DB::table('comprobante_proveedor')
            ->whereDate('fechacomprobante', '>=', $inicio)
            ->whereDate('fechacomprobante', '<=', $fin)
            ->sum('total');
    }

    private static function contarCobranzasMes(): int
    {
        $inicio = Carbon::now()->startOfMonth()->toDateString();
        $fin = Carbon::now()->endOfMonth()->toDateString();

        return (int) DB::table('cobranza')
            ->whereDate('fecha', '>=', $inicio)
            ->whereDate('fecha', '<=', $fin)
            ->count();
    }

    private static function contarCobranzasMesAnterior(): int
    {
        $inicio = Carbon::now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $fin = Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        return (int) DB::table('cobranza')
            ->whereDate('fecha', '>=', $inicio)
            ->whereDate('fecha', '<=', $fin)
            ->count();
    }
}
