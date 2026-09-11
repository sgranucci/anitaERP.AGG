<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor_Comprobante;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Support\Compras\AnitaSync\AplicacionCuentacorriente\AplicacionCuentacorrienteAnitaLadoSupport;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionNumeracionSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Escritura Anita che_ban al emitir IE/OPP (a-movim.c + pago.c / a-tesmov.c):
 * - pago (cabecera, tipo ERP: OPP/ING/EGR/TRA)
 * - auxpag TES + tesmov por cada línea de cuentacaja
 * - TRA: tesmov TED (Debe / entrada) y TEH (Haber / salida) con numerador tctes 314/316,
 *   como a-tesmov.c; auxpag.axp_nro = nro TED/TEH y axp_sucursal 0/1 (D/H)
 * - auxpag TES + tesmov por retenciones RGP/RIP/RSP/RTP (pago.c inserta_valores + graba_auxpag TES)
 * - auxpag FAC por factura aplicada (pago.c graba_auxpag(FAC))
 * - si hay cheques propios emitidos: cpromae + auxpag CHP + tesmov CHP
 */
final class IngresoEgresoAnitaTesmovSupport
{
    public const TIPO_TESMOV_DEBE = 'TED';

    public const TIPO_TESMOV_HABER = 'TEH';

    /** auxpag.axp_sucursal en TRA nativa: 0 = debe (TED), 1 = haber (TEH). */
    private const AXP_SUCURSAL_DEBE = 0;

    private const AXP_SUCURSAL_HABER = 1;

    /** @var array<string, string> imputacion 8 dígitos => tctes_clave */
    private static array $cacheTipoApPorCuenta = [];

    /** @var array<string, array{imputacion: string, desc: string, numero: int}|null> tctes_clave => fila */
    private static array $cacheTctesPorClave = [];

    public static function estaHabilitada(): bool
    {
        return filter_var(
            config('caja.ingresoegreso_anita_tesmov_habilitada', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function sistema(): string
    {
        return (string) config('caja.ingresoegreso_anita_tesmov_sistema', 'che_ban');
    }

    /**
     * Comprobante de anulación en Anita por tipo original. La anulación es el mismo
     * comprobante con importes negativos: conserva el número del original y solo
     * cambia el tipo (OPP 124466 → AOP 124466), como lo graba Anita nativamente.
     *
     * @var array<string, string>
     */
    private const TIPOS_ANULACION = [
        'OPP' => 'AOP',
        'OPA' => 'AOP',
    ];

    public static function grabarDesdeMovimiento(Caja_Movimiento $movimiento): void
    {
        self::grabarInterno($movimiento, 1.0, null, null, null);
    }

    /**
     * Completa auxpag TES + tesmov de retenciones que faltan (RGP/RIP/RSP/RTP).
     * No toca FDT/GPK/CHP ni borra filas existentes.
     *
     * @return list<string> tipos AP insertados
     */
    public static function completarRetencionesTesoreriaDesdeMovimiento(Caja_Movimiento $movimiento): array
    {
        if (! self::estaHabilitada()) {
            return [];
        }

        $movimiento->loadMissing([
            'caja_movimiento_cuentacajas.cuentacajas',
            'tipotransaccioncajas',
            'proveedores',
            'solicitudpagos',
            'cheques.cuentacajas',
            'cheques.proveedores',
            'cheques.chequeras',
        ]);

        $ctx = self::contexto($movimiento);
        if ($ctx === null) {
            return [];
        }
        $ctx['factor'] = 1.0;

        $pagoId = (int) ($movimiento->pagoproveedor_id ?? 0);
        if ($pagoId <= 0) {
            return [];
        }

        $where = ' WHERE axp_tipo = '.self::escSql($ctx['tipo'])
            .' AND axp_rec = '.(int) $ctx['nro']
            .' AND axp_empresa = '.(int) $ctx['empresa'];
        $rawList = (new ApiAnita)->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistema(),
            'tabla' => 'auxpag',
            'campos' => 'axp_tipo_ap',
            'whereArmado' => $where,
        ], 'caja IE auxpag tipos AP '.$movimiento->id);

        $parseado = ApiAnita::parsearRespuestaLista($rawList);
        if ($parseado['error_lectura'] !== null) {
            throw new \RuntimeException(
                'Error al leer auxpag Anita OPP '.$ctx['nro'].': '.$parseado['error_lectura']
            );
        }

        $existentes = [];
        foreach ($parseado['filas'] as $fila) {
            $t = strtoupper(substr(trim((string) ($fila->axp_tipo_ap ?? '')), 0, 3));
            if ($t !== '') {
                $existentes[$t] = true;
            }
        }

        return self::insertAuxpagTesRetenciones($ctx, $pagoId, $existentes);
    }

    /**
     * Anulación contable/tesorería: mismo tipo de tablas que el alta, importes negativos
     * y leyenda "ANULA {tipo} {nro}". Los cheques propios del original se anulan en cpromae.
     */
    public static function grabarAnulacionDesdeMovimiento(
        Caja_Movimiento $movimientoAnulacion,
        Caja_Movimiento $movimientoOriginal
    ): void {
        $movimientoOriginal->loadMissing([
            'tipotransaccioncajas',
            'cheques.cuentacajas',
            'cheques.proveedores',
            'cheques.chequeras',
        ]);
        $tipoOrig = strtoupper(substr(trim((string) ($movimientoOriginal->tipotransaccioncajas->abreviatura ?? 'OPP')), 0, 3));
        if ($tipoOrig === '') {
            $tipoOrig = 'OPP';
        }
        $nroOrig = (int) $movimientoOriginal->numerotransaccion;
        $leyenda = self::recortar('ANULA '.$tipoOrig.' '.$nroOrig, 120);

        $refAnulacion = null;
        if (isset(self::TIPOS_ANULACION[$tipoOrig]) && $nroOrig > 0) {
            $refAnulacion = ['tipo' => self::TIPOS_ANULACION[$tipoOrig], 'nro' => $nroOrig];
        }

        self::grabarInterno($movimientoAnulacion, -1.0, $leyenda, $movimientoOriginal, $refAnulacion);
    }

    /**
     * @param  Caja_Movimiento|null  $movimientoChequesOrigen  cheques a anular en Anita (reversion)
     * @param  array{tipo: string, nro: int}|null  $refOverride  comprobante Anita a forzar
     */
    private static function grabarInterno(
        Caja_Movimiento $movimiento,
        float $factorImporte,
        ?string $leyendaOverride,
        ?Caja_Movimiento $movimientoChequesOrigen,
        ?array $refOverride
    ): void {
        if (! self::estaHabilitada()) {
            return;
        }

        $movimiento->loadMissing([
            'caja_movimiento_cuentacajas.cuentacajas',
            'tipotransaccioncajas',
            'proveedores',
            'solicitudpagos',
            'cheques.cuentacajas',
            'cheques.proveedores',
            'cheques.chequeras',
        ]);

        $ctx = self::contexto($movimiento, $refOverride);
        if ($ctx === null) {
            return;
        }

        $ctx['factor'] = $factorImporte < 0 ? -1.0 : 1.0;
        $ctx['total'] = round(abs((float) $ctx['total']) * $ctx['factor'], 2);
        if ($leyendaOverride !== null && $leyendaOverride !== '') {
            $ctx['detalle'] = $leyendaOverride;
            $ctx['entregadoA'] = self::recortar($leyendaOverride, 30);
        }

        self::insertPago($movimiento, $ctx);

        $esTra = self::esTransferenciaTipo((string) $ctx['tipo']);

        foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
            $cuenta = $linea->cuentacajas;
            $codigoCuenta = $cuenta ? trim((string) $cuenta->codigo) : '';
            if ($codigoCuenta === '') {
                Log::warning('caja.ie.anita.linea_sin_cuenta', [
                    'caja_movimiento_id' => $movimiento->id,
                    'linea_id' => $linea->id,
                ]);

                continue;
            }

            $signed = round((float) $linea->monto * (float) $ctx['factor'], 2);
            $importeAbs = round(abs($signed), 2);
            if ($importeAbs < 0.01) {
                continue;
            }
            $monedaId = (int) ($linea->moneda_id ?: 1);
            $cotizacion = self::cotizacionTesmov($monedaId, (float) ($linea->cotizacion ?: 1));

            if ($esTra) {
                $lado = self::ladoTedTehDesdeImporte($signed);
                $nroTedTeh = self::reservarNumeroTedTeh($lado);
                $importe = $importeAbs;
                $descTesmov = self::descripcionTesmovTedTeh($ctx);
                self::insertAuxpagCuentaCaja(
                    $ctx,
                    $codigoCuenta,
                    $importe,
                    $monedaId,
                    $cotizacion,
                    false,
                    $nroTedTeh,
                    $lado === self::TIPO_TESMOV_DEBE ? self::AXP_SUCURSAL_DEBE : self::AXP_SUCURSAL_HABER
                );
                self::insertTesmovComprobante(
                    $ctx,
                    $codigoCuenta,
                    $importe,
                    $monedaId,
                    $cotizacion,
                    $lado,
                    $nroTedTeh,
                    $descTesmov
                );

                continue;
            }

            $importe = round($importeAbs * (float) $ctx['factor'], 2);
            self::insertAuxpagCuentaCaja(
                $ctx,
                $codigoCuenta,
                $importe,
                $monedaId,
                $cotizacion,
                (int) ($movimiento->pagoproveedor_id ?? 0) > 0
            );
            self::insertTesmovComprobante($ctx, $codigoCuenta, $importe, $monedaId, $cotizacion);
        }

        if ((int) ($movimiento->pagoproveedor_id ?? 0) > 0 && $movimientoChequesOrigen === null) {
            self::insertAuxpagFacturasAplicadas($ctx, (int) $movimiento->pagoproveedor_id, (float) $ctx['factor']);
        }

        $pagoIdRet = (int) ($movimiento->pagoproveedor_id ?? 0);
        if ($movimientoChequesOrigen !== null) {
            $orig = (int) ($movimientoChequesOrigen->pagoproveedor_id ?? 0);
            if ($orig > 0) {
                $pagoIdRet = $orig;
            }
        }
        if ($pagoIdRet > 0) {
            self::insertAuxpagTesRetenciones($ctx, $pagoIdRet, []);
        }

        if ($movimientoChequesOrigen !== null) {
            foreach ($movimientoChequesOrigen->cheques as $cheque) {
                if (strtoupper((string) $cheque->origen) !== 'E') {
                    continue;
                }
                self::anularChequePropioAnita($cheque, $ctx);
            }

            return;
        }

        foreach ($movimiento->cheques as $cheque) {
            if (strtoupper((string) $cheque->origen) !== 'E') {
                continue;
            }
            self::grabarChequePropio($movimiento, $cheque, $ctx);
        }
    }

