<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportAplmovSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportBridgeReader;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportFormatoSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportVentaMatchSupport;
use App\Support\Ventas\ClienteCuentacorrienteDeudaAlcanceSupport;
use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;
use App\Support\Ventas\ClienteCuentacorrienteRemitoInternoSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa deuda de clientes desde Anita (climov) → ERP.
 *
 * Filtro de negocio: solo comprobantes con fila en Anita `venta`
 * (excluye COB/REC/PRE vía tipos_no_deuda; COA pendientes sí se importan).
 * CC de lo abierto queda con el saldo pendiente.
 * Si la factura del ERP ya está aplicada en Anita, entra también el COA que la
 * aplica (contrapartida en la ficha). No hace falta el APA ni la NCI.
 * Por defecto también salda CC del ERP que Anita ya no tiene abiertas
 * (canceladas / monto = cobrado): aplica el COA, el COB o la nota de crédito
 * de aplmov y crea ese comprobante si falta. No graba un cierre sin
 * contrapartida: eso deja la factura paga y el crédito abierto.
 * No escribe Anita.
 */
class ClienteCuentacorrienteImportarDesdeAnitaService
{
    /** Cierre cuando Anita ya no tiene la deuda abierta y no hay contrapartida importable. */
    public const ETIQUETA_CIERRE_SIN_CONTRAPARTIDA = 'Anita sync (sin deuda Anita)';

    private string $motivoSinDeuda = '';
    public function __construct(
        private readonly ClienteCuentacorrienteAnitaImportBridgeReader $reader = new ClienteCuentacorrienteAnitaImportBridgeReader,
        private readonly VentaDeudaImportarDesdeAnitaService $ventaImport = new VentaDeudaImportarDesdeAnitaService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function importar(
        bool $dryRun = true,
        ?string $clienteCodigo = null,
        ?string $desdeIso = null,
        ?string $hastaIso = null,
        ?int $empresaCodigo = null,
        bool $soloConSaldo = true,
        bool $forzarAplicaciones = false,
        ?int $limite = null,
        int $usuarioId = 1,
        bool $importarVentasFaltantes = true,
        bool $cerrarSinDeudaAnita = true,
    ): array {
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $desdeYmd = $desdeIso ? ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($desdeIso) : null;
        $hastaYmd = $hastaIso ? ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($hastaIso) : null;

        $climovs = $this->reader->listarClimovPendiente(
            $clienteCodigo,
            $desdeYmd ?: null,
            $hastaYmd ?: null,
            $empresaCodigo,
            $soloConSaldo,
        );

        $signoPorTipo = $this->mapaSignoTipos();
        $stats = $this->statsVacios($perfil);
        $stats['anita_climov'] = count($climovs);

        $climovsDeuda = [];
        $clavesDoc = [];
        foreach ($climovs as $climov) {
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($climov['cliv_tipo'] ?? ''));
            if ($tipo === '' || ClienteCuentacorrienteAnitaImportFormatoSupport::esTipoNoDeuda($tipo, $perfil)) {
                $stats['omitidas_tipo_no_deuda']++;

                continue;
            }
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeClimov($climov);
            $climovsDeuda[] = $climov;
            $clavesDoc[$clave] = $clave;
        }

        $anitaVentas = $this->reader->indexarVentaPorClaves(array_values($clavesDoc));
        $stats['anita_venta'] = count($anitaVentas);

        $climovsConAnitaVenta = [];
        $clavesConAnita = [];
        $climovsCreditoSinVenta = [];
        $clavesCreditoSinVenta = [];
        foreach ($climovsDeuda as $climov) {
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeClimov($climov);
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($climov['cliv_tipo'] ?? ''));
            if (isset($anitaVentas[$clave])) {
                $climovsConAnitaVenta[] = $climov;
                $clavesConAnita[$clave] = $clave;

                continue;
            }
            if (ClienteCuentacorrienteAnitaImportFormatoSupport::esTipoCreditoSinVenta($tipo, $perfil)) {
                $climovsCreditoSinVenta[] = $climov;
                $clavesCreditoSinVenta[$clave] = $clave;

                continue;
            }
            $stats['omitidas_sin_anita_venta']++;
        }

        $clavesMatch = [];
        foreach (array_merge($clavesConAnita, $clavesCreditoSinVenta) as $clave) {
            [$tipo, $letra, $suc, $nro] = explode('|', $clave);
            $clavesMatch[] = [
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => (int) $suc,
                'numero' => (int) $nro,
            ];
        }
        $indiceVentas = ClienteCuentacorrienteAnitaImportVentaMatchSupport::indexarVentasPorClaves($clavesMatch);

        $faltanErp = [];
        foreach ($clavesConAnita as $clave) {
            if (! isset($indiceVentas[$clave]) || $indiceVentas[$clave] === []) {
                $faltanErp[] = $clave;
            }
        }
        $faltanErpCredito = [];
        foreach ($clavesCreditoSinVenta as $clave) {
            if (! isset($indiceVentas[$clave]) || $indiceVentas[$clave] === []) {
                $faltanErpCredito[] = $clave;
            }
        }
        $stats['ventas_faltantes_erp'] = count($faltanErp) + count($faltanErpCredito);
        $stats['credito_sin_venta_anita'] = count($climovsCreditoSinVenta);

        $statsVenta = [
            'a_crear' => 0,
            'creadas' => 0,
            'ya_en_erp' => 0,
            'sin_cliente' => 0,
            'sin_tipo' => 0,
            'sin_puntoventa' => 0,
            'muestra' => [],
            'errores' => [],
        ];
        if ($importarVentasFaltantes && $faltanErp !== []) {
            $statsVenta = $this->ventaImport->importarPorClaves($faltanErp, $dryRun, $usuarioId);
            $stats['ventas_a_crear'] = (int) ($statsVenta['a_crear'] ?? 0);
            $stats['ventas_creadas'] = (int) ($statsVenta['creadas'] ?? 0);
            $stats['ventas_sin_cliente'] = (int) ($statsVenta['sin_cliente'] ?? 0);
            $stats['ventas_errores'] = array_merge($stats['ventas_errores'], $statsVenta['errores'] ?? []);
            $stats['muestra_ventas'] = $statsVenta['muestra'] ?? [];
            if (! $dryRun && (int) ($statsVenta['creadas'] ?? 0) > 0) {
                $indiceVentas = ClienteCuentacorrienteAnitaImportVentaMatchSupport::indexarVentasPorClaves($clavesMatch);
            }
        } elseif ($faltanErp !== []) {
            $stats['ventas_a_crear'] = count($faltanErp);
            $stats['omitidas_sin_venta_erp'] = count($faltanErp);
        }

        if ($importarVentasFaltantes && $faltanErpCredito !== []) {
            $climovsACrear = [];
            $faltanSet = array_flip($faltanErpCredito);
            foreach ($climovsCreditoSinVenta as $climov) {
                $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeClimov($climov);
                if (isset($faltanSet[$clave])) {
                    $climovsACrear[] = $climov;
                }
            }
            $statsCredito = $this->ventaImport->importarDesdeClimov($climovsACrear, $dryRun, $usuarioId);
            $stats['ventas_a_crear'] += (int) ($statsCredito['a_crear'] ?? 0);
            $stats['ventas_creadas'] += (int) ($statsCredito['creadas'] ?? 0);
            $stats['ventas_sin_cliente'] += (int) ($statsCredito['sin_cliente'] ?? 0);
            $stats['ventas_errores'] = array_merge($stats['ventas_errores'], $statsCredito['errores'] ?? []);
            $stats['muestra_ventas'] = array_merge(
                $stats['muestra_ventas'],
                $statsCredito['muestra'] ?? []
            );
            if (! $dryRun && (int) ($statsCredito['creadas'] ?? 0) > 0) {
                $indiceVentas = ClienteCuentacorrienteAnitaImportVentaMatchSupport::indexarVentasPorClaves($clavesMatch);
            }
        } elseif ($faltanErpCredito !== []) {
            $stats['ventas_a_crear'] += count($faltanErpCredito);
            $stats['omitidas_sin_venta_erp'] += count($faltanErpCredito);
        }

        $climovsParaCc = array_merge($climovsConAnitaVenta, $climovsCreditoSinVenta);

        $plan = [];
        foreach ($climovsParaCc as $climov) {
            $preparado = $this->prepararClimov($climov, $indiceVentas, $signoPorTipo, $perfil, $forzarAplicaciones);
            if ($preparado['estado'] === 'sin_venta') {
                $stats['omitidas_sin_venta_erp']++;

                continue;
            }
            if ($preparado['estado'] === 'ok_al_dia') {
                $stats['omitidas_al_dia']++;

                continue;
            }
            if ($preparado['estado'] !== 'ok') {
                $stats['errores'][] = $preparado['error'] ?? 'Error desconocido';

                continue;
            }

            $plan[] = $preparado;
            if ($limite !== null && $limite > 0 && count($plan) >= $limite) {
                break;
            }
        }

        $stats['a_procesar'] = count($plan);
        $stats['a_crear_cc'] = count(array_filter($plan, static fn (array $p) => $p['accion_cc'] === 'crear'));
        $stats['a_colapsar_saldo'] = count(array_filter(
            $plan,
            static fn (array $p) => ($p['accion_saldo'] ?? '') === 'colapsar'
        ));
        $stats['a_actualizar_aplicaciones'] = 0;
        $stats['muestra'] = array_map(static fn (array $p) => $p['resumen'], array_slice($plan, 0, 25));
        $stats['anita_aplmov'] = 0;
        $stats['aplicaciones_anita'] = 0;

        $extras = [];
        if ($cerrarSinDeudaAnita) {
            $cierre = $this->planearCierreSinDeudaAnita(
                $clienteCodigo,
                $perfil,
                $desdeYmd ?: null,
                $hastaYmd ?: null,
                $empresaCodigo
            );
            $extras = $cierre['items'];
            $stats['anita_climov_abiertos'] = $cierre['anita_abiertos'];
            $stats['extras_clientes'] = (int) ($cierre['clientes'] ?? 0);
            $stats['extras_a_cerrar'] = count($extras);
            $stats['extras_importe'] = round(array_sum(array_column($extras, 'faltante')), 4);
            $stats['muestra_extras'] = array_map(
                static fn (array $e) => [
                    'etiqueta' => $e['etiqueta'],
                    'cc_id' => $e['cc_id'],
                    'fecha' => $e['fecha'],
                    'total' => $e['total'],
                    'faltante' => $e['faltante'],
                    'clave' => $e['clave'],
                ],
                array_slice($extras, 0, 25)
            );
        }

        if ($dryRun) {
            $stats['modo'] = 'dry-run';

            return $stats;
        }

