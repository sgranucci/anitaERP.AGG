<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\RendicionGastronomiaCaja;
use App\Support\Caja\EstacionamientoJornadaComprobantePermiso;
use App\Support\Caja\RendicionEstacionamientoPdfPermiso;
use Illuminate\Support\Facades\Route;

/**
 * Grillas navegables para el modal «origen y movimientos» de campos ERP del Flash.
 *
 * Acciones: array de {url, title, icon, icon_extra?} para renderizar en JS
 * con el mismo patrón btn-accion-tabla del index.
 */
final class FlashCajaOrigenErpDetalleSupport
{
    /**
     * @param  array<string, mixed>  $calculado
     * @return list<array<string, mixed>>
     */
    public static function secciones(string $campo, int $empresaId, string $fechaSql, array $calculado): array
    {
        return match ($campo) {
            'estac', 'cant_vehic' => self::seccionesEstacionamiento($empresaId, $fechaSql, $calculado, $campo),
            'ayb' => self::seccionesAyB($empresaId, $fechaSql, $calculado),
            'vending' => self::seccionesVending($calculado),
            'bingo_cant_carton', 'bingo_total_venta', 'bingo_resultado' => self::seccionesBingo($empresaId, $fechaSql, $calculado, $campo),
            'slot_d', 'slot_r' => self::seccionesImpuestosRendicion($calculado),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $calculado
     * @return list<array<string, mixed>>
     */
    private static function seccionesEstacionamiento(
        int $empresaId,
        string $fechaSql,
        array $calculado,
        string $campo,
    ): array {
        $detalle = is_array($calculado['detalle_erp']['estacionamiento'] ?? null)
            ? $calculado['detalle_erp']['estacionamiento']
            : [];
        $jornadas = is_array($detalle['jornadas'] ?? null) ? $detalle['jornadas'] : [];

        $filasJornada = [];
        foreach ($jornadas as $j) {
            $jornadaId = (int) ($j['jornada_id'] ?? 0);
            $filasJornada[] = [
                'jornada_id' => $jornadaId > 0 ? (string) $jornadaId : '',
                'apertura' => (string) ($j['apertura_en'] ?? ''),
                'cierre' => (string) ($j['cierre_en'] ?? ''),
                'facturas' => (float) ($j['facturas'] ?? 0),
                'notas_credito' => (float) ($j['notas_credito'] ?? 0),
                'neto' => (float) ($j['neto'] ?? 0),
                'cantidad_comprobantes' => (int) ($j['cantidad_comprobantes'] ?? 0),
                'acciones' => self::accionesJornadaEstacionamiento($jornadaId),
            ];
        }

        $secciones = [[
            'titulo' => 'Jornadas de estacionamiento (fuente del Flash)',
            'nota' => 'El total del Flash suma ventas netas (facturas − NC) de jornadas cerradas. '
                .'No usa el arqueo de la rendición de caja.',
            'columnas' => [
                ['key' => 'jornada_id', 'label' => 'Jornada'],
                ['key' => 'apertura', 'label' => 'Apertura'],
                ['key' => 'cierre', 'label' => 'Cierre'],
                ['key' => 'facturas', 'label' => 'Facturas', 'num' => true],
                ['key' => 'notas_credito', 'label' => 'NC', 'num' => true],
                ['key' => 'neto', 'label' => 'Neto', 'num' => true],
                ['key' => 'cantidad_comprobantes', 'label' => 'Comprob.', 'num' => true],
                ['key' => 'acciones', 'label' => 'Acciones', 'acciones' => true],
            ],
            'filas' => $filasJornada,
            'subtotal' => $campo === 'cant_vehic'
                ? (float) ($calculado['cant_vehic'] ?? 0)
                : (float) ($calculado['estac'] ?? 0),
            'truncado' => false,
            'sp' => null,
            'params' => null,
        ]];

        $rendiciones = RendicionEstacionamientoCaja::query()
            ->with([
                'jornada:id,fecha_jornada',
                'turnoOperativo:id,jornada_estacionamiento_id',
                'turnoOperativo.jornada:id,fecha_jornada',
            ])
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($fechaSql) {
                $q->whereHas('jornada', fn ($j) => $j->whereDate('fecha_jornada', $fechaSql))
                    ->orWhereHas('turnoOperativo.jornada', fn ($j) => $j->whereDate('fecha_jornada', $fechaSql));
            })
            ->orderBy('id')
            ->get();

        $filasRend = [];
        foreach ($rendiciones as $r) {
            $filasRend[] = [
                'codigo' => (string) ($r->codigo ?? ''),
                'tipo' => (string) ($r->tipo ?: RendicionEstacionamientoCaja::TIPO_TURNO),
                'nro_oper' => $r->nro_oper_anita !== null ? (string) $r->nro_oper_anita : '',
                'totalfactura' => round((float) ($r->totalfactura ?? 0), 2),
                'totalcobrado' => round((float) ($r->totalcobrado ?? 0), 2),
                'totalnotacredito' => round((float) ($r->totalnotacredito ?? 0), 2),
                'acciones' => self::accionesRendicionEstacionamiento($r),
            ];
        }

        $secciones[] = [
            'titulo' => 'Rendiciones de caja del día (navegación)',
            'nota' => 'Arqueo / presentación en caja. Útil para auditar, pero el Flash no suma estos montos '
                .'si difieren de la venta directa de la jornada.',
            'columnas' => [
                ['key' => 'codigo', 'label' => 'Código'],
                ['key' => 'tipo', 'label' => 'Tipo'],
                ['key' => 'nro_oper', 'label' => 'Nro. oper Anita'],
                ['key' => 'totalfactura', 'label' => 'Facturas', 'num' => true],
                ['key' => 'totalnotacredito', 'label' => 'NC', 'num' => true],
                ['key' => 'totalcobrado', 'label' => 'Cobrado', 'num' => true],
                ['key' => 'acciones', 'label' => 'Acciones', 'acciones' => true],
            ],
            'filas' => $filasRend,
            'subtotal' => null,
            'truncado' => false,
            'sp' => null,
            'params' => $fechaSql,
        ];

        return $secciones;
    }

    /**
     * @param  array<string, mixed>  $calculado
     * @return list<array<string, mixed>>
     */
    private static function seccionesAyB(int $empresaId, string $fechaSql, array $calculado): array
    {
        $ayb = is_array($calculado['detalle_erp']['ayb'] ?? null)
            ? $calculado['detalle_erp']['ayb']
            : [];

        $filasResumen = [[
            'concepto' => 'Facturas gastronomía (excl. PV estacionamiento)',
            'cantidad' => (int) ($ayb['cantidad_facturas'] ?? 0),
            'monto' => (float) ($ayb['bruto'] ?? 0),
        ], [
            'concepto' => 'Notas de crédito',
            'cantidad' => (int) ($ayb['cantidad_nc'] ?? 0),
            'monto' => (float) ($ayb['nc'] ?? 0),
        ], [
            'concepto' => 'Neto (Flash ayb)',
            'cantidad' => (int) ($ayb['cantidad_facturas'] ?? 0) + (int) ($ayb['cantidad_nc'] ?? 0),
            'monto' => (float) ($ayb['neto'] ?? $calculado['ayb'] ?? 0),
        ]];

        $secciones = [[
            'titulo' => 'Resumen venta gastronomía ERP',
            'nota' => 'Neto del día = facturas − NC de emisiones gastronómicas (sin puntos de venta de estacionamiento).',
            'columnas' => [
                ['key' => 'concepto', 'label' => 'Concepto'],
                ['key' => 'cantidad', 'label' => 'Cant.', 'num' => true],
                ['key' => 'monto', 'label' => 'Monto', 'num' => true],
            ],
            'filas' => $filasResumen,
            'subtotal' => (float) ($ayb['neto'] ?? $calculado['ayb'] ?? 0),
            'truncado' => false,
            'sp' => null,
            'params' => null,
        ]];

        $rendiciones = RendicionGastronomiaCaja::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($fechaSql) {
                $q->whereHas('jornada', fn ($j) => $j->whereDate('fecha_jornada', $fechaSql))
                    ->orWhereHas('turnoOperativo.jornada', fn ($j) => $j->whereDate('fecha_jornada', $fechaSql))
                    ->orWhere(function ($legacy) use ($fechaSql) {
                        $legacy->whereNull('jornada_gastronomia_id')
                            ->whereNull('turno_operativo_gastronomia_id')
                            ->whereDate('fecharendicion', $fechaSql);
                    });
            })
            ->orderBy('id')
            ->get(['id', 'codigo', 'nro_oper_anita', 'tipo', 'totalfactura', 'totalcobrado', 'totalnotacredito', 'fecharendicion']);

        $filasRend = [];
        foreach ($rendiciones as $r) {
            $filasRend[] = [
                'codigo' => (string) ($r->codigo ?? ''),
                'tipo' => (string) ($r->tipo ?? ''),
                'nro_oper' => $r->nro_oper_anita !== null ? (string) $r->nro_oper_anita : '',
                'totalfactura' => round((float) ($r->totalfactura ?? 0), 2),
                'totalcobrado' => round((float) ($r->totalcobrado ?? 0), 2),
                'acciones' => self::accionesRendicionGastronomia((int) $r->id),
            ];
        }

        $secciones[] = [
            'titulo' => 'Rendiciones gastronomía de caja (navegación)',
            'nota' => 'Presentaciones del día. El Flash usa venta directa ERP, no necesariamente estos totales de arqueo.',
            'columnas' => [
                ['key' => 'codigo', 'label' => 'Código'],
                ['key' => 'tipo', 'label' => 'Tipo'],
                ['key' => 'nro_oper', 'label' => 'Nro. oper Anita'],
                ['key' => 'totalfactura', 'label' => 'Facturas', 'num' => true],
                ['key' => 'totalcobrado', 'label' => 'Cobrado', 'num' => true],
                ['key' => 'acciones', 'label' => 'Acciones', 'acciones' => true],
            ],
            'filas' => $filasRend,
            'subtotal' => null,
            'truncado' => false,
            'sp' => null,
            'params' => $fechaSql,
        ];

        return $secciones;
    }

