<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Database\SqlDialectSupport;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportAplmovSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportBridgeReader;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportFormatoSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportVentaMatchSupport;
use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa deuda de clientes desde Anita (climov + aplmov) → ERP.
 *
 * Filtro de negocio: solo comprobantes con fila en Anita `venta`
 * (no cobranzas/PRE). Si falta la cabecera en ERP, la importa primero.
 * No escribe Anita.
 */
class ClienteCuentacorrienteImportarDesdeAnitaService
{
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
        bool $cerrarSinDeudaAnita = false,
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
        foreach ($climovsDeuda as $climov) {
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeClimov($climov);
            if (! isset($anitaVentas[$clave])) {
                $stats['omitidas_sin_anita_venta']++;

                continue;
            }
            $climovsConAnitaVenta[] = $climov;
            $clavesConAnita[$clave] = $clave;
        }

        $clavesMatch = [];
        foreach ($clavesConAnita as $clave) {
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
        $stats['ventas_faltantes_erp'] = count($faltanErp);

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

        $clavesDeudaApl = [];
        $plan = [];
        foreach ($climovsConAnitaVenta as $climov) {
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
            $clavesDeudaApl[] = $preparado['clave'];
            if ($limite !== null && $limite > 0 && count($plan) >= $limite) {
                break;
            }
        }

        $stats['a_procesar'] = count($plan);
        $stats['a_crear_cc'] = count(array_filter($plan, static fn (array $p) => $p['accion_cc'] === 'crear'));
        $stats['a_actualizar_aplicaciones'] = count(array_filter(
            $plan,
            static fn (array $p) => $p['accion_aplicaciones'] !== 'omitir'
        ));
        $stats['muestra'] = array_map(static fn (array $p) => $p['resumen'], array_slice($plan, 0, 25));

        $aplmovs = $this->reader->listarAplmovPorDeudas(array_values(array_unique($clavesDeudaApl)));
        $stats['anita_aplmov'] = count($aplmovs);
        $pares = ClienteCuentacorrienteAnitaImportAplmovSupport::paresDesdeFilas(
            $aplmovs,
            $signoPorTipo,
            (bool) $perfil['aplmov_fallback_ref_como_cob']
        );
        $stats['aplicaciones_anita'] = count($pares);
        $paresPorDeuda = [];
        foreach ($pares as $par) {
            $paresPorDeuda[$par['deuda']['clave']][] = $par;
        }

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
            foreach ($plan as $item) {
                $paresItem = $paresPorDeuda[$item['clave']] ?? [];
                $montoApl = round(array_sum(array_column($paresItem, 'monto')), 4);
                $stats['aplicaciones_planificadas'] += count($paresItem);
                if ($item['aplicado_objetivo'] > 0.0001 && abs($montoApl - $item['aplicado_objetivo']) > $perfil['tolerancia_aplicado']) {
                    $stats['aplicaciones_con_ajuste']++;
                }
            }
            $stats['modo'] = 'dry-run';

            return $stats;
        }

