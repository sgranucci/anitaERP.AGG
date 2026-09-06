<?php

namespace App\Services\Compras;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Comprobante_Proveedor_Estado;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Condicionpago;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Empresa;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportAplmovpSupport;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportBridgeReader;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportExistenciaSupport;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportOpaSupport;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportSinCcSupport;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa comprobantes, CC (promov) y aplicaciones (aplmovp) Anita → ERP.
 * No escribe Anita. Las facturas ya cargadas en ERP se omiten.
 *
 * Con $sinCuentaCorriente: solo documentos consultables (comprobante + conceptos +
 * cuotas; OPA como cabecera pagoproveedor). No crea proveedor_cuentacorriente ni
 * aplicaciones — la deuda se trae en una etapa posterior.
 */
class ComprobanteProveedorImportarDesdeAnitaService
{
    /** @var array<string, int|null> */
    private array $cacheEmpresa = [];

    /** @var array<string, Tipotransaccion_Compra|null> */
    private array $cacheTipo = [];

    /** @var array<int, int|null> */
    private array $cacheCondicionpago = [];

    /** @var array<int, int|null> */
    private array $cacheConcepto = [];

    /** @var array<string, int|null> */
    private array $cacheTipoCaja = [];

    public function __construct(
        private readonly ComprobanteProveedorAnitaImportBridgeReader $reader = new ComprobanteProveedorAnitaImportBridgeReader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function importar(
        string $codigoProveedor,
        bool $dryRun = true,
        ?string $desdeIso = null,
        ?string $hastaIso = null,
        ?int $empresaCodigo = null,
        int $usuarioId = 1,
        ?int $limite = null,
        bool $sinCuentaCorriente = false,
    ): array {
        $codigo = trim($codigoProveedor);
        if ($codigo === '') {
            throw new RuntimeException('Indique el código de proveedor.');
        }

        $proveedor = $this->resolverProveedor($codigo);
        if ($proveedor === null) {
            throw new RuntimeException(
                'Proveedor '.$codigo.' no está en el ERP. Corra primero proveedor:sincronizar-anita --codigo='.$codigo
            );
        }

        $desdeYmd = $desdeIso ? ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($desdeIso) : null;
        $hastaYmd = $hastaIso ? ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($hastaIso) : null;

        $compras = $this->reader->listarCompra($codigo, $desdeYmd, $hastaYmd, $empresaCodigo);
        $promovs = $this->reader->listarPromov($codigo, $desdeYmd, $hastaYmd, $empresaCodigo);
        $aplmovps = $sinCuentaCorriente
            ? []
            : $this->reader->listarAplmovp($codigo, $desdeYmd, $hastaYmd);

        $nrosInternos = [];
        foreach ($compras as $compra) {
            $nro = (int) ($compra['com_nro_interno'] ?? 0);
            if ($nro > 0) {
                $nrosInternos[] = $nro;
            }
        }
        $concmov = $this->reader->listarConcmovPorInternos($nrosInternos);

        $signoPorTipo = $this->mapaSignoTipos();
        $indice = ComprobanteProveedorAnitaImportExistenciaSupport::indexarProveedor((int) $proveedor->id);
        $cuitProveedor = ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos(
            is_string($proveedor->nroinscripcion) ? $proveedor->nroinscripcion : null
        );

        $promovPorClave = [];
        foreach ($promovs as $promov) {
            $promovPorClave[ComprobanteProveedorAnitaImportClaveSupport::claveDesdePromov($promov)][] = $promov;
        }

        $stats = $this->statsVacios((int) $proveedor->id, (string) $proveedor->nombre);
        $stats['sin_cuenta_corriente'] = $sinCuentaCorriente;
        $stats['anita_compra'] = count($compras);
        $stats['anita_promov'] = count($promovs);
        $stats['anita_aplmovp'] = count($aplmovps);
        $stats['anita_concmov'] = array_sum(array_map('count', $concmov));

        $plan = [];
        $clavesLote = [];
        foreach ($compras as $compra) {
            $preparado = $this->prepararCompra(
                $compra,
                $proveedor,
                $cuitProveedor,
                $indice,
                $promovPorClave,
                $concmov,
                $empresaCodigo
            );
            if ($preparado['estado'] === 'omitida') {
                $stats['omitidas_ya_en_erp']++;
                $stats['omitidas_detalle'][] = $preparado['omitida'];

                continue;
            }
            if ($preparado['estado'] !== 'ok') {
                $stats[$preparado['estado']]++;
                if (! empty($preparado['error'])) {
                    $stats['errores'][] = $preparado['error'];
                }

                continue;
            }
            $claveFiscal = $preparado['empresa_id'].'|'
                .(int) $proveedor->id.'|'
                .((int) $preparado['tipo']->id).'|'
                .ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($preparado['compra']['com_letra'] ?? '')).'|'
                .((int) ($preparado['compra']['com_sucursal'] ?? 0)).'|'
                .((int) ($preparado['compra']['com_nro'] ?? 0)).'|'
                .$preparado['cuit'];
            if (isset($clavesLote[$claveFiscal])) {
                $stats['duplicadas_lote']++;
                $stats['errores'][] = 'Duplicada en Anita (misma clave fiscal, otro nro interno): '
                    .$preparado['resumen']['etiqueta']
                    .' nro interno '.$preparado['nro_interno']
                    .' (ya planificada nro interno '.$clavesLote[$claveFiscal].')';

                continue;
            }
            $clavesLote[$claveFiscal] = $preparado['nro_interno'];
            $plan[] = $preparado;
            if ($limite !== null && $limite > 0 && count($plan) >= $limite) {
                break;
            }
        }

        $stats['a_crear'] = count($plan);
        foreach ($plan as $item) {
            $stats['cuotas'] += count($item['cuotas']);
            $stats['conceptos'] += count($item['conceptos']);
        }
        $stats['muestra'] = array_map(static fn (array $i) => $i['resumen'], array_slice($plan, 0, 20));

        $pares = ComprobanteProveedorAnitaImportAplmovpSupport::paresDesdeFilas($aplmovps, $signoPorTipo);
        $stats['aplicaciones_anita'] = count($pares);

        $adelantosPlan = $this->prepararAdelantos(
            $promovs,
            (int) $proveedor->id,
            (string) $proveedor->codigo,
            $empresaCodigo
        );
        $stats['adelantos_anita'] = count(array_filter(
            $promovs,
            static fn (array $p) => ComprobanteProveedorAnitaImportOpaSupport::esTipoAdelanto((string) ($p['prov_tipo'] ?? ''))
        ));
        $stats['adelantos_a_crear'] = count($adelantosPlan['a_crear']);
        $stats['adelantos_omitidos_ya_en_erp'] = $adelantosPlan['omitidos'];
        $stats['muestra_adelantos'] = array_map(
            static fn (array $i) => $i['resumen'],
            array_slice($adelantosPlan['a_crear'], 0, 20)
        );
        $stats['errores'] = array_merge($stats['errores'], $adelantosPlan['errores']);

        if ($dryRun) {
            $stats = ComprobanteProveedorAnitaImportSinCcSupport::aplicarDryRun($stats, $pares, $sinCuentaCorriente);
            $stats['modo'] = 'dry-run';

            return $stats;
        }

        return DB::transaction(function () use (
            $plan,
            $pares,
            $adelantosPlan,
            $stats,
            $proveedor,
            $usuarioId,
            $signoPorTipo,
            $sinCuentaCorriente,
        ) {
            $ccPorClave = $sinCuentaCorriente
                ? []
                : $this->indexarCcExistente(
                    (int) $proveedor->id,
                    (string) $proveedor->codigo
                );
            foreach ($plan as $item) {
                $creado = $this->persistirComprobante(
                    $item,
                    $proveedor,
                    $usuarioId,
                    $signoPorTipo,
                    $sinCuentaCorriente,
                );
                $stats['creadas']++;
                $stats['cc'] += count($creado['cc_ids']);
                foreach ($creado['cc_por_clave'] as $clave => $ccs) {
                    foreach ($ccs as $cc) {
                        $ccPorClave[$clave][] = $cc;
                    }
                }
            }

            foreach ($adelantosPlan['a_crear'] as $adelanto) {
                if ($sinCuentaCorriente) {
                    $this->persistirAdelantoDocumento($adelanto, $proveedor, $usuarioId);
                    $stats['adelantos_creados']++;

                    continue;
                }
                $creado = $this->persistirAdelanto($adelanto, $proveedor, $usuarioId);
                $stats['adelantos_creados']++;
                $stats['cc']++;
                $ccPorClave[$adelanto['clave']][] = $creado;
            }

            if ($sinCuentaCorriente) {
                $stats['aplicaciones'] = 0;
                $stats['aplicaciones_pago_sintetico'] = 0;
                $stats['aplicaciones_omitidas'] = count($pares);
            } else {
                $apl = $this->persistirAplicaciones($pares, $ccPorClave, (int) $proveedor->id);
                $stats['aplicaciones'] = $apl['creadas'];
                $stats['aplicaciones_pago_sintetico'] = $apl['pagos_sinteticos'];
                $stats['aplicaciones_omitidas'] = $apl['omitidas'];
                $stats['errores'] = array_merge($stats['errores'], $apl['errores']);
            }
            $stats['modo'] = 'ejecutar';

            return $stats;
        });
    }