    public static function eliminarDesdeMovimiento(Caja_Movimiento $movimiento): void
    {
        if (! self::estaHabilitada()) {
            return;
        }

        $movimiento->loadMissing([
            'tipotransaccioncajas',
            'cheques.cuentacajas',
        ]);

        $ctx = self::contexto($movimiento);
        if ($ctx === null) {
            return;
        }

        foreach ($movimiento->cheques as $cheque) {
            if (strtoupper((string) $cheque->origen) !== 'E') {
                continue;
            }
            self::eliminarChequePropio($cheque, $ctx);
        }

        if (self::esTransferenciaTipo((string) $ctx['tipo'])) {
            self::eliminarTesmovTedTehDeTransferencia($ctx, $movimiento->id);
        }

        self::deleteWhere('auxpag', ' WHERE axp_tipo = '.self::escSql($ctx['tipo'])
            .' AND axp_rec = '.(int) $ctx['nro']
            .' AND axp_empresa = '.(int) $ctx['empresa'],
            'caja IE auxpag delete '.$movimiento->id);

        self::deleteWhere('tesmov', ' WHERE tesv_tipo = '.self::escSql($ctx['tipo'])
            .' AND tesv_nro = '.(int) $ctx['nro']
            .' AND tesv_empresa = '.(int) $ctx['empresa'],
            'caja IE tesmov delete '.$movimiento->id);

        self::deleteWhere('pago', ' WHERE pag_tipo = '.self::escSql($ctx['tipo'])
            .' AND pag_rec = '.(int) $ctx['nro']
            .' AND pag_empresa = '.(int) $ctx['empresa'],
            'caja IE pago delete '.$movimiento->id);
    }

