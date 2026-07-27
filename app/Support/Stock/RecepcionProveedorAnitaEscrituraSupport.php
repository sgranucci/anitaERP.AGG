<?php

namespace App\Support\Stock;

use App\Support\Anita\AnitaTextoSanitizer;

/**
 * Arma campos/valores SQL para el bridge Anita (apiERP.php) en recepción de proveedores.
 * El bridge legacy no acepta arrays asociativos en "campos".
 * Numéricos Anita: 0 explícito, nunca null.
 */
final class RecepcionProveedorAnitaEscrituraSupport
{
    public static function escSql(string $value): string
    {
        return str_replace("'", "''", AnitaTextoSanitizer::sanitizar($value));
    }

    public static function textoSql(string $value, int $maxLen = 0): string
    {
        $s = $maxLen > 0 ? substr($value, 0, $maxLen) : $value;

        return "'".self::escSql($s)."'";
    }

    public static function charFijoSql(string $value, int $len): string
    {
        return self::textoSql(str_pad(substr(trim($value), 0, $len), $len, ' ', STR_PAD_RIGHT), $len);
    }

    public static function enteroSql(int $value): string
    {
        return (string) $value;
    }

    /** Default 6: cantidades ERP (ej. 0.599940) deben cuadrar cant×precio con asiento COM. */
    public static function decimalSql(float $value, int $decimales = 6): string
    {
        return number_format($value, $decimales, '.', '');
    }

    public static function skuAnita13(string $sku): string
    {
        return str_pad(substr(trim($sku), 0, 13), 13, '0', STR_PAD_LEFT);
    }