    /**
     * @param  array<string, mixed>  $compra
     * @param  array{por_interno: array<int, int>, por_clave: array<string, int>, ids: list<int>}  $indice
     * @param  array<string, list<array<string, mixed>>>  $promovPorClave
     * @param  array<int, list<array{concepto: int, importe: float}>>  $concmov
     * @return array<string, mixed>
     */
    private function prepararCompra(
        array $compra,
        Proveedor $proveedor,
        string $cuitProveedor,
        array $indice,
        array $promovPorClave,
        array $concmov,
        ?int $empresaCodigoFiltro,
    ): array {
        $tipoAbrev = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) ($compra['com_tipo'] ?? ''));
        $tipo = $this->resolverTipo($tipoAbrev);
        if ($tipo === null) {
            return [
                'estado' => 'sin_tipo',
                'error' => 'Sin tipotransaccion_compra para '.$tipoAbrev.' '
                    .ComprobanteProveedorAnitaImportExistenciaSupport::etiquetaCompra($compra),
            ];
        }

        $empresaCodigo = (int) ($compra['com_empresa'] ?? 0);
        if ($empresaCodigoFiltro && $empresaCodigo > 0 && $empresaCodigo !== $empresaCodigoFiltro) {
            return ['estado' => 'sin_empresa', 'error' => null];
        }
        $empresaId = $this->resolverEmpresaId($empresaCodigo);
        if (! $empresaId) {
            return [
                'estado' => 'sin_empresa',
                'error' => 'Empresa Anita '.$empresaCodigo.' no mapeada. '
                    .ComprobanteProveedorAnitaImportExistenciaSupport::etiquetaCompra($compra),
            ];
        }

        $cuit = ComprobanteProveedorAnitaImportClaveSupport::cuitDigitos((string) ($compra['com_cuit_prov'] ?? ''))
            ?: $cuitProveedor;

        $existente = ComprobanteProveedorAnitaImportExistenciaSupport::buscarEnIndice(
            $indice,
            $compra,
            $empresaId,
            (int) $proveedor->id,
            (int) $tipo->id,
            $cuit,
        );
        if ($existente !== null) {
            return [
                'estado' => 'omitida',
                'omitida' => $existente,
            ];
        }

        $fecha = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($compra['com_fecha'] ?? '');
        $fechaIva = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($compra['com_fecha_iva'] ?? '') ?: $fecha;
        if ($fecha === '') {
            return [
                'estado' => 'sin_fecha',
                'error' => 'Fecha inválida en '.ComprobanteProveedorAnitaImportExistenciaSupport::etiquetaCompra($compra),
            ];
        }

        $clave = ComprobanteProveedorAnitaImportClaveSupport::claveDesdeCompra($compra);
        $cuotasAnita = $promovPorClave[$clave] ?? [];
        $nroInterno = (int) ($compra['com_nro_interno'] ?? 0);
        $monedaId = RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($compra['com_cod_mon'] ?? 1);
        $cotizacion = (float) ($compra['com_cotizacion'] ?? 1) ?: 1.0;
        $total = round((float) ($compra['com_monto'] ?? 0), 4);
        $vtoCab = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($compra['com_fecha_prox_vto'] ?? '') ?: $fecha;

        $cuotas = [];
        if ($cuotasAnita === []) {
            $cuotas[] = [
                'numero_cuota' => 1,
                'fechavencimiento' => $vtoCab,
                'monto' => abs($total),
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
            ];
        } else {
            foreach ($cuotasAnita as $cuota) {
                $vto = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($cuota['prov_fecha_vto'] ?? '') ?: $fecha;
                $cuotas[] = [
                    'numero_cuota' => (int) ($cuota['prov_nro_cuota'] ?? 1) ?: 1,
                    'fechavencimiento' => $vto,
                    'monto' => abs((float) ($cuota['prov_monto'] ?? 0)),
                    'moneda_id' => RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($cuota['prov_cod_mon'] ?? $compra['com_cod_mon'] ?? 1),
                    'cotizacion' => (float) ($cuota['prov_cotizacion'] ?? $cotizacion) ?: $cotizacion,
                    'total_pagado' => abs((float) ($cuota['prov_t_pagado'] ?? 0)),
                ];
            }
        }

        $conceptos = [];
        $orden = 1;
        foreach ($concmov[$nroInterno] ?? [] as $linea) {
            $conceptoId = $this->resolverConceptoId((int) ($linea['concepto'] ?? 0));
            if (! $conceptoId) {
                continue;
            }
            $conceptos[] = [
                'concepto_ivacompra_id' => $conceptoId,
                'orden' => $orden++,
                'monto' => (float) ($linea['importe'] ?? 0),
            ];
        }

        $subtotal = $conceptos !== []
            ? round(array_sum(array_column($conceptos, 'monto')), 4)
            : $total;

        return [
            'estado' => 'ok',
            'compra' => $compra,
            'clave' => $clave,
            'empresa_id' => $empresaId,
            'tipo' => $tipo,
            'cuit' => $cuit,
            'fecha' => $fecha,
            'fechaiva' => $fechaIva,
            'fechavencimiento' => $vtoCab,
            'total' => $total,
            'subtotal' => $subtotal,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'cuotas' => $cuotas,
            'conceptos' => $conceptos,
            'nro_interno' => $nroInterno,
            'resumen' => [
                'etiqueta' => ComprobanteProveedorAnitaImportExistenciaSupport::etiquetaCompra($compra),
                'fecha' => $fecha,
                'total' => $total,
                'cuotas' => count($cuotas),
                'nro_interno' => $nroInterno,
                'empresa_id' => $empresaId,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $signoPorTipo
     * @return array{cc_ids: list<int>, cc_por_clave: array<string, list<array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int}>>}
     */
    private function persistirComprobante(
        array $item,
        Proveedor $proveedor,
        int $usuarioId,
        array $signoPorTipo,
        bool $sinCuentaCorriente = false,
    ): array {
        $compra = $item['compra'];
        /** @var Tipotransaccion_Compra $tipo */
        $tipo = $item['tipo'];
        $formapagoId = (int) config('comprobante_proveedor.import_anita.formapago_id', 1);

        $comprobante = Comprobante_Proveedor::query()->create([
            'empresa_id' => $item['empresa_id'],
            'proveedor_id' => $proveedor->id,
            'tipotransaccion_compra_id' => $tipo->id,
            'condicionpago_id' => $this->resolverCondicionpagoId((int) ($compra['com_condicion_pago'] ?? 0)),
            'letra' => ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($compra['com_letra'] ?? '')),
            'sucursal' => (int) ($compra['com_sucursal'] ?? 0),
            'numerocomprobante' => (int) ($compra['com_nro'] ?? 0),
            'fechacomprobante' => $item['fecha'],
            'fechaiva' => $item['fechaiva'],
            'fechavencimiento' => $item['fechavencimiento'],
            'subtotal' => $item['subtotal'],
            'total' => abs((float) $item['total']),
            'moneda_id' => $item['moneda_id'],
            'cotizacion' => $item['cotizacion'],
            'es_fce' => strtoupper(trim((string) ($compra['com_es_fce'] ?? 'N'))) === 'S',
            'leyenda' => mb_substr(trim((string) ($compra['com_leyenda'] ?? '')), 0, 255) ?: null,
            'modo_carga' => ComprobanteProveedorModoCarga::SIN_RECEPCION,
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::ANITA_IMPORT,
            'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
            'identificacion_proveedor_cuit' => $item['cuit'] !== '' ? $item['cuit'] : null,
            'anita_nro_interno' => $item['nro_interno'] > 0 ? $item['nro_interno'] : null,
            'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::IMPORTADO,
            'anita_sync_at' => now(),
            'creousuario_id' => $usuarioId,
        ]);

        foreach ($item['conceptos'] as $concepto) {
            Comprobante_Proveedor_Concepto::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'concepto_ivacompra_id' => $concepto['concepto_ivacompra_id'],
                'orden' => $concepto['orden'],
                'monto' => $concepto['monto'],
            ]);
        }

        $signo = ((string) $tipo->signo === 'R') ? -1 : 1;
        $ccPorClave = [];
        $ccIds = [];
        foreach ($item['cuotas'] as $cuotaData) {
            $cuota = Comprobante_Proveedor_Cuota::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'numero_cuota' => $cuotaData['numero_cuota'],
                'fechavencimiento' => $cuotaData['fechavencimiento'],
                'monto' => $cuotaData['monto'],
                'moneda_id' => $cuotaData['moneda_id'],
                'cotizacion' => $cuotaData['cotizacion'],
                'formapago_id' => $formapagoId,
                'total_pagado' => $cuotaData['total_pagado'] ?? 0,
            ]);

            if ($sinCuentaCorriente) {
                continue;
            }

            $montoCc = round((float) $cuotaData['monto'] * $signo, 4);
            if (abs($montoCc) < 0.0001) {
                continue;
            }

            $cc = Proveedor_Cuentacorriente::query()->create([
                'fecha' => $item['fecha'],
                'fechavencimiento' => $cuotaData['fechavencimiento'],
                'proveedor_id' => $proveedor->id,
                'total' => $montoCc,
                'moneda_id' => $cuotaData['moneda_id'],
                'cotizacion' => $cuotaData['cotizacion'],
                'empresa_id' => $item['empresa_id'],
                'comprobante_proveedor_id' => $comprobante->id,
                'comprobante_proveedor_cuota_id' => $cuota->id,
            ]);
            $cuota->proveedor_cuentacorriente_id = $cc->id;
            $cuota->save();

            $ccIds[] = (int) $cc->id;
            $ccPorClave[$item['clave']][] = [
                'id' => (int) $cc->id,
                'saldo' => abs($montoCc),
                'moneda_id' => (int) $cuotaData['moneda_id'],
                'empresa_id' => (int) $item['empresa_id'],
                'comprobante_id' => (int) $comprobante->id,
            ];
        }

        Comprobante_Proveedor_Estado::query()->create([
            'comprobante_proveedor_id' => $comprobante->id,
            'fecha' => $item['fecha'],
            'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
            'usuario_id' => $usuarioId,
            'observacion' => $sinCuentaCorriente
                ? 'Importado desde Anita (documento sin cuenta corriente)'
                : 'Importado desde Anita (compra/promov/aplmovp)',
        ]);

        return [
            'cc_ids' => $ccIds,
            'cc_por_clave' => $ccPorClave,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $promovs
     * @return array{a_crear: list<array<string, mixed>>, omitidos: int, errores: list<string>}
     */
    private function prepararAdelantos(
        array $promovs,
        int $proveedorId,
        string $proveedorCodigo,
        ?int $empresaCodigoFiltro,
    ): array {
        $existentes = $this->indexarPagosExistentes($proveedorId, $proveedorCodigo);
        $aCrear = [];
        $omitidos = 0;
        $errores = [];

        foreach (ComprobanteProveedorAnitaImportOpaSupport::adelantosPendientes($promovs) as $adelanto) {
            if ($empresaCodigoFiltro && $adelanto['empresa_codigo'] > 0
                && $adelanto['empresa_codigo'] !== $empresaCodigoFiltro) {
                continue;
            }
            $empresaId = $this->resolverEmpresaId($adelanto['empresa_codigo']);
            if (! $empresaId) {
                $errores[] = 'Empresa Anita '.$adelanto['empresa_codigo'].' no mapeada. '.$adelanto['etiqueta'];

                continue;
            }
            if (isset($existentes[$adelanto['clave'].'|'.$empresaId])) {
                $omitidos++;

                continue;
            }

            $aCrear[] = [
                'clave' => $adelanto['clave'],
                'tipo' => $adelanto['tipo'],
                'letra' => $adelanto['letra'],
                'sucursal' => $adelanto['sucursal'],
                'numero' => $adelanto['numero'],
                'fecha' => $adelanto['fecha'],
                'fechavencimiento' => $adelanto['fechavencimiento'] ?: $adelanto['fecha'],
                'pendiente' => $adelanto['pendiente'],
                'moneda_id' => RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($adelanto['moneda_anita']),
                'cotizacion' => $adelanto['cotizacion'],
                'empresa_id' => $empresaId,
                'resumen' => [
                    'etiqueta' => $adelanto['etiqueta'],
                    'fecha' => $adelanto['fecha'],
                    'total' => $adelanto['pendiente'],
                    'empresa_id' => $empresaId,
                    'moneda_anita' => $adelanto['moneda_anita'],
                ],
            ];
        }

        return [
            'a_crear' => $aCrear,
            'omitidos' => $omitidos,
            'errores' => $errores,
        ];
    }

    /**
     * @param  array<string, mixed>  $adelanto
     * @return array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int}
     */
    private function persistirAdelanto(array $adelanto, Proveedor $proveedor, int $usuarioId): array
    {
        $monto = round((float) $adelanto['pendiente'], 4);
        $pago = Pagoproveedor::query()->create([
            'empresa_id' => $adelanto['empresa_id'],
            'tipotransaccion_caja_id' => $this->resolverTipoCajaId($adelanto['tipo']),
            'tipocomprobante' => $adelanto['tipo'],
            'letra' => $adelanto['letra'],
            'sucursal' => $adelanto['sucursal'],
            'numerotransaccion' => (string) $adelanto['numero'],
            'fecha' => $adelanto['fecha'],
            'proveedor_id' => $proveedor->id,
            'detalle' => 'Importado desde Anita — OPA sin aplicar',
            'estado' => 'CONFIRMADA',
            'monto' => $monto,
            'cotizacion' => $adelanto['cotizacion'],
            'moneda_id' => $adelanto['moneda_id'],
            'modo_cotizacion' => 'dia',
            'usuario_id' => $usuarioId,
        ]);

        Pagoproveedor_Estado::query()->create([
            'pagoproveedor_id' => $pago->id,
            'fecha' => now(),
            'estado' => 'CONFIRMADA',
            'usuario_id' => $usuarioId,
            'observacion' => 'Importado desde Anita (OPA sin aplicar)',
        ]);

        $cc = Proveedor_Cuentacorriente::query()->create([
            'fecha' => $adelanto['fecha'],
            'fechavencimiento' => $adelanto['fechavencimiento'],
            'proveedor_id' => $proveedor->id,
            'total' => -$monto,
            'moneda_id' => $adelanto['moneda_id'],
            'cotizacion' => $adelanto['cotizacion'],
            'empresa_id' => $adelanto['empresa_id'],
            'pagoproveedor_id' => $pago->id,
        ]);

        return [
            'id' => (int) $cc->id,
            'saldo' => $monto,
            'moneda_id' => (int) $adelanto['moneda_id'],
            'empresa_id' => (int) $adelanto['empresa_id'],
            'comprobante_id' => null,
        ];
    }

    /**
     * OPA como documento pagoproveedor sin fila en cuenta corriente.
     *
     * @param  array<string, mixed>  $adelanto
     */
    private function persistirAdelantoDocumento(array $adelanto, Proveedor $proveedor, int $usuarioId): int
    {
        $monto = round((float) $adelanto['pendiente'], 4);
        $pago = Pagoproveedor::query()->create([
            'empresa_id' => $adelanto['empresa_id'],
            'tipotransaccion_caja_id' => $this->resolverTipoCajaId($adelanto['tipo']),
            'tipocomprobante' => $adelanto['tipo'],
            'letra' => $adelanto['letra'],
            'sucursal' => $adelanto['sucursal'],
            'numerotransaccion' => (string) $adelanto['numero'],
            'fecha' => $adelanto['fecha'],
            'proveedor_id' => $proveedor->id,
            'detalle' => 'Importado desde Anita — OPA documento (sin cuenta corriente)',
            'estado' => 'CONFIRMADA',
            'monto' => $monto,
            'cotizacion' => $adelanto['cotizacion'],
            'moneda_id' => $adelanto['moneda_id'],
            'modo_cotizacion' => 'dia',
            'usuario_id' => $usuarioId,
        ]);

        Pagoproveedor_Estado::query()->create([
            'pagoproveedor_id' => $pago->id,
            'fecha' => now(),
            'estado' => 'CONFIRMADA',
            'usuario_id' => $usuarioId,
            'observacion' => 'Importado desde Anita (OPA sin cuenta corriente)',
        ]);

        return (int) $pago->id;
    }

    /**
     * @return array<string, int>
     */
    private function indexarPagosExistentes(int $proveedorId, string $proveedorCodigo): array
    {
        $out = [];
        $pagos = Pagoproveedor::query()
            ->where('proveedor_id', $proveedorId)
            ->get(['id', 'empresa_id', 'tipocomprobante', 'letra', 'sucursal', 'numerotransaccion']);

        foreach ($pagos as $pago) {
            $clave = ComprobanteProveedorAnitaImportClaveSupport::clave(
                $proveedorCodigo,
                (string) $pago->tipocomprobante,
                (string) $pago->letra,
                (int) $pago->sucursal,
                (int) $pago->numerotransaccion,
            );
            $out[$clave.'|'.(int) $pago->empresa_id] = (int) $pago->id;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $pares
     * @param  array<string, list<array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int}>>  $ccPorClave
     * @return array{creadas:int, pagos_sinteticos:int, omitidas:int, errores: list<string>}
     */
    private function persistirAplicaciones(array $pares, array &$ccPorClave, int $proveedorId): array
    {
        $creadas = 0;
        $pagos = 0;
        $omitidas = 0;
        $errores = [];

        foreach ($pares as $par) {
            $monto = round((float) $par['monto'], 4);
            $idsDeuda = array_map(
                static fn (array $cc) => (int) $cc['id'],
                $ccPorClave[$par['deuda']['clave']] ?? []
            );
            if ($this->aplicacionYaExistePorEtiquetaEnCcs($idsDeuda, (string) $par['etiqueta_credito'], $monto)) {
                $omitidas++;

                continue;
            }

            $deuda = $this->peekCc($ccPorClave, $par['deuda']['clave'], $monto);
            if ($deuda === null) {
                $omitidas++;

                continue;
            }

            $credito = $this->peekCc($ccPorClave, $par['credito']['clave'], $monto);
            if ($credito === null && $par['credito_es_pago']) {
                $credito = $this->crearCcPagoSintetico($par, $deuda, $proveedorId);
                $ccPorClave[$par['credito']['clave']][] = $credito;
                $pagos++;
            }
            if ($credito === null) {
                $omitidas++;

                continue;
            }

            if ($this->aplicacionYaExiste((int) $deuda['id'], (int) $credito['id'], $monto)) {
                $omitidas++;

                continue;
            }

            $this->consumirCc($ccPorClave, $par['deuda']['clave'], (int) ($deuda['_idx'] ?? 0), $monto);
            if (isset($credito['_idx'])) {
                $this->consumirCc($ccPorClave, $par['credito']['clave'], (int) $credito['_idx'], $monto);
            }
            Proveedor_Cuentacorriente_Aplicacion::query()->create([
                'fecha' => $par['fecha'],
                'proveedor_cuentacorriente_id' => $deuda['id'],
                'total' => -$monto,
                'moneda_id' => $deuda['moneda_id'],
                'cotizacion' => 1,
                'comprobanteaplicado' => $par['etiqueta_credito'],
                'comprobante_proveedor_aplicado_id' => $credito['comprobante_id'],
                'empresa_id' => $deuda['empresa_id'],
                'proveedor_cuentacorriente_aplicado_id' => $credito['id'],
            ]);
            Proveedor_Cuentacorriente_Aplicacion::query()->create([
                'fecha' => $par['fecha'],
                'proveedor_cuentacorriente_id' => $credito['id'],
                'total' => $monto,
                'moneda_id' => $credito['moneda_id'],
                'cotizacion' => 1,
                'comprobanteaplicado' => $par['etiqueta_deuda'],
                'comprobante_proveedor_aplicado_id' => $deuda['comprobante_id'],
                'empresa_id' => $credito['empresa_id'],
                'proveedor_cuentacorriente_aplicado_id' => $deuda['id'],
            ]);
            $creadas++;
        }

        return [
            'creadas' => $creadas,
            'pagos_sinteticos' => $pagos,
            'omitidas' => $omitidas,
            'errores' => $errores,
        ];
    }

    /**
     * @param  array<string, list<array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int}>>  $ccPorClave
     * @return array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int, _idx:int}|null
     */
    private function peekCc(array $ccPorClave, string $clave, float $monto): ?array
    {
        if (! isset($ccPorClave[$clave]) || $ccPorClave[$clave] === []) {
            return null;
        }

        foreach ($ccPorClave[$clave] as $i => $cc) {
            if ($monto <= $cc['saldo'] + 0.0001) {
                $cc['_idx'] = (int) $i;

                return $cc;
            }
        }

        $cc = $ccPorClave[$clave][0];
        $cc['_idx'] = 0;

        return $cc;
    }

    /**
     * @param  array<string, list<array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int}>>  $ccPorClave
     */
    private function consumirCc(array &$ccPorClave, string $clave, int $idx, float $monto): void
    {
        if (! isset($ccPorClave[$clave][$idx])) {
            return;
        }
        $ccPorClave[$clave][$idx]['saldo'] = round($ccPorClave[$clave][$idx]['saldo'] - $monto, 4);
    }

    /**
     * @param  array<string, list<array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int}>>  $ccPorClave
     * @return array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int}|null
     */
    private function tomarCc(array &$ccPorClave, string $clave, float $monto): ?array
    {
        $cc = $this->peekCc($ccPorClave, $clave, $monto);
        if ($cc === null) {
            return null;
        }
        $this->consumirCc($ccPorClave, $clave, (int) $cc['_idx'], $monto);
        unset($cc['_idx']);

        return $cc;
    }

    /**
     * @param  array<string, mixed>  $par
     * @param  array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int}  $deuda
     * @return array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int}
     */
    private function crearCcPagoSintetico(array $par, array $deuda, int $proveedorId): array
    {
        $monto = round((float) $par['monto'], 4);
        $cc = Proveedor_Cuentacorriente::query()->create([
            'fecha' => $par['fecha'],
            'fechavencimiento' => $par['fecha'],
            'proveedor_id' => $proveedorId,
            'total' => -$monto,
            'moneda_id' => $deuda['moneda_id'],
            'cotizacion' => 1,
            'empresa_id' => $deuda['empresa_id'],
            'comprobante_proveedor_id' => $deuda['comprobante_id'],
        ]);

        return [
            'id' => (int) $cc->id,
            'saldo' => $monto,
            'moneda_id' => (int) $deuda['moneda_id'],
            'empresa_id' => (int) $deuda['empresa_id'],
            'comprobante_id' => null,
        ];
    }

    private function aplicacionYaExiste(int $deudaId, int $creditoId, float $monto): bool
    {
        return Proveedor_Cuentacorriente_Aplicacion::query()
            ->where('proveedor_cuentacorriente_id', $deudaId)
            ->where('proveedor_cuentacorriente_aplicado_id', $creditoId)
            ->whereRaw('ABS(total) BETWEEN ? AND ?', [round($monto - 0.01, 4), round($monto + 0.01, 4)])
            ->exists();
    }

    /**
     * @param  list<int>  $cuentacorrienteIds
     */
    private function aplicacionYaExistePorEtiquetaEnCcs(array $cuentacorrienteIds, string $etiquetaCredito, float $monto): bool
    {
        $etiqueta = trim($etiquetaCredito);
        $ids = array_values(array_filter($cuentacorrienteIds, static fn (int $id) => $id > 0));
        if ($etiqueta === '' || $ids === []) {
            return false;
        }

        return Proveedor_Cuentacorriente_Aplicacion::query()
            ->whereIn('proveedor_cuentacorriente_id', $ids)
            ->where('comprobanteaplicado', $etiqueta)
            ->whereRaw('ABS(total) BETWEEN ? AND ?', [round($monto - 0.01, 4), round($monto + 0.01, 4)])
            ->exists();
    }

    /**
     * @return array<string, list<array{id:int, saldo:float, moneda_id:int, empresa_id:int, comprobante_id:?int}>>
     */
    private function indexarCcExistente(int $proveedorId, string $proveedorCodigo): array
    {
        $filas = Proveedor_Cuentacorriente::query()
            ->with(['comprobante_proveedores.tipotransaccion_compras', 'pagoproveedores'])
            ->where('proveedor_id', $proveedorId)
            ->where(function ($q): void {
                $q->whereNotNull('comprobante_proveedor_id')
                    ->orWhereNotNull('pagoproveedor_id');
            })
            ->get();

        $out = [];
        foreach ($filas as $cc) {
            $comp = $cc->comprobante_proveedores;
            if ($comp !== null) {
                $tipo = (string) ($comp->tipotransaccion_compras?->abreviatura ?? '');
                if ($tipo !== '') {
                    $clave = ComprobanteProveedorAnitaImportClaveSupport::clave(
                        $proveedorCodigo,
                        $tipo,
                        (string) $comp->letra,
                        (int) $comp->sucursal,
                        (int) $comp->numerocomprobante,
                    );
                    $out[$clave][] = [
                        'id' => (int) $cc->id,
                        'saldo' => abs((float) $cc->total),
                        'moneda_id' => (int) $cc->moneda_id,
                        'empresa_id' => (int) $cc->empresa_id,
                        'comprobante_id' => (int) $comp->id,
                    ];
                }
            }

            $pago = $cc->pagoproveedores;
            if ($pago !== null) {
                $clavePago = ComprobanteProveedorAnitaImportClaveSupport::clave(
                    $proveedorCodigo,
                    (string) $pago->tipocomprobante,
                    (string) $pago->letra,
                    (int) $pago->sucursal,
                    (int) $pago->numerotransaccion,
                );
                $out[$clavePago][] = [
                    'id' => (int) $cc->id,
                    'saldo' => abs((float) $cc->total),
                    'moneda_id' => (int) $cc->moneda_id,
                    'empresa_id' => (int) $cc->empresa_id,
                    'comprobante_id' => null,
                ];
            }
        }

        return $out;
    }

    private function resolverProveedor(string $codigo): ?Proveedor
    {
        $norm = ltrim(trim($codigo), '0');
        if ($norm === '') {
            return null;
        }

        return Proveedor::query()
            ->where('codigo', $norm)
            ->orWhere('codigo', str_pad($norm, 6, '0', STR_PAD_LEFT))
            ->orWhere('codigo', $codigo)
            ->first();
    }

    private function resolverEmpresaId(int $codigo): ?int
    {
        if ($codigo <= 0) {
            $codigo = 1;
        }
        $key = (string) $codigo;
        if (! array_key_exists($key, $this->cacheEmpresa)) {
            $this->cacheEmpresa[$key] = (int) (Empresa::query()->where('codigo', (string) $codigo)->value('id') ?: 0) ?: null;
        }

        return $this->cacheEmpresa[$key];
    }

    private function resolverTipoCajaId(string $abrev): ?int
    {
        $abrev = ComprobanteProveedorAnitaImportClaveSupport::tipo($abrev);
        if ($abrev === '') {
            return null;
        }
        if (! array_key_exists($abrev, $this->cacheTipoCaja)) {
            $id = (int) (Tipotransaccion_Caja::query()
                ->where('abreviatura', $abrev)
                ->value('id') ?: 0);
            if ($id <= 0 && $abrev !== 'OPP') {
                $id = (int) (Tipotransaccion_Caja::query()
                    ->where('abreviatura', 'OPP')
                    ->value('id') ?: 0);
            }
            $this->cacheTipoCaja[$abrev] = $id > 0 ? $id : null;
        }

        return $this->cacheTipoCaja[$abrev];
    }

    private function resolverTipo(string $abrev): ?Tipotransaccion_Compra
    {
        if ($abrev === '') {
            return null;
        }
        if (! array_key_exists($abrev, $this->cacheTipo)) {
            $this->cacheTipo[$abrev] = Tipotransaccion_Compra::query()
                ->where('abreviatura', $abrev)
                ->first();
        }

        return $this->cacheTipo[$abrev];
    }

    private function resolverCondicionpagoId(int $codigo): ?int
    {
        if ($codigo <= 0) {
            return null;
        }
        if (! array_key_exists($codigo, $this->cacheCondicionpago)) {
            $this->cacheCondicionpago[$codigo] = (int) (Condicionpago::query()
                ->where('codigo', $codigo)
                ->value('id') ?: 0) ?: null;
        }

        return $this->cacheCondicionpago[$codigo];
    }

    private function resolverConceptoId(int $codigo): ?int
    {
        if ($codigo <= 0) {
            return null;
        }
        if (! array_key_exists($codigo, $this->cacheConcepto)) {
            $this->cacheConcepto[$codigo] = (int) (Concepto_Ivacompra::query()
                ->where('codigo', $codigo)
                ->value('id') ?: 0) ?: null;
        }

        return $this->cacheConcepto[$codigo];
    }

    /**
     * @return array<string, string>
     */
    private function mapaSignoTipos(): array
    {
        $mapa = [];
        foreach (Tipotransaccion_Compra::query()->get(['abreviatura', 'signo']) as $tipo) {
            $abrev = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) $tipo->abreviatura);
            if ($abrev !== '') {
                $mapa[$abrev] = (string) $tipo->signo === 'R' ? 'R' : 'S';
            }
        }

        return $mapa;
    }

    /**
     * @return array<string, mixed>
     */
    private function statsVacios(int $proveedorId, string $nombre): array
    {
        return [
            'proveedor_id' => $proveedorId,
            'proveedor_nombre' => $nombre,
            'anita_compra' => 0,
            'anita_promov' => 0,
            'anita_aplmovp' => 0,
            'anita_concmov' => 0,
            'a_crear' => 0,
            'creadas' => 0,
            'omitidas_ya_en_erp' => 0,
            'duplicadas_lote' => 0,
            'omitidas_detalle' => [],
            'sin_tipo' => 0,
            'sin_empresa' => 0,
            'sin_fecha' => 0,
            'cuotas' => 0,
            'conceptos' => 0,
            'cc' => 0,
            'aplicaciones_anita' => 0,
            'aplicaciones' => 0,
            'aplicaciones_pago_sintetico' => 0,
            'aplicaciones_omitidas' => 0,
            'adelantos_anita' => 0,
            'adelantos_a_crear' => 0,
            'adelantos_creados' => 0,
            'adelantos_omitidos_ya_en_erp' => 0,
            'adelantos_a_crear_documento' => 0,
            'sin_cuenta_corriente' => false,
            'errores' => [],
            'muestra' => [],
            'muestra_adelantos' => [],
            'modo' => 'dry-run',
        ];
    }
}
