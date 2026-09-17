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
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportOpaSupport;
use App\Support\Compras\AnitaImport\ProveedorCuentacorrienteAnitaImportBridgeReader;
use App\Support\Compras\AnitaImport\ProveedorCuentacorrienteAnitaImportFormatoSupport;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorProvinciaDestinoSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Support\Facades\DB;

/**
 * Deuda limpia proveedores: promov pendiente + compra Anita → CP/CC con saldo.
 * OPA pendientes (sin compra) → pagoproveedor + CC negativa.
 * No graba aplicaciones de pagos a cuenta: el CC queda con el residual Anita.
 * No escribe Anita. Adapta campos por EMPRESA (Ferli sin *_empresa).
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
        'NCD' => ['nombre' => 'Nota de crédito', 'signo' => 'R', 'codigoafip' => '003'],
        'NCF' => ['nombre' => 'Nota de crédito', 'signo' => 'R', 'codigoafip' => '003'],
        'NCB' => ['nombre' => 'Nota de crédito B', 'signo' => 'R', 'codigoafip' => '008'],
        'NCS' => ['nombre' => 'Nota de crédito', 'signo' => 'R', 'codigoafip' => '003'],
        'NCI' => ['nombre' => 'Nota de crédito interna', 'signo' => 'R', 'codigoafip' => '003'],
        'NCA' => ['nombre' => 'Nota de crédito A', 'signo' => 'R', 'codigoafip' => '003'],
        'NCN' => ['nombre' => 'Nota de crédito N', 'signo' => 'R', 'codigoafip' => '003'],
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
            // Informix a veces trae monto≈pagado con <> por precisión; deuda limpia = residual real.
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
        foreach ($deuda as $promov) {
            $clave = ComprobanteProveedorAnitaImportClaveSupport::claveDesdePromov($promov);
            if (! isset($compras[$clave])) {
                $stats['omitidas_sin_compra']++;

                continue;
            }
            $prep = $this->preparar($promov, $compras[$clave], $perfil);
            if ($prep['estado'] === 'sin_proveedor') {
                $stats['omitidas_sin_proveedor']++;
                if (! empty($prep['error'])) {
                    $stats['errores'][] = $prep['error'];
                }

                continue;
            }
            if ($prep['estado'] === 'sin_tipo') {
                $stats['omitidas_sin_tipo']++;
                if (! empty($prep['error'])) {
                    $stats['errores'][] = $prep['error'];
                }

                continue;
            }
            if ($prep['estado'] === 'ok_al_dia') {
                $stats['omitidas_al_dia']++;

                continue;
            }
            if ($prep['estado'] !== 'ok') {
                $stats['errores'][] = $prep['error'] ?? 'Error';

                continue;
            }
            $plan[] = $prep;
            if ($limite !== null && $limite > 0 && count($plan) >= $limite) {
                break;
            }
        }

        if ($limite === null || $limite <= 0 || count($plan) < $limite) {
            foreach (ComprobanteProveedorAnitaImportOpaSupport::adelantosPendientes($promovsOpa) as $adelanto) {
                $prep = $this->prepararOpa($adelanto, $perfil);
                if ($prep['estado'] === 'sin_proveedor') {
                    $stats['omitidas_sin_proveedor']++;
                    if (! empty($prep['error'])) {
                        $stats['errores'][] = $prep['error'];
                    }

                    continue;
                }
                if ($prep['estado'] === 'sin_tipo') {
                    $stats['omitidas_sin_tipo']++;
                    if (! empty($prep['error'])) {
                        $stats['errores'][] = $prep['error'];
                    }

                    continue;
                }
                if ($prep['estado'] === 'ok_al_dia') {
                    $stats['omitidas_al_dia']++;

                    continue;
                }
                if ($prep['estado'] !== 'ok') {
                    $stats['errores'][] = $prep['error'] ?? 'Error';

                    continue;
                }
                $plan[] = $prep;
                if ($limite !== null && $limite > 0 && count($plan) >= $limite) {
                    break;
                }
            }
        }

        $stats['a_procesar'] = count($plan);
        $stats['a_crear_cp'] = count(array_filter(
            $plan,
            static fn (array $p) => ($p['kind'] ?? 'deuda') === 'deuda' && $p['accion_cp'] === 'crear'
        ));
        $stats['a_crear_opa'] = count(array_filter(
            $plan,
            static fn (array $p) => ($p['kind'] ?? '') === 'opa' && ($p['accion_pago'] ?? '') === 'crear'
        ));
        $stats['a_crear_cc'] = count(array_filter($plan, static fn (array $p) => $p['accion_cc'] === 'crear'));
        $stats['a_colapsar_saldo'] = count(array_filter(
            $plan,
            static fn (array $p) => ($p['kind'] ?? 'deuda') === 'deuda' && ($p['accion_saldo'] ?? '') === 'colapsar'
        ));
        $stats['a_actualizar_aplicaciones'] = 0;
        $muestraLimite = max(1, $muestraLimite);
        $stats['muestra'] = array_map(static fn (array $p) => $p['resumen'], array_slice($plan, 0, $muestraLimite));
        $stats['anita_aplmovp'] = 0;
        $stats['aplicaciones_anita'] = 0;

        if ($dryRun) {
            $stats['modo'] = 'dry-run';

            return $stats;
        }

        return DB::transaction(function () use ($plan, $stats, $perfil, $usuarioId) {
            $ccPorClave = [];
            foreach ($plan as $item) {
                if (($item['kind'] ?? 'deuda') === 'opa') {
                    $res = $this->persistirOpa($item, $usuarioId);
                    $stats['opa_creados'] += $res['pago_creado'] ? 1 : 0;
                    $stats['cc_creadas'] += $res['cc_creada'] ? 1 : 0;
                    foreach ($res['errores'] as $e) {
                        $stats['errores'][] = $e;
                    }

                    continue;
                }
                $res = $this->persistirItem($item, [], $perfil, $usuarioId, $ccPorClave);
                $stats['cp_creados'] += $res['cp_creado'] ? 1 : 0;
                $stats['cc_creadas'] += $res['cc_creada'] ? 1 : 0;
                $stats['saldos_colapsados'] += $res['saldo_colapsado'] ? 1 : 0;
                $stats['aplicaciones_creadas'] += $res['aplicaciones_creadas'];
                $stats['aplicaciones_omitidas'] += $res['aplicaciones_omitidas'];
                foreach ($res['errores'] as $e) {
                    $stats['errores'][] = $e;
                }
            }
            $stats['modo'] = 'ejecutar';

            return $stats;
        });
    }

    /**
     * @param  array<string, mixed>  $promov
     * @param  array<string, mixed>  $compra
     * @param  array<string, mixed>  $perfil
     * @return array<string, mixed>
     */
    private function preparar(array $promov, array $compra, array $perfil): array
    {
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

        $monto = round(abs((float) ($promov['prov_monto'] ?? $compra['com_monto'] ?? 0)), 4);
        $pagado = round(abs((float) ($promov['prov_t_pagado'] ?? 0)), 4);
        $pendienteAbs = round(max(0, $monto - $pagado), 4);
        $signo = ((string) $tipo->signo === 'R') ? -1 : 1;
        // Deuda limpia: CC = saldo Anita (no total factura + aplicaciones de pagos a cuenta).
        $totalFirmado = round($pendienteAbs * $signo, 4);
        $fecha = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($promov['prov_fecha'] ?? $compra['com_fecha'] ?? '');
        $fechaIva = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($compra['com_fecha_iva'] ?? '') ?: $fecha;
        $fechaVto = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($promov['prov_fecha_vto'] ?? '') ?: $fecha;
        if ($fecha === '') {
            return ['estado' => 'sin_fecha', 'error' => 'Fecha inválida '.$etiqueta];
        }

        $cp = Comprobante_Proveedor::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('tipotransaccion_compra_id', $tipo->id)
            ->where('letra', $letra)
            ->where('sucursal', $suc)
            ->where('numerocomprobante', $nro)
            ->first();

        $cc = null;
        if ($cp) {
            $ccs = Proveedor_Cuentacorriente::query()
                ->where('comprobante_proveedor_id', $cp->id)
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

        $accionCp = $cp ? 'existente' : 'crear';
        $accionCc = $cc ? 'existente' : 'crear';
        $accionSaldo = 'omitir';
        if ($cc) {
            $saldoErp = round((float) $cc->total + $aplicadoErpFirmado, 4);
            $necesitaColapsarApps = abs($aplicadoErpFirmado) > $tol;
            $necesitaAjustarTotal = abs((float) $cc->total - $totalFirmado) > $tol
                || abs($saldoErp - $totalFirmado) > $tol;
            if ($necesitaColapsarApps || $necesitaAjustarTotal) {
                $accionSaldo = 'colapsar';
            }
        }

        if ($accionCp === 'existente' && $accionCc === 'existente' && $accionSaldo === 'omitir') {
            return ['estado' => 'ok_al_dia'];
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
            'accion_cp' => $accionCp,
            'accion_cc' => $accionCc,
            'accion_apl' => 'omitir',
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
            'aplicado_erp' => abs($aplicadoErpFirmado),
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'nro_interno' => (int) ($compra['com_nro_interno'] ?? $promov['prov_nro_interno'] ?? 0),
            'cuit' => ComprobanteProveedorAnitaImportClaveSupport::cuitDigitos((string) ($compra['com_cuit_prov'] ?? '')),
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
                'accion_apl' => $accionSaldo === 'colapsar' ? 'colapsar_saldo' : 'omitir',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $adelanto  de ComprobanteProveedorAnitaImportOpaSupport
     * @param  array<string, mixed>  $perfil
     * @return array<string, mixed>
     */
    private function prepararOpa(array $adelanto, array $perfil): array
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
        if ($accionPago === 'existente' && $accionCc === 'existente') {
            return ['estado' => 'ok_al_dia'];
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
            'fecha' => $fecha,
            'fechavencimiento' => $fechaVto,
            'letra' => (string) $adelanto['letra'],
            'sucursal' => (int) $adelanto['sucursal'],
            'numero' => (int) $adelanto['numero'],
            'tipo' => (string) $adelanto['tipo'],
            'pendiente' => $pendiente,
            'total' => -$pendiente,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'resumen' => [
                'etiqueta' => $etiqueta,
                'proveedor' => (string) $proveedor->codigo,
                'empresa_id' => $empresaId,
                'empresa_anita' => $empAnita,
                'fecha' => $fecha,
                'total' => -$pendiente,
                'pagado_anita' => round((float) ($adelanto['pagado'] ?? 0), 4),
                'aplicado_erp' => 0.0,
                'accion_cp' => $accionPago,
                'accion_cc' => $accionCc,
                'accion_apl' => 'omitir',
                'kind' => 'opa',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $pares
     * @param  array<string, mixed>  $perfil
     * @param  array<string, list<array{id:int,saldo:float,moneda_id:int,empresa_id:int,comprobante_id:?int}>>  $ccPorClave
     * @return array{cp_creado:bool,cc_creada:bool,saldo_colapsado:bool,aplicaciones_creadas:int,aplicaciones_omitidas:int,errores:list<string>}
     */
    private function persistirItem(
        array $item,
        array $pares,
        array $perfil,
        int $usuarioId,
        array &$ccPorClave,
    ): array {
        $out = [
            'cp_creado' => false,
            'cc_creada' => false,
            'saldo_colapsado' => false,
            'aplicaciones_creadas' => 0,
            'aplicaciones_omitidas' => 0,
            'errores' => [],
        ];

        /** @var Proveedor $proveedor */
        $proveedor = $item['proveedor'];
        /** @var Tipotransaccion_Compra $tipo */
        $tipo = $item['tipo'];

        $cpId = $item['cp_id'] ? (int) $item['cp_id'] : null;
        if ($item['accion_cp'] === 'crear') {
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
            if (\Illuminate\Support\Facades\Schema::hasColumn('comprobante_proveedor', 'provincia_destino_id')) {
                $datosCp['provincia_destino_id'] = ComprobanteProveedorProvinciaDestinoSupport::DEFAULT_PROVINCIA_ID;
            }
            $cp = Comprobante_Proveedor::query()->create($datosCp);
            Comprobante_Proveedor_Cuota::query()->create([
                'comprobante_proveedor_id' => $cp->id,
                'numero_cuota' => $item['cuota'],
                'fechavencimiento' => $item['fechavencimiento'],
                'monto' => $item['monto_abs'],
                'moneda_id' => $item['moneda_id'],
                'cotizacion' => $item['cotizacion'],
                'formapago_id' => (int) config('comprobante_proveedor.import_anita.formapago_id', 1),
                'total_pagado' => $item['pagado_objetivo'],
            ]);
            $cpId = (int) $cp->id;
            $out['cp_creado'] = true;
        }

        $ccId = $item['cc_id'] ? (int) $item['cc_id'] : null;
        if ($item['accion_cc'] === 'crear') {
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
        } elseif ($ccId && ($item['accion_saldo'] ?? '') === 'colapsar') {
            $cc = Proveedor_Cuentacorriente::query()->find($ccId);
            if ($cc) {
                $cc->total = $item['total'];
                $cc->save();
                EloquentAuditDeleteSupport::each(
                    Proveedor_Cuentacorriente_Aplicacion::query()
                        ->where('proveedor_cuentacorriente_id', $ccId)
                );
                $out['saldo_colapsado'] = true;
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
     * @return array{pago_creado:bool,cc_creada:bool,errores:list<string>}
     */
    private function persistirOpa(array $item, int $usuarioId): array
    {
        $out = [
            'pago_creado' => false,
            'cc_creada' => false,
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

        if ($pagoId === null || $item['accion_cc'] !== 'crear') {
            return $out;
        }

        Proveedor_Cuentacorriente::query()->create([
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

        return $out;
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
            // Dry-run / planificación: stub no persistido (ERP vacío de tipos).
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
            'a_procesar' => 0,
            'a_crear_cp' => 0,
            'a_crear_opa' => 0,
            'a_crear_cc' => 0,
            'a_colapsar_saldo' => 0,
            'a_actualizar_aplicaciones' => 0,
            'aplicaciones_planificadas' => 0,
            'cp_creados' => 0,
            'opa_creados' => 0,
            'cc_creadas' => 0,
            'saldos_colapsados' => 0,
            'aplicaciones_creadas' => 0,
            'aplicaciones_omitidas' => 0,
            'muestra' => [],
            'errores' => [],
            'modo' => '',
            'empresa_id' => null,
            'empresa_anita' => null,
        ];
    }
}