    public static function proveedorSql(int|string|null $codigoProveedor): string
    {
        return self::textoSql(RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor), 6);
    }

    public static function agrupacionSql(string $codigoCategoria): string
    {
        $codigo = trim($codigoCategoria);

        return self::textoSql(
            str_pad(substr($codigo !== '' ? $codigo : '0', 0, 4), 4, '0', STR_PAD_LEFT),
            4
        );
    }

    public static function tipoIvaAnitaCodigo(?\App\Models\Stock\Articulo $articulo): int
    {
        if ($articulo === null) {
            return 0;
        }

        $articulo->loadMissing('impuestos');
        $codigo = trim((string) optional($articulo->impuestos)->codigo);
        if ($codigo !== '' && ctype_digit($codigo)) {
            return (int) $codigo;
        }

        $impuestoId = (int) ($articulo->impuesto_id ?? 0);

        return $impuestoId > 0 ? $impuestoId : 0;
    }

    /**
     * @param  array<string, string>  $columnasValorSql  nombre columna => fragmento SQL ya formateado
     * @return array{campos: string, valores: string}
     */
    public static function insert(array $columnasValorSql): array
    {
        return [
            'campos' => implode(', ', array_keys($columnasValorSql)),
            'valores' => implode(', ', array_values($columnasValorSql)),
        ];
    }

    /**
     * @param  array<string, string>  $asignaciones  columna => fragmento SQL
     */
    public static function updateSet(array $asignaciones): string
    {
        $partes = [];
        foreach ($asignaciones as $columna => $valorSql) {
            $partes[] = $columna.' = '.$valorSql;
        }

        return implode(', ', $partes);
    }

    /**
     * PEP / OC en recm_tipo_fac* (no recm_com_*). recm_com_* queda vacío/cero como Anita legacy.
     *
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $ocFac  PEP en recm_tipo_fac*
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $refFac  Factura/remito en recm_ref_*
     * @return array{campos: string, valores: string}
     */
    public static function recepmaeInsert(
        string $codigoProveedor,
        array $clave,
        int $fechaAnita,
        string $estadoConfirmada,
        string $usuario,
        string $observacion,
        int $empresaCodigo,
        array $ocFac,
        array $refFac,
        int $documentoId = 0,
    ): array {
        return self::insert([
            'recm_proveedor' => self::proveedorSql($codigoProveedor),
            'recm_tipo' => self::textoSql($clave['tipo'], 3),
            'recm_letra' => self::textoSql($clave['letra'], 1),
            'recm_sucursal' => self::enteroSql((int) $clave['sucursal']),
            'recm_nro' => self::enteroSql((int) $clave['nro']),
            'recm_fecha' => self::enteroSql($fechaAnita),
            'recm_estado' => self::textoSql($estadoConfirmada, 1),
            'recm_usuario' => self::textoSql($usuario, 8),
            'recm_terminal' => self::textoSql('ERP', 8),
            'recm_fe_ult_act' => self::enteroSql((int) date('Ymd')),
            'recm_observacion' => self::textoSql($observacion, 40),
            'recm_empresa' => self::enteroSql($empresaCodigo),
            'recm_com_tipo' => self::charFijoSql(' ', 3),
            'recm_com_letra' => self::charFijoSql(' ', 1),
            'recm_com_sucursal' => self::enteroSql(0),
            'recm_com_nro' => self::enteroSql(0),
            'recm_tipo_fac' => self::textoSql($ocFac['tipo'], 3),
            'recm_letra_fac' => self::textoSql($ocFac['letra'], 1),
            'recm_sucursal_fac' => self::enteroSql((int) $ocFac['sucursal']),
            'recm_nro_fac' => self::enteroSql((int) $ocFac['nro']),
            'recm_ref_tipo' => self::textoSql($refFac['tipo'], 3),
            'recm_ref_letra' => self::textoSql($refFac['letra'], 1),
            'recm_ref_sucursal' => self::enteroSql((int) $refFac['sucursal']),
            'recm_ref_nro' => self::enteroSql((int) $refFac['nro']),
            'recm_documentoid' => self::enteroSql($documentoId),
        ]);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $ocFac
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $refFac
     */
    public static function recepmaeUpdateSet(
        int $fechaAnita,
        string $estado,
        string $usuario,
        string $observacion,
        int $empresaCodigo,
        array $ocFac,
        array $refFac,
        int $documentoId = 0,
    ): string {
        return self::updateSet([
            'recm_fecha' => self::enteroSql($fechaAnita),
            'recm_estado' => self::textoSql($estado, 1),
            'recm_usuario' => self::textoSql($usuario, 8),
            'recm_fe_ult_act' => self::enteroSql((int) date('Ymd')),
            'recm_observacion' => self::textoSql($observacion, 40),
            'recm_empresa' => self::enteroSql($empresaCodigo),
            'recm_tipo_fac' => self::textoSql($ocFac['tipo'], 3),
            'recm_letra_fac' => self::textoSql($ocFac['letra'], 1),
            'recm_sucursal_fac' => self::enteroSql((int) $ocFac['sucursal']),
            'recm_nro_fac' => self::enteroSql((int) $ocFac['nro']),
            'recm_ref_tipo' => self::textoSql($refFac['tipo'], 3),
            'recm_ref_letra' => self::textoSql($refFac['letra'], 1),
            'recm_ref_sucursal' => self::enteroSql((int) $refFac['sucursal']),
            'recm_ref_nro' => self::enteroSql((int) $refFac['nro']),
            'recm_documentoid' => self::enteroSql($documentoId),
        ]);
    }

    public static function recepmaeAnularSet(string $estadoAnulada): string
    {
        return self::updateSet([
            'recm_estado' => self::textoSql($estadoAnulada, 1),
            'recm_fe_ult_act' => self::enteroSql((int) date('Ymd')),
        ]);
    }

    /**
     * Vínculo COM ↔ PEP por línea (aplicped).
     * aplp_* = COM; aplp_ref_* = PEP; detalle alineado a recepmov / pendmovp.
     *
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $claveCom
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $ocFac
     * @return array{campos: string, valores: string}
     */
    public static function aplicpedLineaInsert(
        string $codigoProveedor,
        array $claveCom,
        array $ocFac,
        int $recvOrden,
        int $penvpOrden,
        string $skuAnita13,
        float $cantidad,
        int $penvpNroInterno,
    ): array {
        return self::insert([
            'aplp_proveedor' => self::proveedorSql($codigoProveedor),
            'aplp_tipo' => self::textoSql($claveCom['tipo'], 3),
            'aplp_letra' => self::textoSql($claveCom['letra'], 1),
            'aplp_sucursal' => self::enteroSql((int) $claveCom['sucursal']),
            'aplp_nro' => self::enteroSql((int) $claveCom['nro']),
            'aplp_ref_tipo' => self::textoSql($ocFac['tipo'], 3),
            'aplp_ref_letra' => self::textoSql($ocFac['letra'], 1),
            'aplp_ref_sucursal' => self::enteroSql((int) $ocFac['sucursal']),
            'aplp_ref_nro' => self::enteroSql((int) $ocFac['nro']),
            'aplp_orden_com' => self::enteroSql($recvOrden),
            'aplp_orden' => self::enteroSql($penvpOrden),
            'aplp_articulo' => self::textoSql($skuAnita13, 13),
            'aplp_cantentr' => self::decimalSql($cantidad),
            'aplp_nro_interno' => self::enteroSql($penvpNroInterno),
            'aplp_cantfact' => self::decimalSql(0),
        ]);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}
     */
    public static function recepmovInsert(
        string $codigoProveedor,
        array $clave,
        int $orden,
        string $skuAnita13,
        string $descripcion,
        float $cantidad,
        float $cantidadRechazada,
        string $motivoRechazo,
        float $precio,
        float $descuentoArticulo,
        int $depositoId,
        int $fechaAnita,
        string $codigoMoneda,
        int $centroCostoCodigo,
        int $empresaCodigo,
        float $cotizacion,
        string $codigoAgrupacion,
        int $tipoIvaAnita,
    ): array {
        return self::insert([
            'recv_proveedor' => self::proveedorSql($codigoProveedor),
            'recv_tipo' => self::textoSql($clave['tipo'], 3),
            'recv_letra' => self::textoSql($clave['letra'], 1),
            'recv_sucursal' => self::enteroSql((int) $clave['sucursal']),
            'recv_nro' => self::enteroSql((int) $clave['nro']),
            'recv_orden' => self::enteroSql($orden),
            'recv_articulo' => self::textoSql($skuAnita13, 13),
            'recv_agrupacion' => self::agrupacionSql($codigoAgrupacion),
            'recv_desc' => self::textoSql($descripcion, 30),
            'recv_cantidad' => self::decimalSql($cantidad),
            'recv_cantrech' => self::decimalSql($cantidadRechazada),
            'recv_motivo_rech' => self::textoSql($motivoRechazo, 40),
            'recv_precio' => self::decimalSql($precio),
            'recv_dto_art' => self::decimalSql($descuentoArticulo),
            'recv_deposito' => self::enteroSql($depositoId),
            'recv_fecha' => self::enteroSql($fechaAnita),
            'recv_incl_impuesto' => self::textoSql('N', 1),
            'recv_cod_mon' => self::textoSql($codigoMoneda, 1),
            'recv_ccosto' => self::enteroSql($centroCostoCodigo),
            'recv_empresa' => self::enteroSql($empresaCodigo),
            'recv_cotizacion' => self::decimalSql($cotizacion),
            'recv_cantfact' => self::decimalSql(0),
            'recv_tipo_iva' => self::enteroSql($tipoIvaAnita),
            'recv_partida' => self::enteroSql(0),
        ]);
    }

    /**
     * Línea recepmov sintética del impuesto interno de cigarrillos (SKU IMPINTERNO).
     * cantidad = 1, precio = importe redondeado: hace que la suma de montos recepmov
     * coincida con el asiento COM (que discrimina el impuesto interno).
     * Devuelve null si el importe no es positivo.
     *
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}|null
     */
    public static function recepmovImpuestoInternoInsert(
        string $codigoProveedor,
        array $clave,
        int $ordenMax,
        string $skuAnita13,
        string $descripcion,
        float $importe,
        int $depositoId,
        int $fechaAnita,
        string $codigoMoneda,
        int $centroCostoCodigo,
        int $empresaCodigo,
        float $cotizacion,
        string $codigoAgrupacion,
        int $tipoIvaAnita,
    ): ?array {
        $importe = round($importe, 2);
        if ($importe <= 0.000001) {
            return null;
        }

        return self::recepmovInsert(
            $codigoProveedor,
            $clave,
            $ordenMax + 1,
            $skuAnita13,
            $descripcion,
            1.0,
            0.0,
            '',
            $importe,
            0.0,
            $depositoId,
            $fechaAnita,
            $codigoMoneda,
            $centroCostoCodigo,
            $empresaCodigo,
            $cotizacion,
            $codigoAgrupacion,
            $tipoIvaAnita,
        );
    }

    /**
     * Movimiento de stock Anita (stkmov) por línea COM. Patrón AGG.
     *
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @param  int  $depositoAnita  Código Anita del depósito (depmae.codigo), no el id ERP.
     * @return array{campos: string, valores: string}
     */
    public static function stkmovInsert(
        array $clave,
        int $fechaAnita,
        string $skuAnita13,
        string $codigoAgrupacion,
        int $ordenLinea,
        string $codigoProveedor,
        int $depositoAnita,
        float $cantidad,
        float $precio,
        string $codigoMoneda,
        int $empresaCodigo,
        string $usuario,
        int $empresaIdBridge = 1,
    ): array {
        $columnas = [
            'stkv_articulo' => self::textoSql($skuAnita13, 13),
            'stkv_agrupacion' => self::agrupacionSql($codigoAgrupacion),
            'stkv_fecha' => self::enteroSql($fechaAnita),
            'stkv_tipo' => self::textoSql($clave['tipo'], 3),
            'stkv_letra' => self::textoSql($clave['letra'], 1),
            'stkv_sucursal' => self::enteroSql((int) $clave['sucursal']),
            'stkv_nro' => self::enteroSql((int) $clave['nro']),
            'stkv_ref_tipo' => self::charFijoSql(' ', 3),
            'stkv_ref_sucursal' => self::enteroSql(0),
            'stkv_ref_nro' => self::enteroSql(0),
            'stkv_deposito' => self::enteroSql($depositoAnita),
            'stkv_cantidad' => self::decimalSql(AnitaStkmovClaveErpSupport::cantidadStkmov($cantidad)),
            'stkv_precio' => self::decimalSql($precio),
            'stkv_cod_mon' => self::textoSql($codigoMoneda, 1),
            'stkv_cod_impuesto' => self::enteroSql(0),
            'stkv_descuento' => self::decimalSql(0),
            'stkv_dto_gral' => self::decimalSql(0),
            'stkv_comision' => self::decimalSql(0),
            'stkv_nro_orden' => self::enteroSql($ordenLinea),
            'stkv_cli_pro' => self::proveedorSql($codigoProveedor),
            'stkv_vendedor' => self::enteroSql(0),
            'stkv_zona_vta' => self::enteroSql(0),
            'stkv_zona_mult' => self::enteroSql(0),
            'stkv_subzona' => self::enteroSql(0),
            'stkv_comprador' => self::enteroSql(0),
            'stkv_partida' => self::enteroSql(0),
            'stkv_pedido' => self::enteroSql(0),
            'stkv_usuario' => self::textoSql(substr($usuario, 0, 8), 8),
            'stkv_terminal' => self::textoSql('ERP', 8),
            'stkv_fe_ult_act' => self::enteroSql((int) date('Ymd')),
            'stkv_cod_entrega' => self::enteroSql(0),
            'stkv_cod_umd' => self::enteroSql(0),
            'stkv_unidad_xenv' => self::enteroSql(0),
            'stkv_cod_umd_alter' => self::enteroSql(0),
        ];

        if (StockAnitaBridgeSupport::stkmovIncluyeColumnasAggMultiempresa($empresaIdBridge)) {
            $columnas['stkv_cant_unidad'] = self::decimalSql(AnitaStkmovClaveErpSupport::cantidadStkmov($cantidad));
            $columnas['stkv_empresa'] = self::enteroSql($empresaCodigo);
        }

        return self::insert($columnas);
    }

    public static function pendmovpCantentrUpdateSet(float $nuevaCantidad): string
    {
        return self::updateSet([
            'penvp_cantentr' => self::decimalSql($nuevaCantidad),
        ]);
    }

    /** Cierre administrativo de línea OC en Anita (penvp_partida = -1). */
    public static function pendmovpCerrarLineaUpdateSet(float $cantidadOc): string
    {
        return self::updateSet([
            'penvp_cantentr' => self::decimalSql($cantidadOc),
            'penvp_partida' => '-1',
        ]);
    }

    public static function pendmovpPrecioUpdateSet(float $precio): string
    {
        return self::updateSet([
            'penvp_precio' => self::decimalSql($precio),
        ]);
    }

    public static function penmpEstadoUpdateSet(string $estadoCabecera): string
    {
        return self::updateSet([
            'penmp_estado' => self::textoSql($estadoCabecera, 1),
        ]);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}
     */
    public static function recpunicaInsert(
        array $clave,
        int $lineaAnita,
        string $skuAnita13,
        int $numeroparte,
    ): array {
        return self::insert([
            'recpu_tipo' => self::textoSql($clave['tipo'], 3),
            'recpu_letra' => self::textoSql($clave['letra'], 1),
            'recpu_sucursal' => self::enteroSql((int) $clave['sucursal']),
            'recpu_nro' => self::enteroSql((int) $clave['nro']),
            'recpu_linea' => self::enteroSql($lineaAnita),
            'recpu_articulo' => self::textoSql($skuAnita13, 13),
            'recpu_id' => self::enteroSql($numeroparte),
        ]);
    }

    public static function stkParteUnicaInsert(string $skuAnita13, int $numeroparte): array
    {
        return self::insert([
            'stkpu_articulo' => self::textoSql($skuAnita13, 13),
            'stkpu_id' => self::enteroSql($numeroparte),
        ]);
    }
}
