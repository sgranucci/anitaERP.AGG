<?php

namespace App\Services\Compras;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportAplmovpSupport;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportOpaSupport;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportOrigenSupport;
use App\Support\Compras\AnitaImport\ProveedorCuentacorrienteAnitaImportBridgeReader;
use App\Support\Compras\AnitaImport\ProveedorCuentacorrienteAnitaImportCcGuardSupport;
use App\Support\Compras\AnitaImport\ProveedorCuentacorrienteAnitaImportFormatoSupport;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorProvinciaDestinoSupport;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Database\DbContencionSupport;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Deuda proveedores Anita → ERP (AGG / Ferli).
 *
 * - Altas solo-Anita: CP+CC con monto original + aplicaciones aplmovp.
 * - Facturas nativas (origen ≠ ANITA_IMPORT): no se pisan; solo se traen apps faltantes.
 * - OPA pendientes: pagoproveedor + CC negativa.
 * - Nunca alinea ni borra apps de CC con pagoproveedor_id (crédito OP/OPA ERP).
 * - Al alinear deuda documento solo borra apps sintéticas (sin pagoproveedor_id).
 * No escribe Anita.
 */
class ProveedorCuentacorrienteImportarDesdeAnitaService
{
    /** @var array<string, array{nombre:string,signo:string,codigoafip:string}> */
    private const TIPOS_SEMILLA = [
        'FAC' => ['nombre' => 'Factura', 'signo' => 'S', 'codigoafip' => '001'],
        'FAS' => ['nombre' => 'Factura saldo', 'signo' => 'S', 'codigoafip' => '001'],
        'FAJ' => ['nombre' => 'Factura ajuste', 'signo' => 'S', 'codigoafip' => '001'],
        'FAL' => ['nombre' => 'Factura', 'signo' => 'S', 'codigoafip' => '001'],
        'FAI' => ['nombre' => 'Factura importación', 'signo' => 'S', 'codigoafip' => '001'],
        'FAN' => ['nombre' => 'Factura N', 'signo' => 'S', 'codigoafip' => '001'],
        'FAF' => ['nombre' => 'Factura F', 'signo' => 'S', 'codigoafip' => '001'],
        'FNB' => ['nombre' => 'Factura NB', 'signo' => 'S', 'codigoafip' => '001'],
        'FIS' => ['nombre' => 'Factura IS', 'signo' => 'S', 'codigoafip' => '001'],
        'FGA' => ['nombre' => 'Factura GA', 'signo' => 'S', 'codigoafip' => '001'],
        'FIB' => ['nombre' => 'Factura IB', 'signo' => 'S', 'codigoafip' => '001'],
        'FNS' => ['nombre' => 'Factura NS', 'signo' => 'S', 'codigoafip' => '001'],
        'NCD' => ['nombre' => 'Nota de crédito', 'signo' => 'R', 'codigoafip' => '003'],
        'NCF' => ['nombre' => 'Nota de crédito', 'signo' => 'R', 'codigoafip' => '003'],
        'NCB' => ['nombre' => 'Nota de crédito B', 'signo' => 'R', 'codigoafip' => '008'],
        'NCS' => ['nombre' => 'Nota de crédito', 'signo' => 'R', 'codigoafip' => '003'],
        'NCI' => ['nombre' => 'Nota de crédito interna', 'signo' => 'R', 'codigoafip' => '003'],
        'NCA' => ['nombre' => 'Nota de crédito A', 'signo' => 'R', 'codigoafip' => '003'],
        'NCN' => ['nombre' => 'Nota de crédito N', 'signo' => 'R', 'codigoafip' => '003'],
        'CIS' => ['nombre' => 'Nota de crédito IS', 'signo' => 'R', 'codigoafip' => '003'],
        'CNS' => ['nombre' => 'Nota de crédito NS', 'signo' => 'R', 'codigoafip' => '003'],
        'NDF' => ['nombre' => 'Nota de débito', 'signo' => 'S', 'codigoafip' => '002'],
        'NDB' => ['nombre' => 'Nota de débito B', 'signo' => 'S', 'codigoafip' => '007'],
        'NDS' => ['nombre' => 'Nota de débito', 'signo' => 'S', 'codigoafip' => '002'],
        'NDR' => ['nombre' => 'Nota de débito', 'signo' => 'S', 'codigoafip' => '002'],
    ];

    /** @var array<string, Tipotransaccion_Compra|null> */
    private array $cacheTipo = [];

    /** @var array<string, Proveedor|null> */
    private array $cacheProveedor = [];

    /** @var array<string, int|null> */
    private array $cacheTipoCaja = [];

    public function __construct(
        private readonly ProveedorCuentacorrienteAnitaImportBridgeReader $reader = new ProveedorCuentacorrienteAnitaImportBridgeReader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function importar(
        bool $dryRun = true,
        ?string $proveedorCodigo = null,
        ?string $desdeIso = null,
        ?string $hastaIso = null,
        int $usuarioId = 1,
        ?int $limite = null,
        ?int $empresaId = null,
        int $muestraLimite = 25,
    ): array {
        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();
        $desdeYmd = $desdeIso ? ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($desdeIso) : null;
        $hastaYmd = $hastaIso ? ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($hastaIso) : null;
        $empresaAnita = null;
        if ($perfil['tiene_empresa'] && $empresaId !== null && $empresaId > 0) {
            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
            if ($empresaAnita <= 0) {
                $empresaAnita = null;
            }
        }

        $empresasPermitidas = $this->empresasPermitidas($empresaId);
        if (! $dryRun) {
            $this->asegurarTiposBasicos();
        }

        $promovs = $this->reader->listarPromovPendiente(
            $desdeYmd ?: null,
            $hastaYmd ?: null,
            $proveedorCodigo,
            $empresaAnita,
        );
        $stats = $this->statsVacios($perfil);
        $stats['anita_promov'] = count($promovs);
        $stats['empresa_id'] = $empresaId;
        $stats['empresa_anita'] = $empresaAnita;

        $deuda = [];
        $promovsOpa = [];
        $clavesCompra = [];
        $tol = (float) $perfil['tolerancia_aplicado'];
        foreach ($promovs as $promov) {
            $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) ($promov['prov_tipo'] ?? ''));
            if ($tipo === '' || ProveedorCuentacorrienteAnitaImportFormatoSupport::esTipoNoDeuda($tipo, $perfil)) {
                $stats['omitidas_tipo_no_deuda']++;

                continue;
            }
            $montoAbs = abs((float) ($promov['prov_monto'] ?? 0));
            $pagadoAbs = abs((float) ($promov['prov_t_pagado'] ?? 0));
            if (abs($montoAbs - $pagadoAbs) <= $tol) {
                $stats['omitidas_saldadas_anita']++;

                continue;
            }
            if (ProveedorCuentacorrienteAnitaImportFormatoSupport::esTipoCreditoSinCompra($tipo, $perfil)) {
                $promovsOpa[] = $promov;

                continue;
            }
            $clave = ComprobanteProveedorAnitaImportClaveSupport::claveDesdePromov($promov);
            $deuda[] = $promov;
            $clavesCompra[$clave] = $clave;
        }

        $compras = $this->reader->indexarCompraPorClaves(array_values($clavesCompra), $empresaAnita);
        $stats['anita_compra'] = count($compras);
        $stats['credito_sin_compra_anita'] = count($promovsOpa);

        $plan = [];
        $clavesEnPlan = [];
        foreach ($deuda as $promov) {
            $clave = ComprobanteProveedorAnitaImportClaveSupport::claveDesdePromov($promov);
            if (! isset($compras[$clave])) {
                $stats['omitidas_sin_compra']++;

                continue;
            }
            $prep = $this->preparar($promov, $compras[$clave], $perfil, $empresasPermitidas);
            $prep = $this->contabilizarPrep($prep, $stats);
            if (($prep['estado'] ?? '') !== 'ok') {
                continue;
            }
            $plan[] = $prep;
            $clavesEnPlan[$clave] = $clave;
            if ($limite !== null && $limite > 0 && count($plan) >= $limite) {
                break;
            }
        }