    /**
     * @param  array<string, mixed>  $calculado
     * @return list<array<string, mixed>>
     */
    private static function seccionesVending(array $calculado): array
    {
        $detalle = is_array($calculado['detalle_erp']['vending'] ?? null)
            ? $calculado['detalle_erp']['vending']
            : [];
        $filasSrc = is_array($detalle['filas'] ?? null) ? $detalle['filas'] : [];
        $filas = [];
        foreach ($filasSrc as $f) {
            $id = (int) ($f['id'] ?? 0);
            $filas[] = [
                'codigo' => (string) ($f['codigo'] ?? ''),
                'maquina' => (string) ($f['maquina'] ?? ''),
                'numero_cierre' => (string) ($f['numero_cierre'] ?? ''),
                'nro_oper' => isset($f['nro_oper_anita']) && $f['nro_oper_anita'] !== null
                    ? (string) $f['nro_oper_anita']
                    : '',
                'total_ventas' => (float) ($f['total_ventas'] ?? 0),
                'total_cobrado' => (float) ($f['total_cobrado'] ?? 0),
                'acciones' => self::accionesRendicionVending($id),
            ];
        }

        return [[
            'titulo' => 'Rendiciones maquinavending ERP del día',
            'nota' => 'El Flash suma total_ventas de cada rendición de máquina vending de la jornada.',
            'columnas' => [
                ['key' => 'codigo', 'label' => 'Código'],
                ['key' => 'maquina', 'label' => 'Máquina'],
                ['key' => 'numero_cierre', 'label' => 'Cierre'],
                ['key' => 'nro_oper', 'label' => 'Nro. oper Anita'],
                ['key' => 'total_ventas', 'label' => 'Ventas', 'num' => true],
                ['key' => 'total_cobrado', 'label' => 'Cobrado', 'num' => true],
                ['key' => 'acciones', 'label' => 'Acciones', 'acciones' => true],
            ],
            'filas' => $filas,
            'subtotal' => (float) ($detalle['total'] ?? $calculado['vending'] ?? 0),
            'truncado' => false,
            'sp' => null,
            'params' => null,
        ]];
    }