        return DB::transaction(function () use ($plan, $stats, $perfil, $extras) {
            foreach ($plan as $item) {
                $resultado = $this->persistirItem($item, [], $perfil, false);
                if ($resultado['cc_creada']) {
                    $stats['cc_creadas']++;
                }
                $stats['saldos_colapsados'] += $resultado['saldo_colapsado'] ? 1 : 0;
                $stats['aplicaciones_creadas'] += $resultado['aplicaciones_creadas'];
                $stats['aplicaciones_omitidas'] += $resultado['aplicaciones_omitidas'];
                $stats['cc_ya_existentes'] += $resultado['cc_existente'] ? 1 : 0;
                foreach ($resultado['errores'] as $err) {
                    $stats['errores'][] = $err;
                }
            }
            foreach ($extras as $extra) {
                $cierre = $this->persistirCierreExtra($extra, $perfil);
                $stats['aplicaciones_creadas'] += $cierre['aplicaciones'];
                $stats['extras_cerrados'] += $cierre['aplicaciones'] > 0 ? 1 : 0;
                foreach ($cierre['errores'] as $err) {
                    $stats['errores'][] = $err;
                }
            }
            $stats['modo'] = 'ejecutar';

            return $stats;
        });
    }

    /**
     * @param  array<string, list<object>>  $indiceVentas
     * @param  array<string, int>  $signoPorTipo
     * @param  array<string, mixed>  $perfil
     * @return array<string, mixed>
     */
    private function prepararClimov(
        array $climov,
        array $indiceVentas,
        array $signoPorTipo,
        array $perfil,
        bool $forzarAplicaciones,
    ): array {
        $venta = ClienteCuentacorrienteAnitaImportVentaMatchSupport::resolver($climov, $indiceVentas);
        if ($venta === null) {
            return ['estado' => 'sin_venta'];
        }

        $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($climov['cliv_tipo'] ?? ''));
        $letra = ClienteCuentacorrienteAnitaImportClaveSupport::letra((string) ($climov['cliv_letra'] ?? ''));
        $suc = (int) ($climov['cliv_sucursal'] ?? 0);
        $nro = (int) ($climov['cliv_nro'] ?? 0);
        $cuota = max(1, (int) ($climov['cliv_nro_cuota'] ?? 1));
        $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento($tipo, $letra, $suc, $nro);
        $monto = round(abs((float) ($climov['cliv_monto'] ?? 0)), 4);
        $cobrado = round(abs((float) ($climov['cliv_t_cobrado'] ?? 0)), 4);
        $pendienteAbs = round(max(0, $monto - $cobrado), 4);
        $signo = $signoPorTipo[$tipo] ?? ClienteCuentacorrienteAnitaImportClaveSupport::signoEntero($venta->signo);
        // Deuda limpia: CC = saldo Anita (no total factura + aplicaciones de cobros a cuenta).
        $totalFirmado = round($pendienteAbs * $signo, 4);

        $fecha = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita($climov['cliv_fecha'] ?? '')
            ?: (string) $venta->fecha;
        $fechaVto = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita($climov['cliv_fecha_vto'] ?? '')
            ?: $fecha;
        $monedaId = RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($climov['cliv_cod_mon'] ?? $venta->moneda_id ?? 1);
        $cotizacion = (float) ($climov['cliv_cotizacion'] ?? $venta->cotizacion ?? 1) ?: 1.0;

        $ccs = Cliente_Cuentacorriente::query()
            ->where('venta_id', (int) $venta->id)
            ->orderBy('id')
            ->get();

        $cc = $this->elegirCuotaCc($ccs, $cuota, $pendienteAbs, $monto, $totalFirmado);
        $aplicadoErpFirmado = $cc
            ? round((float) Cliente_Cuentacorriente_Aplicacion::query()
                ->where('cliente_cuentacorriente_id', $cc->id)
                ->sum('total'), 4)
            : 0.0;
        $tolerancia = (float) $perfil['tolerancia_aplicado'];

        $accionCc = $cc ? 'existente' : 'crear';
        $accionSaldo = 'omitir';
        if ($cc) {
            $saldoErp = round((float) $cc->total + $aplicadoErpFirmado, 4);
            $necesitaColapsarApps = abs($aplicadoErpFirmado) > $tolerancia;
            $necesitaAjustarTotal = abs((float) $cc->total - $totalFirmado) > $tolerancia
                || abs($saldoErp - $totalFirmado) > $tolerancia;
            if ($necesitaColapsarApps || $necesitaAjustarTotal) {
                $accionSaldo = 'colapsar';
            }
        }

        if ($accionCc === 'existente' && $accionSaldo === 'omitir') {
            return ['estado' => 'ok_al_dia'];
        }

        $etiqueta = ClienteCuentacorrienteAnitaImportClaveSupport::etiquetaErp($tipo, $letra, $suc, $nro);

        return [
            'estado' => 'ok',
            'climov' => $climov,
            'clave' => $clave,
            'clave_cuota' => ClienteCuentacorrienteAnitaImportClaveSupport::claveCuota($tipo, $letra, $suc, $nro, $cuota),
            'nro_cuota' => $cuota,
            'venta_id' => (int) $venta->id,
            'cliente_id' => (int) $venta->cliente_id,
            'empresa_id' => $this->empresaIdDesdeVenta($venta),
            'cc_id' => $cc?->id,
            'accion_cc' => $accionCc,
            'accion_aplicaciones' => 'omitir',
            'accion_saldo' => $accionSaldo,
            'fecha' => $fecha,
            'fechavencimiento' => $fechaVto,
            'total' => $totalFirmado,
            'aplicado_objetivo' => 0.0,
            'aplicado_erp' => abs($aplicadoErpFirmado),
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'signo' => $signo,
            'resumen' => [
                'etiqueta' => $etiqueta,
                'venta_id' => (int) $venta->id,
                'fecha' => $fecha,
                'total' => $totalFirmado,
                'aplicado_anita' => $cobrado,
                'aplicado_erp' => abs($aplicadoErpFirmado),
                'accion_cc' => $accionCc,
                'accion_apl' => $accionSaldo === 'colapsar' ? 'colapsar_saldo' : 'omitir',
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Cliente_Cuentacorriente>  $ccs
     */
    private function elegirCuotaCc($ccs, int $nroCuota, float $pendienteAbs, float $montoAbs, float $totalFirmado): ?Cliente_Cuentacorriente
    {
        if ($ccs->isEmpty()) {
            return null;
        }
        if ($ccs->count() === 1) {
            return $ccs->first();
        }

        $porMonto = $ccs->first(
            static function (Cliente_Cuentacorriente $cc) use ($pendienteAbs, $montoAbs, $totalFirmado) {
                $abs = abs((float) $cc->total);

                return abs($abs - $pendienteAbs) < 0.02
                    || abs($abs - $montoAbs) < 0.02
                    || abs((float) $cc->total - $totalFirmado) < 0.02;
            }
        );
        if ($porMonto) {
            return $porMonto;
        }

        $idx = max(0, $nroCuota - 1);

        return $ccs->values()->get($idx) ?? $ccs->first();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $pares
     * @param  array<string, mixed>  $perfil
     * @return array{cc_creada:bool,cc_existente:bool,saldo_colapsado:bool,aplicaciones_creadas:int,aplicaciones_omitidas:int,errores:list<string>}
     */
    private function persistirItem(array $item, array $pares, array $perfil, bool $forzarAplicaciones): array
    {
        $out = [
            'cc_creada' => false,
            'cc_existente' => false,
            'saldo_colapsado' => false,
            'aplicaciones_creadas' => 0,
            'aplicaciones_omitidas' => 0,
            'errores' => [],
        ];

        $ccId = $item['cc_id'] ? (int) $item['cc_id'] : null;
        if ($item['accion_cc'] === 'crear') {
            $cc = Cliente_Cuentacorriente::query()->create([
                'fecha' => $item['fecha'],
                'fechavencimiento' => $item['fechavencimiento'],
                'cliente_id' => $item['cliente_id'],
                'total' => $item['total'],
                'moneda_id' => $item['moneda_id'],
                'cotizacion' => $item['cotizacion'],
                'venta_id' => $item['venta_id'],
                'cobranza_id' => null,
                'empresa_id' => $item['empresa_id'],
            ]);
            $ccId = (int) $cc->id;
            $out['cc_creada'] = true;
        } else {
            $out['cc_existente'] = true;
            // Imports viejos dejaron empresa_id NULL; venta no tiene empresa (está en puntoventa).
            if ($ccId && ! empty($item['empresa_id'])) {
                $ccActual = Cliente_Cuentacorriente::query()->find($ccId);
                if ($ccActual && (int) ($ccActual->empresa_id ?? 0) <= 0) {
                    $ccActual->empresa_id = (int) $item['empresa_id'];
                    $ccActual->save();
                }
            }
            if ($ccId && ($item['accion_saldo'] ?? '') === 'colapsar') {
                $ccActual = Cliente_Cuentacorriente::query()->find($ccId);
                if ($ccActual) {
                    $ccActual->total = $item['total'];
                    $ccActual->save();
                    EloquentAuditDeleteSupport::each(
                        Cliente_Cuentacorriente_Aplicacion::query()
                            ->where('cliente_cuentacorriente_id', $ccId)
                    );
                    $out['saldo_colapsado'] = true;
                }
            }
        }

        return $out;
    }

    /**
     * ERP pendiente cuya clave no está en climov abierto de Anita → se salda.
     * Sin --cliente: todos los clientes con CC pendiente en ERP.
     *
     * @param  array<string, mixed>  $perfil
     * @return array{anita_abiertos:int, items: list<array<string, mixed>>, clientes:int}
     */
    private function planearCierreSinDeudaAnita(
        ?string $clienteCodigo,
        array $perfil,
        ?int $desdeYmd,
        ?int $hastaYmd,
        ?int $empresaCodigo,
    ): array {
        $codigoFiltro = trim((string) $clienteCodigo);
        $clienteIds = null;
        if ($codigoFiltro !== '') {
            $codigoErp = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp($codigoFiltro);
            $cliente = Cliente::query()
                ->where(function ($q) use ($codigoErp, $codigoFiltro) {
                    $q->where('codigo', $codigoErp)->orWhere('codigo', $codigoFiltro);
                })
                ->orderBy('id')
                ->first();
            if ($cliente === null) {
                throw new RuntimeException('No se encontró el cliente '.$codigoFiltro.' en el ERP.');
            }
            $clienteIds = [(int) $cliente->id];
        }

        $climovsAbiertos = $this->reader->listarClimovPendiente(
            $codigoFiltro !== '' ? $codigoFiltro : null,
            $desdeYmd,
            $hastaYmd,
            $empresaCodigo,
            true,
        );
        // clienteAnita|tipo|letra|suc|nro → evita colisiones entre clientes.
        // Incluye COB/REC/etc.: si Anita aún los tiene abiertos, no son “extras”.
        $clavesAbiertas = [];
        foreach ($climovsAbiertos as $climov) {
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($climov['cliv_tipo'] ?? ''));
            if ($tipo === '') {
                continue;
            }
            $cliAnita = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
                (string) ($climov['cliv_cliente'] ?? '')
            );
            $doc = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeClimov($climov);
            $clavesAbiertas[$cliAnita.'|'.$doc] = true;
        }

        $query = Cliente_Cuentacorriente::query()
            ->with(['ventas', 'clientes:id,codigo'])
            ->select('cliente_cuentacorriente.*')
            ->addSelect([
                'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id'),
            ])
            ->whereNotNull('venta_id')
            ->whereRaw(SqlDialectSupport::sqlSinCobranzaClienteCc())
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteClienteCc())
            ->orderBy('cliente_id')
            ->orderBy('id');
        // Solo deuda comercial (FAC/NC/ND/COA…): no cerrar COB/REC de la ficha.
        ClienteCuentacorrienteDeudaAlcanceSupport::aplicar($query);
        if ($clienteIds !== null) {
            $query->whereIn('cliente_id', $clienteIds);
        }

        $items = [];
        $clientesTocados = [];
        foreach ($query->get() as $cc) {
            $etiqueta = (string) ($cc->ventas->codigo ?? ClienteCuentacorrienteGrillaSupport::etiquetaComprobante($cc));
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta($etiqueta);
            $codigoCliente = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
                (string) ($cc->clientes->codigo ?? '')
            );
            if ($clave !== null && isset($clavesAbiertas[$codigoCliente.'|'.$clave])) {
                continue;
            }

            $total = round((float) $cc->total, 4);
            $faltante = round(ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                $total,
                (float) ($cc->aplicado ?? 0)
            ), 4);
            if ($faltante <= (float) $perfil['tolerancia_aplicado']) {
                continue;
            }

            $fechaCc = $cc->fecha;
            if ($fechaCc instanceof \DateTimeInterface) {
                $fechaIso = $fechaCc->format('Y-m-d');
            } else {
                $fechaIso = substr(trim((string) $fechaCc), 0, 10);
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaIso)) {
                $fechaIso = date('Y-m-d');
            }

            $clientesTocados[(int) $cc->cliente_id] = true;
            $items[] = [
                'cc_id' => (int) $cc->id,
                'venta_id' => (int) $cc->venta_id,
                'cliente_id' => (int) $cc->cliente_id,
                'etiqueta' => $etiqueta !== '' ? $etiqueta : ('CC #'.$cc->id),
                'clave' => $clave ?? '',
                'fecha' => $fechaIso,
                'total' => $total,
                'faltante' => $faltante,
                'moneda_id' => (int) ($cc->moneda_id ?? 1),
                'cotizacion' => (float) ($cc->cotizacion ?? 1) ?: 1.0,
            ];
        }

        return [
            'anita_abiertos' => count($clavesAbiertas),
            'clientes' => count($clientesTocados),
            'items' => $items,
        ];
    }

    /**
     * CC del ERP con saldo cuya clave ya no está en climov abierto de Anita.
     * Aplica el COA, el COB o la nota de crédito de aplmov. Si no alcanza,
     * el saldo queda abierto y se informa: no se inventa un cierre.
     *
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $perfil
     * @return array{aplicaciones:int, errores:list<string>}
     */
    private function persistirCierreExtra(array $extra, array $perfil): array
    {
        $cc = Cliente_Cuentacorriente::query()->with(['ventas', 'clientes'])->find((int) $extra['cc_id']);
        if ($cc === null) {
            return ['aplicaciones' => 0, 'errores' => ['CC #'.$extra['cc_id'].' no existe.']];
        }

        $tolerancia = (float) $perfil['tolerancia_aplicado'];
        $errores = [];
        $aplicaciones = 0;

        $mat = $this->aplicarAplmovFaltanteSobreSaldo($cc, $perfil, 1);
        $aplicaciones += $mat['aplicaciones'];
        foreach ($mat['errores'] as $err) {
            $errores[] = $err;
        }

        $cc->refresh();
        $aplicado = round((float) Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $cc->id)
            ->sum('total'), 4);
        $saldo = ClienteCuentacorrienteGrillaSupport::saldoPendiente((float) $cc->total, $aplicado);
        if (abs($saldo) <= $tolerancia) {
            return ['aplicaciones' => $aplicaciones, 'errores' => $errores];
        }

        $etiqueta = (string) ($extra['etiqueta'] ?? $cc->ventas->codigo ?? ('CC #'.$cc->id));
        $errores[] = $etiqueta.': Anita ya no la tiene abierta y quedan '
            .number_format(abs($saldo), 2, ',', '.')
            .' sin COA, COB ni nota de crédito en aplmov. No se cierra sin comprobante.';

        return ['aplicaciones' => $aplicaciones, 'errores' => $errores];
    }

    /**
     * Aplica pares aplmov (NCP/COA/…) que faltan sobre el saldo pendiente del CC.
     *
     * @param  array<string, mixed>  $perfil
     * @return array{aplicaciones:int, errores:list<string>}
     */
    private function aplicarAplmovFaltanteSobreSaldo(
        Cliente_Cuentacorriente $cc,
        array $perfil,
        int $usuarioId,
    ): array {
        $etiquetaDeuda = (string) ($cc->ventas->codigo ?? '');
        $claveDeuda = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta($etiquetaDeuda);
        $out = ['aplicaciones' => 0, 'errores' => []];
        if ($claveDeuda === null) {
            return $out;
        }

        $tolerancia = (float) $perfil['tolerancia_aplicado'];
        $aplicado = round((float) Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $cc->id)
            ->sum('total'), 4);
        $saldo = ClienteCuentacorrienteGrillaSupport::saldoPendiente((float) $cc->total, $aplicado);
        $aCubrir = round(abs($saldo), 4);
        if ($aCubrir <= $tolerancia) {
            return $out;
        }

        $signoPorTipo = $this->mapaSignoTipos();
        $lineas = $this->lineasContrapartidaCoa(
            $this->reader->listarAplmovPorDeudas([$claveDeuda]),
            $claveDeuda,
            $signoPorTipo,
            (bool) $perfil['aplmov_fallback_ref_como_cob']
        );
        if ($lineas === []) {
            return $out;
        }

        $codigoCliente = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
            (string) ($cc->clientes->codigo ?? '')
        );
        $climovDeuda = $this->elegirClimov(
            $this->reader->listarClimovPorDocumentos([$claveDeuda], $codigoCliente),
            $codigoCliente
        );
        if ($climovDeuda !== null && strtoupper(trim((string) ($climovDeuda['cliv_estado'] ?? ''))) !== 'C') {
            $montoDoc = round(abs((float) ($climovDeuda['cliv_monto'] ?? 0)), 4);
            $cobradoDoc = round(abs((float) ($climovDeuda['cliv_t_cobrado'] ?? 0)), 4);
            $pendienteAnita = round(max(0, $montoDoc - min($montoDoc, $cobradoDoc)), 4);
            // El total del CC ya puede ser el saldo (el cobrado está descontado).
            // Aplicar de nuevo ese aplmov deja la factura por debajo de Anita.
            $aCubrir = round(max(0, $aCubrir - $pendienteAnita), 4);
            if ($aCubrir <= $tolerancia) {
                return $out;
            }
        }
        $climovPorClave = $this->indexarClimovPorClave(array_column($lineas, 'clave'));
        $restante = $aCubrir;

        foreach ($lineas as $linea) {
            if ($restante <= $tolerancia) {
                break;
            }
            $etiquetaCredito = (string) $linea['etiqueta'];
            // Ya está en el ERP (cualquier importe): no reaplicar ni fraccionar.
            if (Cliente_Cuentacorriente_Aplicacion::query()
                ->where('cliente_cuentacorriente_id', $cc->id)
                ->where('comprobanteaplicado', $etiquetaCredito)
                ->exists()) {
                continue;
            }

            $monto = round(min((float) $linea['monto'], $restante), 4);
            if ($monto <= $tolerancia) {
                continue;
            }

            $climov = $this->elegirClimov($climovPorClave[$linea['clave']] ?? [], $codigoCliente);
            if ($climov === null) {
                $out['errores'][] = $etiquetaDeuda.': no está el climov de '.$etiquetaCredito.'.';

                continue;
            }

            try {
                $cabecera = $this->ventaImport->asegurarCabeceraDesdeClimov(
                    $climov,
                    (int) $cc->cliente_id,
                    false,
                    $usuarioId
                );
                if ($cabecera['error'] !== null || (int) ($cabecera['venta_id'] ?? 0) <= 0) {
                    $out['errores'][] = $etiquetaDeuda.': '.($cabecera['error'] ?? 'no se creó '.$etiquetaCredito);

                    continue;
                }
                $ccCredito = $this->asegurarCcContrapartida(
                    $cc,
                    $cabecera,
                    $climov,
                    $linea,
                    $this->montoDocumentoClimov($climovPorClave[$linea['clave']] ?? [$climov], $codigoCliente)
                );
                if ($saldo >= 0) {
                    $this->grabarParAplicacion(
                        $cc,
                        $ccCredito,
                        $monto,
                        (string) $linea['fecha'],
                        (string) $cabecera['etiqueta'],
                        $etiquetaDeuda
                    );
                } else {
                    // Crédito ERP (NCD) con saldo a favor: la contrapartida es la deuda aplicada.
                    $this->grabarParAplicacion(
                        $ccCredito,
                        $cc,
                        $monto,
                        (string) $linea['fecha'],
                        $etiquetaDeuda,
                        (string) $cabecera['etiqueta']
                    );
                }
                $out['aplicaciones']++;
                $restante = round($restante - $monto, 4);
            } catch (\Throwable $e) {
                $out['errores'][] = $etiquetaDeuda.': '.$e->getMessage();
            }
        }

        return $out;
    }

    /**
     * Reemplaza aplicaciones sin movimiento (cierre fantasma o cobro/NC etiquetado)
     * por el comprobante de Anita que aplica la factura. APA y NCI no se importan.
     *
     * @return array<string, mixed>
     */
    public function repararContrapartidasSinMovimiento(
        bool $dryRun = true,
        ?string $clienteCodigo = null,
        int $usuarioId = 1,
        ?int $limite = null,
        ?callable $progreso = null,
    ): array {
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $codigo = trim((string) $clienteCodigo);
        $query = Cliente_Cuentacorriente_Aplicacion::query()
            ->with(['cliente_cuentacorrientes.ventas', 'cliente_cuentacorrientes.clientes'])
            ->where(function ($q) {
                $q->where('comprobanteaplicado', self::ETIQUETA_CIERRE_SIN_CONTRAPARTIDA)
                    ->orWhere(function ($sinPar) {
                        $sinPar->where('total', '<', -0.009)
                            ->where(function ($par) {
                                $par->whereNull('cliente_cuentacorriente_aplicado_id')
                                    ->orWhere('cliente_cuentacorriente_aplicado_id', 0);
                            });
                    });
            })
            ->orderBy('id');
        // El remito interno no tiene fila en aplmov: no hay contrapartida para grabar.
        $query->whereHas('cliente_cuentacorrientes.ventas', function ($q) {
            $q->where('codigo', 'not like', 'RIN %');
        });
        if ($codigo !== '') {
            $codigoErp = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp($codigo);
            $ids = Cliente::query()
                ->where(function ($q) use ($codigoErp, $codigo) {
                    $q->where('codigo', $codigoErp)->orWhere('codigo', $codigo);
                })
                ->pluck('id');
            $query->whereHas('cliente_cuentacorrientes', function ($q) use ($ids) {
                $q->whereIn('cliente_id', $ids);
            });
        }

        $porCc = [];
        foreach ($query->get() as $apl) {
            $cc = $apl->cliente_cuentacorrientes;
            if ($cc === null) {
                continue;
            }
            $porCc[(int) $cc->id]['cc'] = $cc;
            $porCc[(int) $cc->id]['importe'] = round(
                ($porCc[(int) $cc->id]['importe'] ?? 0) + abs((float) $apl->total),
                4
            );
        }
        $items = array_values($porCc);
        if ($limite !== null && $limite > 0) {
            $items = array_slice($items, 0, $limite);
        }

        $stats = [
            'modo' => $dryRun ? 'dry-run' : 'ejecutar',
            'fantasmas' => count($items),
            'reparados' => 0,
            'parciales' => 0,
            'omitidos' => 0,
            'importe' => 0.0,
            'descubierto' => 0.0,
            'muestra' => [],
            'errores' => [],
        ];
        $signoPorTipo = $this->mapaSignoTipos();
        $total = count($items);
        $hechos = 0;

        foreach (array_chunk($items, 10) as $lote) {
            $clavesDeuda = [];
            foreach ($lote as $item) {
                $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta(
                    (string) ($item['cc']->ventas->codigo ?? '')
                );
                if ($clave !== null) {
                    $clavesDeuda[] = $clave;
                }
            }
            $filasApl = $clavesDeuda === [] ? [] : $this->reader->listarAplmovPorDeudas($clavesDeuda);
            $lineasPorCc = [];
            $clavesCredito = [];
            foreach ($lote as $item) {
                /** @var Cliente_Cuentacorriente $cc */
                $cc = $item['cc'];
                $lineas = $this->lineasParaCubrir($cc, $filasApl, $perfil, $signoPorTipo);
                if ($lineas === []) {
                    $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta(
                        (string) ($cc->ventas->codigo ?? '')
                    );
                    if ($clave !== null) {
                        $lineas = $this->lineasParaCubrir(
                            $cc,
                            $this->reader->listarAplmovPorDeudas([$clave]),
                            $perfil,
                            $signoPorTipo
                        );
                    }
                }
                $lineasPorCc[(int) $cc->id] = $lineas;
                foreach ($lineas as $linea) {
                    $clavesCredito[] = $linea['clave'];
                }
            }
            $climovPorClave = $this->indexarClimovPorClave($clavesCredito);

            foreach ($lote as $item) {
                /** @var Cliente_Cuentacorriente $cc */
                $cc = $item['cc'];
                try {
                    $resultado = $this->materializarContrapartidaCoa(
                        $cc,
                        $perfil,
                        $dryRun,
                        $usuarioId,
                        $lineasPorCc[(int) $cc->id] ?? [],
                        $climovPorClave,
                    );
                } catch (\Throwable $e) {
                    $resultado = [
                        'aplicaciones' => 0,
                        'errores' => [($cc->ventas->codigo ?? ('CC #'.$cc->id)).': '.$e->getMessage()],
                        'detalle' => [],
                        'parcial' => false,
                        'descubierto' => 0.0,
                        'cubierto' => 0.0,
                    ];
                }
                if ($resultado['aplicaciones'] > 0) {
                    $stats['reparados']++;
                    $stats['importe'] = round($stats['importe'] + (float) ($resultado['cubierto'] ?? $item['importe']), 4);
                    if (! empty($resultado['parcial'])) {
                        $stats['parciales']++;
                        $stats['descubierto'] = round($stats['descubierto'] + (float) ($resultado['descubierto'] ?? 0), 4);
                    }
                } else {
                    $stats['omitidos']++;
                }
                foreach ($resultado['errores'] as $err) {
                    $stats['errores'][] = $err;
                }
                if (count($stats['muestra']) < 25 && ($resultado['detalle'] ?? []) !== []) {
                    $stats['muestra'][] = $resultado['detalle'];
                }
                $hechos++;
            }
            if ($progreso !== null) {
                $progreso($hechos, $total);
            }
        }

        return $stats;
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, list<array<string, mixed>>>
     */
    private function indexarClimovPorClave(array $claves): array
    {
        $claves = array_values(array_unique(array_filter($claves)));
        $map = [];
        if ($claves === []) {
            return $map;
        }
        foreach ($this->reader->listarClimovPorDocumentos($claves, null) as $climov) {
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeClimov($climov);
            $map[$clave][] = $climov;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return array{aplicaciones:int, errores:list<string>, detalle:array<string, mixed>}
     */
    /**
     * @param  list<array{clave:string, etiqueta:string, monto:float, fecha:string, tipo:string, letra:string, sucursal:int, numero:int}>|null  $lineasPrecargadas
     * @param  array<string, list<array<string, mixed>>>|null  $climovPorClave
     * @return array{aplicaciones:int, errores:list<string>, detalle:array<string, mixed>, parcial:bool, descubierto:float, cubierto:float}
     */
    private function materializarContrapartidaCoa(
        Cliente_Cuentacorriente $cc,
        array $perfil,
        bool $dryRun,
        int $usuarioId,
        ?array $lineasPrecargadas = null,
        ?array $climovPorClave = null,
    ): array {
        $etiquetaDeuda = (string) ($cc->ventas->codigo ?? '');
        $claveDeuda = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta($etiquetaDeuda);
        $vacio = [
            'aplicaciones' => 0,
            'errores' => [],
            'detalle' => [],
            'parcial' => false,
            'descubierto' => 0.0,
            'cubierto' => 0.0,
        ];
        if ($claveDeuda === null) {
            $vacio['errores'][] = 'Sin clave de comprobante en CC #'.$cc->id.' ('.$etiquetaDeuda.').';

            return $vacio;
        }

        $sueltas = $this->aplicacionesSinContrapartida((int) $cc->id);
        $aCubrir = round((float) $sueltas->sum(static fn ($a) => abs((float) $a->total)), 4);
        if ($aCubrir <= (float) $perfil['tolerancia_aplicado']) {
            return $vacio;
        }

        $signoPorTipo = $this->mapaSignoTipos();
        $lineas = $lineasPrecargadas ?? $this->lineasParaCubrir($cc, $this->reader->listarAplmovPorDeudas([$claveDeuda]), $perfil, $signoPorTipo);
        $cubiertoLineas = round(array_sum(array_column($lineas, 'monto')), 4);
        if ($lineas === [] || $cubiertoLineas <= (float) $perfil['tolerancia_aplicado']) {
            $vacio['errores'][] = $etiquetaDeuda.' sin comprobante en Anita que aplique '
                .number_format($aCubrir, 2, ',', '.');

            return $vacio;
        }

        $codigoCliente = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
            (string) ($cc->clientes->codigo ?? '')
        );
        if ($climovPorClave === null) {
            $climovPorClave = $this->indexarClimovPorClave(array_column($lineas, 'clave'));
        }

        $detalleLineas = [];
        $plan = [];
        $restante = $aCubrir;
        foreach ($lineas as $linea) {
            if ($restante <= (float) $perfil['tolerancia_aplicado']) {
                break;
            }
            $monto = round(min((float) $linea['monto'], $restante), 4);
            $climov = $this->elegirClimov($climovPorClave[$linea['clave']] ?? [], $codigoCliente);
            if ($climov === null) {
                $vacio['errores'][] = $etiquetaDeuda.': no está el climov de '.$linea['etiqueta'].'.';

                continue;
            }
            $cabecera = $this->ventaImport->asegurarCabeceraDesdeClimov(
                $climov,
                (int) $cc->cliente_id,
                true,
                $usuarioId
            );
            if ($cabecera['error'] !== null) {
                $vacio['errores'][] = $etiquetaDeuda.': '.$cabecera['error'];

                continue;
            }
            $plan[] = ['linea' => $linea, 'climov' => $climov, 'monto' => $monto, 'etiqueta' => $cabecera['etiqueta']];
            $detalleLineas[] = $cabecera['etiqueta'].' '.number_format($monto, 2, ',', '.');
            $restante = round($restante - $monto, 4);
        }
        if ($plan === []) {
            return $vacio;
        }

        $descubierto = $restante > (float) $perfil['tolerancia_aplicado'] ? $restante : 0.0;
        $cubierto = round($aCubrir - $descubierto, 4);
        if ($descubierto > 0) {
            $vacio['errores'][] = $etiquetaDeuda.' quedó con '.number_format($descubierto, 2, ',', '.')
                .' sin contrapartida. Pasa a deuda abierta.';
        }

        if (! $dryRun) {
            DB::transaction(function () use ($cc, $plan, $sueltas, $usuarioId, $etiquetaDeuda, $codigoCliente, $climovPorClave) {
                if ($sueltas->isNotEmpty()) {
                    EloquentAuditDeleteSupport::each(
                        Cliente_Cuentacorriente_Aplicacion::query()->whereIn('id', $sueltas->pluck('id')->all())
                    );
                }
                foreach ($plan as $item) {
                    $cabecera = $this->ventaImport->asegurarCabeceraDesdeClimov(
                        $item['climov'],
                        (int) $cc->cliente_id,
                        false,
                        $usuarioId
                    );
                    if ($cabecera['error'] !== null || (int) ($cabecera['venta_id'] ?? 0) <= 0) {
                        throw new RuntimeException($etiquetaDeuda.': '.($cabecera['error'] ?? 'no se creó la contrapartida'));
                    }
                    $ccCredito = $this->asegurarCcContrapartida(
                    $cc,
                    $cabecera,
                    $item['climov'],
                    $item['linea'],
                    $this->montoDocumentoClimov($climovPorClave[$item['linea']['clave']] ?? [$item['climov']], $codigoCliente)
                );
                    $this->grabarParAplicacion(
                        $cc,
                        $ccCredito,
                        (float) $item['monto'],
                        (string) $item['linea']['fecha'],
                        (string) $cabecera['etiqueta'],
                        $etiquetaDeuda
                    );
                }
            });
        }

        return [
            'aplicaciones' => count($plan),
            'errores' => $vacio['errores'],
            'parcial' => $descubierto > 0,
            'descubierto' => $descubierto,
            'cubierto' => $cubierto,
            'detalle' => [
                'factura' => $etiquetaDeuda,
                'cc_id' => (int) $cc->id,
                'importe' => $cubierto,
                'contrapartida' => implode(' | ', $detalleLineas),
            ],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Cliente_Cuentacorriente_Aplicacion>
     */
    private function aplicacionesSinContrapartida(int $ccId)
    {
        return Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $ccId)
            ->where('total', '<', -0.009)
            ->where(function ($q) {
                $q->where('comprobanteaplicado', self::ETIQUETA_CIERRE_SIN_CONTRAPARTIDA)
                    ->orWhereNull('cliente_cuentacorriente_aplicado_id')
                    ->orWhere('cliente_cuentacorriente_aplicado_id', 0);
            })
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $filasApl
     * @param  array<string, mixed>  $perfil
     * @param  array<string, int>  $signoPorTipo
     * @return list<array{clave:string, etiqueta:string, monto:float, fecha:string, tipo:string, letra:string, sucursal:int, numero:int}>
     */
    private function lineasParaCubrir(Cliente_Cuentacorriente $cc, array $filasApl, array $perfil, array $signoPorTipo): array
    {
        $claveDeuda = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta(
            (string) ($cc->ventas->codigo ?? '')
        );
        $lineas = $claveDeuda === null
            ? []
            : $this->lineasContrapartidaCoa($filasApl, $claveDeuda, $signoPorTipo, (bool) $perfil['aplmov_fallback_ref_como_cob']);

        $sueltas = $this->aplicacionesSinContrapartida((int) $cc->id);
        $aCubrir = round((float) $sueltas->sum(static fn ($a) => abs((float) $a->total)), 4);
        $cubierto = round(array_sum(array_column($lineas, 'monto')), 4);
        if ($cubierto + (float) $perfil['tolerancia_aplicado'] >= $aCubrir) {
            return $lineas;
        }

        $vistas = [];
        foreach ($lineas as $linea) {
            $vistas[$linea['clave'].'|'.number_format((float) $linea['monto'], 2, '.', '')] = true;
        }
        foreach ($sueltas as $apl) {
            if ((string) $apl->comprobanteaplicado === self::ETIQUETA_CIERRE_SIN_CONTRAPARTIDA) {
                continue;
            }
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta((string) $apl->comprobanteaplicado);
            if ($clave === null) {
                continue;
            }
            $monto = round(abs((float) $apl->total), 4);
            $marca = $clave.'|'.number_format($monto, 2, '.', '');
            if (isset($vistas[$marca])) {
                continue;
            }
            $partes = explode('|', $clave);
            if (count($partes) < 4) {
                continue;
            }
            $vistas[$marca] = true;
            $lineas[] = [
                'clave' => $clave,
                'etiqueta' => (string) $apl->comprobanteaplicado,
                'monto' => $monto,
                'fecha' => $apl->fecha?->format('Y-m-d') ?? ($cc->fecha?->format('Y-m-d') ?? date('Y-m-d')),
                'tipo' => $partes[0],
                'letra' => $partes[1],
                'sucursal' => (int) $partes[2],
                'numero' => (int) $partes[3],
            ];
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>|null
     */
    private function elegirClimov(array $filas, string $codigoCliente): ?array
    {
        if ($filas === []) {
            return null;
        }
        $codigo = ltrim($codigoCliente, '0');
        $coinciden = [];
        foreach ($filas as $fila) {
            $cli = ltrim(trim((string) ($fila['cliv_cliente'] ?? '')), '0');
            if ($codigo !== '' && $cli !== '' && $cli === $codigo) {
                $coinciden[] = $fila;
            }
        }
        if ($coinciden !== []) {
            return $coinciden[0];
        }
        if (count($filas) === 1 && trim((string) ($filas[0]['cliv_cliente'] ?? '')) === '') {
            return $filas[0];
        }
        if (count($filas) === 1) {
            return $filas[0];
        }

        return null;
    }

    /**
     * COA (u otro pago) que aplica la factura. APA se ignora. NCI solo si no hay COA.
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, int>  $signoPorTipo
     * @return list<array{clave:string, etiqueta:string, monto:float, fecha:string, tipo:string, letra:string, sucursal:int, numero:int}>
     */
    private function lineasContrapartidaCoa(array $filas, string $claveDeuda, array $signoPorTipo, bool $fallbackRef): array
    {
        $pagos = [];
        $notas = [];
        foreach (ClienteCuentacorrienteAnitaImportAplmovSupport::paresDesdeFilas($filas, $signoPorTipo, $fallbackRef) as $par) {
            if (($par['deuda']['clave'] ?? '') !== $claveDeuda) {
                continue;
            }
            $tipo = (string) ($par['credito']['tipo'] ?? '');
            if ($tipo === '' || $tipo === 'APA') {
                continue;
            }
            $linea = [
                'clave' => (string) $par['credito']['clave'],
                'etiqueta' => (string) $par['etiqueta_credito'],
                'monto' => (float) $par['monto'],
                'fecha' => (string) $par['fecha'],
                'tipo' => $tipo,
                'letra' => (string) $par['credito']['letra'],
                'sucursal' => (int) $par['credito']['sucursal'],
                'numero' => (int) $par['credito']['numero'],
            ];
            if (ClienteCuentacorrienteAnitaImportAplmovSupport::esTipoPago($tipo)) {
                $pagos[] = $linea;
            } elseif (str_starts_with($tipo, 'NC') && $tipo !== 'NCI') {
                $notas[] = $linea;
            }
        }

        usort($pagos, static function (array $a, array $b): int {
            $pa = $a['tipo'] === 'COA' ? 0 : 1;
            $pb = $b['tipo'] === 'COA' ? 0 : 1;

            return $pa <=> $pb;
        });

        // Primero el pago (COA/COB). La NC cubre el resto si la factura quedó aplicada en parte.
        return array_merge($pagos, $notas);
    }

    /**
     * @param  array{venta_id:?int, etiqueta:string, total:float}  $cabecera
     * @param  array<string, mixed>  $climov
     * @param  array{fecha:string, monto:float}  $linea
     */
    private function asegurarCcContrapartida(
        Cliente_Cuentacorriente $ccDeuda,
        array $cabecera,
        array $climov,
        array $linea,
        float $montoDocumento = 0,
    ): Cliente_Cuentacorriente {
        $ventaId = (int) ($cabecera['venta_id'] ?? 0);
        $tipoClimov = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($climov['cliv_tipo'] ?? ''));
        if ($ventaId > 0 && $tipoClimov !== '') {
            $codigoVenta = (string) (Venta::query()->whereKey($ventaId)->value('codigo') ?? '');
            $tipoVenta = ClienteCuentacorrienteAnitaImportClaveSupport::tipo(explode(' ', $codigoVenta)[0] ?? '');
            if ($tipoVenta !== '' && $tipoVenta !== $tipoClimov) {
                throw new RuntimeException(
                    'El comprobante '.$codigoVenta.' no es '.$tipoClimov
                    .' '.ClienteCuentacorrienteAnitaImportClaveSupport::etiquetaErp(
                        $tipoClimov,
                        (string) ($climov['cliv_letra'] ?? ''),
                        (int) ($climov['cliv_sucursal'] ?? 0),
                        (int) ($climov['cliv_nro'] ?? 0),
                    ).'.'
                );
            }
        }
        $montoDoc = round($montoDocumento > 0
            ? $montoDocumento
            : abs((float) ($climov['cliv_monto'] ?? $linea['monto'] ?? 0)), 4);
        $total = round(-1 * $montoDoc, 4);
        $existente = $ventaId > 0
            ? Cliente_Cuentacorriente::query()->where('venta_id', $ventaId)->orderBy('id')->first()
            : null;
        if ($existente) {
            if ($montoDoc > abs((float) $existente->total) + 0.02) {
                $existente->total = $total;
                $existente->save();
                $this->ampliarTotalVentaCredito($ventaId, $montoDoc, $total);
            }

            return $existente;
        }

        $fecha = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita($climov['cliv_fecha'] ?? '')
            ?: (string) ($linea['fecha'] ?? $ccDeuda->fecha?->format('Y-m-d'));
        $fechaVto = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita($climov['cliv_fecha_vto'] ?? '')
            ?: $fecha;
        $cotizacion = (float) ($climov['cliv_cotizacion'] ?? $ccDeuda->cotizacion ?? 1);
        if ($cotizacion <= 0) {
            $cotizacion = 1.0;
        }

        $creada = Cliente_Cuentacorriente::query()->create([
            'fecha' => $fecha,
            'fechavencimiento' => $fechaVto,
            'cliente_id' => $ccDeuda->cliente_id,
            'total' => $total,
            'moneda_id' => $ccDeuda->moneda_id,
            'cotizacion' => $cotizacion,
            'venta_id' => $ventaId,
            'cobranza_id' => null,
            'empresa_id' => $ccDeuda->empresa_id,
        ]);
        $this->ampliarTotalVentaCredito($ventaId, $montoDoc, $total);

        return $creada;
    }

    /**
     * Anita graba el mismo COB/NC en varias filas de climov (una por factura aplicada).
     * El importe del documento es la suma, no la primera fila.
     *
     * @param  list<array<string, mixed>>  $filas
     */
    private function montoDocumentoClimov(array $filas, string $codigoCliente): float
    {
        $codigo = ltrim($codigoCliente, '0');
        $sumar = static function (array $candidatas): float {
            $sum = 0.0;
            foreach ($candidatas as $fila) {
                $sum += abs((float) ($fila['cliv_monto'] ?? 0));
            }

            return round($sum, 4);
        };
        $delCliente = [];
        foreach ($filas as $fila) {
            $cli = ltrim(trim((string) ($fila['cliv_cliente'] ?? '')), '0');
            if ($codigo !== '' && $cli !== '' && $cli === $codigo) {
                $delCliente[] = $fila;
            }
        }

        return $sumar($delCliente !== [] ? $delCliente : $filas);
    }

    private function ampliarTotalVentaCredito(int $ventaId, float $montoDoc, float $totalFirmado): void
    {
        if ($ventaId <= 0) {
            return;
        }
        $venta = \App\Models\Ventas\Venta::query()->find($ventaId);
        if ($venta === null || abs((float) $venta->total) + 0.02 >= $montoDoc) {
            return;
        }
        $venta->total = $totalFirmado;
        $venta->save();
    }

    private function grabarParAplicacion(
        Cliente_Cuentacorriente $deuda,
        Cliente_Cuentacorriente $credito,
        float $monto,
        string $fecha,
        string $etiquetaCredito,
        string $etiquetaDeuda,
    ): void {
        $monto = round(abs($monto), 4);
        if ($monto <= 0) {
            return;
        }
        if ($this->aplicacionYaExistePorEtiqueta((int) $deuda->id, $etiquetaCredito, -$monto)) {
            return;
        }

        Cliente_Cuentacorriente_Aplicacion::query()->create([
            'fecha' => $fecha,
            'cliente_cuentacorriente_id' => $deuda->id,
            'total' => -$monto,
            'moneda_id' => $deuda->moneda_id,
            'cotizacion' => $deuda->cotizacion ?: 1,
            'ventaaplicado_id' => $credito->venta_id,
            'cobranza_id' => null,
            'comprobanteaplicado' => $etiquetaCredito,
            'empresa_id' => $deuda->empresa_id,
            'cliente_cuentacorriente_aplicado_id' => $credito->id,
        ]);
        Cliente_Cuentacorriente_Aplicacion::query()->create([
            'fecha' => $fecha,
            'cliente_cuentacorriente_id' => $credito->id,
            'total' => $monto,
            'moneda_id' => $credito->moneda_id,
            'cotizacion' => $credito->cotizacion ?: 1,
            'ventaaplicado_id' => $deuda->venta_id,
            'cobranza_id' => null,
            'comprobanteaplicado' => $etiquetaDeuda !== '' ? $etiquetaDeuda : ('CC #'.$deuda->id),
            'empresa_id' => $credito->empresa_id,
            'cliente_cuentacorriente_aplicado_id' => $deuda->id,
        ]);
    }

    /**
     * @param  array{tipo:string,letra:string,sucursal:int,numero:int,clave:string}  $lado
     * @return array{venta_id:?int,cc_id:?int}
     */
    private function resolverCcCredito(array $lado): array
    {
        $indice = ClienteCuentacorrienteAnitaImportVentaMatchSupport::indexarVentasPorClaves([[
            'tipo' => $lado['tipo'],
            'letra' => $lado['letra'],
            'sucursal' => $lado['sucursal'],
            'numero' => $lado['numero'],
        ]]);
        $venta = ($indice[$lado['clave']] ?? [])[0] ?? null;
        if ($venta === null) {
            return ['venta_id' => null, 'cc_id' => null];
        }
        $cc = Cliente_Cuentacorriente::query()->where('venta_id', (int) $venta->id)->orderBy('id')->first();

        return [
            'venta_id' => (int) $venta->id,
            'cc_id' => $cc ? (int) $cc->id : null,
        ];
    }

    private function aplicacionYaExistePorEtiqueta(int $ccId, string $etiqueta, float $total): bool
    {
        return Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $ccId)
            ->where('comprobanteaplicado', $etiqueta)
            ->whereRaw('ABS(total - ?) < 0.02', [$total])
            ->exists();
    }

    /**
     * empresa_id vive en puntoventa, no en venta.
     */
    private function empresaIdDesdeVenta(object $venta): ?int
    {
        $empresaId = (int) ($venta->empresa_id ?? 0);
        if ($empresaId <= 0) {
            $pvId = (int) ($venta->puntoventa_id ?? 0);
            if ($pvId > 0) {
                $empresaId = (int) DB::table('puntoventa')->where('id', $pvId)->value('empresa_id');
            }
        }

        return $empresaId > 0 ? $empresaId : null;
    }

    /**
     * @return array<string, int>
     */
    private function mapaSignoTipos(): array
    {
        $map = [];
        foreach (Tipotransaccion::query()->select(['abreviatura', 'signo'])->get() as $tipo) {
            $abrev = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) $tipo->abreviatura);
            if ($abrev === '') {
                continue;
            }
            $map[$abrev] = ClienteCuentacorrienteAnitaImportClaveSupport::signoEntero($tipo->getAttributes()['signo'] ?? $tipo->signo);
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return array<string, mixed>
     */
    private function statsVacios(array $perfil): array
    {
        return [
            'entorno' => $perfil['entorno'],
            'climov_tiene_empresa' => $perfil['climov_tiene_empresa'],
            'anita_climov' => 0,
            'anita_venta' => 0,
            'anita_aplmov' => 0,
            'aplicaciones_anita' => 0,
            'omitidas_tipo_no_deuda' => 0,
            'omitidas_sin_anita_venta' => 0,
            'omitidas_sin_venta_erp' => 0,
            'omitidas_al_dia' => 0,
            'credito_sin_venta_anita' => 0,
            'ventas_faltantes_erp' => 0,
            'ventas_a_crear' => 0,
            'ventas_creadas' => 0,
            'ventas_sin_cliente' => 0,
            'ventas_errores' => [],
            'muestra_ventas' => [],
            'a_procesar' => 0,
            'a_crear_cc' => 0,
            'a_colapsar_saldo' => 0,
            'a_actualizar_aplicaciones' => 0,
            'aplicaciones_planificadas' => 0,
            'aplicaciones_con_ajuste' => 0,
            'cc_creadas' => 0,
            'cc_ya_existentes' => 0,
            'saldos_colapsados' => 0,
            'aplicaciones_creadas' => 0,
            'aplicaciones_omitidas' => 0,
            'anita_climov_abiertos' => 0,
            'extras_a_cerrar' => 0,
            'extras_cerrados' => 0,
            'extras_importe' => 0.0,
            'extras_clientes' => 0,
            'muestra_extras' => [],
            'muestra' => [],
            'errores' => [],
            'modo' => '',
        ];
    }

    /**
     * Cierra en la ficha lo que Anita no tiene en aplmov: un AJU por comprobante,
     * misma fecha, sin caja ni asiento. La etiqueta dice que es aplicación Anita.
     *
     * @return array{documentos:int, importe:float, errores:list<string>}
     */
    public function cerrarFichaConAjusteAnita(bool $dryRun = true, int $usuarioId = 1): array
    {
        $tipoAjuId = (int) Tipotransaccion::query()
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['AJU'])
            ->value('id');
        if ($tipoAjuId <= 0) {
            throw new RuntimeException('No hay tipo de comprobante AJU.');
        }

        $porCc = [];
        $aplicaciones = Cliente_Cuentacorriente_Aplicacion::query()
            ->with(['cliente_cuentacorrientes.ventas'])
            ->where('comprobanteaplicado', self::ETIQUETA_CIERRE_SIN_CONTRAPARTIDA)
            ->orderBy('id')
            ->get();
        foreach ($aplicaciones as $apl) {
            $cc = $apl->cliente_cuentacorrientes;
            if ($cc === null) {
                continue;
            }
            $porCc[(int) $cc->id]['cc'] = $cc;
            $porCc[(int) $cc->id]['ids'][] = (int) $apl->id;
            $porCc[(int) $cc->id]['importe'] = round(
                ($porCc[(int) $cc->id]['importe'] ?? 0) + abs((float) $apl->total),
                4
            );
        }

        $stats = ['documentos' => 0, 'importe' => 0.0, 'errores' => []];
        foreach ($porCc as $item) {
            /** @var Cliente_Cuentacorriente $cc */
            $cc = $item['cc'];
            $monto = round((float) $item['importe'], 4);
            $origen = $cc->ventas;
            if ($origen === null || $monto <= 0.009) {
                $stats['errores'][] = 'CC #'.$cc->id.' sin comprobante de origen.';

                continue;
            }
            if (ClienteCuentacorrienteRemitoInternoSupport::es($origen->codigo, $origen->tipotransacciones->abreviatura ?? null)) {
                continue;
            }
            $codigo = mb_substr('AJU Anita '.$origen->codigo, 0, 100);
            $stats['documentos']++;
            $stats['importe'] = round($stats['importe'] + $monto, 4);
            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($cc, $origen, $codigo, $monto, $tipoAjuId, $usuarioId, $item) {
                $venta = Venta::query()->where('codigo', $codigo)->first();
                if ($venta === null) {
                    $fecha = $cc->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
                    $venta = Venta::query()->create([
                        'fecha' => $fecha,
                        'fechajornada' => $fecha,
                        'tipotransaccion_id' => $tipoAjuId,
                        'puntoventa_id' => (int) $origen->puntoventa_id,
                        'numerocomprobante' => 900000 + (int) $cc->id,
                        'codigo_afip' => $origen->codigo_afip,
                        'cliente_id' => (int) $cc->cliente_id,
                        'condicionventa_id' => $origen->condicionventa_id,
                        'vendedor_id' => $origen->vendedor_id,
                        'total' => round(-1 * $monto, 4),
                        'moneda_id' => (int) $cc->moneda_id,
                        'cotizacion' => (float) ($cc->cotizacion ?: 1),
                        'estado' => 'C',
                        'usuario_id' => $usuarioId,
                        'leyenda' => 'Aplicación Anita. No está en aplmov; cierra la ficha contra la deuda.',
                        'descuento' => 0,
                        'codigo' => $codigo,
                        'nombre' => (string) ($origen->nombre ?: 'Ajuste aplicación Anita'),
                        'domicilio' => (string) ($origen->domicilio ?: '-'),
                        'localidad_id' => $origen->localidad_id,
                        'provincia_id' => $origen->provincia_id,
                        'pais_id' => (int) ($origen->pais_id ?: 1),
                        'numeroremito' => 0,
                        'cantidadbulto' => 0,
                    ]);
                }

                $ccCredito = Cliente_Cuentacorriente::query()->where('venta_id', $venta->id)->orderBy('id')->first();
                $total = round(-1 * $monto, 4);
                if ($ccCredito === null) {
                    $fecha = $cc->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
                    $ccCredito = Cliente_Cuentacorriente::query()->create([
                        'fecha' => $fecha,
                        'fechavencimiento' => $fecha,
                        'cliente_id' => (int) $cc->cliente_id,
                        'total' => $total,
                        'moneda_id' => (int) $cc->moneda_id,
                        'cotizacion' => (float) ($cc->cotizacion ?: 1),
                        'venta_id' => (int) $venta->id,
                        'cobranza_id' => null,
                        'empresa_id' => $cc->empresa_id,
                    ]);
                } else {
                    $total = round((float) $ccCredito->total - $monto, 4);
                    $ccCredito->total = $total;
                    $ccCredito->save();
                }
                if (abs((float) $venta->total - $total) > 0.02) {
                    $venta->total = $total;
                    $venta->save();
                }

                EloquentAuditDeleteSupport::each(
                    Cliente_Cuentacorriente_Aplicacion::query()->whereIn('id', $item['ids'])
                );
                $this->grabarParAplicacion(
                    $cc,
                    $ccCredito,
                    $monto,
                    $cc->fecha?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    $codigo,
                    (string) $origen->codigo
                );
            });
        }

        return $stats;
    }

    /**
     * Patron Hansen: COB/COA en ERP con saldo abierto, pero en Anita climov
     * el mismo documento tiene varias filas (una por factura) cuya suma es mayor.
     * Amplia el credito, crea CC de deudas faltantes y graba las aplicaciones.
     *
     * @return array<string, mixed>
     */
    public function repararCobMultifila(
        bool $dryRun = true,
        ?string $clienteCodigo = null,
        int $usuarioId = 1,
        ?int $limite = null,
        ?callable $progreso = null,
    ): array {
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $codigo = trim((string) $clienteCodigo);
        $query = Cliente_Cuentacorriente::query()
            ->with(['ventas', 'clientes'])
            ->where('cliente_cuentacorriente.total', '<', -0.01)
            ->whereHas('ventas', function ($v) {
                $v->where(function ($q) {
                    $q->where('codigo', 'like', 'COB%')
                        ->orWhere('codigo', 'like', 'COA%');
                });
            })
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteClienteCc())
            ->orderBy('cliente_cuentacorriente.id');

        if ($codigo !== '') {
            $codigoErp = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp($codigo);
            $ids = Cliente::query()
                ->where(function ($q) use ($codigoErp, $codigo) {
                    $q->where('codigo', $codigoErp)->orWhere('codigo', $codigo);
                })
                ->pluck('id');
            $query->whereIn('cliente_cuentacorriente.cliente_id', $ids);
        }

        $candidatos = $query->get();
        $claves = [];
        foreach ($candidatos as $cc) {
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta(
                (string) ($cc->ventas->codigo ?? '')
            );
            if ($clave !== null) {
                $claves[$clave] = $cc;
            }
        }

        $climovPorClave = $this->indexarClimovPorClave(array_keys($claves));
        $items = [];
        foreach ($claves as $clave => $cc) {
            $filas = $climovPorClave[$clave] ?? [];
            if (count($filas) < 2) {
                continue;
            }
            $codigoCli = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
                (string) ($cc->clientes->codigo ?? '')
            );
            $montoAnita = $this->montoDocumentoClimov($filas, $codigoCli);
            $erp = round(abs((float) $cc->total), 4);
            if ($montoAnita <= $erp + 0.05) {
                continue;
            }
            $items[] = [
                'cc' => $cc,
                'clave' => $clave,
                'filas' => $filas,
                'monto_anita' => $montoAnita,
                'monto_erp' => $erp,
                'diff' => round($montoAnita - $erp, 4),
            ];
        }
        if ($limite !== null && $limite > 0) {
            $items = array_slice($items, 0, $limite);
        }

        $stats = [
            'modo' => $dryRun ? 'dry-run' : 'ejecutar',
            'candidatos' => count($items),
            'reparados' => 0,
            'cob_ampliados' => 0,
            'cc_deuda_creadas' => 0,
            'aplicaciones' => 0,
            'importe_ampliado' => 0.0,
            'muestra' => [],
            'errores' => [],
        ];
        $total = count($items);
        $hechos = 0;

        foreach ($items as $item) {
            /** @var Cliente_Cuentacorriente $ccCob */
            $ccCob = $item['cc'];
            $etiquetaCob = (string) ($ccCob->ventas->codigo ?? '');
            try {
                $resultado = $this->persistirCobMultifila($item, $perfil, $dryRun, $usuarioId);
                if ($resultado['ok']) {
                    $stats['reparados']++;
                    $stats['cob_ampliados'] += $resultado['cob_ampliado'] ? 1 : 0;
                    $stats['cc_deuda_creadas'] += $resultado['cc_creadas'];
                    $stats['aplicaciones'] += $resultado['aplicaciones'];
                    $stats['importe_ampliado'] = round(
                        $stats['importe_ampliado'] + (float) $resultado['diff'],
                        4
                    );
                }
                foreach ($resultado['errores'] as $err) {
                    $stats['errores'][] = $err;
                }
                if (count($stats['muestra']) < 30) {
                    $stats['muestra'][] = [
                        'cliente' => (string) ($ccCob->clientes->codigo ?? ''),
                        'cob' => $etiquetaCob,
                        'erp' => $item['monto_erp'],
                        'anita' => $item['monto_anita'],
                        'diff' => $item['diff'],
                        'apps' => $resultado['aplicaciones'],
                        'cc_nuevas' => $resultado['cc_creadas'],
                    ];
                }
            } catch (\Throwable $e) {
                $stats['errores'][] = $etiquetaCob.': '.$e->getMessage();
            }
            $hechos++;
            if ($progreso !== null) {
                $progreso($hechos, $total);
            }
        }

        return $stats;
    }

    /**
     * @param  array{cc:Cliente_Cuentacorriente,clave:string,filas:list<array<string,mixed>>,monto_anita:float,monto_erp:float,diff:float}  $item
     * @param  array<string, mixed>  $perfil
     * @return array{ok:bool,cob_ampliado:bool,cc_creadas:int,aplicaciones:int,diff:float,errores:list<string>}
     */
    private function persistirCobMultifila(array $item, array $perfil, bool $dryRun, int $usuarioId): array
    {
        $ccCob = $item['cc'];
        $filas = $item['filas'];
        $montoAnita = (float) $item['monto_anita'];
        $diff = (float) $item['diff'];
        $out = [
            'ok' => false,
            'cob_ampliado' => false,
            'cc_creadas' => 0,
            'aplicaciones' => 0,
            'diff' => $diff,
            'errores' => [],
        ];

        $lineas = [];
        foreach ($filas as $fila) {
            $refTipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['cliv_ref_tipo'] ?? ''));
            $refLetra = ClienteCuentacorrienteAnitaImportClaveSupport::letra((string) ($fila['cliv_ref_letra'] ?? ''));
            $refSuc = (int) ($fila['cliv_ref_sucursal'] ?? 0);
            $refNro = (int) ($fila['cliv_ref_nro'] ?? 0);
            $monto = round(abs((float) ($fila['cliv_monto'] ?? 0)), 4);
            if ($refTipo === '' || $refNro <= 0 || $monto < 0.01) {
                continue;
            }
            $claveDeuda = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                $refTipo,
                $refLetra,
                $refSuc,
                $refNro
            );
            $lineas[] = [
                'clave' => $claveDeuda,
                'tipo' => $refTipo,
                'letra' => $refLetra,
                'sucursal' => $refSuc,
                'numero' => $refNro,
                'monto' => $monto,
                'fecha' => ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita(
                    (string) ($fila['cliv_fecha'] ?? '')
                ) ?: ($ccCob->fecha?->format('Y-m-d') ?? now()->format('Y-m-d')),
            ];
        }
        if ($lineas === []) {
            $out['errores'][] = ($ccCob->ventas->codigo ?? 'COB').' sin referencias FAC en climov.';

            return $out;
        }

        $clavesDeuda = array_values(array_unique(array_column($lineas, 'clave')));
        $climovDeuda = $this->indexarClimovPorClave($clavesDeuda);

        if ($dryRun) {
            $out['ok'] = true;
            $out['cob_ampliado'] = $diff > 0.05;
            foreach ($lineas as $linea) {
                $resuelto = $this->resolverCcCredito([
                    'tipo' => $linea['tipo'],
                    'letra' => $linea['letra'],
                    'sucursal' => $linea['sucursal'],
                    'numero' => $linea['numero'],
                    'clave' => $linea['clave'],
                ]);
                if (($resuelto['cc_id'] ?? null) === null) {
                    $out['cc_creadas']++;
                }
                $out['aplicaciones']++;
            }

            return $out;
        }

        DB::transaction(function () use (
            $ccCob,
            $montoAnita,
            $diff,
            $lineas,
            $climovDeuda,
            $usuarioId,
            &$out
        ) {
            if ($diff > 0.05) {
                $totalFirmado = round(-1 * $montoAnita, 4);
                $ccCob->total = $totalFirmado;
                $ccCob->save();
                $ventaId = (int) ($ccCob->venta_id ?? 0);
                if ($ventaId > 0) {
                    $this->ampliarTotalVentaCredito($ventaId, $montoAnita, $totalFirmado);
                }
                $out['cob_ampliado'] = true;
            }

            $etiquetaCob = (string) ($ccCob->ventas->codigo ?? '');
            $ccCob->refresh();
            $ccCob->loadMissing(['ventas', 'clientes']);

            foreach ($lineas as $linea) {
                $filasDeuda = $climovDeuda[$linea['clave']] ?? [];
                $climovFac = $filasDeuda[0] ?? null;
                if ($climovFac === null) {
                    $climovFac = [
                        'cliv_tipo' => $linea['tipo'],
                        'cliv_letra' => $linea['letra'],
                        'cliv_sucursal' => $linea['sucursal'],
                        'cliv_nro' => $linea['numero'],
                        'cliv_monto' => $linea['monto'],
                        'cliv_fecha' => str_replace('-', '', $linea['fecha']),
                        'cliv_fecha_vto' => str_replace('-', '', $linea['fecha']),
                        'cliv_cliente' => ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
                            (string) ($ccCob->clientes->codigo ?? '')
                        ),
                        'cliv_cod_mon' => '1',
                        'cliv_cotizacion' => 1,
                        'cliv_nro_cuota' => 1,
                    ];
                }

                $cabecera = $this->ventaImport->asegurarCabeceraDesdeClimov(
                    $climovFac,
                    (int) $ccCob->cliente_id,
                    false,
                    $usuarioId
                );
                if ($cabecera['error'] !== null || (int) ($cabecera['venta_id'] ?? 0) <= 0) {
                    $out['errores'][] = ($cabecera['etiqueta'] ?? $linea['clave']).': '
                        .($cabecera['error'] ?? 'sin venta');

                    continue;
                }

                $ventaDeuda = Venta::query()->find((int) $cabecera['venta_id']);
                if ($ventaDeuda === null) {
                    $out['errores'][] = $cabecera['etiqueta'].' venta no encontrada.';

                    continue;
                }

                $ccDeuda = Cliente_Cuentacorriente::query()
                    ->where('venta_id', (int) $ventaDeuda->id)
                    ->orderBy('id')
                    ->first();
                if ($ccDeuda === null) {
                    $montoDoc = round(abs((float) ($climovFac['cliv_monto'] ?? $ventaDeuda->total ?? $linea['monto'])), 4);
                    if ($montoDoc < 0.01) {
                        $montoDoc = $linea['monto'];
                    }
                    $signo = 1;
                    $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($climovFac['cliv_tipo'] ?? $linea['tipo']));
                    if (str_starts_with($tipo, 'NC') || $tipo === 'CIM') {
                        $signo = -1;
                    }
                    $fecha = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita(
                        (string) ($climovFac['cliv_fecha'] ?? '')
                    ) ?: (string) ($ventaDeuda->fecha?->format('Y-m-d') ?? $linea['fecha']);
                    $fechaVto = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita(
                        (string) ($climovFac['cliv_fecha_vto'] ?? '')
                    ) ?: $fecha;
                    $ccDeuda = Cliente_Cuentacorriente::query()->create([
                        'fecha' => $fecha,
                        'fechavencimiento' => $fechaVto,
                        'cliente_id' => (int) $ccCob->cliente_id,
                        'total' => round($montoDoc * $signo, 4),
                        'moneda_id' => (int) ($ventaDeuda->moneda_id ?: $ccCob->moneda_id),
                        'cotizacion' => (float) ($ventaDeuda->cotizacion ?: $ccCob->cotizacion ?: 1),
                        'venta_id' => (int) $ventaDeuda->id,
                        'cobranza_id' => null,
                        'empresa_id' => $this->empresaIdDesdeVenta($ventaDeuda) ?? $ccCob->empresa_id,
                    ]);
                    $out['cc_creadas']++;
                }

                $antes = Cliente_Cuentacorriente_Aplicacion::query()
                    ->where('cliente_cuentacorriente_id', (int) $ccDeuda->id)
                    ->where('comprobanteaplicado', $etiquetaCob)
                    ->count();
                $this->grabarParAplicacion(
                    $ccDeuda,
                    $ccCob,
                    (float) $linea['monto'],
                    (string) $linea['fecha'],
                    $etiquetaCob,
                    (string) ($ventaDeuda->codigo ?: $cabecera['etiqueta'])
                );
                $despues = Cliente_Cuentacorriente_Aplicacion::query()
                    ->where('cliente_cuentacorriente_id', (int) $ccDeuda->id)
                    ->where('comprobanteaplicado', $etiquetaCob)
                    ->count();
                if ($despues > $antes) {
                    $out['aplicaciones']++;
                }
            }
            $out['ok'] = true;
        });

        return $out;
    }

    /**
     * El saldo “a cuenta” de un COB importado suele ser un pago que Anita ya aplicó
     * a facturas que no entraron al ERP. Trae esas facturas con sus créditos (NC, etc.)
     * y graba las aplicaciones, para que el COB quede aplicado y la factura no abra deuda.
     * No convierte el sobrante en COA y no escribe Anita: las aplicaciones ya están en aplmov.
     * No borra aplicaciones del ERP: pueden estar en aplmov aunque el climov del COB no las liste.
     *
     * @return array<string, mixed>
     */
    public function completarAplicacionesCobConFacturasAnita(
        bool $dryRun = true,
        ?string $clienteCodigo = null,
        int $usuarioId = 1,
    ): array {
        $codigo = trim((string) $clienteCodigo);
        $query = Cliente_Cuentacorriente::query()
            ->with(['ventas', 'clientes'])
            ->where('cliente_cuentacorriente.total', '<', -0.01)
            ->whereHas('ventas', fn ($v) => $v->where('codigo', 'like', 'COB%'))
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteClienteCc())
            ->orderBy('cliente_cuentacorriente.id');

        if ($codigo !== '') {
            $codigoErp = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp($codigo);
            $ids = Cliente::query()
                ->where(function ($q) use ($codigoErp, $codigo) {
                    $q->where('codigo', $codigoErp)->orWhere('codigo', $codigo);
                })
                ->pluck('id');
            $query->whereIn('cliente_cuentacorriente.cliente_id', $ids);
        }

        $stats = [
            'modo' => $dryRun ? 'dry-run' : 'ejecutar',
            'cob' => 0,
            'facturas' => 0,
            'creditos' => 0,
            'aplicaciones' => 0,
            'desaplicadas' => 0,
            'omitidas' => [],
            'muestra' => [],
            'errores' => [],
        ];

        foreach ($query->get() as $ccCob) {
            $plan = $this->planearFacturasAplicadasDeCob($ccCob);
            if ($plan === null) {
                continue;
            }
            $stats['cob']++;
            $stats['facturas'] += count($plan['claves_deuda']);
            $stats['creditos'] += count($plan['claves_credito']);
            $stats['aplicaciones'] += count($plan['pares']);
            $stats['desaplicadas'] += count($plan['ajenas']);
            foreach ($plan['omitidas'] as $omision) {
                $stats['omitidas'][] = $omision;
            }
            if (count($stats['muestra']) < 40) {
                $stats['muestra'][] = [
                    'cliente' => (string) ($ccCob->clientes->codigo ?? ''),
                    'cob' => (string) ($ccCob->ventas->codigo ?? ''),
                    'pendiente' => $plan['pendiente'],
                    'a_aplicar' => $plan['a_aplicar'],
                    'desaplicar' => $plan['a_desaplicar'],
                    'facturas' => array_map(
                        static fn (array $p) => $p['etiqueta'],
                        $plan['facturas']
                    ),
                ];
            }
            if ($dryRun || $plan['pares'] === []) {
                continue;
            }

            try {
                $this->persistirFacturasAplicadasDeCob($plan, $usuarioId);
            } catch (\Throwable $e) {
                $stats['errores'][] = ((string) ($ccCob->ventas->codigo ?? 'COB')).': '.$e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planearFacturasAplicadasDeCob(Cliente_Cuentacorriente $ccCob): ?array
    {
        $etiquetaCob = (string) ($ccCob->ventas->codigo ?? '');
        $claveCob = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta($etiquetaCob);
        if ($claveCob === null) {
            return null;
        }

        $codigoCli = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
            (string) ($ccCob->clientes->codigo ?? '')
        );
        $filas = $this->reader->listarClimovPorDocumentos([$claveCob], $codigoCli);
        $lineas = [];
        foreach ($filas as $fila) {
            if (ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita((string) ($fila['cliv_cliente'] ?? '')) !== $codigoCli) {
                continue;
            }
            $refTipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['cliv_ref_tipo'] ?? ''));
            $refNro = (int) ($fila['cliv_ref_nro'] ?? 0);
            $monto = round(abs((float) ($fila['cliv_monto'] ?? 0)), 4);
            if ($refTipo === '' || $refNro <= 0 || $monto < 0.01) {
                continue;
            }
            $refLetra = ClienteCuentacorrienteAnitaImportClaveSupport::letra((string) ($fila['cliv_ref_letra'] ?? ''));
            $refSuc = (int) ($fila['cliv_ref_sucursal'] ?? 0);
            $lineas[] = [
                'clave' => ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento($refTipo, $refLetra, $refSuc, $refNro),
                'etiqueta' => ClienteCuentacorrienteAnitaImportClaveSupport::etiquetaErp($refTipo, $refLetra, $refSuc, $refNro),
                'monto' => $monto,
                'usada' => false,
            ];
        }
        if ($lineas === []) {
            return null;
        }

        $apps = Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $ccCob->id)
            ->where('total', '>', 0.01)
            ->get();
        $ajenas = [];
        foreach ($apps as $app) {
            $etiqueta = trim((string) $app->comprobanteaplicado);
            $monto = round(abs((float) $app->total), 4);
            $matched = false;
            foreach ($lineas as $i => $linea) {
                if ($linea['usada']) {
                    continue;
                }
                if ($linea['etiqueta'] === $etiqueta && abs($linea['monto'] - $monto) < 0.02) {
                    $lineas[$i]['usada'] = true;
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                $ajenas[] = $app;
            }
        }

        $faltantes = array_values(array_filter($lineas, static fn (array $l) => ! $l['usada']));
        if ($faltantes === []) {
            return null;
        }

        $aplicado = (float) Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $ccCob->id)
            ->sum('total');
        $pendiente = round(abs(min(0, (float) $ccCob->total + $aplicado)), 4);
        $aAplicar = round(array_sum(array_column($faltantes, 'monto')), 4);
        // No se desaplican filas del ERP: pueden existir en aplmov aunque el climov
        // del COB no las liste. Si no entra el tramo en el saldo libre, se omite el COB.
        $aDesaplicar = 0.0;
        $ajenasOk = [];
        $capacidad = $pendiente;
        if ($aAplicar > $capacidad + 0.05) {
            return [
                'cc' => $ccCob,
                'pendiente' => $pendiente,
                'a_aplicar' => $aAplicar,
                'a_desaplicar' => $aDesaplicar,
                'claves_deuda' => [],
                'claves_credito' => [],
                'facturas' => [],
                'pares' => [],
                'ajenas' => [],
                'omitidas' => [$etiquetaCob.' pide aplicar '.$aAplicar.' y solo hay '.$capacidad.' libre.'],
            ];
        }

        $signo = $this->mapaSignoTipos();
        $clavesDeuda = array_values(array_unique(array_column($faltantes, 'clave')));
        $climovDeuda = $this->indexarClimovPorClave($clavesDeuda);
        $aplmov = $this->reader->listarAplmovPorDeudas($clavesDeuda);
        $paresPorDeuda = [];
        foreach ($aplmov as $fila) {
            $par = ClienteCuentacorrienteAnitaImportAplmovSupport::parDesdeFila($fila, $signo, true);
            if ($par === null) {
                continue;
            }
            $paresPorDeuda[$par['deuda']['clave']][] = $par;
        }

        $clavesImportar = [];
        $pares = [];
        $facturas = [];
        $omitidas = [];
        $clavesCredito = [];
        foreach ($faltantes as $linea) {
            $claveDeuda = $linea['clave'];
            $montoDoc = 0.0;
            foreach ($climovDeuda[$claveDeuda] ?? [] as $filaDeuda) {
                if (ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita((string) ($filaDeuda['cliv_cliente'] ?? '')) !== $codigoCli) {
                    continue;
                }
                $montoDoc = round($montoDoc + abs((float) ($filaDeuda['cliv_monto'] ?? 0)), 4);
            }
            if ($montoDoc < 0.01) {
                $montoDoc = $linea['monto'];
            }
            $paresDeuda = $paresPorDeuda[$claveDeuda] ?? [];
            $sumaPares = round(array_sum(array_column($paresDeuda, 'monto')), 4);
            $ventaYa = $this->resolverCcCredito([
                'tipo' => explode('|', $claveDeuda)[0],
                'letra' => explode('|', $claveDeuda)[1],
                'sucursal' => (int) explode('|', $claveDeuda)[2],
                'numero' => (int) explode('|', $claveDeuda)[3],
                'clave' => $claveDeuda,
            ]);
            $yaEnErp = ($ventaYa['cc_id'] ?? null) !== null;
            if (! $yaEnErp && abs($sumaPares - $montoDoc) > 0.05) {
                $omitidas[] = $linea['etiqueta'].' aplmov '.$sumaPares.' no cierra el comprobante '.$montoDoc.'.';

                continue;
            }

            $cubreCob = false;
            $clavesDeEsta = [$claveDeuda];
            $paresDeEsta = [];
            foreach ($paresDeuda as $par) {
                if ($par['credito']['clave'] === $claveCob && abs($par['monto'] - $linea['monto']) < 0.02) {
                    $cubreCob = true;
                }
                if ($par['credito']['clave'] === $claveCob) {
                    $paresDeEsta[] = $par;

                    continue;
                }
                $clavesDeEsta[] = $par['credito']['clave'];
                $clavesCredito[$par['credito']['clave']] = true;
                $paresDeEsta[] = $par;
            }
            if (! $cubreCob) {
                $omitidas[] = $linea['etiqueta'].' no tiene en aplmov el tramo '.$linea['monto'].' de '.$etiquetaCob.'.';

                continue;
            }
            foreach ($clavesDeEsta as $claveImp) {
                if ($claveImp === $claveDeuda && $yaEnErp) {
                    continue;
                }
                if ($claveImp !== $claveDeuda) {
                    $partesCred = explode('|', $claveImp);
                    $yaCredito = $this->resolverCcCredito([
                        'tipo' => $partesCred[0],
                        'letra' => $partesCred[1],
                        'sucursal' => (int) $partesCred[2],
                        'numero' => (int) $partesCred[3],
                        'clave' => $claveImp,
                    ]);
                    if (($yaCredito['venta_id'] ?? null) !== null && ($yaCredito['cc_id'] ?? null) !== null) {
                        continue;
                    }
                }
                $clavesImportar[$claveImp] = true;
            }
            foreach ($paresDeEsta as $par) {
                $pares[] = $par;
            }
            $facturas[] = [
                'clave' => $claveDeuda,
                'etiqueta' => $linea['etiqueta'],
                'monto' => $montoDoc,
                'ya_en_erp' => $yaEnErp,
            ];
        }

        if ($pares === []) {
            return [
                'cc' => $ccCob,
                'pendiente' => $pendiente,
                'a_aplicar' => 0.0,
                'a_desaplicar' => 0.0,
                'claves_deuda' => [],
                'claves_credito' => [],
                'facturas' => [],
                'pares' => [],
                'ajenas' => [],
                'omitidas' => $omitidas,
            ];
        }

        return [
            'cc' => $ccCob,
            'cliente_id' => (int) $ccCob->cliente_id,
            'empresa_id' => (int) ($ccCob->empresa_id ?? 0),
            'pendiente' => $pendiente,
            'a_aplicar' => round(array_sum(array_map(static fn (array $p) => $p['monto'], array_filter(
                $pares,
                static fn (array $p) => $p['credito']['clave'] === $claveCob
            ))), 4),
            'a_desaplicar' => $aDesaplicar,
            'claves_deuda' => array_values(array_unique(array_column($facturas, 'clave'))),
            'claves_credito' => array_keys($clavesCredito),
            'claves_importar' => array_keys($clavesImportar),
            'facturas' => $facturas,
            'pares' => $pares,
            'ajenas' => $ajenasOk,
            'omitidas' => $omitidas,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function persistirFacturasAplicadasDeCob(array $plan, int $usuarioId): void
    {
        /** @var Cliente_Cuentacorriente $ccCob */
        $ccCob = $plan['cc'];
        $claves = $plan['claves_importar'] ?? [];
        if ($claves !== []) {
            $import = $this->ventaImport->importarPorClaves($claves, false, $usuarioId);
            if (($import['errores'] ?? []) !== []) {
                throw new RuntimeException(implode(' ', $import['errores']));
            }
        }

        DB::transaction(function () use ($plan, $ccCob) {
            $ccCob->refresh();
            $cacheCc = [(int) $ccCob->venta_id => $ccCob];
            foreach ($plan['pares'] as $par) {
                $ccDeuda = $this->asegurarCcDeVentaPorClave($par['deuda'], (int) $plan['cliente_id'], (int) $plan['empresa_id'], $cacheCc);
                $ccCredito = $par['credito']['clave'] === ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta((string) ($ccCob->ventas->codigo ?? ''))
                    ? $ccCob
                    : $this->asegurarCcDeVentaPorClave($par['credito'], (int) $plan['cliente_id'], (int) $plan['empresa_id'], $cacheCc);
                if ($ccDeuda === null || $ccCredito === null) {
                    throw new RuntimeException(
                        'Sin cuenta corriente para '.$par['etiqueta_deuda'].' / '.$par['etiqueta_credito'].'.'
                    );
                }
                $this->grabarParAplicacion(
                    $ccDeuda,
                    $ccCredito,
                    (float) $par['monto'],
                    (string) $par['fecha'],
                    (string) $par['etiqueta_credito'],
                    (string) $par['etiqueta_deuda'],
                );
            }
        });
    }

    /**
     * @param  array{tipo:string,letra:string,sucursal:int,numero:int,clave:string}  $lado
     * @param  array<int, Cliente_Cuentacorriente>  $cacheCc
     */
    private function asegurarCcDeVentaPorClave(array $lado, int $clienteId, int $empresaFallback, array &$cacheCc): ?Cliente_Cuentacorriente
    {
        $resuelto = $this->resolverCcCredito($lado);
        $ventaId = (int) ($resuelto['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            $filas = $this->reader->listarClimovPorDocumentos([$lado['clave']]);
            if ($filas === []) {
                return null;
            }
            $monto = 0.0;
            foreach ($filas as $fila) {
                $monto += abs((float) ($fila['cliv_monto'] ?? 0));
            }
            $base = $filas[0];
            $base['cliv_monto'] = round($monto, 4);
            $cabecera = $this->ventaImport->asegurarCabeceraDesdeClimov($base, $clienteId, false, 1);
            if (($cabecera['error'] ?? null) !== null) {
                return null;
            }
            $ventaId = (int) ($cabecera['venta_id'] ?? 0);
        }
        if ($ventaId <= 0) {
            return null;
        }
        if (isset($cacheCc[$ventaId])) {
            return $cacheCc[$ventaId];
        }
        $cc = Cliente_Cuentacorriente::query()->where('venta_id', $ventaId)->orderBy('id')->first();
        if ($cc === null) {
            $venta = Venta::query()->find($ventaId);
            if ($venta === null || (int) $venta->cliente_id !== $clienteId) {
                return null;
            }
            $fecha = $venta->fecha instanceof \DateTimeInterface
                ? $venta->fecha->format('Y-m-d')
                : substr((string) $venta->fecha, 0, 10);
            $cc = Cliente_Cuentacorriente::query()->create([
                'fecha' => $fecha,
                'fechavencimiento' => $fecha,
                'cliente_id' => $clienteId,
                'total' => round((float) $venta->total, 4),
                'moneda_id' => (int) ($venta->moneda_id ?: 1),
                'cotizacion' => (float) ($venta->cotizacion ?: 1) ?: 1.0,
                'venta_id' => $ventaId,
                'cobranza_id' => null,
                'empresa_id' => $this->empresaIdDesdeVenta($venta) ?? ($empresaFallback > 0 ? $empresaFallback : null),
            ]);
        }
        $cacheCc[$ventaId] = $cc;

        return $cc;
    }

    /**
     * Un COB del ERP cuyo número en Anita es un COA (o tiene además un COA)
     * queda como COA, con las aplicaciones del aplmov. No cambia el tipo de un
     * COB cuyo importe es el del climov COB, ni inventa comprobantes que Anita no tiene.
     *
     * @return array<string, mixed>
     */
    public function alinearCobGrabadoComoCoa(bool $dryRun = true, int $usuarioId = 1, ?array $soloCodigos = null): array
    {
        $claveSupport = ClienteCuentacorrienteAnitaImportClaveSupport::class;
        $stats = [
            'modo' => $dryRun ? 'dry-run' : 'ejecutar',
            'cob' => 0,
            'retyped' => 0,
            'coa_aparte' => 0,
            'aplicaciones' => 0,
            'facturas_nuevas' => 0,
            'detalle' => [],
            'omitidas' => [],
            'errores' => [],
        ];
        $cobs = Venta::query()
            ->join('tipotransaccion as t', 't.id', '=', 'venta.tipotransaccion_id')
            ->join('cliente as c', 'c.id', '=', 'venta.cliente_id')
            ->where('t.abreviatura', 'COB')
            ->when($soloCodigos !== null, fn ($q) => $q->whereIn('venta.codigo', $soloCodigos))
            ->get(['venta.id', 'venta.codigo', 'venta.total', 'venta.cliente_id', 'c.codigo as cliente_codigo']);
        $porCoa = [];
        foreach ($cobs as $cob) {
            $clave = $claveSupport::claveDesdeCodigoVenta((string) $cob->codigo);
            if ($clave === null) {
                continue;
            }
            $p = explode('|', $clave);
            $coaClave = $claveSupport::claveDocumento('COA', $p[1], (int) $p[2], (int) $p[3]);
            $porCoa[$coaClave][] = $cob;
        }
        $climovCoa = [];
        foreach (array_chunk(array_keys($porCoa), 40) as $chunk) {
            foreach ($this->reader->listarClimovPorDocumentos($chunk) as $fila) {
                $climovCoa[$claveSupport::claveDesdeClimov($fila)][] = $fila;
            }
        }
        $signo = $this->mapaSignoTipos();
        $tipoCoaId = (int) Tipotransaccion::query()->where('abreviatura', 'COA')->value('id');
        foreach ($porCoa as $coaClave => $ventas) {
            $filasCoa = $climovCoa[$coaClave] ?? [];
            if ($filasCoa === []) {
                continue;
            }
            foreach ($ventas as $cob) {
                $codigoCli = ltrim($claveSupport::clienteCodigoAnita((string) $cob->cliente_codigo), '0');
                $filasCliente = [];
                foreach ($filasCoa as $filaCoa) {
                    $cli = ltrim(trim((string) ($filaCoa['cliv_cliente'] ?? '')), '0');
                    if ($codigoCli !== '' && $cli === $codigoCli) {
                        $filasCliente[] = $filaCoa;
                    }
                }
                if ($filasCliente === []) {
                    continue;
                }
                $montoCoa = $this->montoDocumentoClimov($filasCliente, $codigoCli);
                if ($montoCoa < 0.01) {
                    continue;
                }
                $stats['cob']++;
                $claveCob = $claveSupport::claveDesdeCodigoVenta((string) $cob->codigo);
                $filasCob = [];
                foreach ($this->reader->listarClimovPorDocumentos([$claveCob]) as $filaCob) {
                    $cli = ltrim(trim((string) ($filaCob['cliv_cliente'] ?? '')), '0');
                    if ($codigoCli !== '' && $cli === $codigoCli) {
                        $filasCob[] = $filaCob;
                    }
                }
                $montoCob = $this->montoDocumentoClimov($filasCob, $codigoCli);
                $erp = round(abs((float) $cob->total), 4);
                $mismoDocumento = abs($erp - $montoCoa) < 0.05
                    && ($montoCob + 0.05 < $erp || abs($montoCob - $montoCoa) < 0.05);
                try {
                    $pares = $this->paresCreditoAnita($coaClave, $signo);
                    $resultado = $mismoDocumento
                        ? $this->persistirCobComoCoa($cob, $pares, $filasCob, $tipoCoaId, $montoCoa, $dryRun, $usuarioId)
                        : $this->persistirCoaAparte($cob, $filasCliente, $coaClave, $pares, $montoCoa, $dryRun, $usuarioId);
                    if ($resultado['hecho']) {
                        $stats[$mismoDocumento ? 'retyped' : 'coa_aparte']++;
                        $stats['aplicaciones'] += $resultado['aplicaciones'];
                        $stats['facturas_nuevas'] += $resultado['facturas'];
                        $stats['detalle'][] = ($mismoDocumento ? 'COA ' : 'aparte ').$cob->codigo;
                    }
                    foreach ($resultado['omitidas'] as $omision) {
                        $stats['omitidas'][] = $omision;
                    }
                } catch (\Throwable $e) {
                    $stats['errores'][] = $cob->codigo.': '.$e->getMessage();
                }
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, int>  $signo
     * @return list<array<string, mixed>>
     */
    private function paresCreditoAnita(string $claveCredito, array $signo): array
    {
        $out = [];
        foreach ($this->reader->listarAplmovPorCreditos([$claveCredito]) as $fila) {
            $par = ClienteCuentacorrienteAnitaImportAplmovSupport::parDesdeFila($fila, $signo, true);
            if ($par === null || ($par['credito']['clave'] ?? '') !== $claveCredito) {
                continue;
            }
            $out[] = $par;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $pares
     * @param  list<array<string, mixed>>  $filasCob
     * @return array{hecho:bool, aplicaciones:int, facturas:int, omitidas:list<string>}
     */
    private function persistirCobComoCoa(object $cob, array $pares, array $filasCob, int $tipoCoaId, float $montoCoa, bool $dryRun, int $usuarioId): array
    {
        $codigoViejo = (string) $cob->codigo;
        $codigoNuevo = preg_replace('/^COB\b/', 'COA', $codigoViejo) ?: $codigoViejo;
        $cc = Cliente_Cuentacorriente::query()->where('venta_id', (int) $cob->id)->orderBy('id')->first();
        $clavesCob = [];
        foreach ($filasCob as $fila) {
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['cliv_ref_tipo'] ?? ''));
            $nro = (int) ($fila['cliv_ref_nro'] ?? 0);
            if ($tipo === '' || $nro <= 0) {
                continue;
            }
            $clavesCob[ClienteCuentacorrienteAnitaImportClaveSupport::etiquetaErp(
                $tipo,
                (string) ($fila['cliv_ref_letra'] ?? ''),
                (int) ($fila['cliv_ref_sucursal'] ?? 0),
                $nro
            ).'|'.number_format(abs((float) $fila['cliv_monto']), 2, '.', '')] = true;
        }
        $clavesCoa = [];
        foreach ($pares as $par) {
            $clavesCoa[$par['etiqueta_deuda'].'|'.number_format((float) $par['monto'], 2, '.', '')] = true;
        }
        $aMover = [];
        if ($cc) {
            foreach (Cliente_Cuentacorriente_Aplicacion::query()->where('cliente_cuentacorriente_id', $cc->id)->where('total', '>', 0.01)->get() as $app) {
                $marca = trim((string) $app->comprobanteaplicado).'|'.number_format(abs((float) $app->total), 2, '.', '');
                if (! isset($clavesCoa[$marca]) && isset($clavesCob[$marca])) {
                    $aMover[] = $app;
                }
            }
        }
        $out = ['hecho' => false, 'aplicaciones' => 0, 'facturas' => 0, 'omitidas' => []];
        $cobertura = $this->coberturaAplicable($pares, (int) $cob->cliente_id, $montoCoa);
        if (! $cobertura['cierra']) {
            $out['omitidas'][] = $codigoViejo.' sigue como COB: el COA de Anita no cierra con facturas ya saldadas (faltan '.number_format($cobertura['faltante'], 2, ',', '.').').';

            return $out;
        }
        $out['hecho'] = true;
        $out['facturas'] = $cobertura['facturas'];
        if ($dryRun) {
            $out['aplicaciones'] = $cobertura['aplicables'];

            return $out;
        }
        $venta = Venta::query()->find((int) $cob->id);
        if ($venta === null) {
            throw new RuntimeException($codigoViejo.' no existe.');
        }
        $venta->tipotransaccion_id = $tipoCoaId;
        $venta->codigo = $codigoNuevo;
        $venta->save();
        Cliente_Cuentacorriente_Aplicacion::query()
            ->where('comprobanteaplicado', $codigoViejo)
            ->update(['comprobanteaplicado' => $codigoNuevo]);
        DB::table('asiento')
            ->where('venta_id', $venta->id)
            ->where('anita_tipo', 'COB')
            ->update(['anita_tipo' => 'COA']);
        $cc = Cliente_Cuentacorriente::query()->where('venta_id', $venta->id)->orderBy('id')->first();
        if ($cc === null) {
            throw new RuntimeException($codigoNuevo.' sin cuenta corriente.');
        }
        if ($aMover !== []) {
            $montoCob = 0.0;
            foreach ($aMover as $app) {
                $montoCob += abs((float) $app->total);
            }
            $ccCob = $this->asegurarCobDeLineas($filasCob, (int) $cob->cliente_id, $usuarioId, round($montoCob, 4));
            if ($ccCob === null) {
                throw new RuntimeException($codigoViejo.' no pudo separarse del COA.');
            }
            foreach ($aMover as $app) {
                $this->moverAplicacionAOtroCredito($app, $ccCob, $codigoViejo);
            }
        }
        $puesto = $this->aplicarParesSobreCredito($cc, $pares, (int) $cob->cliente_id, $usuarioId);
        $out['aplicaciones'] = $puesto['aplicaciones'];
        $out['facturas'] = $puesto['facturas'];
        $out['omitidas'] = $puesto['omitidas'];

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filasCoa
     * @param  list<array<string, mixed>>  $pares
     * @return array{hecho:bool, aplicaciones:int, facturas:int, omitidas:list<string>}
     */
    private function persistirCoaAparte(object $cob, array $filasCoa, string $coaClave, array $pares, float $montoCoa, bool $dryRun, int $usuarioId): array
    {
        $out = ['hecho' => false, 'aplicaciones' => 0, 'facturas' => 0, 'omitidas' => []];
        $cobertura = $this->coberturaAplicable($pares, (int) $cob->cliente_id, $montoCoa);
        if (! $cobertura['cierra']) {
            $out['omitidas'][] = $cob->codigo.' conserva el COB: el COA '.$coaClave.' no cierra (faltan '.number_format($cobertura['faltante'], 2, ',', '.').').';

            return $out;
        }
        $out['hecho'] = true;
        $out['facturas'] = $cobertura['facturas'];
        $out['aplicaciones'] = $cobertura['aplicables'];
        if ($dryRun) {
            return $out;
        }
        $fila = null;
        $codigoCli = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita((string) ($cob->cliente_codigo ?? ''));
        foreach ($filasCoa as $candidata) {
            $cli = ltrim(trim((string) ($candidata['cliv_cliente'] ?? '')), '0');
            if ($codigoCli !== '' && ltrim($codigoCli, '0') === $cli) {
                $fila = $candidata;
                break;
            }
        }
        $fila ??= $filasCoa[0];
        $fila['cliv_monto'] = $this->montoDocumentoClimov($filasCoa, $codigoCli);
        $cabecera = $this->ventaImport->asegurarCabeceraDesdeClimov($fila, (int) $cob->cliente_id, false, $usuarioId);
        if (($cabecera['error'] ?? null) !== null || (int) ($cabecera['venta_id'] ?? 0) <= 0) {
            throw new RuntimeException((string) ($cabecera['error'] ?? 'No se creó el COA '.$coaClave));
        }
        $ccCoa = $this->ccDeVenta((int) $cabecera['venta_id'], (int) $cob->cliente_id);
        if ($ccCoa === null) {
            throw new RuntimeException('Sin cuenta corriente para '.$cabecera['etiqueta']);
        }
        if (abs((float) $ccCoa->total) + 0.02 < (float) $fila['cliv_monto']) {
            $ccCoa->total = round(-1 * (float) $fila['cliv_monto'], 4);
            $ccCoa->save();
            $this->ampliarTotalVentaCredito((int) $ccCoa->venta_id, (float) $fila['cliv_monto'], (float) $ccCoa->total);
        }
        $ccCob = Cliente_Cuentacorriente::query()->where('venta_id', (int) $cob->id)->orderBy('id')->first();
        if ($ccCob) {
            foreach ($pares as $par) {
                $app = Cliente_Cuentacorriente_Aplicacion::query()
                    ->where('cliente_cuentacorriente_id', $ccCob->id)
                    ->where('comprobanteaplicado', $par['etiqueta_deuda'])
                    ->whereRaw('ABS(total - ?) < 0.02', [(float) $par['monto']])
                    ->first();
                if ($app) {
                    $this->moverAplicacionAOtroCredito($app, $ccCoa, (string) $cabecera['etiqueta']);
                }
            }
        }
        $puesto = $this->aplicarParesSobreCredito($ccCoa, $pares, (int) $cob->cliente_id, $usuarioId);
        $out['aplicaciones'] = $puesto['aplicaciones'];
        $out['facturas'] = $puesto['facturas'];
        $out['omitidas'] = $puesto['omitidas'];
        $aplicado = (float) Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $ccCoa->id)
            ->sum('total');
        $pendiente = min(0.0, round((float) $ccCoa->total + $aplicado, 4));
        if (abs($pendiente) > 0.05 && ($cabecera['creada'] ?? false) === true && abs($aplicado) < 0.01) {
            $ventaId = (int) $ccCoa->venta_id;
            $ccCoa->delete();
            DB::table('venta_impuesto')->where('venta_id', $ventaId)->delete();
            Venta::query()->whereKey($ventaId)->first()?->delete();
            $out['hecho'] = false;
            $out['aplicaciones'] = 0;
            $out['facturas'] = 0;
            $out['omitidas'][] = $cob->codigo.' no suma el COA '.$coaClave.': Anita lo tiene aplicado a un comprobante que no se pudo traer.';

            return $out;
        }

        return $out;
    }

    /**
     * El COB que Anita tiene aparte del COA, por el importe que ya estaba aplicado en el ERP.
     *
     * @param  list<array<string, mixed>>  $filasCob
     */
    private function asegurarCobDeLineas(array $filasCob, int $clienteId, int $usuarioId, float $monto): ?Cliente_Cuentacorriente
    {
        if ($filasCob === [] || $monto < 0.01) {
            return null;
        }
        $base = $filasCob[0];
        $base['cliv_tipo'] = 'COB';
        $base['cliv_monto'] = round($monto, 4);
        $base['cliv_ref_tipo'] = '';
        $base['cliv_ref_nro'] = 0;
        $cabecera = $this->ventaImport->asegurarCabeceraDesdeClimov($base, $clienteId, false, $usuarioId);
        $ventaId = (int) ($cabecera['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            return null;
        }
        $cc = $this->ccDeVenta($ventaId, $clienteId);
        if ($cc !== null && abs((float) $cc->total) + 0.02 < $monto) {
            $cc->total = round(-1 * $monto, 4);
            $cc->save();
            $this->ampliarTotalVentaCredito($ventaId, $monto, (float) $cc->total);
        }

        return $cc;
    }

    private function ccDeVenta(int $ventaId, int $clienteId): ?Cliente_Cuentacorriente
    {
        $cc = Cliente_Cuentacorriente::query()->where('venta_id', $ventaId)->orderBy('id')->first();
        if ($cc !== null) {
            return $cc;
        }
        $venta = Venta::query()->find($ventaId);
        if ($venta === null || (int) $venta->cliente_id !== $clienteId) {
            return null;
        }
        $fecha = $venta->fecha instanceof \DateTimeInterface
            ? $venta->fecha->format('Y-m-d')
            : substr((string) $venta->fecha, 0, 10);

        return Cliente_Cuentacorriente::query()->create([
            'fecha' => $fecha,
            'fechavencimiento' => $fecha,
            'cliente_id' => $clienteId,
            'total' => round((float) $venta->total, 4),
            'moneda_id' => (int) ($venta->moneda_id ?: 1),
            'cotizacion' => (float) ($venta->cotizacion ?: 1) ?: 1.0,
            'venta_id' => $ventaId,
            'cobranza_id' => null,
            'empresa_id' => $this->empresaIdDesdeVenta($venta),
            'leyenda' => 'Importado Anita climov ('.ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) explode(' ', (string) $venta->codigo)[0]).')',
        ]);
    }

    private function moverAplicacionAOtroCredito(Cliente_Cuentacorriente_Aplicacion $app, Cliente_Cuentacorriente $destino, string $etiquetaDestino): void
    {
        $origenId = (int) $app->cliente_cuentacorriente_id;
        $deudaId = (int) $app->cliente_cuentacorriente_aplicado_id;
        $monto = round(abs((float) $app->total), 4);
        $app->cliente_cuentacorriente_id = $destino->id;
        $app->save();
        if ($deudaId <= 0) {
            return;
        }
        $ladoDeuda = Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $deudaId)
            ->where('cliente_cuentacorriente_aplicado_id', $origenId)
            ->whereRaw('ABS(total + ?) < 0.02', [$monto])
            ->first();
        if ($ladoDeuda === null) {
            return;
        }
        $ladoDeuda->comprobanteaplicado = $etiquetaDestino;
        $ladoDeuda->cliente_cuentacorriente_aplicado_id = $destino->id;
        $ladoDeuda->ventaaplicado_id = $destino->venta_id;
        $ladoDeuda->save();
    }

    /**
     * @param  list<array<string, mixed>>  $pares
     * @return array{aplicaciones:int, facturas:int, omitidas:list<string>}
     */
    private function aplicarParesSobreCredito(Cliente_Cuentacorriente $credito, array $pares, int $clienteId, int $usuarioId): array
    {
        $out = ['aplicaciones' => 0, 'facturas' => 0, 'omitidas' => []];
        $credito->loadMissing('ventas');
        $etiquetaCredito = (string) ($credito->ventas->codigo ?? '');
        foreach ($pares as $par) {
            $deuda = $par['deuda'];
            $resuelto = $this->resolverCcCredito($deuda);
            $ccDeuda = (int) ($resuelto['cc_id'] ?? 0) > 0
                ? Cliente_Cuentacorriente::query()->find((int) $resuelto['cc_id'])
                : null;
            if ($ccDeuda === null && (int) ($resuelto['venta_id'] ?? 0) > 0) {
                $ccDeuda = $this->ccDeVenta((int) $resuelto['venta_id'], $clienteId);
            }
            if ($ccDeuda === null) {
                $importada = $this->importarFacturaSaldada((string) $deuda['clave'], $clienteId, $usuarioId);
                if ($importada === null) {
                    $motivo = $this->motivoSinDeuda !== '' ? $this->motivoSinDeuda : 'Anita no la tiene saldada';
                    $out['omitidas'][] = $etiquetaCredito.' no aplica '.$par['etiqueta_deuda'].' ('.$motivo.').';

                    continue;
                }
                $out['facturas']++;
                $ccDeuda = $importada;
            }
            $antes = Cliente_Cuentacorriente_Aplicacion::query()
                ->where('cliente_cuentacorriente_id', $ccDeuda->id)
                ->where('comprobanteaplicado', $etiquetaCredito)
                ->whereRaw('ABS(total + ?) < 0.02', [(float) $par['monto']])
                ->count();
            $this->grabarParAplicacion(
                $ccDeuda,
                $credito,
                (float) $par['monto'],
                (string) $par['fecha'],
                $etiquetaCredito,
                (string) $par['etiqueta_deuda']
            );
            $despues = Cliente_Cuentacorriente_Aplicacion::query()
                ->where('cliente_cuentacorriente_id', $ccDeuda->id)
                ->where('comprobanteaplicado', $etiquetaCredito)
                ->whereRaw('ABS(total + ?) < 0.02', [(float) $par['monto']])
                ->count();
            if ($despues > $antes) {
                $out['aplicaciones']++;
            }
        }

        return $out;
    }

    private function importarFacturaSaldada(string $clave, int $clienteId, int $usuarioId): ?Cliente_Cuentacorriente
    {
        $this->motivoSinDeuda = '';
        $signo = $this->mapaSignoTipos();
        $filas = $this->reader->listarAplmovPorDeudas([$clave]);
        $pares = [];
        $suma = 0.0;
        foreach ($filas as $fila) {
            $par = ClienteCuentacorrienteAnitaImportAplmovSupport::parDesdeFila($fila, $signo, true);
            if ($par === null || ($par['deuda']['clave'] ?? '') !== $clave) {
                continue;
            }
            $pares[] = $par;
            $suma += (float) $par['monto'];
        }
        $climov = $this->reader->listarClimovPorDocumentos([$clave]);
        $monto = 0.0;
        foreach ($climov as $fila) {
            $monto += abs((float) ($fila['cliv_monto'] ?? 0));
        }
        if ($monto < 0.01 || abs($suma - $monto) > 0.05) {
            $this->motivoSinDeuda = 'Anita no la tiene saldada (documento '.number_format($monto, 2, ',', '.').', aplicado '.number_format($suma, 2, ',', '.').')';

            return null;
        }
        $claves = [$clave];
        foreach ($pares as $par) {
            $claves[] = (string) $par['credito']['clave'];
        }
        $this->ventaImport->importarPorClaves(array_values(array_unique($claves)), false, $usuarioId);
        $cache = [];
        $partes = explode('|', $clave);
        $cc = $this->asegurarCcDeVentaPorClave([
            'tipo' => $partes[0],
            'letra' => $partes[1],
            'sucursal' => (int) $partes[2],
            'numero' => (int) $partes[3],
            'clave' => $clave,
        ], $clienteId, 0, $cache);
        if ($cc === null) {
            $this->motivoSinDeuda = 'no se pudo grabar '.$clave.' en el ERP';

            return null;
        }
        foreach ($pares as $par) {
            $ccCredito = $this->asegurarCcDeVentaPorClave($par['credito'], $clienteId, (int) ($cc->empresa_id ?? 0), $cache);
            if ($ccCredito === null) {
                return $cc;
            }
            $this->grabarParAplicacion(
                $cc,
                $ccCredito,
                (float) $par['monto'],
                (string) $par['fecha'],
                (string) $par['etiqueta_credito'],
                (string) $par['etiqueta_deuda']
            );
        }

        return $cc;
    }

    /**
     * El COA solo se graba si sus aplicaciones de Anita caen en facturas que ya
     * están en el ERP o que Anita tiene saldadas por completo. Si no, el pendiente
     * del COA movería la deuda.
     *
     * @param  list<array<string, mixed>>  $pares
     * @return array{cierra:bool, faltante:float, aplicables:int, facturas:int}
     */
    private function coberturaAplicable(array $pares, int $clienteId, float $monto): array
    {
        $aplicable = 0.0;
        $aplicables = 0;
        $facturas = [];
        $saldadas = [];
        foreach ($pares as $par) {
            $deuda = $par['deuda'];
            $clave = (string) ($deuda['clave'] ?? '');
            $resuelto = $this->resolverCcCredito($deuda);
            $ventaId = (int) ($resuelto['venta_id'] ?? 0);
            $existe = $ventaId > 0
                && (int) Venta::query()->whereKey($ventaId)->value('cliente_id') === $clienteId;
            if (! $existe) {
                if ($clave === '') {
                    continue;
                }
                if (! array_key_exists($clave, $saldadas)) {
                    $saldadas[$clave] = $this->facturaEstaSaldada($clave);
                }
                if (! $saldadas[$clave]) {
                    continue;
                }
                $facturas[$clave] = true;
            }
            $aplicable += (float) $par['monto'];
            $aplicables++;
        }
        $faltante = round($monto - $aplicable, 4);

        return [
            'cierra' => abs($faltante) <= 0.05,
            'faltante' => max(0.0, $faltante),
            'aplicables' => $aplicables,
            'facturas' => count($facturas),
        ];
    }

    private function facturaEstaSaldada(string $clave): bool
    {
        $signo = $this->mapaSignoTipos();
        $suma = 0.0;
        foreach ($this->reader->listarAplmovPorDeudas([$clave]) as $fila) {
            $par = ClienteCuentacorrienteAnitaImportAplmovSupport::parDesdeFila($fila, $signo, true);
            if ($par === null || ($par['deuda']['clave'] ?? '') !== $clave) {
                continue;
            }
            $suma += (float) $par['monto'];
        }
        $monto = 0.0;
        foreach ($this->reader->listarClimovPorDocumentos([$clave]) as $fila) {
            $monto += abs((float) ($fila['cliv_monto'] ?? 0));
        }

        return $monto >= 0.01 && abs($suma - $monto) <= 0.05;
    }
}