        if ($limite === null || $limite <= 0 || count($plan) < $limite) {
            $extra = $this->planearNativasSaldadasAnita(
                $proveedorCodigo,
                $empresaId,
                $empresaAnita,
                $perfil,
                $empresasPermitidas,
                $clavesEnPlan,
                $limite !== null && $limite > 0 ? ($limite - count($plan)) : null,
            );
            foreach ($extra['items'] as $prep) {
                $plan[] = $prep;
                $clavesEnPlan[(string) $prep['clave']] = (string) $prep['clave'];
            }
            $stats['nativas_saldadas_anita'] = $extra['candidatas'];
            foreach ($extra['errores'] as $e) {
                $stats['errores'][] = $e;
            }
        }

        if ($limite === null || $limite <= 0 || count($plan) < $limite) {
            foreach (ComprobanteProveedorAnitaImportOpaSupport::adelantosPendientes(
                $promovsOpa,
                $perfil['tipos_credito_sin_compra'] ?? null,
            ) as $adelanto) {
                $prep = $this->prepararOpa($adelanto, $perfil, $empresasPermitidas);
                $prep = $this->contabilizarPrep($prep, $stats);
                if (($prep['estado'] ?? '') !== 'ok') {
                    continue;
                }
                $plan[] = $prep;
                if ($limite !== null && $limite > 0 && count($plan) >= $limite) {
                    break;
                }
            }
        }

        $signoPorTipo = $this->mapaSignoTipos();
        $clavesApl = [];
        foreach ($plan as $item) {
            if (($item['kind'] ?? 'deuda') !== 'deuda') {
                continue;
            }
            if (($item['accion_apl'] ?? '') !== 'importar') {
                continue;
            }
            $clavesApl[(string) $item['clave']] = (string) $item['clave'];
        }
        $aplFilas = $clavesApl === []
            ? []
            : $this->reader->listarAplmovpPorDeudas(array_values($clavesApl));
        $pares = ComprobanteProveedorAnitaImportAplmovpSupport::paresDesdeFilas($aplFilas, $signoPorTipo);
        $stats['anita_aplmovp'] = count($aplFilas);
        $stats['aplicaciones_anita'] = count($pares);
        $stats['aplicaciones_planificadas'] = count($pares);

        $stats['a_procesar'] = count($plan);
        $stats['a_crear_cp'] = count(array_filter(
            $plan,
            static fn (array $p) => ($p['kind'] ?? 'deuda') === 'deuda' && $p['accion_cp'] === 'crear'
        ));
        $stats['a_crear_opa'] = count(array_filter(
            $plan,
            static fn (array $p) => ($p['kind'] ?? '') === 'opa' && ($p['accion_pago'] ?? '') === 'crear'
        ));
        $stats['a_crear_cc'] = count(array_filter($plan, static fn (array $p) => ($p['accion_cc'] ?? '') === 'crear'));
        $stats['a_alinear_anita_import'] = count(array_filter(
            $plan,
            static fn (array $p) => ($p['accion_saldo'] ?? '') === 'alinear'
        ));
        $stats['a_colapsar_saldo'] = 0;
        $stats['a_actualizar_aplicaciones'] = count(array_filter(
            $plan,
            static fn (array $p) => ($p['accion_apl'] ?? '') === 'importar'
        ));
        $stats['nativas_solo_apps'] += count(array_filter(
            $plan,
            static fn (array $p) => ! empty($p['es_nativo'])
                && ($p['accion_cp'] ?? '') === 'existente'
                && ($p['accion_apl'] ?? '') === 'importar'
        ));
        $muestraLimite = max(1, $muestraLimite);
        $stats['muestra'] = array_map(static fn (array $p) => $p['resumen'], array_slice($plan, 0, $muestraLimite));

        if ($dryRun) {
            $stats['modo'] = 'dry-run';

            return $stats;
        }