    /**
     * @param  array<string, mixed>  $calculado
     * @return list<array<string, mixed>>
     */
    private static function seccionesBingo(int $empresaId, string $fechaSql, array $calculado, string $campo): array
    {
        $bingo = is_array($calculado['detalle_erp']['bingo'] ?? null)
            ? $calculado['detalle_erp']['bingo']
            : [];
        $fuente = (string) ($bingo['fuente'] ?? 'erp');

        $filas = [];
        if ($fuente === 'anita_rendbingo') {
            $filas[] = [
                'codigo' => 'Anita rendbingo',
                'nro_oper' => '',
                'cant_cartones' => (int) ($bingo['bingo_cant_carton'] ?? 0),
                'total_cartones' => (float) ($bingo['bingo_total_venta'] ?? 0),
                'resultado' => (float) ($bingo['bingo_resultado'] ?? 0),
                'acciones' => [],
            ];
        } else {
            $rendiciones = RendicionBingoCaja::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha_jornada', $fechaSql)
                ->orderBy('id')
                ->get();

            foreach ($rendiciones as $r) {
                $resultado = round(
                    (float) ($r->deposito ?? 0)
                    - (float) ($r->sobrante_faltante ?? 0)
                    - (float) ($r->vales ?? 0)
                    - (float) ($r->refuerzo_prestamo ?? 0)
                    - (float) ($r->redondeo ?? 0),
                    2
                );
                $filas[] = [
                    'codigo' => (string) ($r->codigo ?? ''),
                    'nro_oper' => $r->nro_oper_anita !== null ? (string) $r->nro_oper_anita : '',
                    'cant_cartones' => (int) ($r->cant_cartones ?? 0),
                    'total_cartones' => round((float) ($r->total_cartones ?? 0), 2),
                    'resultado' => $resultado,
                    'acciones' => self::accionesRendicionBingo((int) $r->id),
                ];
            }
        }

        $subtotal = match ($campo) {
            'bingo_cant_carton' => (float) ($calculado['bingo_cant_carton'] ?? 0),
            'bingo_resultado' => (float) ($calculado['bingo_resultado'] ?? 0),
            default => (float) ($calculado['bingo_total_venta'] ?? 0),
        };

        return [[
            'titulo' => 'Rendiciones bingo del día (fuente: '.$fuente.')',
            'nota' => 'Preferencia ERP rendicion_bingo_caja; si no hay datos y el fallback está activo, Informix rendbingo.',
            'columnas' => [
                ['key' => 'codigo', 'label' => 'Código'],
                ['key' => 'nro_oper', 'label' => 'Nro. oper Anita'],
                ['key' => 'cant_cartones', 'label' => 'Cartones', 'num' => true],
                ['key' => 'total_cartones', 'label' => 'Ventas', 'num' => true],
                ['key' => 'resultado', 'label' => 'Resultado', 'num' => true],
                ['key' => 'acciones', 'label' => 'Acciones', 'acciones' => true],
            ],
            'filas' => $filas,
            'subtotal' => $subtotal,
            'truncado' => false,
            'sp' => null,
            'params' => $fechaSql,
        ]];
    }

