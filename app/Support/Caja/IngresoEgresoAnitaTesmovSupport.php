<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cheque;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Escritura Anita che_ban al emitir IE/OPP (a-movim.c + pago.c):
 * - pago (cabecera)
 * - auxpag + tesmov por cada línea de cuentacaja (axp_tipo_ap desde tctes por imputación)
 * - si hay cheques propios emitidos: cpromae + auxpag CHP + tesmov CHP
 */
final class IngresoEgresoAnitaTesmovSupport
{
    /** @var array<string, string> imputacion 8 dígitos => tctes_clave */
    private static array $cacheTipoApPorCuenta = [];

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

    public static function grabarDesdeMovimiento(Caja_Movimiento $movimiento): void
    {
        self::grabarInterno($movimiento, 1.0, null, null);
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

        self::grabarInterno($movimientoAnulacion, -1.0, $leyenda, $movimientoOriginal);
    }

    /**
     * @param  Caja_Movimiento|null  $movimientoChequesOrigen  cheques a anular en Anita (reversion)
     */
    private static function grabarInterno(
        Caja_Movimiento $movimiento,
        float $factorImporte,
        ?string $leyendaOverride,
        ?Caja_Movimiento $movimientoChequesOrigen
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

        $ctx = self::contexto($movimiento);
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

            $importeAbs = round(abs((float) $linea->monto), 2);
            if ($importeAbs < 0.01) {
                continue;
            }
            $importe = round($importeAbs * $ctx['factor'], 2);
            $cotizacion = (float) ($linea->cotizacion ?: 1);
            $monedaId = (int) ($linea->moneda_id ?: 1);

            self::insertAuxpagCuentaCaja($ctx, $codigoCuenta, $importe, $monedaId, $cotizacion);
            self::insertTesmovComprobante($ctx, $codigoCuenta, $importe, $monedaId, $cotizacion);
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
    private static function contexto(Caja_Movimiento $movimiento): ?array
    {
        $tipo = strtoupper(substr(trim((string) ($movimiento->tipotransaccioncajas->abreviatura ?? '')), 0, 3));
        if ($tipo === '') {
            $tipo = 'OPP';
        }

        $nro = (int) $movimiento->numerotransaccion;
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
                $cotizacion = (float) $linea->cotizacion;
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
        ];
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
        float $cotizacion
    ): void {
        $tipoAp = self::tipoAplicacionPorCuentaCaja($codigoCuenta);
        $imputacion = self::imputacionTctesDesdeCodigo($codigoCuenta);

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
                '".$ctx['nro']."',
                '".self::esc($tipoAp)."',
                '".$importe."',
                '".$monedaId."',
                '".$ctx['fecha']."',
                '".$imputacion."',
                ' ',
                '".$ctx['sucursal']."',
                '".self::esc($ctx['letra'])."',
                '0',
                '0',
                '0',
                '".$ctx['empresa']."',
                '0',
                ' '",
        ], 'caja IE auxpag '.$tipoAp);

        self::assertOk($raw, 'auxpag '.$tipoAp, (int) $ctx['nro']);
    }

    /** @param  array<string, mixed>  $ctx */
    private static function insertTesmovComprobante(
        array $ctx,
        string $codigoCuenta,
        float $importe,
        int $monedaId,
        float $cotizacion
    ): void {
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
                '".self::esc($ctx['tipo'])."',
                ' ',
                '".$ctx['sucursal']."',
                '".$ctx['nro']."',
                '".$importe."',
                '".$cotizacion."',
                '".self::esc(self::recortar($ctx['entregadoA'] !== '' ? $ctx['entregadoA'] : $ctx['detalle'], 30))."',
                ' ',
                'S/C',
                '0',
                '0',
                '".$ctx['empresa']."',
                '".$monedaId."'",
        ], 'caja IE tesmov');

        self::assertOk($raw, 'tesmov', (int) $ctx['nro']);
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
        $cotizacion = (float) ($cheque->cotizacion ?: 1);
        $monedaId = (int) ($cheque->moneda_id ?: 1);
        $fechaEmi = date('Ymd', strtotime((string) ($cheque->fechaemision ?: $movimiento->fecha)));
        $fechaChe = date('Ymd', strtotime((string) ($cheque->fechapago ?: $cheque->fechaemision ?: $movimiento->fecha)));

        $proveedorCodigo = $ctx['proveedorCodigo'];
        if ($cheque->proveedores) {
            $proveedorCodigo = str_pad((string) $cheque->proveedores->codigo, 6, '0', STR_PAD_LEFT);
        }

        $entregado = self::recortar((string) ($cheque->entregado ?: $ctx['entregadoA']), 30);
        $aNombre = self::recortar((string) ($cheque->anombrede ?: $entregado), 40);
        $modelo = (int) ($cheque->chequeras->codigo ?? 0);
        $paraDep = self::mapearParaDepositar((string) ($cheque->caracter ?? ''));
        $estado = ((int) $fechaChe <= (int) $fechaEmi) ? '*' : ' ';

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'cpromae',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                cpro_cuenta,
                cpro_nro_cheque,
                cpro_fecha_cheque,
                cpro_fecha_emision,
                cpro_importe,
                cpro_proveedor,
                cpro_entregado_a,
                cpro_nro_op,
                cpro_cod_mon,
                cpro_cotizacion,
                cpro_estado,
                cpro_contrapartida,
                cpro_fecha_anula,
                cpro_fl_imprimio,
                cpro_a_nombre_de,
                cpro_modelo,
                cpro_para_dep,
                cpro_empresa',
            'valores' => "
                '".str_pad($codigoCuenta, 8, '0', STR_PAD_LEFT)."',
                '".$nroCheque."',
                '".$fechaChe."',
                '".$fechaEmi."',
                '".$importe."',
                '".$proveedorCodigo."',
                '".self::esc($entregado)."',
                '".$ctx['nro']."',
                '".$monedaId."',
                '".$cotizacion."',
                '".$estado."',
                ' ',
                '0',
                ' ',
                '".self::esc($aNombre)."',
                '".$modelo."',
                '".self::esc($paraDep)."',
                '".$ctx['empresa']."'",
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
                '".$ctx['sucursal']."',
                ' ',
                '".$nroCheque."',
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
            ." AND tesv_cuenta = ".self::escSql($cuentaPad)
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
                '".$ctx['sucursal']."',
                '".self::esc($ctx['letra'])."',
                '0',
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

    private static function mapearParaDepositar(string $caracter): string
    {
        return match (strtoupper($caracter)) {
            'S', 'D' => 'S',
            'E' => 'E',
            'O' => 'O',
            default => ' ',
        };
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
