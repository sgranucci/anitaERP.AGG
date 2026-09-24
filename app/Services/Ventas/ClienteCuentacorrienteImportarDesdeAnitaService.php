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
 * (canceladas / monto = cobrado): aplica NCP/COA faltantes de aplmov y, si
 * queda remanente, una aplicación sintética de cierre.
 * No escribe Anita.
 */
class ClienteCuentacorrienteImportarDesdeAnitaService
{
    /** Cierre cuando Anita ya no tiene la deuda abierta y no hay contrapartida importable. */
    public const ETIQUETA_CIERRE_SIN_CONTRAPARTIDA = 'Anita sync (sin deuda Anita)';
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
     * 1) Aplica NCP/COA/… de aplmov que aún no están en el ERP.
     * 2) Si sigue el saldo, graba aplicación sintética de cierre (Anita ya canceló).
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

        $ajuste = round(-1 * $saldo, 4);
        $fecha = (string) ($extra['fecha'] ?? '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = $cc->fecha instanceof \DateTimeInterface
                ? $cc->fecha->format('Y-m-d')
                : date('Y-m-d');
        }

        if (! $this->aplicacionYaExistePorEtiqueta((int) $cc->id, self::ETIQUETA_CIERRE_SIN_CONTRAPARTIDA, $ajuste)) {
            Cliente_Cuentacorriente_Aplicacion::query()->create([
                'fecha' => $fecha,
                'cliente_cuentacorriente_id' => $cc->id,
                'total' => $ajuste,
                'moneda_id' => $cc->moneda_id,
                'cotizacion' => ((float) ($cc->cotizacion ?? 1)) ?: 1.0,
                'ventaaplicado_id' => null,
                'cobranza_id' => null,
                'comprobanteaplicado' => self::ETIQUETA_CIERRE_SIN_CONTRAPARTIDA,
                'empresa_id' => $cc->empresa_id,
                'cliente_cuentacorriente_aplicado_id' => null,
            ]);
            $aplicaciones++;
        }

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
}