    /**
     * @param  array<string, mixed>  $calculado
     * @return list<array<string, mixed>>
     */
    private static function seccionesImpuestosRendicion(array $calculado): array
    {
        $imp = is_array($calculado['impuestos_rendicion'] ?? null) ? $calculado['impuestos_rendicion'] : [];
        $origen = (string) ($imp['origen'] ?? 'ninguno');
        $rendicionId = (int) ($imp['rendicion_id'] ?? 0);
        $acciones = [];
        if ($origen === 'erp' && $rendicionId > 0) {
            $acciones = self::accionesRendicionMaquina($rendicionId);
        }

        $origenVenta = (string) ($imp['origen_venta_ficha'] ?? $origen);
        $nota = 'Venta de fichas suma a slot_d / slot_r; impuesto drop se resta una sola vez. '
            .'Impuesto venta ya no entra a drop/win.';
        if ($origen === 'anita') {
            $nota = 'Origen Anita rendmaquina'
                .($imp['nro_oper'] ? ' nro_oper '.(string) $imp['nro_oper'] : '')
                .'. '.$nota;
        } elseif ($origenVenta === 'wigos_sesion') {
            $nota = 'Sin turno C: venta de fichas toma VentaTickets de sesión Wigos (M+T+N). '.$nota;
        } else {
            $nota = 'Preferencia Anita turno C; fallback ERP. '.$nota;
        }

        return [[
            'titulo' => 'Rendición máquinas turno C (venta fichas e impuestos)',
            'nota' => $nota,
            'columnas' => [
                ['key' => 'origen', 'label' => 'Origen'],
                ['key' => 'nro_oper', 'label' => 'Nro. oper'],
                ['key' => 'venta_ficha', 'label' => 'Venta fichas', 'num' => true],
                ['key' => 'impuesto_drop', 'label' => 'Imp. drop', 'num' => true],
                ['key' => 'impuesto_venta', 'label' => 'Imp. venta (no entra)', 'num' => true],
                ['key' => 'acciones', 'label' => 'Acciones', 'acciones' => true],
            ],
            'filas' => [[
                'origen' => $origenVenta !== '' ? $origenVenta : $origen,
                'nro_oper' => $imp['nro_oper'] !== null ? (string) $imp['nro_oper'] : '',
                'venta_ficha' => (float) ($imp['venta_ficha'] ?? 0),
                'impuesto_venta' => (float) ($imp['impuesto_venta'] ?? 0),
                'impuesto_drop' => (float) ($imp['impuesto_drop'] ?? 0),
                'acciones' => $acciones,
            ]],
            'subtotal' => (float) ($imp['venta_ficha'] ?? 0),
            'truncado' => false,
            'sp' => null,
            'params' => null,
        ]];
    }