    /**
     * Backfill MultiEmpresa: axp_sucursal_cob = sucursal/empresa en líneas de cuenta-caja.
     * No toca CHP: ahí axp_sucursal es el nro de cheque y axp_sucursal_cob es la empresa.
     * pag_sucursal ya lleva nroemp.
     *
     * @return array{
     *   movimiento_id: int,
     *   tipo: string,
     *   nro: int,
     *   empresa: int,
     *   sucursal: int,
     *   filas_anita: int,
     *   filas_a_corregir: int,
     *   filas_actualizadas: int,
     *   omitido: ?string
     * }
     */
    public static function corregirSucursalCobDesdeMovimiento(Caja_Movimiento $movimiento, bool $ejecutar): array
    {
        $movimiento->loadMissing(['tipotransaccioncajas']);
        $base = [
            'movimiento_id' => (int) $movimiento->id,
            'tipo' => '',
            'nro' => 0,
            'empresa' => 0,
            'sucursal' => 0,
            'filas_anita' => 0,
            'filas_a_corregir' => 0,
            'filas_actualizadas' => 0,
            'omitido' => null,
        ];

        if (! self::estaHabilitada()) {
            $base['omitido'] = 'escritura Anita deshabilitada';

            return $base;
        }

        $ctx = self::contexto($movimiento);
        if ($ctx === null) {
            $base['omitido'] = 'sin contexto Anita (tipo/nro)';

            return $base;
        }

        $base['tipo'] = (string) $ctx['tipo'];
        $base['nro'] = (int) $ctx['nro'];
        $base['empresa'] = (int) $ctx['empresa'];
        $base['sucursal'] = (int) $ctx['sucursal'];

        $where = ' WHERE axp_tipo = '.self::escSql($ctx['tipo'])
            .' AND axp_rec = '.(int) $ctx['nro']
            .' AND axp_empresa = '.(int) $ctx['empresa'];

        $rawList = (new ApiAnita)->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistema(),
            'tabla' => 'auxpag',
            'campos' => 'axp_rec,axp_tipo,axp_tipo_ap,axp_sucursal,axp_sucursal_cob,axp_empresa',
            'whereArmado' => $where,
        ], 'caja IE auxpag list sucursal_cob '.$movimiento->id);

        $parseado = ApiAnita::parsearRespuestaLista($rawList);
        if ($parseado['error_lectura'] !== null) {
            throw new \RuntimeException(
                'Error al leer auxpag Anita OPP '.$ctx['nro'].': '.$parseado['error_lectura']
            );
        }

        $aCorregir = 0;
        foreach ($parseado['filas'] as $fila) {
            $tipoAp = strtoupper(substr(trim((string) ($fila->axp_tipo_ap ?? '')), 0, 3));
            if ($tipoAp === 'CHP') {
                continue;
            }
            if ((int) ($fila->axp_sucursal_cob ?? 0) === (int) $ctx['sucursal']) {
                continue;
            }
            $aCorregir++;
        }

        $base['filas_anita'] = count($parseado['filas']);
        $base['filas_a_corregir'] = $aCorregir;

        if (! $ejecutar || $aCorregir === 0) {
            return $base;
        }

        $rawUpd = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'auxpag',
            'acc' => 'update',
            'sistema' => self::sistema(),
            'valores' => 'axp_sucursal_cob = '.(int) $ctx['sucursal'],
            'whereArmado' => $where
                .' AND axp_tipo_ap <> '.self::escSql('CHP')
                .' AND axp_sucursal_cob <> '.(int) $ctx['sucursal'],
        ], 'caja IE auxpag update sucursal_cob '.$movimiento->id);

        $base['filas_actualizadas'] = ApiAnita::extraerFilasAfectadas($rawUpd) ?? $aCorregir;

        return $base;
    }

    /**
     * Pasa tesmov TRA (ambas piernas) a TED/TEH nativos de a-tesmov.c.
     *
     * @return array{
     *   movimiento_id: int,
     *   tipo: string,
     *   nro: int,
     *   empresa: int,
     *   omitido: ?string,
     *   filas: list<array<string, mixed>>,
     *   filas_a_corregir: int,
     *   filas_actualizadas: int
     * }
     */
    public static function corregirTesmovTedTehDesdeMovimiento(Caja_Movimiento $movimiento, bool $ejecutar): array
    {
        $movimiento->loadMissing([
            'caja_movimiento_cuentacajas.cuentacajas',
            'tipotransaccioncajas',
        ]);
        $base = [
            'movimiento_id' => (int) $movimiento->id,
            'tipo' => '',
            'nro' => 0,
            'empresa' => 0,
            'omitido' => null,
            'filas' => [],
            'filas_a_corregir' => 0,
            'filas_actualizadas' => 0,
        ];

        if (! self::estaHabilitada()) {
            $base['omitido'] = 'escritura Anita deshabilitada';

            return $base;
        }

        $ctx = self::contexto($movimiento);
        if ($ctx === null) {
            $base['omitido'] = 'sin contexto Anita (tipo/nro)';

            return $base;
        }

        $base['tipo'] = (string) $ctx['tipo'];
        $base['nro'] = (int) $ctx['nro'];
        $base['empresa'] = (int) $ctx['empresa'];

        if (! self::esTransferenciaTipo((string) $ctx['tipo'])) {
            $base['omitido'] = 'no es TRA';

            return $base;
        }

        $tesmov = self::listarFilasAnita(
            'tesmov',
            'tesv_tipo,tesv_nro,tesv_cuenta,tesv_importe,tesv_desc_mov,tesv_empresa',
            ' WHERE tesv_tipo = '.self::escSql($ctx['tipo'])
                .' AND tesv_nro = '.(int) $ctx['nro']
                .' AND tesv_empresa = '.(int) $ctx['empresa'],
            'caja IE tesmov TRA list '.$movimiento->id
        );

        $tesmovPorCuenta = [];
        foreach ($tesmov as $fila) {
            $cta = self::imputacionTctesDesdeCodigo(trim((string) ($fila->tesv_cuenta ?? '')));
            $tesmovPorCuenta[$cta] = $fila;
        }

        $plan = [];
        foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
            $cuenta = $linea->cuentacajas;
            $codigoCuenta = $cuenta ? trim((string) $cuenta->codigo) : '';
            if ($codigoCuenta === '') {
                continue;
            }
            $signed = round((float) $linea->monto, 2);
            if (abs($signed) < 0.01) {
                continue;
            }
            $cta = self::imputacionTctesDesdeCodigo($codigoCuenta);
            $lado = self::ladoTedTehDesdeImporte($signed);
            $tes = $tesmovPorCuenta[$cta] ?? null;
            if ($tes === null) {
                $plan[] = [
                    'cuenta' => $cta,
                    'lado' => $lado,
                    'skip' => 'sin tesmov TRA de esa cuenta',
                ];

                continue;
            }
            $plan[] = [
                'cuenta' => $cta,
                'lado' => $lado,
                'tesv_tipo_old' => strtoupper(trim((string) ($tes->tesv_tipo ?? ''))),
                'tesv_nro_old' => (int) ($tes->tesv_nro ?? 0),
                'tesv_importe' => (float) ($tes->tesv_importe ?? 0),
                'desc_old' => trim((string) ($tes->tesv_desc_mov ?? '')),
                'desc_new' => self::descripcionTesmovTedTeh($ctx),
                'axp_sucursal_new' => $lado === self::TIPO_TESMOV_DEBE
                    ? self::AXP_SUCURSAL_DEBE
                    : self::AXP_SUCURSAL_HABER,
            ];
        }

        $aCorregir = 0;
        foreach ($plan as $item) {
            if (! isset($item['skip'])) {
                $aCorregir++;
            }
        }
        $base['filas'] = $plan;
        $base['filas_a_corregir'] = $aCorregir;

        if (! $ejecutar || $aCorregir === 0) {
            return $base;
        }

        $actualizadas = 0;
        foreach ($plan as $i => $item) {
            if (isset($item['skip'])) {
                continue;
            }
            $lado = (string) $item['lado'];
            $nroNuevo = self::reservarNumeroTedTeh($lado);
            $cuentaSql = self::escSql($item['cuenta']);
            $whereTes = ' WHERE tesv_tipo = '.self::escSql($ctx['tipo'])
                .' AND tesv_nro = '.(int) $ctx['nro']
                .' AND tesv_cuenta = '.$cuentaSql
                .' AND tesv_empresa = '.(int) $ctx['empresa'];
            $rawTes = (new ApiAnita)->apiCallEscritura([
                'tabla' => 'tesmov',
                'acc' => 'update',
                'sistema' => self::sistema(),
                'valores' => 'tesv_tipo = '.self::escSql($lado)
                    .', tesv_nro = '.(int) $nroNuevo
                    .', tesv_desc_mov = '.self::escSql($item['desc_new']),
                'whereArmado' => $whereTes,
            ], 'caja IE tesmov TRA->'.$lado.' '.$movimiento->id);
            self::assertOk($rawTes, 'tesmov TRA->'.$lado, (int) $ctx['nro']);

            $whereAxp = ' WHERE axp_tipo = '.self::escSql($ctx['tipo'])
                .' AND axp_rec = '.(int) $ctx['nro']
                .' AND axp_banco = '.$cuentaSql
                .' AND axp_empresa = '.(int) $ctx['empresa']
                .' AND axp_tipo_ap <> '.self::escSql('CHP');
            $rawAxp = (new ApiAnita)->apiCallEscritura([
                'tabla' => 'auxpag',
                'acc' => 'update',
                'sistema' => self::sistema(),
                'valores' => 'axp_nro = '.(int) $nroNuevo
                    .', axp_sucursal = '.(int) $item['axp_sucursal_new'],
                'whereArmado' => $whereAxp,
            ], 'caja IE auxpag TRA TED/TEH '.$movimiento->id);
            self::assertOk($rawAxp, 'auxpag TRA TED/TEH', (int) $ctx['nro']);

            $plan[$i]['tesv_nro_new'] = $nroNuevo;
            $actualizadas++;
        }

        $base['filas'] = $plan;
        $base['filas_actualizadas'] = $actualizadas;

        return $base;
    }

    /**
     * @return array{
     *   tipo: string,
     *   nro: int,
     *   empresa: int,
     *   fecha: string,
     *   sucursal: int,
     *   letra: string,
     *   proveedorCodigo: string,
     *   entregadoA: string,
     *   detalle: string,
     *   total: float,
     *   cotizacion: float,
     *   spCodigo: int,
     *   usuario: string
     * }|null
     */
    /** @param  array{tipo: string, nro: int}|null  $refOverride */
    private static function contexto(Caja_Movimiento $movimiento, ?array $refOverride = null): ?array
    {
        $tipo = strtoupper(substr(trim((string) ($movimiento->tipotransaccioncajas->abreviatura ?? '')), 0, 3));
        if ($tipo === '') {
            $tipo = 'OPP';
        }

        $nro = (int) $movimiento->numerotransaccion;

        if ($refOverride !== null) {
            $tipo = strtoupper(trim((string) ($refOverride['tipo'] ?? $tipo)));
            $nro = (int) ($refOverride['nro'] ?? $nro);
        }

        if ($nro <= 0) {
            return null;
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $movimiento->empresa_id);
        if ($empresaAnita <= 0) {
            $empresaAnita = (int) $movimiento->empresa_id;
        }

        // a-movim.c MultiEmpresa: fecha_op / pag_sucursal / tesv_sucursal = nroemp
        $sucursalCfg = config('caja.ingresoegreso_anita_tesmov_sucursal');
        $sucursal = $sucursalCfg === null || $sucursalCfg === ''
            ? $empresaAnita
            : (int) $sucursalCfg;

        $letra = (string) config('caja.ingresoegreso_anita_tesmov_letra', ' ');
        if ($letra === '') {
            $letra = ' ';
        }

        $proveedorCodigo = '000000';
        $entregadoA = '';
        if ($movimiento->proveedores) {
            $proveedorCodigo = str_pad((string) $movimiento->proveedores->codigo, 6, '0', STR_PAD_LEFT);
            $entregadoA = self::recortar((string) ($movimiento->proveedores->nombre ?? ''), 30);
        }

        $total = 0.0;
        $cotizacion = 1.0;
        foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
            $total += abs((float) $linea->monto);
            if ((float) ($linea->cotizacion ?: 0) > 0) {
                $monedaLin = (int) ($linea->moneda_id ?: 1);
                $cotizacion = self::cotizacionTesmov($monedaLin, (float) $linea->cotizacion);
            }
        }
        foreach ($movimiento->cheques as $cheque) {
            if (strtoupper((string) $cheque->origen) === 'E') {
                $total += abs((float) $cheque->monto);
            }
        }

        $spCodigo = 0;
        if ($movimiento->solicitudpagos) {
            $spCodigo = (int) ($movimiento->solicitudpagos->codigo ?? 0);
        }

        $usuario = self::recortar((string) (Auth::user()->nombre ?? Auth::user()->usuario ?? 'ERP'), 8);

        return [
            'tipo' => $tipo,
            'nro' => $nro,
            'empresa' => $empresaAnita,
            'fecha' => date('Ymd', strtotime((string) $movimiento->fecha)),
            'sucursal' => $sucursal,
            'letra' => $letra,
            'proveedorCodigo' => $proveedorCodigo,
            'entregadoA' => $entregadoA !== '' ? $entregadoA : self::recortar((string) ($movimiento->detalle ?? ''), 30),
            'detalle' => self::recortar((string) ($movimiento->detalle ?? 'Movimiento de caja'), 120),
            'total' => round($total, 2),
            'cotizacion' => $cotizacion > 0 ? $cotizacion : 1.0,
            'spCodigo' => $spCodigo,
            'usuario' => $usuario,
            'cbuPago' => self::cbuPagoDesdeMovimiento($movimiento),
        ];
    }

    private static function cbuPagoDesdeMovimiento(Caja_Movimiento $movimiento): string
    {
        $cbu = \App\Support\Compras\ProveedorCbuPagoSupport::cbuDesdeDocumento(
            (int) ($movimiento->proveedor_formapago_id ?? 0) ?: null,
            (string) ($movimiento->cbu_pago ?? ''),
            (int) ($movimiento->proveedor_id ?? 0),
            (string) ($movimiento->detalle ?? '')
        );

        return $cbu;
    }

    /** @param  array<string, mixed>  $ctx */
    private static function insertPago(Caja_Movimiento $movimiento, array $ctx): void
    {
        $tipoVale = ' ';
        $nroVale = 0;
        if ($ctx['spCodigo'] > 0) {
            // a-movim.c SOLPAGO → pag_tipo_vale=SOL + pag_nro_vale=solicitud
            $tipoVale = 'SOL';
            $nroVale = (int) $ctx['spCodigo'];
        }

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'pago',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                pag_pro,
                pag_fecha,
                pag_tipo,
                pag_rec,
                pag_trec,
                pag_cotizacion,
                pag_leyenda,
                pag_entregado_a,
                pag_letra,
                pag_sucursal,
                pag_mov_ext,
                pag_cod_mon_me,
                pag_cobrador,
                pag_sucursal_p,
                pag_recibo_p,
                pag_sin_comision,
                pag_emp_sueldos,
                pag_legajo,
                pag_tipo_vale,
                pag_nro_vale,
                pag_vendedor,
                pag_usuario,
                pag_fecha_ult_act,
                pag_empresa,
                pag_fecha_pago,
                pag_documento_id',
            'valores' => "
                '".$ctx['proveedorCodigo']."',
                '".$ctx['fecha']."',
                '".self::esc($ctx['tipo'])."',
                '".$ctx['nro']."',
                '".$ctx['total']."',
                '".$ctx['cotizacion']."',
                '".self::esc($ctx['detalle'])."',
                '".self::esc($ctx['entregadoA'])."',
                '".self::esc($ctx['letra'])."',
                '".$ctx['sucursal']."',
                'E',
                '2',
                '0',
                '0',
                '0',
                '0',
                '0',
                '0',
                '".self::esc($tipoVale)."',
                '".$nroVale."',
                '0',
                '".self::esc($ctx['usuario'])."',
                '".date('Ymd')."',
                '".$ctx['empresa']."',
                '0',
                '0'",
        ], 'caja IE pago insert '.$movimiento->id);

        self::assertOk($raw, 'pago', $movimiento->id);
    }

    /**
     * Tipo de aplicación auxpag (axp_tipo_ap) desde che_ban.tctes por cuenta de caja.
     * Preferir tctes_numero = '000' (medios sin chequera / caja); si no hay, el primero.
     * Fallback ATE solo si Anita no responde o no hay fila.
     */
    private static function tipoAplicacionPorCuentaCaja(string $codigoCuenta): string
    {
        $imputacion = self::imputacionTctesDesdeCodigo($codigoCuenta);
        if (isset(self::$cacheTipoApPorCuenta[$imputacion])) {
            return self::$cacheTipoApPorCuenta[$imputacion];
        }

        $fallback = 'ATE';
        try {
            $raw = (new ApiAnita)->apiCallEscritura([
                'acc' => 'list',
                'sistema' => self::sistema(),
                'tabla' => 'tctes',
                'campos' => 'tctes_clave,tctes_imputacion,tctes_numero,tctes_desc',
                'whereArmado' => ' WHERE tctes_imputacion = '.self::escSql($imputacion),
            ], 'caja IE tctes por imputacion '.$imputacion);

            $err = ApiAnita::extraerMensajeError($raw);
            if ($err !== null) {
                Log::warning('caja.ie.anita.tctes_error', [
                    'imputacion' => $imputacion,
                    'error' => $err,
                ]);
                self::$cacheTipoApPorCuenta[$imputacion] = $fallback;

                return $fallback;
            }

            $filas = json_decode((string) $raw);
            if (! is_array($filas) || $filas === []) {
                Log::warning('caja.ie.anita.tctes_sin_filas', ['imputacion' => $imputacion]);
                self::$cacheTipoApPorCuenta[$imputacion] = $fallback;

                return $fallback;
            }

            $elegida = null;
            foreach ($filas as $fila) {
                $numero = str_pad(trim((string) ($fila->tctes_numero ?? '')), 3, '0', STR_PAD_LEFT);
                if ($numero === '000') {
                    $elegida = $fila;
                    break;
                }
            }
            if ($elegida === null) {
                $elegida = $filas[0];
            }

            $clave = strtoupper(substr(trim((string) ($elegida->tctes_clave ?? '')), 0, 3));
            if ($clave === '') {
                $clave = $fallback;
            }

            self::$cacheTipoApPorCuenta[$imputacion] = $clave;

            return $clave;
        } catch (\Throwable $e) {
            Log::warning('caja.ie.anita.tctes_exception', [
                'imputacion' => $imputacion,
                'error' => $e->getMessage(),
            ]);
            self::$cacheTipoApPorCuenta[$imputacion] = $fallback;

            return $fallback;
        }
    }

    private static function imputacionTctesDesdeCodigo(string $codigoCuenta): string
    {
        $digits = preg_replace('/\D+/', '', trim($codigoCuenta)) ?? '';
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            $digits = '0';
        }

        return str_pad($digits, 8, '0', STR_PAD_LEFT);
    }

    /** @param  array<string, mixed>  $ctx */
    private static function insertAuxpagCuentaCaja(
        array $ctx,
        string $codigoCuenta,
        float $importe,
        int $monedaId,
        float $cotizacion,
        bool $desdePagoProveedor = false,
        ?int $nroTesmovOverride = null,
        ?int $sucursalAxpOverride = null
    ): void {
        $tipoAp = self::tipoAplicacionPorCuentaCaja($codigoCuenta);
        $imputacion = self::imputacionTctesDesdeCodigo($codigoCuenta);
        // pago.c TES: axp_nro=0, axp_fecha_co=0, letra_comp=letra OP.
        // axp_sucursal_cob = sucursal de la OP (empresa Anita / nroemp). Con 0 Anita no encuentra la OP.
        // TRA (a-tesmov.c): axp_nro = nro TED/TEH; axp_sucursal 0=debe / 1=haber.
        $nroAp = $nroTesmovOverride !== null
            ? $nroTesmovOverride
            : ($desdePagoProveedor ? 0 : (int) $ctx['nro']);
        $fechaCo = $desdePagoProveedor ? '0' : $ctx['fecha'];
        $letraComp = $desdePagoProveedor ? self::esc($ctx['letra']) : ' ';
        $sucursalCob = $desdePagoProveedor ? (int) $ctx['empresa'] : (int) $ctx['sucursal'];
        $sucursalAxp = $sucursalAxpOverride !== null ? $sucursalAxpOverride : (int) $ctx['sucursal'];

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'auxpag',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                axp_pro,
                axp_fecha,
                axp_rec,
                axp_tipo,
                axp_nro,
                axp_tipo_ap,
                axp_monto_ap,
                axp_cod_mon_co,
                axp_fecha_co,
                axp_banco,
                axp_letra_comp,
                axp_sucursal,
                axp_letra_cob,
                axp_sucursal_cob,
                axp_vendedor,
                axp_nro_interno,
                axp_empresa,
                axp_concepto,
                axp_cbu',
            'valores' => "
                '".$ctx['proveedorCodigo']."',
                '".$ctx['fecha']."',
                '".$ctx['nro']."',
                '".self::esc($ctx['tipo'])."',
                '".$nroAp."',
                '".self::esc($tipoAp)."',
                '".$importe."',
                '".$monedaId."',
                '".$fechaCo."',
                '".$imputacion."',
                '".$letraComp."',
                '".$sucursalAxp."',
                '".self::esc($ctx['letra'])."',
                '".$sucursalCob."',
                '0',
                '0',
                '".$ctx['empresa']."',
                '0',
                '".self::esc(self::cbuAnita22((string) ($ctx['cbuPago'] ?? '')))."'",
        ], 'caja IE auxpag '.$tipoAp);

        self::assertOk($raw, 'auxpag '.$tipoAp, (int) $ctx['nro']);
    }

    /**
     * pago.c inserta_valores(RGP/RIP/RSP/RTP) + graba_tesoreria + graba_auxpag(TES):
     * una fila auxpag y una tesmov OPP por tipo, importe = suma de certificados.
     * axp_nro/axp_fecha_co/axp_sucursal = 0; axp_banco = tctes_imputacion.
     *
     * @param  array<string, mixed>  $ctx
     * @param  array<string, true>  $omitirTiposAp  axp_tipo_ap ya presentes (no reinsertar)
     * @return list<string>
     */
    private static function insertAuxpagTesRetenciones(
        array $ctx,
        int $pagoproveedorId,
        array $omitirTiposAp = []
    ): array {
        $retenciones = Pagoproveedor_Retencion::query()
            ->where('pagoproveedor_id', $pagoproveedorId)
            ->orderBy('id')
            ->get();

        $totales = [];
        $monedaId = 1;
        foreach ($retenciones as $ret) {
            $importeAbs = round(abs((float) $ret->importe), 2);
            if ($importeAbs < 0.01) {
                continue;
            }
            $tipoAp = PagoproveedorAnitaRetencionNumeracionSupport::tipoApAuxpag((string) $ret->tiporetencion);
            if ($tipoAp === null) {
                continue;
            }
            $totales[$tipoAp] = ($totales[$tipoAp] ?? 0.0) + $importeAbs;
            if ((int) ($ret->moneda_id ?: 0) > 0) {
                $monedaId = (int) $ret->moneda_id;
            }
        }

        $cotizacion = self::cotizacionTesmov($monedaId, (float) ($ctx['cotizacion'] ?? 1));
        $insertados = [];

        foreach ($totales as $tipoAp => $importeAbs) {
            if (isset($omitirTiposAp[$tipoAp])) {
                continue;
            }
            $tctes = self::tctesPorClave($tipoAp);
            if ($tctes === null) {
                throw new \RuntimeException(
                    'Tipo de tesorería Anita inexistente para retención '.$tipoAp.' (tctes).'
                );
            }
            $imputacion = $tctes['imputacion'];
            if ($imputacion === '' || $imputacion === '00000000') {
                Log::warning('caja.ie.anita.retencion_sin_imputacion', [
                    'tipo_ap' => $tipoAp,
                    'pagoproveedor_id' => $pagoproveedorId,
                ]);

                continue;
            }

            $importe = round($importeAbs * (float) $ctx['factor'], 2);
            self::insertAuxpagTesValor($ctx, $tipoAp, $imputacion, $importe, $monedaId);
            self::insertTesmovComprobante($ctx, $imputacion, $importe, $monedaId, $cotizacion);
            $insertados[] = $tipoAp;
        }

        return $insertados;
    }

    /**
     * pago.c TES (retención): axp_tipo_ap = RGP/RIP/RSP/RTP, axp_nro=0,
     * axp_sucursal=0, axp_sucursal_cob = empresa, axp_banco = tctes_imputacion.
     *
     * @param  array<string, mixed>  $ctx
     */
    private static function insertAuxpagTesValor(
        array $ctx,
        string $tipoAp,
        string $imputacion,
        float $importe,
        int $monedaId
    ): void {
        $letraComp = self::esc($ctx['letra'] ?? ' ');

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'auxpag',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                axp_pro,
                axp_fecha,
                axp_rec,
                axp_tipo,
                axp_nro,
                axp_tipo_ap,
                axp_monto_ap,
                axp_cod_mon_co,
                axp_fecha_co,
                axp_banco,
                axp_letra_comp,
                axp_sucursal,
                axp_letra_cob,
                axp_sucursal_cob,
                axp_vendedor,
                axp_nro_interno,
                axp_empresa,
                axp_concepto,
                axp_cbu',
            'valores' => "
                '".$ctx['proveedorCodigo']."',
                '".$ctx['fecha']."',
                '".$ctx['nro']."',
                '".self::esc($ctx['tipo'])."',
                '0',
                '".self::esc($tipoAp)."',
                '".$importe."',
                '".$monedaId."',
                '0',
                '".self::esc($imputacion)."',
                '".$letraComp."',
                '0',
                '".self::esc($ctx['letra'])."',
                '".$ctx['empresa']."',
                '0',
                '0',
                '".$ctx['empresa']."',
                '0',
                ' '",
        ], 'caja IE auxpag '.$tipoAp);

        self::assertOk($raw, 'auxpag '.$tipoAp, (int) $ctx['nro']);
    }

    /**
     * @return array{imputacion: string, desc: string, numero: int}|null
     */
    private static function tctesPorClave(string $clave): ?array
    {
        $clave = strtoupper(substr(trim($clave), 0, 3));
        if ($clave === '') {
            return null;
        }
        if (array_key_exists($clave, self::$cacheTctesPorClave)) {
            return self::$cacheTctesPorClave[$clave];
        }

        try {
            $raw = (new ApiAnita)->apiCallEscritura([
                'acc' => 'list',
                'sistema' => self::sistema(),
                'tabla' => 'tctes',
                'campos' => 'tctes_clave,tctes_imputacion,tctes_numero,tctes_desc',
                'whereArmado' => ' WHERE tctes_clave = '.self::escSql($clave),
            ], 'caja IE tctes por clave '.$clave);

            $err = ApiAnita::extraerMensajeError($raw);
            if ($err !== null) {
                Log::warning('caja.ie.anita.tctes_clave_error', [
                    'clave' => $clave,
                    'error' => $err,
                ]);
                self::$cacheTctesPorClave[$clave] = null;

                return null;
            }

            $fila = ApiAnita::primeraFilaLista((string) $raw);
            if ($fila === null) {
                self::$cacheTctesPorClave[$clave] = null;

                return null;
            }

            $imputacion = self::imputacionTctesDesdeCodigo(trim((string) ($fila->tctes_imputacion ?? '')));
            $desc = trim((string) ($fila->tctes_desc ?? ''));
            self::$cacheTctesPorClave[$clave] = [
                'imputacion' => $imputacion,
                'desc' => $desc,
                'numero' => (int) ($fila->tctes_numero ?? 0),
            ];

            return self::$cacheTctesPorClave[$clave];
        } catch (\Throwable $e) {
            Log::warning('caja.ie.anita.tctes_clave_exception', [
                'clave' => $clave,
                'error' => $e->getMessage(),
            ]);
            self::$cacheTctesPorClave[$clave] = null;

            return null;
        }
    }

    /**
     * pago.c graba_auxpag(FAC): una fila por comprobante aplicado (FIS/CIS/OPA…).
     * Anita no usa importes negativos: axp_tipo_ap es el tipo del comprobante.
     * axp_nro / letra_comp / axp_sucursal = clave del comprobante,
     * axp_sucursal_cob = sucursal de la OP (empresa Anita), clave MultiEmpresa,
     * axp_banco = nro de cuota (6 dígitos), axp_nro_interno = interno Anita.
     *
     * @param  array<string, mixed>  $ctx
     */
    private static function insertAuxpagFacturasAplicadas(array $ctx, int $pagoproveedorId, float $factor): void
    {
        $lineas = Pagoproveedor_Comprobante::query()
            ->where('pagoproveedor_id', $pagoproveedorId)
            ->orderBy('id')
            ->get();

        foreach ($lineas as $pc) {
            $deuda = Proveedor_Cuentacorriente::query()
                ->with([
                    'proveedores',
                    'empresas',
                    'monedas',
                    'comprobante_proveedores.tipotransaccion_compras',
                    'comprobante_proveedores.monedas',
                    'comprobante_proveedor_cuotas',
                    'pagoproveedores',
                ])
                ->find((int) $pc->proveedor_cuentacorriente_id);
            if ($deuda === null) {
                continue;
            }

            $lado = AplicacionCuentacorrienteAnitaLadoSupport::desdeCc($deuda);
            if ($lado === null) {
                Log::warning('caja.ie.anita.auxpag_fac_sin_clave', [
                    'pagoproveedor_id' => $pagoproveedorId,
                    'cc_id' => $deuda->id,
                ]);

                continue;
            }

            $tipoAp = strtoupper(substr(trim((string) $lado['tipo']), 0, 3));
            if ($tipoAp === 'OPA') {
                $tipoAp = 'APA';
            }

            $monto = round(abs((float) $pc->montoaplicado) * ($factor < 0 ? -1.0 : 1.0), 2);
            if (abs($monto) < 0.01) {
                continue;
            }

            $fechaCo = '0';
            $fechaFac = $deuda->comprobante_proveedores?->fechacomprobante
                ?? $deuda->fecha
                ?? null;
            if ($fechaFac) {
                $fechaCo = date('Ymd', strtotime((string) $fechaFac));
            }

            $cuota = (int) ($lado['nro_cuota'] ?? 1);
            if ($cuota <= 0) {
                $cuota = 1;
            }
            $bancoCuota = str_pad((string) $cuota, 6, '0', STR_PAD_LEFT);

            $codMon = (string) ((int) ($pc->moneda_id ?: $deuda->moneda_id ?: 1));
            $codMon = $codMon !== '' ? substr($codMon, 0, 1) : '1';

            // pago.c arrastra el concepto de cash-flow del comprobante aplicado (com_concepto
            // -> axp_concepto). Sin esto el EFE pierde el rubro cuando no hay cuenta de gasto.
            $conceptoCashflow = (int) (
                $deuda->comprobante_proveedores->conceptogasto_id
                    ?: ($deuda->proveedores->conceptogasto_id ?? 0)
            );

            $raw = (new ApiAnita)->apiCallEscritura([
                'tabla' => 'auxpag',
                'acc' => 'insert',
                'sistema' => self::sistema(),
                'campos' => '
                    axp_pro,
                    axp_fecha,
                    axp_rec,
                    axp_tipo,
                    axp_nro,
                    axp_tipo_ap,
                    axp_monto_ap,
                    axp_cod_mon_co,
                    axp_fecha_co,
                    axp_banco,
                    axp_letra_comp,
                    axp_sucursal,
                    axp_letra_cob,
                    axp_sucursal_cob,
                    axp_vendedor,
                    axp_nro_interno,
                    axp_empresa,
                    axp_concepto,
                    axp_cbu',
                'valores' => "
                    '".$ctx['proveedorCodigo']."',
                    '".$ctx['fecha']."',
                    '".$ctx['nro']."',
                    '".self::esc($ctx['tipo'])."',
                    '".(int) $lado['numero']."',
                    '".self::esc($tipoAp)."',
                    '".$monto."',
                    '".$codMon."',
                    '".$fechaCo."',
                    '".$bancoCuota."',
                    '".self::esc((string) $lado['letra'])."',
                    '".(int) $lado['sucursal']."',
                    '".self::esc($ctx['letra'])."',
                    '".$ctx['empresa']."',
                    '0',
                    '".(int) ($lado['nro_interno'] ?? 0)."',
                    '".$ctx['empresa']."',
                    '".$conceptoCashflow."',
                    ' '",
            ], 'caja IE auxpag FAC '.$lado['etiqueta']);

            self::assertOk($raw, 'auxpag FAC '.$lado['etiqueta'], (int) $ctx['nro']);
        }
    }

    private static function cbuAnita22(string $cbu): string
    {
        $n = preg_replace('/\D+/', '', $cbu) ?? '';
        if (strlen($n) !== 22) {
            return ' ';
        }

        return $n;
    }

    /** @param  array<string, mixed>  $ctx */
    private static function insertTesmovComprobante(
        array $ctx,
        string $codigoCuenta,
        float $importe,
        int $monedaId,
        float $cotizacion,
        ?string $tipoTesmov = null,
        ?int $nroTesmov = null,
        ?string $descTesmov = null
    ): void {
        $tipo = $tipoTesmov !== null && $tipoTesmov !== '' ? $tipoTesmov : (string) $ctx['tipo'];
        $nro = $nroTesmov !== null ? $nroTesmov : (int) $ctx['nro'];
        $desc = $descTesmov !== null && $descTesmov !== ''
            ? $descTesmov
            : self::recortar($ctx['entregadoA'] !== '' ? $ctx['entregadoA'] : $ctx['detalle'], 30);
        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'tesmov',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                tesv_cuenta,
                tesv_fecha_mov,
                tesv_fecha_dev,
                tesv_tipo,
                tesv_letra,
                tesv_sucursal,
                tesv_nro,
                tesv_importe,
                tesv_cotizacion,
                tesv_desc_mov,
                tesv_conciliado,
                tesv_contrapartida,
                tesv_nro_conc,
                tesv_fecha_conc,
                tesv_empresa,
                tesv_cod_mon',
            'valores' => "
                '".str_pad($codigoCuenta, 8, '0', STR_PAD_LEFT)."',
                '".$ctx['fecha']."',
                '".$ctx['fecha']."',
                '".self::esc($tipo)."',
                ' ',
                '".$ctx['sucursal']."',
                '".$nro."',
                '".$importe."',
                '".$cotizacion."',
                '".self::esc($desc)."',
                ' ',
                'S/C',
                '0',
                '0',
                '".$ctx['empresa']."',
                '".$monedaId."'",
        ], 'caja IE tesmov');

        self::assertOk($raw, 'tesmov', (int) $nro);
    }

    /** @param  array<string, mixed>  $ctx */
    private static function grabarChequePropio(Caja_Movimiento $movimiento, Cheque $cheque, array $ctx): void
    {
        $cuenta = $cheque->cuentacajas;
        $codigoCuenta = $cuenta ? trim((string) $cuenta->codigo) : '';
        if ($codigoCuenta === '') {
            Log::warning('caja.ie.anita.chp_sin_cuenta', [
                'caja_movimiento_id' => $movimiento->id,
                'cheque_id' => $cheque->id,
            ]);

            return;
        }

        $nroCheque = (int) preg_replace('/\D/', '', (string) $cheque->numerocheque);
        if ($nroCheque <= 0) {
            $nroCheque = (int) $cheque->numerocheque;
        }
        if ($nroCheque <= 0) {
            return;
        }

        $importe = round(abs((float) $cheque->monto), 2);
        $cotizacion = ChequePropioCpromaeAnitaMapper::cotizacion((float) ($cheque->cotizacion ?? 0));
        $monedaId = (int) ($cheque->moneda_id ?: 1);
        $fechaChe = ChequePropioCpromaeAnitaMapper::ymd((string) ($cheque->fechapago ?: $cheque->fechaemision ?: $movimiento->fecha));
        $fechaEmi = ChequePropioCpromaeAnitaMapper::ymd((string) ($cheque->fechaemision ?: $movimiento->fecha));

        $proveedorCodigo = $ctx['proveedorCodigo'];
        if ($cheque->proveedores) {
            $proveedorCodigo = str_pad((string) $cheque->proveedores->codigo, 6, '0', STR_PAD_LEFT);
        }

        $entregado = self::recortar((string) ($cheque->entregado ?: $ctx['entregadoA']), 30);
        $sucursalesAxp = ChequePropioAuxpagAnitaMapper::sucursales($nroCheque, (int) $ctx['empresa']);
        $filaCpromae = ChequePropioCpromaeAnitaMapper::mapear([
            'cuenta' => $codigoCuenta,
            'nro' => $nroCheque,
            'fecha_emision' => (string) ($cheque->fechaemision ?: $movimiento->fecha),
            'fecha_pago' => (string) ($cheque->fechapago ?: $cheque->fechaemision ?: $movimiento->fecha),
            'importe' => $importe,
            'proveedor' => $proveedorCodigo,
            'entregado' => $entregado,
            'anombrede' => (string) ($cheque->anombrede ?: $entregado),
            'nro_op' => $ctx['nro'],
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'empresa' => $ctx['empresa'],
            'chequera_codigo' => (string) ($cheque->chequeras->codigo ?? '0'),
            'chequera_tipo' => (string) ($cheque->chequeras->tipochequera ?? 'F'),
            'caracter' => (string) ($cheque->caracter ?? ''),
            'para_dep' => (string) ($cheque->para_dep ?? ''),
            'negociable' => (string) ($cheque->negociable ?? ''),
            'nro_echeq' => (string) ($cheque->nro_echeq ?? ''),
            'fecha_entrega' => (string) ($cheque->fecha_entrega ?? ''),
            'sucursal_pago' => (string) ($cheque->sucursalpago ?? ''),
            'tipo_distrib' => (string) ($cheque->tipodistribucion ?? ''),
            'estado_erp' => (string) ($cheque->estado ?? ''),
        ]);
        $camposCpromae = array_keys($filaCpromae);
        $valoresCpromae = [];
        foreach ($filaCpromae as $valor) {
            $valoresCpromae[] = "'".self::esc((string) $valor)."'";
        }

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'cpromae',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => implode(",\n                ", $camposCpromae),
            'valores' => implode(",\n                ", $valoresCpromae),
        ], 'caja IE cpromae '.$cheque->id);
        self::assertOk($raw, 'cpromae', $cheque->id);

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'auxpag',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                axp_pro,
                axp_fecha,
                axp_rec,
                axp_tipo,
                axp_nro,
                axp_tipo_ap,
                axp_monto_ap,
                axp_cod_mon_co,
                axp_fecha_co,
                axp_banco,
                axp_letra_comp,
                axp_sucursal,
                axp_letra_cob,
                axp_sucursal_cob,
                axp_vendedor,
                axp_nro_interno,
                axp_empresa,
                axp_concepto,
                axp_cbu',
            'valores' => "
                '".$proveedorCodigo."',
                '".$ctx['fecha']."',
                '".$ctx['nro']."',
                '".self::esc($ctx['tipo'])."',
                '".$nroCheque."',
                'CHP',
                '".$importe."',
                '".$monedaId."',
                '".$fechaChe."',
                '".str_pad($codigoCuenta, 8, '0', STR_PAD_LEFT)."',
                ' ',
                '".$sucursalesAxp['axp_sucursal']."',
                ' ',
                '".$sucursalesAxp['axp_sucursal_cob']."',
                '0',
                '0',
                '".$ctx['empresa']."',
                '0',
                ' '",
        ], 'caja IE auxpag CHP '.$cheque->id);
        self::assertOk($raw, 'auxpag CHP', $cheque->id);

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'tesmov',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                tesv_cuenta,
                tesv_fecha_mov,
                tesv_fecha_dev,
                tesv_tipo,
                tesv_letra,
                tesv_sucursal,
                tesv_nro,
                tesv_importe,
                tesv_cotizacion,
                tesv_desc_mov,
                tesv_conciliado,
                tesv_contrapartida,
                tesv_nro_conc,
                tesv_fecha_conc,
                tesv_empresa,
                tesv_cod_mon',
            'valores' => "
                '".str_pad($codigoCuenta, 8, '0', STR_PAD_LEFT)."',
                '".$fechaEmi."',
                '".$fechaChe."',
                'CHP',
                ' ',
                '0',
                '".$nroCheque."',
                '".$importe."',
                '".$cotizacion."',
                '".self::esc($entregado)."',
                ' ',
                ' ',
                '0',
                '0',
                '".$ctx['empresa']."',
                '".$monedaId."'",
        ], 'caja IE tesmov CHP '.$cheque->id);
        self::assertOk($raw, 'tesmov CHP', $cheque->id);
    }

    /** @param  array<string, mixed>  $ctx */
    private static function eliminarChequePropio(Cheque $cheque, array $ctx): void
    {
        $cuenta = $cheque->cuentacajas;
        $codigoCuenta = $cuenta ? trim((string) $cuenta->codigo) : '';
        $nroCheque = (int) preg_replace('/\D/', '', (string) $cheque->numerocheque);
        if ($nroCheque <= 0) {
            $nroCheque = (int) $cheque->numerocheque;
        }
        if ($codigoCuenta === '' || $nroCheque <= 0) {
            return;
        }

        $cuentaPad = str_pad($codigoCuenta, 8, '0', STR_PAD_LEFT);

        self::deleteWhere('tesmov', " WHERE tesv_tipo = 'CHP' AND tesv_nro = ".$nroCheque
            .' AND tesv_cuenta = '.self::escSql($cuentaPad)
            .' AND tesv_empresa = '.(int) $ctx['empresa'],
            'caja IE tesmov CHP delete '.$cheque->id);

        self::deleteWhere('cpromae', ' WHERE cpro_cuenta = '.self::escSql($cuentaPad)
            .' AND cpro_nro_cheque = '.$nroCheque,
            'caja IE cpromae delete '.$cheque->id);
    }

    /**
     * Reversión: marca fecha de anulación en cpromae y graba tesmov/auxpag CHP con signo invertido
     * bajo el nro de la OP de anulación.
     *
     * @param  array<string, mixed>  $ctx
     */
    private static function anularChequePropioAnita(Cheque $cheque, array $ctx): void
    {
        $cuenta = $cheque->cuentacajas;
        $codigoCuenta = $cuenta ? trim((string) $cuenta->codigo) : '';
        $nroCheque = (int) preg_replace('/\D/', '', (string) $cheque->numerocheque);
        if ($nroCheque <= 0) {
            $nroCheque = (int) $cheque->numerocheque;
        }
        if ($codigoCuenta === '' || $nroCheque <= 0) {
            return;
        }

        $cuentaPad = str_pad($codigoCuenta, 8, '0', STR_PAD_LEFT);
        $fechaAnula = (string) ($ctx['fecha'] ?? date('Ymd'));
        $importe = round(abs((float) $cheque->monto) * (float) ($ctx['factor'] ?? -1), 2);
        $cotizacion = (float) ($cheque->cotizacion ?: 1);
        $monedaId = (int) ($cheque->moneda_id ?: 1);

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'cpromae',
            'acc' => 'update',
            'sistema' => self::sistema(),
            'valores' => "cpro_fecha_anula = '".$fechaAnula."'",
            'whereArmado' => ' WHERE cpro_cuenta = '.self::escSql($cuentaPad)
                .' AND cpro_nro_cheque = '.$nroCheque,
        ], 'caja IE cpromae anula '.$cheque->id);
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::warning('caja.ie.anita.cpromae_anula_fail', [
                'cheque_id' => $cheque->id,
                'error' => $err,
            ]);
        }

        $proveedorCodigo = $ctx['proveedorCodigo'];
        if ($cheque->proveedores) {
            $proveedorCodigo = str_pad((string) $cheque->proveedores->codigo, 6, '0', STR_PAD_LEFT);
        }

        $sucursalesAxp = ChequePropioAuxpagAnitaMapper::sucursales($nroCheque, (int) $ctx['empresa']);
        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'auxpag',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                axp_pro,
                axp_fecha,
                axp_rec,
                axp_tipo,
                axp_nro,
                axp_tipo_ap,
                axp_monto_ap,
                axp_cod_mon_co,
                axp_fecha_co,
                axp_banco,
                axp_letra_comp,
                axp_sucursal,
                axp_letra_cob,
                axp_sucursal_cob,
                axp_vendedor,
                axp_nro_interno,
                axp_empresa,
                axp_concepto,
                axp_cbu',
            'valores' => "
                '".$proveedorCodigo."',
                '".$ctx['fecha']."',
                '".$ctx['nro']."',
                '".self::esc($ctx['tipo'])."',
                '".$nroCheque."',
                'CHP',
                '".$importe."',
                '".$monedaId."',
                '".$ctx['fecha']."',
                '".$cuentaPad."',
                ' ',
                '".$sucursalesAxp['axp_sucursal']."',
                '".self::esc($ctx['letra'])."',
                '".$sucursalesAxp['axp_sucursal_cob']."',
                '0',
                '0',
                '".$ctx['empresa']."',
                '0',
                ' '",
        ], 'caja IE auxpag CHP anula '.$cheque->id);
        self::assertOk($raw, 'auxpag CHP anula', $cheque->id);

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'tesmov',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                tesv_cuenta,
                tesv_fecha_mov,
                tesv_fecha_dev,
                tesv_tipo,
                tesv_letra,
                tesv_sucursal,
                tesv_nro,
                tesv_importe,
                tesv_cotizacion,
                tesv_desc_mov,
                tesv_conciliado,
                tesv_contrapartida,
                tesv_nro_conc,
                tesv_fecha_conc,
                tesv_empresa,
                tesv_cod_mon',
            'valores' => "
                '".$cuentaPad."',
                '".$ctx['fecha']."',
                '".$ctx['fecha']."',
                'CHP',
                ' ',
                '".$ctx['sucursal']."',
                '".$nroCheque."',
                '".$importe."',
                '".$cotizacion."',
                '".self::esc(self::recortar((string) ($ctx['detalle'] ?? 'ANULA CHP'), 30))."',
                ' ',
                'S/C',
                '0',
                '0',
                '".$ctx['empresa']."',
                '".$monedaId."'",
        ], 'caja IE tesmov CHP anula '.$cheque->id);
        self::assertOk($raw, 'tesmov CHP anula', $cheque->id);
    }

    public static function esTransferenciaTipo(string $tipo): bool
    {
        return strtoupper(substr(trim($tipo), 0, 3)) === IngresoEgresoTransferenciaSupport::ABREV_TRA;
    }

    private static function ladoTedTehDesdeImporte(float $signed): string
    {
        return $signed >= 0 ? self::TIPO_TESMOV_DEBE : self::TIPO_TESMOV_HABER;
    }

    /** @param  array<string, mixed>  $ctx */
    private static function descripcionTesmovTedTeh(array $ctx): string
    {
        $leyenda = (string) ($ctx['entregadoA'] !== '' ? $ctx['entregadoA'] : $ctx['detalle']);

        return self::recortar(trim($ctx['tipo'].' '.$ctx['nro'].' '.$leyenda), 30);
    }

    private static function claveNumeradorTedTeh(string $tipoTedTeh): int
    {
        $tctes = self::tctesPorClave($tipoTedTeh);
        $clave = (int) ($tctes['numero'] ?? 0);
        if ($clave <= 0) {
            throw new \RuntimeException(
                'Tipo de tesorería Anita '.$tipoTedTeh.' sin numerador (tctes_numero).'
            );
        }

        return $clave;
    }

    private static function reservarNumeroTedTeh(string $tipoTedTeh): int
    {
        $clave = self::claveNumeradorTedTeh($tipoTedTeh);
        $lock = Cache::lock('caja:anita:numerador:tedteh:'.$clave, 120);
        if (! $lock->block(90)) {
            throw new \RuntimeException(
                'Otra terminal está numerando tesmov '.$tipoTedTeh.' en Anita. Reintente.'
            );
        }

        try {
            $ultimo = IngresoEgresoAnitaNumeracionSupport::leerUltimoNumero($clave);
            $siguiente = $ultimo + 1;
            IngresoEgresoAnitaNumeracionSupport::actualizarNumerador($clave, $siguiente);

            return $siguiente;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private static function eliminarTesmovTedTehDeTransferencia(array $ctx, int $movimientoId): void
    {
        $filas = self::listarFilasAnita(
            'auxpag',
            'axp_tipo_ap,axp_nro,axp_banco,axp_sucursal',
            ' WHERE axp_tipo = '.self::escSql($ctx['tipo'])
                .' AND axp_rec = '.(int) $ctx['nro']
                .' AND axp_empresa = '.(int) $ctx['empresa'],
            'caja IE auxpag TRA tesmov '.$movimientoId
        );

        foreach ($filas as $fila) {
            $tipoAp = strtoupper(substr(trim((string) ($fila->axp_tipo_ap ?? '')), 0, 3));
            if ($tipoAp === 'CHP') {
                continue;
            }
            $nroTes = (int) ($fila->axp_nro ?? 0);
            $cuenta = trim((string) ($fila->axp_banco ?? ''));
            if ($nroTes <= 0 || $cuenta === '') {
                continue;
            }
            $sucursalAxp = (int) ($fila->axp_sucursal ?? -1);
            $tipos = [];
            if ($sucursalAxp === self::AXP_SUCURSAL_DEBE) {
                $tipos[] = self::TIPO_TESMOV_DEBE;
            } elseif ($sucursalAxp === self::AXP_SUCURSAL_HABER) {
                $tipos[] = self::TIPO_TESMOV_HABER;
            } else {
                $tipos = [self::TIPO_TESMOV_DEBE, self::TIPO_TESMOV_HABER];
            }
            foreach ($tipos as $tipoTes) {
                self::deleteWhere(
                    'tesmov',
                    ' WHERE tesv_tipo = '.self::escSql($tipoTes)
                        .' AND tesv_nro = '.$nroTes
                        .' AND tesv_cuenta = '.self::escSql($cuenta)
                        .' AND tesv_empresa = '.(int) $ctx['empresa'],
                    'caja IE tesmov '.$tipoTes.' delete '.$movimientoId
                );
            }
        }
    }

    /**
     * @return list<object>
     */
    private static function listarFilasAnita(string $tabla, string $campos, string $where, string $contexto): array
    {
        $raw = (new ApiAnita)->apiCallEscritura([
            'acc' => 'list',
            'sistema' => self::sistema(),
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
        ], $contexto);

        $parseado = ApiAnita::parsearRespuestaLista($raw);
        if ($parseado['error_lectura'] !== null) {
            throw new \RuntimeException(
                'Error al leer '.$tabla.' Anita: '.$parseado['error_lectura']
            );
        }

        return $parseado['filas'];
    }

    private static function deleteWhere(string $tabla, string $where, string $contexto): void
    {
        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => $tabla,
            'acc' => 'delete',
            'sistema' => self::sistema(),
            'whereArmado' => $where,
        ], $contexto);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::error('caja.ie.anita.delete_fail', [
                'tabla' => $tabla,
                'contexto' => $contexto,
                'error' => $err,
            ]);
            throw new \RuntimeException('Error al borrar '.$tabla.' Anita: '.$err);
        }
    }

    private static function assertOk(?string $raw, string $tabla, int $refId): void
    {
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::error('caja.ie.anita.insert_fail', [
                'tabla' => $tabla,
                'ref' => $refId,
                'error' => $err,
            ]);
            throw new \RuntimeException('Error al grabar '.$tabla.' Anita: '.$err);
        }
    }

    private static function cotizacionTesmov(int $monedaId, float $cotizacion): float
    {
        // También en MN: si viene TC real (p.ej. DOL del día) se conserva para vistas ME.
        if ($cotizacion > 1.0001) {
            return $cotizacion;
        }

        return $monedaId <= 1 ? 1.0 : ($cotizacion > 0 ? $cotizacion : 1.0);
    }

    private static function recortar(string $valor, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $max);
        }

        return substr($valor, 0, $max);
    }

    private static function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }

    private static function escSql(string $valor): string
    {
        return "'".self::esc($valor)."'";
    }
}
