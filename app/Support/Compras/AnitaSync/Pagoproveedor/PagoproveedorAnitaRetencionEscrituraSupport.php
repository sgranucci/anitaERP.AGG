<?php

namespace App\Support\Compras\AnitaSync\Pagoproveedor;

use App\ApiAnita;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Provincia;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Escritura Anita de retenciones practicadas en OP (pago.c graba_ret*):
 *   Ganancias → retmov (retv_*)
 *   IIBB      → retibrmov (retibr_*)
 *   IVA       → retimov (retiv_*)
 *   SUSS      → retsmov (retsv_*)
 *
 * Misma clave que tesmov/ctamov MultiEmpresa: tipo / letra / sucursal / nro / empresa.
 * Reverso: filas AOP con importes positivos (SICORE aplica signo por tipo).
 */
final class PagoproveedorAnitaRetencionEscrituraSupport
{
    public static function estaHabilitada(): bool
    {
        return (bool) config('pagoproveedor.anita_escritura_habilitada', true);
    }

    public static function sistema(): string
    {
        return (string) config('pagoproveedor.anita_sistema_retenciones', 'compras');
    }

    /**
     * Borra filas OPP del pago y vuelve a insertar desde pagoproveedor_retencion.
     */
    public static function sincronizarDesdePago(Pagoproveedor $pago, bool $reemplazar = true): void
    {
        if (! self::estaHabilitada()) {
            return;
        }

        if ((string) $pago->estado === 'PRE CARGA') {
            return;
        }

        $pago->loadMissing(['proveedores', 'pagoproveedor_retenciones.provincias']);

        if ($reemplazar) {
            self::eliminarPorClave($pago, [(string) ($pago->tipocomprobante ?: 'OPP')]);
        }

        $ctx = self::contextoClave($pago);
        $proveedor = $pago->proveedores;
        if ($proveedor === null || $ctx['nro'] <= 0) {
            return;
        }

        foreach ($pago->pagoproveedor_retenciones as $ret) {
            if ((float) $ret->importe <= 0) {
                continue;
            }

            match ((string) $ret->tiporetencion) {
                Pagoproveedor_Retencion::TIPO_GANANCIAS => self::insertRetmov($ctx, $proveedor, $pago, $ret),
                Pagoproveedor_Retencion::TIPO_IVA => self::insertRetimov($ctx, $proveedor, $pago, $ret),
                Pagoproveedor_Retencion::TIPO_SUSS => self::insertRetsmov($ctx, $proveedor, $pago, $ret),
                Pagoproveedor_Retencion::TIPO_IIBB => self::insertRetibrmov($ctx, $proveedor, $pago, $ret),
                default => null,
            };
        }
    }

    /**
     * Compensación AOP al revertir (conserva OPP originales en Anita).
     *
     * @param  Collection<int, Pagoproveedor_Retencion>|iterable<Pagoproveedor_Retencion>  $retenciones
     */
    public static function grabarReversoDesdeRetenciones(
        Pagoproveedor $origen,
        Pagoproveedor $reverso,
        iterable $retenciones,
    ): void {
        if (! self::estaHabilitada()) {
            return;
        }

        $origen->loadMissing(['proveedores']);
        $proveedor = $origen->proveedores;
        if ($proveedor === null) {
            return;
        }

        $ctx = self::contextoClave($reverso);
        $ctx['tipo'] = 'AOP';

        foreach ($retenciones as $ret) {
            if (! $ret instanceof Pagoproveedor_Retencion) {
                continue;
            }
            if ((float) $ret->importe <= 0) {
                continue;
            }

            match ((string) $ret->tiporetencion) {
                Pagoproveedor_Retencion::TIPO_GANANCIAS => self::insertRetmov($ctx, $proveedor, $origen, $ret),
                Pagoproveedor_Retencion::TIPO_IVA => self::insertRetimov($ctx, $proveedor, $origen, $ret),
                Pagoproveedor_Retencion::TIPO_SUSS => self::insertRetsmov($ctx, $proveedor, $origen, $ret),
                Pagoproveedor_Retencion::TIPO_IIBB => self::insertRetibrmov($ctx, $proveedor, $origen, $ret),
                default => null,
            };
        }
    }