    /**
     * @return list<array{url: string, title: string, icon: string, icon_extra?: string}>
     */
    private static function accionesJornadaEstacionamiento(int $jornadaId): array
    {
        if ($jornadaId <= 0 || ! Route::has('estacionamiento_jornada_comprobante_totales_z')) {
            return [];
        }
        $puede = EstacionamientoJornadaComprobantePermiso::puedeVerComprobanteTotalesZ()
            || can('crear-flash-caja', false)
            || can('actualizar-flash-caja', false);
        if (! $puede) {
            return [];
        }

        return [self::accionLink(
            route('estacionamiento_jornada_comprobante_totales_z', [
                'jornadaId' => $jornadaId,
                'inline' => 1,
            ]),
            'Reporte Totales Z jornada',
            'fa-file-pdf-o',
            'text-danger',
        )];
    }

    /**
     * Mismas acciones que el index de rendición estacionamiento (consultar / PDF / Totales Z o cierre turno).
     *
     * @return list<array{url: string, title: string, icon: string, icon_extra?: string}>
     */
    private static function accionesRendicionEstacionamiento(RendicionEstacionamientoCaja $r): array
    {
        $id = (int) $r->id;
        if ($id <= 0) {
            return [];
        }

        $parts = [];
        $puedeConsultar = can('editar-rendicion-estacionamiento-caja', false)
            || can('listar-rendicion-estacionamiento-caja', false)
            || can('crear-flash-caja', false)
            || can('actualizar-flash-caja', false);

        if (Route::has('editar_rendicionestacionamiento') && $puedeConsultar) {
            $parts[] = self::accionLink(
                route('editar_rendicionestacionamiento', [
                    'id' => $id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'Consultar rendición',
                'fa-edit',
            );
        }

        if (Route::has('imprimir_rendicion_estacionamiento')
            && (RendicionEstacionamientoPdfPermiso::puedeVerPdfRendicion()
                || can('listar-rendicion-estacionamiento-caja', false)
                || can('crear-flash-caja', false)
                || can('actualizar-flash-caja', false))) {
            $parts[] = self::accionLink(
                route('imprimir_rendicion_estacionamiento', ['id' => $id, 'inline' => 1]),
                'Ver PDF rendición',
                'fa-print',
            );
        }

        $turnoId = (int) ($r->turno_operativo_estacionamiento_id ?? 0);
        if ($turnoId > 0
            && Route::has('estacionamiento_cierre_turno_comprobante_cierre')
            && can('ver-comprobante-cierre-turno-estacionamiento', false)) {
            $parts[] = self::accionLink(
                route('estacionamiento_cierre_turno_comprobante_cierre', [
                    'id' => $turnoId,
                    'inline' => 1,
                ]),
                'Ver comprobante cierre turno',
                'fa-file-pdf-o',
                'text-danger',
            );
        } elseif (
            $r->esRendicionJornada()
            && (int) ($r->jornada_estacionamiento_id ?? 0) > 0
            && Route::has('estacionamiento_jornada_comprobante_totales_z')
            && EstacionamientoJornadaComprobantePermiso::puedeVerComprobanteTotalesZ()
        ) {
            $parts[] = self::accionLink(
                route('estacionamiento_jornada_comprobante_totales_z', [
                    'jornadaId' => (int) $r->jornada_estacionamiento_id,
                    'inline' => 1,
                ]),
                'Reporte Totales Z jornada',
                'fa-file-pdf-o',
                'text-danger',
            );
        }

        return $parts;
    }

    /**
     * @return list<array{url: string, title: string, icon: string, icon_extra?: string}>
     */
    private static function accionesRendicionGastronomia(int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        $parts = [];
        $puedeConsultar = can('editar-rendicion-gastronomia-caja', false)
            || can('listar-rendicion-gastronomia-caja', false)
            || can('crear-flash-caja', false)
            || can('actualizar-flash-caja', false);

        if (Route::has('editar_rendiciongastronomia') && $puedeConsultar) {
            $parts[] = self::accionLink(
                route('editar_rendiciongastronomia', [
                    'id' => $id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]),
                'Consultar rendición',
                'fa-edit',
            );
        }
        if (Route::has('imprimir_rendicion_gastronomia')
            && (can('ver-pdf-rendicion-gastronomia-caja', false)
                || can('listar-rendicion-gastronomia-caja', false)
                || can('crear-flash-caja', false)
                || can('actualizar-flash-caja', false))) {
            $parts[] = self::accionLink(
                route('imprimir_rendicion_gastronomia', ['id' => $id, 'inline' => 1]),
                'Ver PDF rendición',
                'fa-print',
            );
        }

        return $parts;
    }

    /**
     * @return list<array{url: string, title: string, icon: string, icon_extra?: string}>
     */
    private static function accionesRendicionVending(int $id): array
    {
        if ($id <= 0 || ! Route::has('editar_maquinavending_rendicion_gastronomia')) {
            return [];
        }
        $puede = can('editar-maquinavending-rendicion-gastronomia', false)
            || can('listar-maquinavending-rendicion-gastronomia', false)
            || can('crear-flash-caja', false)
            || can('actualizar-flash-caja', false);
        if (! $puede) {
            return [];
        }

        $parts = [self::accionLink(
            route('editar_maquinavending_rendicion_gastronomia', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            'Consultar rendición',
            'fa-edit',
        )];
        if (Route::has('maquinavending_rendicion_comprobante')) {
            $parts[] = self::accionLink(
                route('maquinavending_rendicion_comprobante', ['id' => $id]),
                'Ver comprobante',
                'fa-print',
            );
        }

        return $parts;
    }

    /**
     * @return list<array{url: string, title: string, icon: string, icon_extra?: string}>
     */
    private static function accionesRendicionBingo(int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        $parts = [];
        if (Route::has('imprimir_rendicion_bingo')
            && (can('imprimir-rendicion-bingo-caja', false)
                || can('listar-rendicion-bingo-caja', false)
                || can('crear-flash-caja', false)
                || can('actualizar-flash-caja', false))) {
            $parts[] = self::accionLink(
                route('imprimir_rendicion_bingo', ['id' => $id, 'inline' => 1]),
                'Ver PDF rendición',
                'fa-print',
            );
        }
        if (Route::has('rendicionbingo')
            && (can('listar-rendicion-bingo-caja', false)
                || can('crear-flash-caja', false)
                || can('actualizar-flash-caja', false))) {
            $parts[] = self::accionLink(
                route('rendicionbingo', [
                    'filtro_modo' => 'campo',
                    'filtro_campo' => 'id',
                    'filtro_operador' => 'igual',
                    'filtro_valor' => $id,
                ]),
                'Ver en listado bingo',
                'fa-list',
            );
        }

        return $parts;
    }

    /**
     * @return list<array{url: string, title: string, icon: string, icon_extra?: string}>
     */
    private static function accionesRendicionMaquina(int $id): array
    {
        if ($id <= 0 || ! Route::has('editar_rendicion_maquina')) {
            return [];
        }
        $puede = can('editar-rendicion-maquina', false)
            || can('listar-rendicion-maquina', false)
            || can('crear-flash-caja', false)
            || can('actualizar-flash-caja', false);
        if (! $puede) {
            return [];
        }

        $parts = [self::accionLink(
            route('editar_rendicion_maquina', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            'Consultar rendición máquinas',
            'fa-edit',
        )];
        if (Route::has('imprimir_rendicion_maquina')) {
            $parts[] = self::accionLink(
                route('imprimir_rendicion_maquina', ['id' => $id, 'inline' => 1]),
                'Ver PDF rendición',
                'fa-print',
            );
        }

        return $parts;
    }

    /**
     * @return array{url: string, title: string, icon: string, icon_extra?: string}
     */
    private static function accionLink(string $url, string $title, string $icon, ?string $iconExtra = null): array
    {
        $accion = [
            'url' => $url,
            'title' => $title,
            'icon' => $icon,
        ];
        if ($iconExtra !== null && $iconExtra !== '') {
            $accion['icon_extra'] = $iconExtra;
        }

        return $accion;
    }
}