        return DB::transaction(function () use ($plan, $pares, $stats, $usuarioId) {
            $ccPorClave = [];
            foreach ($plan as $item) {
                if (($item['kind'] ?? 'deuda') === 'opa') {
                    $res = $this->persistirOpa($item, $usuarioId);
                    $stats['opa_creados'] += $res['pago_creado'] ? 1 : 0;
                    $stats['cc_creadas'] += $res['cc_creada'] ? 1 : 0;
                    if (! empty($res['cc_id']) && ! empty($item['clave'])) {
                        $ccPorClave[(string) $item['clave']][] = [
                            'id' => (int) $res['cc_id'],
                            'saldo' => abs((float) $item['total']),
                            'moneda_id' => (int) $item['moneda_id'],
                            'empresa_id' => (int) $item['empresa_id'],
                            'comprobante_id' => null,
                        ];
                    }
                    foreach ($res['errores'] as $e) {
                        $stats['errores'][] = $e;
                    }

                    continue;
                }
                $res = $this->persistirItem($item, $usuarioId, $ccPorClave);
                $stats['cp_creados'] += $res['cp_creado'] ? 1 : 0;
                $stats['cc_creadas'] += $res['cc_creada'] ? 1 : 0;
                $stats['saldos_alineados'] += $res['saldo_alineado'] ? 1 : 0;
                foreach ($res['errores'] as $e) {
                    $stats['errores'][] = $e;
                }
            }

            $this->enriquecerCcPorClaveDesdeErp($ccPorClave, $pares);
            $apl = $this->persistirAplicaciones($pares, $ccPorClave);
            $stats['aplicaciones_creadas'] = $apl['creadas'];
            $stats['aplicaciones_omitidas'] = $apl['omitidas'];
            $stats['pagos_sinteticos'] = $apl['pagos_sinteticos'];
            foreach ($apl['errores'] as $e) {
                $stats['errores'][] = $e;
            }
            $stats['saldos_alineados'] += $this->alinearResidualesTrasApps($plan);
            $stats['modo'] = 'ejecutar';

            return $stats;
        });
    }

    /**
     * Nativas con pendiente ERP cuyo promov Anita ya está saldado (no sale en listarPromovPendiente).
     *
     * @param  array<string, mixed>  $perfil
     * @param  list<int>|null  $empresasPermitidas
     * @param  array<string, string>  $clavesEnPlan
     * @return array{candidatas:int,solo_apps:int,items:list<array<string,mixed>>,errores:list<string>}
     */
    private function planearNativasSaldadasAnita(
        ?string $proveedorCodigo,
        ?int $empresaId,
        ?int $empresaAnita,
        array $perfil,
        ?array $empresasPermitidas,
        array $clavesEnPlan,
        ?int $cupo,
    ): array {
        $out = ['candidatas' => 0, 'solo_apps' => 0, 'items' => [], 'errores' => []];
        $filas = $this->listarNativasPendientesErp($proveedorCodigo, $empresaId, $empresasPermitidas);
        if ($filas === []) {
            return $out;
        }

        $claves = [];
        $porClave = [];
        foreach ($filas as $fila) {
            $clave = (string) $fila['clave'];
            if (isset($clavesEnPlan[$clave])) {
                continue;
            }
            $claves[$clave] = $clave;
            $porClave[$clave] = $fila;
        }
        $out['candidatas'] = count($claves);
        if ($claves === []) {
            return $out;
        }

        $promovs = $this->reader->indexarPromovPorClaves(array_values($claves), $empresaAnita);
        $compras = $this->reader->indexarCompraPorClaves(array_values($claves), $empresaAnita);

        foreach ($porClave as $clave => $filaErp) {
            if ($cupo !== null && $cupo > 0 && count($out['items']) >= $cupo) {
                break;
            }
            $listaPromov = $promovs[$clave] ?? [];
            $compra = $compras[$clave] ?? null;
            if ($compra === null) {
                $out['errores'][] = 'Nativa '.$filaErp['etiqueta'].' sin compra Anita';

                continue;
            }
            $promov = $listaPromov[0] ?? null;
            if ($promov === null) {
                $promov = $this->promovSinteticoDesdeCompraYErp($compra, $filaErp);
            }
            $prep = $this->preparar($promov, $compra, $perfil, $empresasPermitidas);
            if (($prep['estado'] ?? '') === 'ok_al_dia') {
                continue;
            }
            if (($prep['estado'] ?? '') !== 'ok') {
                if (! empty($prep['error'])) {
                    $out['errores'][] = (string) $prep['error'];
                }

                continue;
            }
            $out['items'][] = $prep;
            if (! empty($prep['es_nativo']) && ($prep['accion_apl'] ?? '') === 'importar') {
                $out['solo_apps']++;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>|null  $empresasPermitidas
     * @return list<array<string, mixed>>
     */
    private function listarNativasPendientesErp(
        ?string $proveedorCodigo,
        ?int $empresaId,
        ?array $empresasPermitidas,
    ): array {
        $tol = 0.02;
        $q = Proveedor_Cuentacorriente::query()
            ->from('proveedor_cuentacorriente as cc')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cc.comprobante_proveedor_id')
            ->join('proveedor as p', 'p.id', '=', 'cp.proveedor_id')
            ->join('tipotransaccion_compra as t', 't.id', '=', 'cp.tipotransaccion_compra_id')
            ->leftJoin('proveedor_cuentacorriente_aplicacion as apl', 'apl.proveedor_cuentacorriente_id', '=', 'cc.id')
            ->where(function ($w) {
                $w->whereNull('cp.origen_entrada')
                    ->orWhere('cp.origen_entrada', '!=', ComprobanteProveedorOrigenEntrada::ANITA_IMPORT);
            })
            ->where(function ($w) {
                $w->whereNull('cp.estado')
                    ->orWhere('cp.estado', '!=', ComprobanteProveedorEstados::ANULADO);
            })
            ->groupBy(
                'cc.id',
                'cp.id',
                'cp.empresa_id',
                'cp.origen_entrada',
                'p.codigo',
                't.abreviatura',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
                'cp.total',
                'cc.total',
                'cc.moneda_id',
                'cc.cotizacion',
                'cc.fecha',
                'cc.fechavencimiento',
            )
            ->havingRaw('ABS(cc.total + COALESCE(SUM(apl.total), 0)) > ?', [$tol])
            ->select([
                'cc.id as cc_id',
                'cp.id as cp_id',
                'cp.empresa_id',
                'cp.origen_entrada',
                'p.codigo as proveedor_codigo',
                't.abreviatura',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
                'cp.total as cp_total',
                'cc.total as cc_total',
                'cc.moneda_id',
                'cc.cotizacion',
                'cc.fecha',
                'cc.fechavencimiento',
                DB::raw('COALESCE(SUM(apl.total), 0) as aplicado'),
            ]);

        if ($empresaId !== null && $empresaId > 0) {
            $q->where('cp.empresa_id', $empresaId);
        } elseif ($empresasPermitidas !== null) {
            $q->whereIn('cp.empresa_id', $empresasPermitidas);
        }

        if ($proveedorCodigo !== null && trim($proveedorCodigo) !== '') {
            $norm = ltrim(trim($proveedorCodigo), '0');
            if ($norm === '') {
                $norm = '0';
            }
            $q->where(function ($w) use ($norm, $proveedorCodigo) {
                $w->where('p.codigo', $norm)
                    ->orWhere('p.codigo', str_pad($norm, 6, '0', STR_PAD_LEFT))
                    ->orWhere('p.codigo', trim($proveedorCodigo));
            });
        }

        $out = [];
        foreach ($q->get() as $row) {
            $clave = ComprobanteProveedorAnitaImportClaveSupport::clave(
                (string) $row->proveedor_codigo,
                (string) $row->abreviatura,
                (string) $row->letra,
                (int) $row->sucursal,
                (int) $row->numerocomprobante,
            );
            $out[] = [
                'clave' => $clave,
                'cc_id' => (int) $row->cc_id,
                'cp_id' => (int) $row->cp_id,
                'empresa_id' => (int) $row->empresa_id,
                'origen_entrada' => $row->origen_entrada,
                'proveedor_codigo' => (string) $row->proveedor_codigo,
                'abreviatura' => (string) $row->abreviatura,
                'letra' => (string) $row->letra,
                'sucursal' => (int) $row->sucursal,
                'numero' => (int) $row->numerocomprobante,
                'cp_total' => (float) $row->cp_total,
                'cc_total' => (float) $row->cc_total,
                'aplicado' => (float) $row->aplicado,
                'pendiente' => (float) $row->cc_total + (float) $row->aplicado,
                'moneda_id' => (int) $row->moneda_id,
                'cotizacion' => (float) $row->cotizacion,
                'fecha' => (string) $row->fecha,
                'fechavencimiento' => (string) ($row->fechavencimiento ?: $row->fecha),
                'etiqueta' => ComprobanteProveedorAnitaImportClaveSupport::etiqueta(
                    (string) $row->abreviatura,
                    (string) $row->letra,
                    (int) $row->sucursal,
                    (int) $row->numerocomprobante,
                ),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $compra
     * @param  array<string, mixed>  $filaErp
     * @return array<string, mixed>
     */
    private function promovSinteticoDesdeCompraYErp(array $compra, array $filaErp): array
    {
        $monto = abs((float) ($compra['com_monto'] ?? $filaErp['cp_total'] ?? 0));

        return [
            'prov_proveedor' => $compra['com_proveedor'] ?? $filaErp['proveedor_codigo'],
            'prov_tipo' => $compra['com_tipo'] ?? $filaErp['abreviatura'],
            'prov_letra' => $compra['com_letra'] ?? $filaErp['letra'],
            'prov_sucursal' => $compra['com_sucursal'] ?? $filaErp['sucursal'],
            'prov_nro' => $compra['com_nro'] ?? $filaErp['numero'],
            'prov_fecha' => $compra['com_fecha'] ?? '',
            'prov_fecha_vto' => $compra['com_fecha'] ?? '',
            'prov_monto' => $monto,
            // Sin promov real asumimos saldada en Anita (pase de nativas pagadas).
            'prov_t_pagado' => $monto,
            'prov_cod_mon' => $compra['com_cod_mon'] ?? 1,
            'prov_cotizacion' => $compra['com_cotizacion'] ?? 1,
            'prov_nro_cuota' => 1,
            'prov_nro_interno' => $compra['com_nro_interno'] ?? 0,
            'prov_empresa' => $compra['com_empresa'] ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $prep
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function contabilizarPrep(array $prep, array &$stats): array
    {
        $estado = (string) ($prep['estado'] ?? '');
        if ($estado === 'sin_proveedor') {
            $stats['omitidas_sin_proveedor']++;
            if (! empty($prep['error'])) {
                $stats['errores'][] = $prep['error'];
            }

            return $prep;
        }
        if ($estado === 'sin_tipo') {
            $stats['omitidas_sin_tipo']++;
            if (! empty($prep['error'])) {
                $stats['errores'][] = $prep['error'];
            }

            return $prep;
        }
        if ($estado === 'empresa_fuera') {
            $stats['omitidas_empresa']++;

            return $prep;
        }
        if ($estado === 'ok_al_dia') {
            $stats['omitidas_al_dia']++;

            return $prep;
        }
        if ($estado !== 'ok') {
            $stats['errores'][] = $prep['error'] ?? 'Error';
        }

        return $prep;
    }

    /**
     * @param  array<string, mixed>  $promov
     * @param  array<string, mixed>  $compra
     * @param  array<string, mixed>  $perfil
     * @param  list<int>|null  $empresasPermitidas
     * @return array<string, mixed>
     */
    private function preparar(
        array $promov,
        array $compra,
        array $perfil,
        ?array $empresasPermitidas,
    ): array {
        $clave = ComprobanteProveedorAnitaImportClaveSupport::claveDesdePromov($promov);
        $tipoAbrev = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) ($promov['prov_tipo'] ?? ''));
        $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($promov['prov_letra'] ?? ''));
        $suc = (int) ($promov['prov_sucursal'] ?? 0);
        $nro = (int) ($promov['prov_nro'] ?? 0);
        $cuotaNro = max(1, (int) ($promov['prov_nro_cuota'] ?? 1));
        $etiqueta = ComprobanteProveedorAnitaImportClaveSupport::etiqueta($tipoAbrev, $letra, $suc, $nro);

        $proveedor = $this->resolverProveedor((string) ($promov['prov_proveedor'] ?? ''));
        if ($proveedor === null) {
            return [
                'estado' => 'sin_proveedor',
                'error' => 'Proveedor Anita '.($promov['prov_proveedor'] ?? '').' no está en ERP ('.$etiqueta.')',
            ];
        }

        $tipo = $this->resolverTipo($tipoAbrev, permitirStub: true);
        if ($tipo === null) {
            return ['estado' => 'sin_tipo', 'error' => 'Sin tipotransaccion_compra '.$tipoAbrev.' para '.$etiqueta];
        }

        $empresaId = $perfil['empresa_id_default'];
        if ($perfil['tiene_empresa']) {
            $empAnita = (int) ($compra['com_empresa'] ?? $promov['prov_empresa'] ?? 0);
            if ($empAnita > 0) {
                $empresaId = $this->mapEmpresaId($empAnita) ?? $empresaId;
            }
        }
        if ($empresasPermitidas !== null && ! in_array($empresaId, $empresasPermitidas, true)) {
            return ['estado' => 'empresa_fuera'];
        }

        $monto = round(abs((float) ($promov['prov_monto'] ?? $compra['com_monto'] ?? 0)), 4);
        $pagado = round(abs((float) ($promov['prov_t_pagado'] ?? 0)), 4);
        $pendienteAbs = round(max(0, $monto - $pagado), 4);
        $signo = ((string) $tipo->signo === 'R') ? -1 : 1;
        // CC nueva = monto original; el residual lo dan las aplicaciones aplmovp.
        $totalFirmado = round($monto * $signo, 4);
        $pendienteFirmado = round($pendienteAbs * $signo, 4);
        $fecha = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($promov['prov_fecha'] ?? $compra['com_fecha'] ?? '');
        $fechaIva = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($compra['com_fecha_iva'] ?? '') ?: $fecha;
        $fechaVto = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($promov['prov_fecha_vto'] ?? '') ?: $fecha;
        if ($fecha === '') {
            return ['estado' => 'sin_fecha', 'error' => 'Fecha inválida '.$etiqueta];
        }

        $cpQuery = Comprobante_Proveedor::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('tipotransaccion_compra_id', $tipo->id)
            ->where('letra', $letra)
            ->where('sucursal', $suc)
            ->where('numerocomprobante', $nro);
        if ($perfil['tiene_empresa']) {
            $cpQuery->where('empresa_id', $empresaId);
        }
        $cp = $cpQuery->first();

        $cuit = ComprobanteProveedorAnitaImportClaveSupport::cuitDigitos((string) ($compra['com_cuit_prov'] ?? ''));
        if ($cp === null && $cuit === '') {
            $cuit = ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos(
                is_string($proveedor->nroinscripcion) ? $proveedor->nroinscripcion : null
            );
        }
        if ($cp === null && $cuit !== '' && $tipo->id) {
            $cp = ComprobanteProveedorUnicidadSupport::findDuplicadoPorAfip(
                $empresaId,
                ComprobanteProveedorUnicidadSupport::codigoAfipDesdeTipoId((int) $tipo->id),
                $letra,
                $suc,
                $nro,
                $cuit,
            );
        }

        $esNativo = $cp !== null && ComprobanteProveedorAnitaImportOrigenSupport::esNativo(
            is_string($cp->origen_entrada) ? $cp->origen_entrada : null
        );

        $cc = null;
        if ($cp) {
            $ccs = ProveedorCuentacorrienteAnitaImportCcGuardSupport::soloDeudaDocumento(
                Proveedor_Cuentacorriente::query()
                    ->where('comprobante_proveedor_id', $cp->id)
            )
                ->orderBy('id')
                ->get();
            $cc = $ccs->count() === 1
                ? $ccs->first()
                : ($ccs->first(static function ($row) use ($pendienteAbs, $monto) {
                    $abs = abs((float) $row->total);

                    return abs($abs - $pendienteAbs) < 0.02 || abs($abs - $monto) < 0.02;
                }) ?? $ccs->values()->get($cuotaNro - 1) ?? $ccs->first());
        }

        $aplicadoErpFirmado = $cc
            ? round((float) Proveedor_Cuentacorriente_Aplicacion::query()
                ->where('proveedor_cuentacorriente_id', $cc->id)
                ->sum('total'), 4)
            : 0.0;
        $tol = (float) $perfil['tolerancia_aplicado'];
        $saldoErp = $cc ? round((float) $cc->total + $aplicadoErpFirmado, 4) : null;

        $accionCp = $cp ? 'existente' : 'crear';
        $accionCc = $cc ? 'existente' : 'crear';
        $accionSaldo = 'omitir';
        $accionApl = 'omitir';

        if ($esNativo) {
            // Nunca pisar CP/CC nativos: solo aplicaciones faltantes.
            $accionSaldo = 'omitir';
            $faltaApps = abs(abs($aplicadoErpFirmado) - $pagado) > $tol
                || ($saldoErp !== null && abs($saldoErp - $pendienteFirmado) > $tol);
            if ($faltaApps) {
                $accionApl = 'importar';
            }
            if ($accionCc === 'existente' && $accionApl === 'omitir') {
                return ['estado' => 'ok_al_dia'];
            }
            if ($accionCc === 'crear') {
                // CC faltante sobre nativa: monto del CP (no residual).
                $totalFirmado = round(abs((float) ($cp->total ?? $monto)) * $signo, 4);
                $accionApl = 'importar';
            }
        } elseif ($cp === null) {
            $accionApl = $pagado > $tol ? 'importar' : 'importar';
        } else {
            // ANITA_IMPORT existente.
            if ($cc && $saldoErp !== null && abs($saldoErp - $pendienteFirmado) <= $tol) {
                return ['estado' => 'ok_al_dia'];
            }
            if ($cc) {
                $ccEsResidual = abs(abs((float) $cc->total) - $pendienteAbs) <= $tol
                    && abs($aplicadoErpFirmado) <= $tol;
                $ccEsMonto = abs(abs((float) $cc->total) - $monto) <= $tol;
                if ($ccEsResidual) {
                    // Ferli-style residual ya refleja deuda Anita: al día.
                    return ['estado' => 'ok_al_dia'];
                }
                if (ProveedorCuentacorrienteAnitaImportCcGuardSupport::tieneAplicacionOperativa((int) $cc->id)) {
                    // Ya aplicada por OP ERP: no alinear ni colapsar apps.
                    if ($pagado > abs($aplicadoErpFirmado) + $tol) {
                        $accionApl = 'importar';
                    } else {
                        return ['estado' => 'ok_al_dia'];
                    }
                } elseif (! $ccEsMonto || abs($saldoErp - $pendienteFirmado) > $tol) {
                    // Piso residual = pendiente Anita (aplmovp incompleto o apps de más).
                    $accionSaldo = 'alinear';
                    $accionApl = 'omitir';
                } elseif ($pagado > abs($aplicadoErpFirmado) + $tol) {
                    $accionApl = 'importar';
                } else {
                    return ['estado' => 'ok_al_dia'];
                }
            } else {
                $accionApl = 'importar';
            }
        }

        $monedaId = RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita(
            $promov['prov_cod_mon'] ?? $compra['com_cod_mon'] ?? 1
        );
        $cotizacion = (float) ($promov['prov_cotizacion'] ?? $compra['com_cotizacion'] ?? 1) ?: 1.0;

        return [
            'estado' => 'ok',
            'kind' => 'deuda',
            'clave' => $clave,
            'promov' => $promov,
            'compra' => $compra,
            'proveedor' => $proveedor,
            'tipo' => $tipo,
            'empresa_id' => $empresaId,
            'cp_id' => $cp?->id,
            'cc_id' => $cc?->id,
            'es_nativo' => $esNativo,
            'accion_cp' => $accionCp,
            'accion_cc' => $accionCc,
            'accion_apl' => $accionApl,
            'accion_saldo' => $accionSaldo,
            'fecha' => $fecha,
            'fechaiva' => $fechaIva,
            'fechavencimiento' => $fechaVto,
            'letra' => $letra,
            'sucursal' => $suc,
            'numero' => $nro,
            'cuota' => $cuotaNro,
            'total' => $totalFirmado,
            'monto_abs' => $monto,
            'pagado_objetivo' => $pagado,
            'pendiente_anita' => $pendienteFirmado,
            'aplicado_erp' => abs($aplicadoErpFirmado),
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'nro_interno' => (int) ($compra['com_nro_interno'] ?? $promov['prov_nro_interno'] ?? 0),
            'cuit' => $cuit,
            'resumen' => [
                'etiqueta' => $etiqueta,
                'proveedor' => (string) $proveedor->codigo,
                'empresa_id' => $empresaId,
                'empresa_anita' => (int) ($compra['com_empresa'] ?? $promov['prov_empresa'] ?? 0),
                'fecha' => $fecha,
                'total' => $totalFirmado,
                'pagado_anita' => $pagado,
                'aplicado_erp' => abs($aplicadoErpFirmado),
                'accion_cp' => $accionCp,
                'accion_cc' => $accionCc,
                'accion_apl' => $accionApl === 'importar'
                    ? ($esNativo ? 'nativa_apps' : 'importar_apps')
                    : ($accionSaldo === 'alinear' ? 'alinear' : 'omitir'),
                'es_nativo' => $esNativo,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $adelanto
     * @param  array<string, mixed>  $perfil
     * @param  list<int>|null  $empresasPermitidas
     * @return array<string, mixed>
     */
    private function prepararOpa(array $adelanto, array $perfil, ?array $empresasPermitidas): array
    {
        $clave = (string) $adelanto['clave'];
        $parts = explode('|', $clave);
        $proveedorCodigo = (string) ($parts[0] ?? '');
        $etiqueta = (string) ($adelanto['etiqueta'] ?? '');
        $pendiente = round((float) ($adelanto['pendiente'] ?? 0), 4);

        $proveedor = $this->resolverProveedor($proveedorCodigo);
        if ($proveedor === null) {
            return [
                'estado' => 'sin_proveedor',
                'error' => 'Proveedor Anita '.$proveedorCodigo.' no está en ERP ('.$etiqueta.')',
            ];
        }

        if ($this->resolverTipoCajaId((string) ($adelanto['tipo'] ?? 'OPA')) === null) {
            return ['estado' => 'sin_tipo', 'error' => 'Sin tipotransaccion_caja OPA/OPP para '.$etiqueta];
        }

        if ($pendiente < 0.009 || (string) ($adelanto['fecha'] ?? '') === '') {
            return ['estado' => 'ok_al_dia'];
        }

        $empresaId = $perfil['empresa_id_default'];
        $empAnita = (int) ($adelanto['empresa_codigo'] ?? 0);
        if ($perfil['tiene_empresa'] && $empAnita > 0) {
            $empresaId = $this->mapEmpresaId($empAnita) ?? $empresaId;
        }
        if ($empresasPermitidas !== null && ! in_array($empresaId, $empresasPermitidas, true)) {
            return ['estado' => 'empresa_fuera'];
        }

        $pago = Pagoproveedor::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('empresa_id', $empresaId)
            ->where('tipocomprobante', (string) $adelanto['tipo'])
            ->where('letra', (string) $adelanto['letra'])
            ->where('sucursal', (int) $adelanto['sucursal'])
            ->where('numerotransaccion', (string) $adelanto['numero'])
            ->first();

        $cc = null;
        if ($pago) {
            $cc = Proveedor_Cuentacorriente::query()
                ->where('pagoproveedor_id', $pago->id)
                ->whereNull('comprobante_proveedor_id')
                ->orderBy('id')
                ->first();
        }

        $accionPago = $pago ? 'existente' : 'crear';
        $accionCc = $cc ? 'existente' : 'crear';
        $accionSaldo = 'omitir';
        $tol = (float) $perfil['tolerancia_aplicado'];
        $objetivo = round(-$pendiente, 4);

        if ($cc !== null) {
            $aplicado = round((float) Proveedor_Cuentacorriente_Aplicacion::query()
                ->where('proveedor_cuentacorriente_id', $cc->id)
                ->sum('total'), 4);
            $residual = round((float) $cc->total + $aplicado, 4);
            if (abs($residual - $objetivo) <= $tol && $accionPago === 'existente') {
                return ['estado' => 'ok_al_dia'];
            }
            if (abs($residual - $objetivo) > $tol) {
                $accionSaldo = 'alinear';
            } elseif ($accionPago === 'existente' && $accionCc === 'existente') {
                return ['estado' => 'ok_al_dia'];
            }
        }

        $monedaId = RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita(
            $adelanto['moneda_anita'] ?? 1
        );
        $cotizacion = (float) ($adelanto['cotizacion'] ?? 1) ?: 1.0;
        $fecha = (string) $adelanto['fecha'];
        $fechaVto = (string) ($adelanto['fechavencimiento'] ?: $fecha);

        return [
            'estado' => 'ok',
            'kind' => 'opa',
            'clave' => $clave,
            'adelanto' => $adelanto,
            'proveedor' => $proveedor,
            'empresa_id' => $empresaId,
            'pago_id' => $pago?->id,
            'cc_id' => $cc?->id,
            'accion_pago' => $accionPago,
            'accion_cc' => $accionCc,
            'accion_saldo' => $accionSaldo,
            'fecha' => $fecha,
            'fechavencimiento' => $fechaVto,
            'letra' => (string) $adelanto['letra'],
            'sucursal' => (int) $adelanto['sucursal'],
            'numero' => (int) $adelanto['numero'],
            'tipo' => (string) $adelanto['tipo'],
            'pendiente' => $pendiente,
            'total' => $objetivo,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'resumen' => [
                'etiqueta' => $etiqueta,
                'proveedor' => (string) $proveedor->codigo,
                'empresa_id' => $empresaId,
                'empresa_anita' => $empAnita,
                'fecha' => $fecha,
                'total' => $objetivo,
                'pagado_anita' => round((float) ($adelanto['pagado'] ?? 0), 4),
                'aplicado_erp' => 0.0,
                'accion_cp' => $accionPago,
                'accion_cc' => $accionCc === 'crear' ? 'crear' : ($accionSaldo === 'alinear' ? 'alinear' : $accionCc),
                'accion_apl' => 'omitir',
                'kind' => 'opa',
                'es_nativo' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, list<array{id:int,saldo:float,moneda_id:int,empresa_id:int,comprobante_id:?int}>>  $ccPorClave
     * @return array{cp_creado:bool,cc_creada:bool,saldo_alineado:bool,errores:list<string>}
     */
    private function persistirItem(array $item, int $usuarioId, array &$ccPorClave): array
    {
        $out = [
            'cp_creado' => false,
            'cc_creada' => false,
            'saldo_alineado' => false,
            'errores' => [],
        ];

        /** @var Proveedor $proveedor */
        $proveedor = $item['proveedor'];
        /** @var Tipotransaccion_Compra $tipo */
        $tipo = $item['tipo'];
        $esNativo = ! empty($item['es_nativo']);

        $cpId = $item['cp_id'] ? (int) $item['cp_id'] : null;
        if ($item['accion_cp'] === 'crear') {
            $cpExistente = $this->buscarCpExistenteAlPersistir($item, $proveedor, $tipo);
            if ($cpExistente !== null) {
                $cpId = (int) $cpExistente->id;
            } else {
                $datosCp = [
                    'empresa_id' => $item['empresa_id'],
                    'proveedor_id' => $proveedor->id,
                    'tipotransaccion_compra_id' => $tipo->id,
                    'letra' => $item['letra'],
                    'sucursal' => $item['sucursal'],
                    'numerocomprobante' => $item['numero'],
                    'fechacomprobante' => $item['fecha'],
                    'fechaiva' => $item['fechaiva'],
                    'fechavencimiento' => $item['fechavencimiento'],
                    'subtotal' => $item['monto_abs'],
                    'total' => $item['monto_abs'],
                    'moneda_id' => $item['moneda_id'],
                    'cotizacion' => $item['cotizacion'],
                    'leyenda' => mb_substr(trim((string) ($item['compra']['com_leyenda'] ?? '')), 0, 255) ?: 'Importado Anita (deuda CC)',
                    'modo_carga' => ComprobanteProveedorModoCarga::SIN_RECEPCION,
                    'origen_entrada' => ComprobanteProveedorOrigenEntrada::ANITA_IMPORT,
                    'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
                    'identificacion_proveedor_cuit' => $item['cuit'] !== '' ? $item['cuit'] : null,
                    'anita_nro_interno' => $item['nro_interno'] > 0 ? $item['nro_interno'] : null,
                    'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::IMPORTADO,
                    'anita_sync_at' => now(),
                    'creousuario_id' => $usuarioId,
                ];
                if (Schema::hasColumn('comprobante_proveedor', 'provincia_destino_id')) {
                    $datosCp['provincia_destino_id'] = ComprobanteProveedorProvinciaDestinoSupport::DEFAULT_PROVINCIA_ID;
                }
                try {
                    $cp = Comprobante_Proveedor::query()->create($datosCp);
                    Comprobante_Proveedor_Cuota::query()->firstOrCreate(
                        [
                            'comprobante_proveedor_id' => $cp->id,
                            'numero_cuota' => $item['cuota'],
                        ],
                        [
                            'fechavencimiento' => $item['fechavencimiento'],
                            'monto' => $item['monto_abs'],
                            'moneda_id' => $item['moneda_id'],
                            'cotizacion' => $item['cotizacion'],
                            'formapago_id' => (int) config('comprobante_proveedor.import_anita.formapago_id', 1),
                            'total_pagado' => 0,
                        ]
                    );
                    $cpId = (int) $cp->id;
                    $out['cp_creado'] = true;
                } catch (Throwable $e) {
                    if (! DbContencionSupport::esViolacionUnicidad($e)) {
                        throw $e;
                    }
                    $cpExistente = $this->buscarCpExistenteAlPersistir($item, $proveedor, $tipo);
                    if ($cpExistente === null) {
                        throw $e;
                    }
                    $cpId = (int) $cpExistente->id;
                    $out['errores'][] = 'CP duplicado reutilizado '.$item['resumen']['etiqueta'].' id='.$cpId;
                }
            }
        }

        $ccId = $item['cc_id'] ? (int) $item['cc_id'] : null;
        if ($item['accion_cc'] === 'crear') {
            $ccExistente = null;
            if ($cpId) {
                $ccExistente = ProveedorCuentacorrienteAnitaImportCcGuardSupport::soloDeudaDocumento(
                    Proveedor_Cuentacorriente::query()
                        ->where('comprobante_proveedor_id', $cpId)
                )
                    ->orderBy('id')
                    ->first();
            }
            if ($ccExistente !== null) {
                $ccId = (int) $ccExistente->id;
            } else {
                $cc = Proveedor_Cuentacorriente::query()->create([
                    'fecha' => $item['fecha'],
                    'fechavencimiento' => $item['fechavencimiento'],
                    'proveedor_id' => $proveedor->id,
                    'total' => $item['total'],
                    'moneda_id' => $item['moneda_id'],
                    'cotizacion' => $item['cotizacion'],
                    'comprobante_proveedor_id' => $cpId,
                    'empresa_id' => $item['empresa_id'],
                ]);
                $ccId = (int) $cc->id;
                $out['cc_creada'] = true;
                if ($cpId) {
                    Comprobante_Proveedor_Cuota::query()
                        ->where('comprobante_proveedor_id', $cpId)
                        ->where('numero_cuota', $item['cuota'])
                        ->update(['proveedor_cuentacorriente_id' => $ccId]);
                }
            }
        } elseif ($ccId && ($item['accion_saldo'] ?? '') === 'alinear' && ! $esNativo) {
            $cc = Proveedor_Cuentacorriente::query()->find($ccId);
            if ($cc) {
                // Residual Anita (no el monto bruto). Guard: no tocar OP ni apps operativas.
                $objetivo = array_key_exists('pendiente_anita', $item)
                    ? (float) $item['pendiente_anita']
                    : (float) $item['total'];
                if (ProveedorCuentacorrienteAnitaImportCcGuardSupport::alinearTotalDeudaDocumento($cc, $objetivo)) {
                    $out['saldo_alineado'] = true;
                }
            }
        }

        if ($ccId) {
            $ccPorClave[$item['clave']][] = [
                'id' => $ccId,
                'saldo' => abs((float) $item['total']),
                'moneda_id' => (int) $item['moneda_id'],
                'empresa_id' => (int) $item['empresa_id'],
                'comprobante_id' => $cpId,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function buscarCpExistenteAlPersistir(
        array $item,
        Proveedor $proveedor,
        Tipotransaccion_Compra $tipo,
    ): ?Comprobante_Proveedor {
        $cp = Comprobante_Proveedor::query()
            ->where('empresa_id', (int) $item['empresa_id'])
            ->where('proveedor_id', $proveedor->id)
            ->where('tipotransaccion_compra_id', $tipo->id)
            ->where('letra', $item['letra'])
            ->where('sucursal', (int) $item['sucursal'])
            ->where('numerocomprobante', (int) $item['numero'])
            ->first();
        if ($cp !== null) {
            return $cp;
        }

        // Misma clave única física (empresa+tipo_id+letra+suc+nro+cuit).
        $cuit = (string) ($item['cuit'] ?? '');
        if ($cuit === '') {
            $cuit = ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos(
                is_string($proveedor->nroinscripcion) ? $proveedor->nroinscripcion : null
            );
        }
        if ($cuit === '') {
            return null;
        }

        return Comprobante_Proveedor::query()
            ->where('empresa_id', (int) $item['empresa_id'])
            ->where('tipotransaccion_compra_id', $tipo->id)
            ->where('letra', $item['letra'])
            ->where('sucursal', (int) $item['sucursal'])
            ->where('numerocomprobante', (int) $item['numero'])
            ->where('identificacion_proveedor_cuit', $cuit)
            ->first();
    }

    /**
     * Tras aplmovp: si el residual ERP ≠ pendiente Anita en docs ANITA_IMPORT, pisar a residual.
     * Nunca toca CC de pago ni deudas con apps de OP ERP.
     *
     * @param  list<array<string, mixed>>  $plan
     */
    private function alinearResidualesTrasApps(array $plan): int
    {
        $alineados = 0;
        $tol = 0.02;
        foreach ($plan as $item) {
            if (($item['kind'] ?? 'deuda') !== 'deuda' || ! empty($item['es_nativo'])) {
                continue;
            }
            if (! array_key_exists('pendiente_anita', $item)) {
                continue;
            }

            $cc = null;
            $ccId = (int) ($item['cc_id'] ?? 0);
            if ($ccId > 0) {
                $cc = Proveedor_Cuentacorriente::query()->find($ccId);
            }
            if ($cc === null) {
                $cpId = (int) ($item['cp_id'] ?? 0);
                if ($cpId <= 0 && isset($item['proveedor'], $item['tipo'])) {
                    /** @var Proveedor $proveedor */
                    $proveedor = $item['proveedor'];
                    /** @var Tipotransaccion_Compra $tipo */
                    $tipo = $item['tipo'];
                    $cpId = (int) (Comprobante_Proveedor::query()
                        ->where('empresa_id', (int) $item['empresa_id'])
                        ->where('proveedor_id', $proveedor->id)
                        ->where('tipotransaccion_compra_id', $tipo->id)
                        ->where('letra', $item['letra'])
                        ->where('sucursal', (int) $item['sucursal'])
                        ->where('numerocomprobante', (int) $item['numero'])
                        ->value('id') ?: 0);
                }
                if ($cpId > 0) {
                    $cc = ProveedorCuentacorrienteAnitaImportCcGuardSupport::soloDeudaDocumento(
                        Proveedor_Cuentacorriente::query()
                            ->where('comprobante_proveedor_id', $cpId)
                    )
                        ->orderBy('id')
                        ->first();
                }
            }
            if ($cc === null) {
                continue;
            }

            $aplicado = round((float) Proveedor_Cuentacorriente_Aplicacion::query()
                ->where('proveedor_cuentacorriente_id', $cc->id)
                ->sum('total'), 4);
            $residual = round((float) $cc->total + $aplicado, 4);
            $objetivo = round((float) $item['pendiente_anita'], 4);
            if (abs($residual - $objetivo) <= $tol) {
                continue;
            }
            if (ProveedorCuentacorrienteAnitaImportCcGuardSupport::alinearTotalDeudaDocumento($cc, $objetivo)) {
                $alineados++;
            }
        }

        return $alineados;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{pago_creado:bool,cc_creada:bool,cc_id:?int,errores:list<string>}
     */
    private function persistirOpa(array $item, int $usuarioId): array
    {
        $out = [
            'pago_creado' => false,
            'cc_creada' => false,
            'cc_id' => $item['cc_id'] ? (int) $item['cc_id'] : null,
            'errores' => [],
        ];

        /** @var Proveedor $proveedor */
        $proveedor = $item['proveedor'];
        $pendiente = round((float) $item['pendiente'], 4);
        $tipoCajaId = $this->resolverTipoCajaId((string) $item['tipo']);
        if ($tipoCajaId === null) {
            $out['errores'][] = 'Sin tipotransaccion_caja para '.$item['resumen']['etiqueta'];

            return $out;
        }

        $pagoId = $item['pago_id'] ? (int) $item['pago_id'] : null;
        if ($item['accion_pago'] === 'crear') {
            $pago = Pagoproveedor::query()->create([
                'empresa_id' => $item['empresa_id'],
                'tipotransaccion_caja_id' => $tipoCajaId,
                'tipocomprobante' => $item['tipo'],
                'letra' => $item['letra'],
                'sucursal' => $item['sucursal'],
                'numerotransaccion' => (string) $item['numero'],
                'fecha' => $item['fecha'],
                'proveedor_id' => $proveedor->id,
                'detalle' => 'Importado Anita (deuda CC) — OPA sin aplicar',
                'estado' => 'CONFIRMADA',
                'monto' => $pendiente,
                'cotizacion' => $item['cotizacion'],
                'moneda_id' => $item['moneda_id'],
                'modo_cotizacion' => 'dia',
                'usuario_id' => $usuarioId,
            ]);
            Pagoproveedor_Estado::query()->create([
                'pagoproveedor_id' => $pago->id,
                'fecha' => now(),
                'estado' => 'CONFIRMADA',
                'usuario_id' => $usuarioId,
                'observacion' => 'Importado Anita (OPA deuda CC)',
            ]);
            $pagoId = (int) $pago->id;
            $out['pago_creado'] = true;
        }

        if ($pagoId === null) {
            return $out;
        }

        if (($item['accion_saldo'] ?? '') === 'alinear' && $out['cc_id']) {
            $cc = Proveedor_Cuentacorriente::query()->find((int) $out['cc_id']);
            // OPA: la CC tiene pagoproveedor_id a propósito. Solo ajusta el residual;
            // nunca borra apps (pueden vincular la OPA a facturas ERP).
            if ($cc !== null) {
                $cc->total = round(-$pendiente, 4);
                $cc->save();
            }

            return $out;
        }

        if ($item['accion_cc'] !== 'crear') {
            return $out;
        }

        $cc = Proveedor_Cuentacorriente::query()->create([
            'fecha' => $item['fecha'],
            'fechavencimiento' => $item['fechavencimiento'],
            'proveedor_id' => $proveedor->id,
            'total' => -$pendiente,
            'moneda_id' => $item['moneda_id'],
            'cotizacion' => $item['cotizacion'],
            'empresa_id' => $item['empresa_id'],
            'pagoproveedor_id' => $pagoId,
        ]);
        $out['cc_creada'] = true;
        $out['cc_id'] = (int) $cc->id;

        return $out;
    }

    /**
     * @param  array<string, list<array{id:int,saldo:float,moneda_id:int,empresa_id:int,comprobante_id:?int}>>  $ccPorClave
     * @param  list<array<string, mixed>>  $pares
     */
    private function enriquecerCcPorClaveDesdeErp(array &$ccPorClave, array $pares): void
    {
        $claves = [];
        foreach ($pares as $par) {
            $claves[(string) ($par['deuda']['clave'] ?? '')] = true;
            $claves[(string) ($par['credito']['clave'] ?? '')] = true;
        }
        foreach (array_keys($claves) as $clave) {
            if ($clave === '' || isset($ccPorClave[$clave])) {
                continue;
            }
            $parts = explode('|', $clave);
            if (count($parts) < 5) {
                continue;
            }
            [$prov, $tipo, $letra, $suc, $nro] = $parts;
            $proveedor = $this->resolverProveedor($prov);
            if ($proveedor === null) {
                continue;
            }

            $tipoCompra = Tipotransaccion_Compra::query()->where('abreviatura', $tipo)->first();
            if ($tipoCompra) {
                $cp = Comprobante_Proveedor::query()
                    ->where('proveedor_id', $proveedor->id)
                    ->where('tipotransaccion_compra_id', $tipoCompra->id)
                    ->where('letra', $letra)
                    ->where('sucursal', (int) $suc)
                    ->where('numerocomprobante', (int) $nro)
                    ->first();
                if ($cp) {
                    $ccs = ProveedorCuentacorrienteAnitaImportCcGuardSupport::soloDeudaDocumento(
                        Proveedor_Cuentacorriente::query()
                            ->where('comprobante_proveedor_id', $cp->id)
                    )
                        ->orderBy('id')
                        ->get();
                    foreach ($ccs as $cc) {
                        $aplicado = abs((float) Proveedor_Cuentacorriente_Aplicacion::query()
                            ->where('proveedor_cuentacorriente_id', $cc->id)
                            ->sum('total'));
                        $saldoLibre = max(0, round(abs((float) $cc->total) - $aplicado, 4));
                        $ccPorClave[$clave][] = [
                            'id' => (int) $cc->id,
                            'saldo' => $saldoLibre > 0.0001 ? $saldoLibre : abs((float) $cc->total),
                            'moneda_id' => (int) $cc->moneda_id,
                            'empresa_id' => (int) $cc->empresa_id,
                            'comprobante_id' => (int) $cp->id,
                        ];
                    }
                }
            }

            $pago = Pagoproveedor::query()
                ->where('proveedor_id', $proveedor->id)
                ->where('tipocomprobante', $tipo)
                ->where('letra', $letra)
                ->where('sucursal', (int) $suc)
                ->where('numerotransaccion', (string) $nro)
                ->orderBy('id')
                ->first();
            if ($pago) {
                $ccs = Proveedor_Cuentacorriente::query()
                    ->where('pagoproveedor_id', $pago->id)
                    ->orderBy('id')
                    ->get();
                foreach ($ccs as $cc) {
                    $aplicado = abs((float) Proveedor_Cuentacorriente_Aplicacion::query()
                        ->where('proveedor_cuentacorriente_id', $cc->id)
                        ->sum('total'));
                    $saldoLibre = max(0, round(abs((float) $cc->total) - $aplicado, 4));
                    $ccPorClave[$clave][] = [
                        'id' => (int) $cc->id,
                        'saldo' => $saldoLibre > 0.0001 ? $saldoLibre : abs((float) $cc->total),
                        'moneda_id' => (int) $cc->moneda_id,
                        'empresa_id' => (int) $cc->empresa_id,
                        'comprobante_id' => null,
                    ];
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $pares
     * @param  array<string, list<array{id:int,saldo:float,moneda_id:int,empresa_id:int,comprobante_id:?int}>>  $ccPorClave
     * @return array{creadas:int,pagos_sinteticos:int,omitidas:int,errores:list<string>}
     */
    private function persistirAplicaciones(array $pares, array &$ccPorClave): array
    {
        $creadas = 0;
        $pagos = 0;
        $omitidas = 0;
        $errores = [];

        $montoPorCreditoPago = [];
        foreach ($pares as $par) {
            if (! ($par['credito_es_pago'] ?? false)) {
                continue;
            }
            $claveCredito = (string) ($par['credito']['clave'] ?? '');
            if ($claveCredito === '') {
                continue;
            }
            $montoPorCreditoPago[$claveCredito] = round(
                ($montoPorCreditoPago[$claveCredito] ?? 0) + (float) $par['monto'],
                4
            );
        }

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
            if ($credito === null && ($par['credito_es_pago'] ?? false)) {
                $claveCredito = (string) $par['credito']['clave'];
                $montoCc = $montoPorCreditoPago[$claveCredito] ?? $monto;
                $partes = explode('|', $claveCredito);
                $proveedorId = 0;
                if (count($partes) >= 1) {
                    $prov = $this->resolverProveedor((string) $partes[0]);
                    $proveedorId = $prov ? (int) $prov->id : 0;
                }
                if ($proveedorId <= 0) {
                    $omitidas++;

                    continue;
                }
                $credito = $this->crearCcPagoSintetico($par, $deuda, $proveedorId, $montoCc);
                $ccPorClave[$claveCredito][] = $credito;
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
     * @param  array<string, list<array{id:int,saldo:float,moneda_id:int,empresa_id:int,comprobante_id:?int}>>  $ccPorClave
     * @return array{id:int,saldo:float,moneda_id:int,empresa_id:int,comprobante_id:?int,_idx:int}|null
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
     * @param  array<string, list<array{id:int,saldo:float,moneda_id:int,empresa_id:int,comprobante_id:?int}>>  $ccPorClave
     */
    private function consumirCc(array &$ccPorClave, string $clave, int $idx, float $monto): void
    {
        if (! isset($ccPorClave[$clave][$idx])) {
            return;
        }
        $ccPorClave[$clave][$idx]['saldo'] = round($ccPorClave[$clave][$idx]['saldo'] - $monto, 4);
    }

    /**
     * @param  array<string, mixed>  $par
     * @param  array{id:int,saldo:float,moneda_id:int,empresa_id:int,comprobante_id:?int}  $deuda
     * @return array{id:int,saldo:float,moneda_id:int,empresa_id:int,comprobante_id:?int}
     */
    private function crearCcPagoSintetico(array $par, array $deuda, int $proveedorId, float $montoTotalCredito): array
    {
        $credito = $par['credito'] ?? [];
        $pago = $this->buscarPagoproveedorParaCredito($proveedorId, $credito);

        if ($pago !== null) {
            $existente = Proveedor_Cuentacorriente::query()
                ->where('pagoproveedor_id', (int) $pago->id)
                ->orderBy('id')
                ->first();
            if ($existente !== null) {
                $saldo = abs((float) $existente->total);
                $aplicado = abs((float) Proveedor_Cuentacorriente_Aplicacion::query()
                    ->where('proveedor_cuentacorriente_id', (int) $existente->id)
                    ->sum('total'));
                $saldoLibre = max(0, round($saldo - $aplicado, 4));

                return [
                    'id' => (int) $existente->id,
                    'saldo' => $saldoLibre > 0.0001 ? $saldoLibre : $saldo,
                    'moneda_id' => (int) $existente->moneda_id,
                    'empresa_id' => (int) $existente->empresa_id,
                    'comprobante_id' => null,
                ];
            }
        }

        $montoPago = $pago !== null ? abs((float) $pago->monto) : 0.0;
        $monto = round(max($montoPago, abs($montoTotalCredito), abs((float) $par['monto'])), 4);
        $cc = Proveedor_Cuentacorriente::query()->create([
            'fecha' => $par['fecha'],
            'fechavencimiento' => $par['fecha'],
            'proveedor_id' => $proveedorId,
            'total' => -$monto,
            'moneda_id' => $deuda['moneda_id'],
            'cotizacion' => $pago !== null ? ((float) $pago->cotizacion ?: 1) : 1,
            'empresa_id' => $pago !== null ? (int) $pago->empresa_id : $deuda['empresa_id'],
            'pagoproveedor_id' => $pago !== null ? (int) $pago->id : null,
        ]);

        return [
            'id' => (int) $cc->id,
            'saldo' => $monto,
            'moneda_id' => (int) $cc->moneda_id,
            'empresa_id' => (int) $cc->empresa_id,
            'comprobante_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $credito
     */
    private function buscarPagoproveedorParaCredito(int $proveedorId, array $credito): ?Pagoproveedor
    {
        $tipo = trim((string) ($credito['tipo'] ?? ''));
        $numero = (int) ($credito['numero'] ?? 0);
        if ($tipo === '' || $numero <= 0) {
            return null;
        }

        return Pagoproveedor::query()
            ->where('proveedor_id', $proveedorId)
            ->where('tipocomprobante', $tipo)
            ->where('letra', (string) ($credito['letra'] ?? 'A'))
            ->where('sucursal', (int) ($credito['sucursal'] ?? 0))
            ->where('numerotransaccion', (string) $numero)
            ->orderBy('id')
            ->first();
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
     * @return list<int>|null  null = sin filtro extra
     */
    private function empresasPermitidas(?int $empresaId): ?array
    {
        if ($empresaId !== null && $empresaId > 0) {
            return [$empresaId];
        }
        if (EntornoEmpresaSupport::esAgg()) {
            return ComprobanteProveedorAnitaImportOrigenSupport::empresasOperativasAgg();
        }

        return null;
    }

    private function asegurarTiposBasicos(): void
    {
        foreach (self::TIPOS_SEMILLA as $abrev => $data) {
            if (Tipotransaccion_Compra::query()->where('abreviatura', $abrev)->exists()) {
                continue;
            }
            Tipotransaccion_Compra::query()->create([
                'nombre' => $data['nombre'],
                'operacion' => 'L',
                'abreviatura' => $abrev,
                'codigoafip' => $data['codigoafip'],
                'signo' => $data['signo'],
                'subdiario' => 'C',
                'asientocontable' => 'N',
                'retieneiva' => 'N',
                'retieneganancia' => 'N',
                'retieneIIBB' => 'N',
                'estado' => 'A',
            ]);
        }
        $this->cacheTipo = [];
    }

    private function resolverProveedor(string $codigo): ?Proveedor
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        if (array_key_exists($codigo, $this->cacheProveedor)) {
            return $this->cacheProveedor[$codigo];
        }
        $norm = ltrim($codigo, '0');
        if ($norm === '') {
            $norm = '0';
        }
        $p = Proveedor::query()
            ->where(function ($q) use ($norm, $codigo) {
                $q->where('codigo', $norm)
                    ->orWhere('codigo', str_pad($norm, 6, '0', STR_PAD_LEFT))
                    ->orWhere('codigo', $codigo);
            })
            ->first();

        return $this->cacheProveedor[$codigo] = $p;
    }

    private function resolverTipo(string $abrev, bool $permitirStub = false): ?Tipotransaccion_Compra
    {
        if (array_key_exists($abrev, $this->cacheTipo)) {
            return $this->cacheTipo[$abrev];
        }
        $tipo = Tipotransaccion_Compra::query()->where('abreviatura', $abrev)->first();
        if ($tipo === null && $permitirStub && isset(self::TIPOS_SEMILLA[$abrev])) {
            $tipo = new Tipotransaccion_Compra([
                'abreviatura' => $abrev,
                'signo' => self::TIPOS_SEMILLA[$abrev]['signo'],
                'nombre' => self::TIPOS_SEMILLA[$abrev]['nombre'],
            ]);
        }

        return $this->cacheTipo[$abrev] = $tipo;
    }

    private function mapEmpresaId(int $codigoAnita): ?int
    {
        $id = DB::table('empresa')->where('codigo', (string) $codigoAnita)->value('id');

        return $id ? (int) $id : null;
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

    /**
     * @return array<string, string>
     */
    private function mapaSignoTipos(): array
    {
        $out = [];
        foreach (Tipotransaccion_Compra::query()->get(['abreviatura', 'signo']) as $t) {
            $ab = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) $t->abreviatura);
            if ($ab !== '') {
                $out[$ab] = (string) $t->signo;
            }
        }
        foreach (self::TIPOS_SEMILLA as $ab => $data) {
            $out[$ab] ??= $data['signo'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return array<string, mixed>
     */
    private function statsVacios(array $perfil): array
    {
        return [
            'entorno' => $perfil['entorno'],
            'tiene_empresa' => $perfil['tiene_empresa'],
            'anita_promov' => 0,
            'anita_compra' => 0,
            'anita_aplmovp' => 0,
            'aplicaciones_anita' => 0,
            'credito_sin_compra_anita' => 0,
            'omitidas_tipo_no_deuda' => 0,
            'omitidas_saldadas_anita' => 0,
            'omitidas_sin_compra' => 0,
            'omitidas_sin_proveedor' => 0,
            'omitidas_sin_tipo' => 0,
            'omitidas_al_dia' => 0,
            'omitidas_empresa' => 0,
            'nativas_saldadas_anita' => 0,
            'nativas_solo_apps' => 0,
            'a_procesar' => 0,
            'a_crear_cp' => 0,
            'a_crear_opa' => 0,
            'a_crear_cc' => 0,
            'a_colapsar_saldo' => 0,
            'a_alinear_anita_import' => 0,
            'a_actualizar_aplicaciones' => 0,
            'aplicaciones_planificadas' => 0,
            'cp_creados' => 0,
            'opa_creados' => 0,
            'cc_creadas' => 0,
            'saldos_alineados' => 0,
            'saldos_colapsados' => 0,
            'aplicaciones_creadas' => 0,
            'aplicaciones_omitidas' => 0,
            'pagos_sinteticos' => 0,
            'muestra' => [],
            'errores' => [],
            'modo' => '',
            'empresa_id' => null,
            'empresa_anita' => null,
        ];
    }
}