    /**
     * Baja física: elimina OPP (y AOP del mismo nro si existiera).
     */
    public static function eliminarDesdePago(Pagoproveedor $pago): void
    {
        if (! self::estaHabilitada()) {
            return;
        }

        self::eliminarPorClave($pago, ['OPP', 'AOP']);
    }

    /**
     * @param  list<string>  $tipos
     */
    private static function eliminarPorClave(Pagoproveedor $pago, array $tipos): void
    {
        $ctx = self::contextoClave($pago);
        if ($ctx['nro'] <= 0 || $ctx['empresa'] <= 0) {
            return;
        }

        $tiposSql = [];
        foreach ($tipos as $t) {
            $t = strtoupper(substr(trim($t), 0, 3));
            if ($t !== '') {
                $tiposSql[$t] = "'".self::esc($t)."'";
            }
        }
        if ($tiposSql === []) {
            return;
        }
        $inTipos = implode(',', array_values($tiposSql));

        $pares = [
            ['retmov', 'retv'],
            ['retibrmov', 'retibr'],
            ['retimov', 'retiv'],
            ['retsmov', 'retsv'],
        ];

        foreach ($pares as [$tabla, $pref]) {
            $where = ' WHERE '.$pref.'_empresa = '.(int) $ctx['empresa']
                .' AND '.$pref.'_sucursal = '.(int) $ctx['sucursal']
                .' AND '.$pref.'_nro = '.(int) $ctx['nro']
                .' AND '.$pref.'_tipo IN ('.$inTipos.')';

            self::deleteWhere($tabla, $where, 'pagoproveedor ret '.$tabla.' '.$pago->id);
        }
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int, empresa: int, fecha: int}
     */
    private static function contextoClave(Pagoproveedor $pago): array
    {
        $tipo = strtoupper(substr(trim((string) ($pago->tipocomprobante ?: 'OPP')), 0, 3));
        if ($tipo === '') {
            $tipo = 'OPP';
        }

        $letra = (string) ($pago->letra ?? config('pagoproveedor.letra_default', ' '));
        if ($letra === '') {
            $letra = ' ';
        }
        $letra = substr($letra, 0, 1);

        $empresa = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $pago->empresa_id);
        if ($empresa <= 0) {
            $empresa = (int) $pago->empresa_id;
        }

        $fecha = $pago->fecha?->format('Ymd');
        if ($fecha === null || $fecha === '') {
            $fecha = date('Ymd');
        }