        return DB::transaction(function () use ($plan, $paresPorDeuda, $stats, $perfil, $forzarAplicaciones, $extras) {
            foreach ($plan as $item) {
                $resultado = $this->persistirItem(
                    $item,
                    $paresPorDeuda[$item['clave']] ?? [],
                    $perfil,
                    $forzarAplicaciones
                );
                if ($resultado['cc_creada']) {
                    $stats['cc_creadas']++;
                }
                $stats['aplicaciones_creadas'] += $resultado['aplicaciones_creadas'];
                $stats['aplicaciones_omitidas'] += $resultado['aplicaciones_omitidas'];
                $stats['cc_ya_existentes'] += $resultado['cc_existente'] ? 1 : 0;
                foreach ($resultado['errores'] as $err) {
                    $stats['errores'][] = $err;
                }
            }
            foreach ($extras as $extra) {
                $stats['aplicaciones_creadas'] += $this->persistirCierreExtra($extra, $perfil);
                $stats['extras_cerrados']++;
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
        $signo = $signoPorTipo[$tipo] ?? ClienteCuentacorrienteAnitaImportClaveSupport::signoEntero($venta->signo);
        $totalFirmado = round($monto * $signo, 4);

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

        $cc = $this->elegirCuotaCc($ccs, $cuota, $monto, $totalFirmado);
        $aplicadoErp = $cc
            ? round((float) Cliente_Cuentacorriente_Aplicacion::query()
                ->where('cliente_cuentacorriente_id', $cc->id)
                ->sum('total'), 4)
            : 0.0;
        $aplicadoErpAbs = abs($aplicadoErp);
        $tolerancia = (float) $perfil['tolerancia_aplicado'];

        $accionCc = $cc ? 'existente' : 'crear';
        $accionApl = 'omitir';
        if ($cobrado > $tolerancia) {
            if (! $cc || $forzarAplicaciones || abs($aplicadoErpAbs - $cobrado) > $tolerancia) {
                $accionApl = $forzarAplicaciones || $aplicadoErpAbs < $tolerancia
                    ? 'sincronizar'
                    : 'ajustar';
            }
        }

        if ($accionCc === 'existente' && $accionApl === 'omitir') {
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
            'accion_aplicaciones' => $accionApl,
            'fecha' => $fecha,
            'fechavencimiento' => $fechaVto,
            'total' => $totalFirmado,
            'aplicado_objetivo' => $cobrado,
            'aplicado_erp' => $aplicadoErpAbs,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'signo' => $signo,
            'resumen' => [
                'etiqueta' => $etiqueta,
                'venta_id' => (int) $venta->id,
                'fecha' => $fecha,
                'total' => $totalFirmado,
                'aplicado_anita' => $cobrado,
                'aplicado_erp' => $aplicadoErpAbs,
                'accion_cc' => $accionCc,
                'accion_apl' => $accionApl,
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Cliente_Cuentacorriente>  $ccs
     */
    private function elegirCuotaCc($ccs, int $nroCuota, float $montoAbs, float $totalFirmado): ?Cliente_Cuentacorriente
    {
        if ($ccs->isEmpty()) {
            return null;
        }
        if ($ccs->count() === 1) {
            return $ccs->first();
        }

        $porMonto = $ccs->first(
            static fn (Cliente_Cuentacorriente $cc) => abs(abs((float) $cc->total) - $montoAbs) < 0.02
                || abs((float) $cc->total - $totalFirmado) < 0.02
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
     * @return array{cc_creada:bool,cc_existente:bool,aplicaciones_creadas:int,aplicaciones_omitidas:int,errores:list<string>}
     */
    private function persistirItem(array $item, array $pares, array $perfil, bool $forzarAplicaciones): array
    {
        $out = [
            'cc_creada' => false,
            'cc_existente' => false,
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
        }

        if ($ccId === null || $item['accion_aplicaciones'] === 'omitir') {
            return $out;
        }

        $aplicadoActual = round((float) Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $ccId)
            ->sum('total'), 4);

        if ($forzarAplicaciones && abs($aplicadoActual) > 0.0001) {
            Cliente_Cuentacorriente_Aplicacion::query()
                ->where('cliente_cuentacorriente_id', $ccId)
                ->whereNull('cobranza_id')
                ->whereNull('ventaaplicado_id')
                ->where('comprobanteaplicado', 'like', 'Anita sync%')
                ->get()
                ->each(static function (Cliente_Cuentacorriente_Aplicacion $apl) {
                    $apl->delete();
                });
            $aplicadoActual = round((float) Cliente_Cuentacorriente_Aplicacion::query()
                ->where('cliente_cuentacorriente_id', $ccId)
                ->sum('total'), 4);
        }

        $objetivo = (float) $item['aplicado_objetivo'];
        $faltanteAbs = max(0, $objetivo - abs($aplicadoActual));
        if ($faltanteAbs <= (float) $perfil['tolerancia_aplicado']) {
            $out['aplicaciones_omitidas']++;

            return $out;
        }

        $signoApl = $item['total'] >= 0 ? -1.0 : 1.0;
        $creadas = 0;
        $acum = 0.0;

        foreach ($pares as $par) {
            $monto = round(min((float) $par['monto'], $faltanteAbs - $acum), 4);
            if ($monto < 0.0001) {
                break;
            }
            if ($this->aplicacionYaExistePorEtiqueta($ccId, (string) $par['etiqueta_credito'], $monto * $signoApl)) {
                $out['aplicaciones_omitidas']++;

                continue;
            }

            $ventaCreditoId = null;
            $ccCreditoId = null;
            if (! $par['credito_es_pago']) {
                $vinculo = $this->resolverCcCredito($par['credito']);
                $ventaCreditoId = $vinculo['venta_id'];
                $ccCreditoId = $vinculo['cc_id'];
            }

            Cliente_Cuentacorriente_Aplicacion::query()->create([
                'fecha' => $par['fecha'],
                'cliente_cuentacorriente_id' => $ccId,
                'total' => round($monto * $signoApl, 4),
                'moneda_id' => $item['moneda_id'],
                'cotizacion' => $item['cotizacion'],
                'ventaaplicado_id' => $ventaCreditoId,
                'cobranza_id' => null,
                'comprobanteaplicado' => $par['etiqueta_credito'],
                'cliente_cuentacorriente_aplicado_id' => $ccCreditoId,
            ]);

            if ($ccCreditoId) {
                if (! $this->aplicacionYaExistePorEtiqueta(
                    $ccCreditoId,
                    (string) $par['etiqueta_deuda'],
                    round($monto * -$signoApl, 4)
                )) {
                    Cliente_Cuentacorriente_Aplicacion::query()->create([
                        'fecha' => $par['fecha'],
                        'cliente_cuentacorriente_id' => $ccCreditoId,
                        'total' => round($monto * -$signoApl, 4),
                        'moneda_id' => $item['moneda_id'],
                        'cotizacion' => $item['cotizacion'],
                        'ventaaplicado_id' => $item['venta_id'],
                        'cobranza_id' => null,
                        'comprobanteaplicado' => $par['etiqueta_deuda'],
                        'cliente_cuentacorriente_aplicado_id' => $ccId,
                    ]);
                }
            }

            $acum += $monto;
            $creadas++;
        }

        $restante = round($faltanteAbs - $acum, 4);
        if ($restante > (float) $perfil['tolerancia_aplicado']) {
            Cliente_Cuentacorriente_Aplicacion::query()->create([
                'fecha' => $item['fecha'],
                'cliente_cuentacorriente_id' => $ccId,
                'total' => round($restante * $signoApl, 4),
                'moneda_id' => $item['moneda_id'],
                'cotizacion' => $item['cotizacion'],
                'ventaaplicado_id' => null,
                'cobranza_id' => null,
                'comprobanteaplicado' => 'Anita sync aplmov (ajuste '.$restante.')',
                'cliente_cuentacorriente_aplicado_id' => null,
            ]);
            $creadas++;
        }

        $out['aplicaciones_creadas'] = $creadas;

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
        // clienteAnita|tipo|letra|suc|nro → evita colisiones entre clientes
        $clavesAbiertas = [];
        foreach ($climovsAbiertos as $climov) {
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($climov['cliv_tipo'] ?? ''));
            if ($tipo === '' || ClienteCuentacorrienteAnitaImportFormatoSupport::esTipoNoDeuda($tipo, $perfil)) {
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
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $perfil
     */
    private function persistirCierreExtra(array $extra, array $perfil): int
    {
        $ccId = (int) $extra['cc_id'];
        $aplicado = round((float) Cliente_Cuentacorriente_Aplicacion::query()
            ->where('cliente_cuentacorriente_id', $ccId)
            ->sum('total'), 4);
        $faltante = round(ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
            (float) $extra['total'],
            $aplicado
        ), 4);
        if ($faltante <= (float) $perfil['tolerancia_aplicado']) {
            return 0;
        }

        $signoApl = (float) $extra['total'] >= 0 ? -1.0 : 1.0;
        $etiqueta = 'Anita sync (sin deuda Anita)';
        if ($this->aplicacionYaExistePorEtiqueta($ccId, $etiqueta, round($faltante * $signoApl, 4))) {
            return 0;
        }

        Cliente_Cuentacorriente_Aplicacion::query()->create([
            'fecha' => $extra['fecha'],
            'cliente_cuentacorriente_id' => $ccId,
            'total' => round($faltante * $signoApl, 4),
            'moneda_id' => $extra['moneda_id'],
            'cotizacion' => $extra['cotizacion'],
            'ventaaplicado_id' => null,
            'cobranza_id' => null,
            'comprobanteaplicado' => $etiqueta,
            'cliente_cuentacorriente_aplicado_id' => null,
        ]);

        return 1;
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
            'ventas_faltantes_erp' => 0,
            'ventas_a_crear' => 0,
            'ventas_creadas' => 0,
            'ventas_sin_cliente' => 0,
            'ventas_errores' => [],
            'muestra_ventas' => [],
            'a_procesar' => 0,
            'a_crear_cc' => 0,
            'a_actualizar_aplicaciones' => 0,
            'aplicaciones_planificadas' => 0,
            'aplicaciones_con_ajuste' => 0,
            'cc_creadas' => 0,
            'cc_ya_existentes' => 0,
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
}
