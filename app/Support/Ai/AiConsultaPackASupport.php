<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\Stock\Articulo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\HistorialPreciosArticuloService;
use App\Services\Compras\ProyeccionPagosReporteService;
use App\Support\Compras\ContratoVencimientoReporteFiltros;
use App\Support\Compras\HistorialPreciosArticuloFiltros;
use App\Support\Compras\OrdencompraContratoVencimientoSupport;
use App\Support\Compras\ProyeccionPagosReporteFiltros;
use App\Support\Stock\ArticuloSaldosDepositoSupport;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Pack A del copiloto: proyección pagos, contratos por vencer,
 * precio por proveedor, existencias por depósito (tabla).
 */
final class AiConsultaPackASupport
{
    public const LIMITE_DEFAULT = 12;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function proyeccionPagos(array $params): array
    {
        $intent = AiConsultaOperativaSupport::INTENT_PROYECCION_PAGOS;

        $filtros = ProyeccionPagosReporteFiltros::defaults();
        $filtros['salida'] = ProyeccionPagosReporteFiltros::SALIDA_RESUMEN;
        $filtros['agrupacion'] = ProyeccionPagosReporteFiltros::AGRUPACION_TRAMO;
        $filtros['orden'] = ProyeccionPagosReporteFiltros::ORDEN_VENCIMIENTO;
        $filtros['tramos_dias'] = '7,15,30,60';
        $filtros['tipo_informe'] = ! empty($params['vencidos'])
            ? ProyeccionPagosReporteFiltros::INFORME_VENCIDOS
            : ProyeccionPagosReporteFiltros::INFORME_A_VENCER;
        $filtros['consolidar_empresas'] = true;

        if (! empty($params['empresa_id']) && (int) $params['empresa_id'] > 0) {
            $filtros['empresa_ids'] = [(int) $params['empresa_id']];
        }

        try {
            ini_set('memory_limit', '512M');
            $resultado = app(ProyeccionPagosReporteService::class)->generar($filtros);
        } catch (Throwable $e) {
            return self::fallo($intent, 'No se pudo armar la proyección: '.$e->getMessage());
        }

        $tramosMeta = $resultado['tramos']['tramos'] ?? [];
        $etiquetasTramo = [];
        foreach ($tramosMeta as $t) {
            $etiquetasTramo[(string) ($t['clave'] ?? '')] = (string) ($t['etiqueta'] ?? $t['clave'] ?? '');
        }

        $importes = $resultado['totales']['importes'] ?? [];
        $filas = [];
        foreach (['tramo_1', 'tramo_2', 'tramo_3', 'tramo_4', 'posterior', 'adelantos', 'pend_aprobacion'] as $clave) {
            if (! array_key_exists($clave, $importes)) {
                continue;
            }
            $monto = (float) $importes[$clave];
            if (abs($monto) < 0.005) {
                continue;
            }
            $filas[] = [
                'tramo' => $etiquetasTramo[$clave] ?? match ($clave) {
                    'posterior' => 'Posterior',
                    'adelantos' => 'Adelantos',
                    'pend_aprobacion' => 'Pend. aprobación',
                    default => $clave,
                },
                'importe' => self::fmtImporte($monto),
            ];
        }

        $total = (float) ($resultado['totales']['total_adeudado'] ?? 0);
        $modo = $filtros['tipo_informe'] === ProyeccionPagosReporteFiltros::INFORME_VENCIDOS
            ? 'Vencidos (antigüedad)'
            : 'A vencer (proyección)';

        $parrafos = [
            'Proyección de pagos · '.$modo.' · base '.date('d/m/Y'),
            'Proveedores: '.(int) ($resultado['totales']['proveedores'] ?? 0)
                .' · Movimientos: '.(int) ($resultado['totales']['cantidad'] ?? 0)
                .' · Total adeudado: '.self::fmtImporte($total),
        ];

        $links = [];
        if (can('listar-reporte-proyeccion-pagos', false)) {
            $links[] = [
                'etiqueta' => 'Abrir proyección de pagos',
                'url' => route('reporte_proyeccion_pagos', ProyeccionPagosReporteFiltros::paraQueryString(array_merge($filtros, [
                    'consultar' => 1,
                ]))),
            ];
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'total_adeudado' => $total,
                'proveedores' => (int) ($resultado['totales']['proveedores'] ?? 0),
                'tipo_informe' => $filtros['tipo_informe'],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'tramo', 'label' => 'Tramo'],
                    ['key' => 'importe', 'label' => 'Importe'],
                ],
                'filas' => $filas,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function contratosPorVencer(array $params): array
    {
        $intent = AiConsultaOperativaSupport::INTENT_CONTRATOS_POR_VENCER;

        $horizonte = isset($params['dias_horizonte']) && is_numeric($params['dias_horizonte'])
            ? max(0, min(1095, (int) $params['dias_horizonte']))
            : ContratoVencimientoReporteFiltros::HORIZONTE_DEFAULT;

        $tipoAlerta = strtoupper(trim((string) ($params['tipo_alerta'] ?? ContratoVencimientoReporteFiltros::ALERTA_TODOS)));
        if (! array_key_exists($tipoAlerta, ContratoVencimientoReporteFiltros::OPCIONES_ALERTA)) {
            $tipoAlerta = ContratoVencimientoReporteFiltros::ALERTA_TODOS;
        }

        $empresaIds = [];
        if (! empty($params['empresa_id']) && (int) $params['empresa_id'] > 0) {
            $empresaIds = [(int) $params['empresa_id']];
        } else {
            $empresaIds = array_values(array_filter(array_map(
                'intval',
                app(EmpresaRepositoryInterface::class)->traeEmpresasAsignadas() ?: []
            )));
            if ($empresaIds === []) {
                $empresaIds = app(EmpresaRepositoryInterface::class)
                    ->allFiltrado()
                    ->pluck('id')
                    ->map(static fn ($id) => (int) $id)
                    ->filter(static fn (int $id) => $id > 0)
                    ->values()
                    ->all();
            }
        }

        $filtros = ContratoVencimientoReporteFiltros::defaults();
        $filtros['empresa_ids'] = $empresaIds;
        $filtros['dias_horizonte'] = $horizonte;
        $filtros['tipo_alerta'] = $tipoAlerta;

        try {
            $contratos = OrdencompraContratoVencimientoSupport::recopilar();
            foreach ($contratos as $i => $contrato) {
                $avisos = OrdencompraContratoVencimientoSupport::avisosCandidatos($contrato);
                $contratos[$i]['avisos'] = $avisos;
                $contratos[$i]['aviso_principal'] = OrdencompraContratoVencimientoSupport::avisoPrincipal($avisos);
                $contratos[$i]['motivo'] = OrdencompraContratoVencimientoSupport::motivo($contratos[$i]);
            }
            $filasRaw = ContratoVencimientoReporteFiltros::aplicar($contratos, $filtros);
        } catch (Throwable $e) {
            return self::fallo($intent, 'No se pudo leer contratos: '.$e->getMessage());
        }

        usort($filasRaw, static function (array $a, array $b): int {
            return ((int) ($a['dias_para_vencer'] ?? 9999)) <=> ((int) ($b['dias_para_vencer'] ?? 9999));
        });

        $limite = self::limite($params);
        $slice = array_slice($filasRaw, 0, $limite);
        $filas = [];
        foreach ($slice as $c) {
            $dias = (int) ($c['dias_para_vencer'] ?? 0);
            $filas[] = [
                'oc' => (string) ($c['numero'] ?? ''),
                'proveedor' => (string) ($c['proveedor'] ?? ''),
                'empresa' => (string) ($c['empresa'] ?? ''),
                'vence' => OrdencompraContratoVencimientoSupport::fmtFecha($c['vigencia_hasta'] ?? null),
                'dias' => (string) $dias,
                'alerta' => (string) (($c['aviso_principal']['tipo'] ?? null) ?: ($c['motivo'] ?? '—')),
                'disponible' => OrdencompraContratoVencimientoSupport::fmtNumero((float) ($c['monto_disponible'] ?? 0)),
            ];
        }

        $vencidos = 0;
        $porVencer = 0;
        foreach ($filasRaw as $c) {
            if ((int) ($c['dias_para_vencer'] ?? 0) < 0) {
                $vencidos++;
            } else {
                $porVencer++;
            }
        }

        $parrafos = [
            'Contratos / OC abiertas · horizonte '.$horizonte.' días'
                .' · alerta '.(ContratoVencimientoReporteFiltros::OPCIONES_ALERTA[$tipoAlerta] ?? 'Todos'),
            count($filasRaw).' contrato(s): '.$porVencer.' por vencer / en alerta, '.$vencidos.' vencido(s).',
        ];
        if (count($filasRaw) > count($filas)) {
            $parrafos[] = 'Se muestran '.count($filas).' de '.count($filasRaw).'.';
        }
        if ($filas === []) {
            $parrafos[] = 'No hay contratos en este filtro.';
        }

        $links = [];
        if (can('listar-reporte-contrato-vencimiento', false)) {
            $links[] = [
                'etiqueta' => 'Reporte contratos por vencer',
                'url' => route('reporte_contrato_vencimiento', ContratoVencimientoReporteFiltros::paraQueryString($filtros)),
            ];
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'cantidad' => count($filasRaw),
                'vencidos' => $vencidos,
                'por_vencer' => $porVencer,
                'dias_horizonte' => $horizonte,
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'oc', 'label' => 'OC'],
                    ['key' => 'proveedor', 'label' => 'Proveedor'],
                    ['key' => 'vence', 'label' => 'Vigencia'],
                    ['key' => 'dias', 'label' => 'Días'],
                    ['key' => 'alerta', 'label' => 'Alerta'],
                    ['key' => 'disponible', 'label' => 'Disponible'],
                    ['key' => 'empresa', 'label' => 'Empresa'],
                ],
                'filas' => $filas,
            ],
        ];
    }

    /**
     * Último precio de costo por proveedor para un artículo.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function precioPorProveedor(Articulo $articulo, array $params): array
    {
        $intent = AiConsultaOperativaSupport::INTENT_PRECIO_POR_PROVEEDOR;

        $filtros = HistorialPreciosArticuloFiltros::defaults();
        $filtros['articulo_id'] = (int) $articulo->id;
        $filtros['sku'] = '';
        $filtros['modo'] = HistorialPreciosArticuloFiltros::MODO_RESUMEN;
        $filtros['agrupacion'] = HistorialPreciosArticuloFiltros::AGRUPACION_ARTICULO_PROVEEDOR;
        $filtros['fecha_desde'] = null;
        $filtros['fecha_hasta'] = null;
        $filtros['solo_con_variacion'] = false;

        try {
            $resultado = app(HistorialPreciosArticuloService::class)->generar($filtros);
        } catch (Throwable $e) {
            return self::fallo($intent, 'No se pudo leer precios por proveedor: '.$e->getMessage());
        }

        $filasRaw = $resultado['filas'] ?? [];
        usort($filasRaw, static function (array $a, array $b): int {
            return ((float) ($b['precio_ultimo'] ?? 0)) <=> ((float) ($a['precio_ultimo'] ?? 0));
        });

        $limite = self::limite($params);
        $slice = array_slice($filasRaw, 0, $limite);
        $filas = [];
        foreach ($slice as $fila) {
            $varPct = $fila['variacion_pct'] ?? null;
            $filas[] = [
                'proveedor' => trim(
                    ((string) ($fila['codigoproveedor'] ?? '')).' — '.((string) ($fila['nombreproveedor'] ?? ''))
                ),
                'precio' => self::fmtImporte((float) ($fila['precio_ultimo'] ?? 0), (string) ($fila['moneda_abrev'] ?? '')),
                'fecha' => self::fmtFecha((string) ($fila['fecha_ultima'] ?? '')),
                'variacion' => $varPct === null
                    ? '—'
                    : (($varPct > 0 ? '+' : '').number_format((float) $varPct, 2, ',', '.').'%'),
                'com' => (string) ($fila['numerorecepcion'] ?? ''),
                'empresa' => (string) ($fila['nombreempresa'] ?? ''),
            ];
        }

        $parrafos = [
            'Último precio de costo por proveedor · '.$articulo->sku.' — '.($articulo->descripcion ?? ''),
            count($filasRaw).' proveedor(es) con COM confirmada.',
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay compras confirmadas para este artículo.';
        } elseif (count($filasRaw) > 1) {
            $ordenPrecio = $filasRaw;
            usort($ordenPrecio, static fn ($a, $b) => ((float) ($a['precio_ultimo'] ?? 0)) <=> ((float) ($b['precio_ultimo'] ?? 0)));
            $barato = $ordenPrecio[0] ?? null;
            $caro = $ordenPrecio[count($ordenPrecio) - 1] ?? null;
            if ($barato && $caro) {
                $parrafos[] = 'Más barato: '.($barato['codigoproveedor'] ?? '').' '
                    .self::fmtImporte((float) $barato['precio_ultimo'], (string) ($barato['moneda_abrev'] ?? ''))
                    .' · Más caro: '.($caro['codigoproveedor'] ?? '').' '
                    .self::fmtImporte((float) $caro['precio_ultimo'], (string) ($caro['moneda_abrev'] ?? ''));
            }
        }

        $links = [];
        if (can('listar-reporte-historial-precios-compra', false)) {
            $links[] = [
                'etiqueta' => 'Historial de precios (reporte)',
                'url' => route('reporte_historial_precios_articulo', HistorialPreciosArticuloFiltros::paraQueryString([
                    'articulo_id' => (int) $articulo->id,
                    'modo' => HistorialPreciosArticuloFiltros::MODO_RESUMEN,
                    'agrupacion' => HistorialPreciosArticuloFiltros::AGRUPACION_ARTICULO_PROVEEDOR,
                    'fecha_desde' => date('Y-m-d', strtotime('-2 years')),
                    'fecha_hasta' => date('Y-m-d'),
                ])),
            ];
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'score' => 0.91,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'articulo_id' => (int) $articulo->id,
                'sku' => (string) $articulo->sku,
                'proveedores' => count($filasRaw),
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'proveedor', 'label' => 'Proveedor'],
                    ['key' => 'precio', 'label' => 'Último precio'],
                    ['key' => 'fecha', 'label' => 'Fecha'],
                    ['key' => 'variacion', 'label' => 'Var. %'],
                    ['key' => 'com', 'label' => 'COM'],
                    ['key' => 'empresa', 'label' => 'Empresa'],
                ],
                'filas' => $filas,
            ],
        ];
    }

    /**
     * Tabla de existencias por depósito (complementa articulo_saldo).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function existenciasPorDeposito(Articulo $articulo, array $params): array
    {
        $intent = AiConsultaOperativaSupport::INTENT_EXISTENCIAS_DEPOSITO;
        // Permiso de intent ya validado en consultar(); puedeConsultar() exige sesión de rol.
        if (empty($params['_mcp_bridge']) && ! ArticuloSaldosDepositoSupport::puedeConsultar()) {
            return self::fallo($intent, 'Sin permiso para consultar saldos de depósito.');
        }

        $empresaId = isset($params['empresa_id']) && (int) $params['empresa_id'] > 0
            ? (int) $params['empresa_id']
            : null;
        $saldos = ArticuloSaldosDepositoSupport::listadoPorArticulo((int) $articulo->id, $empresaId);

        $filas = [];
        foreach ($saldos['filas'] as $fila) {
            $filas[] = [
                'deposito' => trim(($fila['codigo'] ?? '').' — '.($fila['nombre'] ?? '')),
                'saldo' => (string) ($fila['saldo_fmt'] ?? $fila['saldo'] ?? '0'),
                'empresa' => (string) ($fila['empresa_nombre'] ?? ''),
            ];
        }

        $parrafos = [
            'Existencias por depósito · '.$articulo->sku.' — '.($articulo->descripcion ?? ''),
            'Unidad: '.($saldos['articulo']['unidad_medida_abreviatura'] ?? $saldos['articulo']['unidad_medida'] ?? '—'),
            'Saldo total: '.($saldos['total_fmt'] ?? '0').' en '.count($filas).' depósito(s).',
        ];
        if ($filas === []) {
            $parrafos[] = 'Sin movimientos en depósitos autorizados.';
        }

        $links = [];
        if (can('editar-articulos', false) || can('listar-articulos', false)) {
            $links[] = [
                'etiqueta' => 'Artículo '.$articulo->sku,
                'url' => route('editar_articulo', ['id' => $articulo->id]).'?origen=modal_consulta&vista=consulta',
            ];
        }
        if (Route::has('reporte_existencias_deposito')
            && (can('listar-reporte-existencias-deposito', false) || can('listar-articulos', false))) {
            $links[] = [
                'etiqueta' => 'Reporte existencias por depósito',
                'url' => route('reporte_existencias_deposito'),
            ];
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'score' => 0.92,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'articulo_id' => (int) $articulo->id,
                'sku' => (string) $articulo->sku,
                'total_saldo' => $saldos['total'] ?? 0,
                'depositos' => count($filas),
            ],
            'tabla' => [
                'columnas' => array_values(array_filter([
                    ['key' => 'deposito', 'label' => 'Depósito'],
                    ['key' => 'saldo', 'label' => 'Saldo'],
                    ! empty($saldos['mostrar_empresa']) ? ['key' => 'empresa', 'label' => 'Empresa'] : null,
                ])),
                'filas' => $filas,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function limite(array $params): int
    {
        $n = isset($params['limite']) && is_numeric($params['limite'])
            ? (int) $params['limite']
            : (isset($params['max_lineas']) && is_numeric($params['max_lineas'])
                ? (int) $params['max_lineas']
                : self::LIMITE_DEFAULT);

        return max(1, min(40, $n > 0 ? $n : self::LIMITE_DEFAULT));
    }

    private static function fmtImporte(float $valor, string $moneda = ''): string
    {
        $txt = number_format($valor, 2, ',', '.');

        return $moneda !== '' ? $txt.' '.$moneda : $txt;
    }

    private static function fmtFecha(string $iso): string
    {
        if ($iso === '') {
            return '—';
        }
        $ts = strtotime($iso);

        return $ts ? date('d/m/Y', $ts) : $iso;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallo(string $intent, string $mensaje): array
    {
        return [
            'ok' => false,
            'intent' => $intent,
            'score' => 0.0,
            'parrafos' => [],
            'links' => [],
            'datos' => [],
            'error' => $mensaje,
        ];
    }
}