        return [
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => (int) ($pago->sucursal ?: $empresa),
            'nro' => (int) $pago->numerotransaccion,
            'empresa' => $empresa,
            'fecha' => (int) $fecha,
        ];
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int, empresa: int, fecha: int}  $ctx
     */
    private static function insertRetmov(
        array $ctx,
        Proveedor $proveedor,
        Pagoproveedor $pago,
        Pagoproveedor_Retencion $ret,
    ): void {
        $det = is_array($ret->detalle_calculo) ? $ret->detalle_calculo : [];
        $netoPago = (float) ($det['neto_pago'] ?? $ret->base_calculo);
        $sujeto = (float) ($det['base_retenible'] ?? $ret->base_calculo);
        $pagoAnterior = (float) ($det['neto_acumulado_previo'] ?? 0);
        $retAnterior = (float) ($det['retenido_previo'] ?? 0);
        $retMes = (float) ($det['retencion_periodo'] ?? $ret->importe);
        $codigoRet = (int) ($ret->codigo_retencion ?: ($det['codigo'] ?? $det['retv_codigo_ret'] ?? 0));
        $porcExcl = (float) ($det['porc_excl'] ?? $det['porcentaje_exclusion'] ?? 0);
        $pagoActual = abs((float) $pago->monto) > 0.0001
            ? abs((float) $pago->monto)
            : (float) ($det['pago_actual_anita'] ?? $netoPago);

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'retmov',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                retv_proveedor,
                retv_tipo,
                retv_letra,
                retv_sucursal,
                retv_nro,
                retv_fecha,
                retv_codigo_ret,
                retv_gravado,
                retv_pago_actual,
                retv_pago_anterior,
                retv_sujeto,
                retv_retencion,
                retv_porc_ret,
                retv_nro_retencion,
                retv_ret_mes,
                retv_ret_anterior,
                retv_nombre_prov,
                retv_cuit_prov,
                retv_porc_excl,
                retv_cod_mon,
                retv_empresa',
            'valores' => "
                '".self::codigoProveedor6($proveedor)."',
                '".self::esc($ctx['tipo'])."',
                '".self::esc($ctx['letra'])."',
                '".$ctx['sucursal']."',
                '".$ctx['nro']."',
                '".$ctx['fecha']."',
                '".$codigoRet."',
                '".self::num($netoPago)."',
                '".self::num($pagoActual)."',
                '".self::num($pagoAnterior)."',
                '".self::num($sujeto)."',
                '".self::num((float) $ret->importe)."',
                '".self::num((float) $ret->alicuota)."',
                '".(int) $ret->nro_certificado."',
                '".self::num($retMes)."',
                '".self::num($retAnterior)."',
                '".self::esc(self::recortar((string) ($proveedor->nombre ?? ''), 30))."',
                '".self::esc(self::recortar(self::cuitProveedor($proveedor), 15))."',
                '".self::num($porcExcl)."',
                '".self::esc(self::codMonedaAnita($pago))."',
                '".$ctx['empresa']."'",
        ], 'pagoproveedor retmov '.$pago->id);

        self::assertOk($raw, 'retmov', (int) $pago->id);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int, empresa: int, fecha: int}  $ctx
     */
    private static function insertRetibrmov(
        array $ctx,
        Proveedor $proveedor,
        Pagoproveedor $pago,
        Pagoproveedor_Retencion $ret,
    ): void {
        $det = is_array($ret->detalle_calculo) ? $ret->detalle_calculo : [];
        $gravado = (float) ($det['neto'] ?? $det['base'] ?? $ret->base_calculo);
        $pagoActual = abs((float) $pago->monto) > 0.0001
            ? abs((float) $pago->monto)
            : $gravado;
        $provinciaAnita = self::codigoProvinciaAnita($ret);

        $tipoComp = self::recortar(strtoupper(trim((string) ($det['tipo_comp'] ?? ''))), 3);
        $letraComp = self::recortar(strtoupper(trim((string) ($det['letra_comp'] ?? ''))), 1);
        if ($letraComp === '') {
            $letraComp = ' ';
        }

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'retibrmov',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                retibr_proveedor,
                retibr_tipo,
                retibr_letra,
                retibr_sucursal,
                retibr_nro,
                retibr_fecha,
                retibr_gravado,
                retibr_pago_actual,
                retibr_sujeto,
                retibr_retencion,
                retibr_porc_ret,
                retibr_nro_ret,
                retibr_tipo_comp,
                retibr_letra_comp,
                retibr_suc_comp,
                retibr_nro_comp,
                retibr_fecha_comp,
                retibr_nro_interno,
                retibr_provincia,
                retibr_empresa',
            'valores' => "
                '".self::codigoProveedor6($proveedor)."',
                '".self::esc($ctx['tipo'])."',
                '".self::esc($ctx['letra'])."',
                '".$ctx['sucursal']."',
                '".$ctx['nro']."',
                '".$ctx['fecha']."',
                '".self::num($gravado)."',
                '".self::num($pagoActual)."',
                '".self::num($gravado)."',
                '".self::num((float) $ret->importe)."',
                '".self::num((float) $ret->alicuota)."',
                '".(int) $ret->nro_certificado."',
                '".self::esc($tipoComp !== '' ? $tipoComp : ' ')."',
                '".self::esc($letraComp)."',
                '".(int) ($det['suc_comp'] ?? 0)."',
                '".(int) ($det['nro_comp'] ?? 0)."',
                '".(int) ($det['fecha_comp'] ?? 0)."',
                '".(int) ($det['nro_interno'] ?? 0)."',
                '".$provinciaAnita."',
                '".$ctx['empresa']."'",
        ], 'pagoproveedor retibrmov '.$pago->id);

        self::assertOk($raw, 'retibrmov', (int) $pago->id);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int, empresa: int, fecha: int}  $ctx
     */
    private static function insertRetimov(
        array $ctx,
        Proveedor $proveedor,
        Pagoproveedor $pago,
        Pagoproveedor_Retencion $ret,
    ): void {
        $det = is_array($ret->detalle_calculo) ? $ret->detalle_calculo : [];
        $gravado = (float) ($det['neto_pago'] ?? $det['neto'] ?? $ret->base_calculo);
        $iva = (float) ($det['importe_iva'] ?? $det['iva'] ?? 0);
        $sujeto = (float) ($det['base_retenible'] ?? $det['sujeto'] ?? $ret->base_calculo);
        $pagoActual = abs((float) $pago->monto) > 0.0001
            ? abs((float) $pago->monto)
            : ($gravado + $iva);
        $codigoRet = (int) ($ret->codigo_retencion ?: ($det['codigo'] ?? 0));
        $porcExcl = (float) ($det['porc_excl'] ?? $det['porcentaje_exclusion'] ?? 0);

        $tipoComp = self::recortar(strtoupper(trim((string) ($det['tipo_comp'] ?? ''))), 3);
        $letraComp = self::recortar(strtoupper(trim((string) ($det['letra_comp'] ?? ''))), 1);
        if ($letraComp === '') {
            $letraComp = ' ';
        }

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'retimov',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                retiv_proveedor,
                retiv_tipo,
                retiv_letra,
                retiv_sucursal,
                retiv_nro,
                retiv_fecha,
                retiv_codigo_ret,
                retiv_gravado,
                retiv_iva,
                retiv_pago_actual,
                retiv_sujeto,
                retiv_retencion,
                retiv_porc_ret,
                retiv_nro_ret,
                retiv_tipo_comp,
                retiv_letra_comp,
                retiv_suc_comp,
                retiv_nro_comp,
                retiv_fecha_comp,
                retiv_nro_interno,
                retiv_nombre_prov,
                retiv_cuit_prov,
                retiv_porc_excl,
                retiv_empresa',
            'valores' => "
                '".self::codigoProveedor6($proveedor)."',
                '".self::esc($ctx['tipo'])."',
                '".self::esc($ctx['letra'])."',
                '".$ctx['sucursal']."',
                '".$ctx['nro']."',
                '".$ctx['fecha']."',
                '".$codigoRet."',
                '".self::num($gravado)."',
                '".self::num($iva)."',
                '".self::num($pagoActual)."',
                '".self::num($sujeto)."',
                '".self::num((float) $ret->importe)."',
                '".self::num((float) $ret->alicuota)."',
                '".(int) $ret->nro_certificado."',
                '".self::esc($tipoComp !== '' ? $tipoComp : ' ')."',
                '".self::esc($letraComp)."',
                '".(int) ($det['suc_comp'] ?? 0)."',
                '".(int) ($det['nro_comp'] ?? 0)."',
                '".(int) ($det['fecha_comp'] ?? 0)."',
                '".(int) ($det['nro_interno'] ?? 0)."',
                '".self::esc(self::recortar((string) ($proveedor->nombre ?? ''), 30))."',
                '".self::esc(self::recortar(self::cuitProveedor($proveedor), 15))."',
                '".self::num($porcExcl)."',
                '".$ctx['empresa']."'",
        ], 'pagoproveedor retimov '.$pago->id);

        self::assertOk($raw, 'retimov', (int) $pago->id);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int, empresa: int, fecha: int}  $ctx
     */
    private static function insertRetsmov(
        array $ctx,
        Proveedor $proveedor,
        Pagoproveedor $pago,
        Pagoproveedor_Retencion $ret,
    ): void {
        $det = is_array($ret->detalle_calculo) ? $ret->detalle_calculo : [];
        $gravado = (float) ($det['neto_pago'] ?? $det['neto'] ?? $ret->base_calculo);
        $baseCalculo = (float) ($det['base_retenible'] ?? $det['base_calculo'] ?? $ret->base_calculo);
        $codigoRet = (int) ($ret->codigo_retencion ?: ($det['codigo'] ?? 0));

        $raw = (new ApiAnita)->apiCallEscritura([
            'tabla' => 'retsmov',
            'acc' => 'insert',
            'sistema' => self::sistema(),
            'campos' => '
                retsv_proveedor,
                retsv_tipo,
                retsv_letra,
                retsv_sucursal,
                retsv_nro,
                retsv_fecha,
                retsv_codigo_ret,
                retsv_gravado,
                retsv_retencion,
                retsv_porc_ret,
                retsv_nro_ret,
                retsv_empresa,
                retsv_base_calculo',
            'valores' => "
                '".self::codigoProveedor6($proveedor)."',
                '".self::esc($ctx['tipo'])."',
                '".self::esc($ctx['letra'])."',
                '".$ctx['sucursal']."',
                '".$ctx['nro']."',
                '".$ctx['fecha']."',
                '".$codigoRet."',
                '".self::num($gravado)."',
                '".self::num((float) $ret->importe)."',
                '".self::num((float) $ret->alicuota)."',
                '".(int) $ret->nro_certificado."',
                '".$ctx['empresa']."',
                '".self::num($baseCalculo)."'",
        ], 'pagoproveedor retsmov '.$pago->id);

        self::assertOk($raw, 'retsmov', (int) $pago->id);
    }

    private static function codigoProvinciaAnita(Pagoproveedor_Retencion $ret): int
    {
        $provincia = $ret->provincias;
        if (! $provincia instanceof Provincia) {
            $provId = (int) ($ret->provincia_id ?: 0);
            if ($provId > 0) {
                $provincia = Provincia::query()->find($provId);
            }
        }

        $codigos = IngresosBrutosProvinciaAnitaSupport::codigosAnita($provincia);
        if ($codigos !== []) {
            // Preferir codigoexterno corto (p.ej. BA=2) cuando está en la lista.
            foreach ($codigos as $c) {
                if ($c > 0 && $c < 100) {
                    return $c;
                }
            }

            return (int) $codigos[0];
        }

        $det = is_array($ret->detalle_calculo) ? $ret->detalle_calculo : [];

        return (int) ($det['provincia_id'] ?? $ret->provincia_id ?? 0);
    }

    private static function codigoProveedor6(Proveedor $proveedor): string
    {
        return str_pad((string) ($proveedor->codigo ?? '0'), 6, '0', STR_PAD_LEFT);
    }

    private static function cuitProveedor(Proveedor $proveedor): string
    {
        $cuit = trim((string) ($proveedor->nroinscripcion ?? $proveedor->cuit ?? ''));

        return $cuit !== '' ? $cuit : ' ';
    }

    private static function codMonedaAnita(Pagoproveedor $pago): string
    {
        $monedaId = (int) ($pago->moneda_id ?: 1);

        return $monedaId <= 1 ? '1' : (string) min($monedaId, 9);
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
            Log::error('pagoproveedor.anita.retencion.delete_fail', [
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
            Log::error('pagoproveedor.anita.retencion.insert_fail', [
                'tabla' => $tabla,
                'ref' => $refId,
                'error' => $err,
            ]);
            throw new \RuntimeException('Error al grabar '.$tabla.' Anita: '.$err);
        }
    }

    private static function num(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 6, '.', ''), '0'), '.') ?: '0';
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
}
