<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;

/**
 * Arma campos/valores SQL para pendmaep, pendmovp y movpresup (ERP → Anita).
 * Numéricos sin dato → 0 explícito.
 */
final class OrdencompraAnitaEscrituraSupport
{
    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}
     */
    public static function pendmaepInsert(
        Ordencompra $oc,
        OrdencompraAnitaErpContext $ctx,
        array $clave,
    ): array {
        return RecepcionProveedorAnitaEscrituraSupport::insert(self::pendmaepColumnas($oc, $ctx, $clave));
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    public static function pendmaepUpdateSet(
        Ordencompra $oc,
        OrdencompraAnitaErpContext $ctx,
        array $clave,
    ): string {
        return RecepcionProveedorAnitaEscrituraSupport::updateSet(self::pendmaepColumnas($oc, $ctx, $clave, false));
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}
     */
    public static function pendmovpInsert(
        Ordencompra $oc,
        Ordencompra_Articulo $linea,
        OrdencompraAnitaErpContext $ctx,
        array $clave,
        string $codigoProveedor6,
    ): array {
        return RecepcionProveedorAnitaEscrituraSupport::insert(
            self::pendmovpColumnas($oc, $linea, $ctx, $clave, $codigoProveedor6)
        );
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}
     */
    public static function movpresupInsert(
        Ordencompra $oc,
        Ordencompra_Articulo $linea,
        OrdencompraAnitaErpContext $ctx,
        array $clave,
    ): array {
        return RecepcionProveedorAnitaEscrituraSupport::insert(
            self::movpresupColumnas($oc, $linea, $ctx, $clave)
        );
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array<string, string>
     */
    private static function pendmaepColumnas(
        Ordencompra $oc,
        OrdencompraAnitaErpContext $ctx,
        array $clave,
        bool $incluirClave = true,
    ): array {
        $fecha = $ctx->fechaYmd($oc->fecha);
        $fechaEnt = $ctx->fechaYmd($oc->fechaentrega);
        $detalle = trim((string) ($oc->detalle ?? ''));
        if ($detalle === '') {
            $detalle = 'OC '.(int) $oc->numeroordencompra;
        }
        $lugarentrega = trim((string) ($oc->lugarentrega ?? ''));
        $ccDest = $ctx->codigoCentrocosto((int) ($oc->centrocosto_id ?? 0));
        $cotizacion = $ctx->cotizacionCabecera($oc);

        $columnas = [
            'penmp_proveedor' => RecepcionProveedorAnitaEscrituraSupport::proveedorSql($ctx->codigoProveedor6((int) $oc->proveedor_id)),
            'penmp_fecha' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fecha),
            'penmp_fecha_ent' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fechaEnt),
            'penmp_cond_compra' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoCondicioncompra((int) ($oc->condicioncompra_id ?? 0))),
            'penmp_cond_entrega' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoCondicionentrega((int) ($oc->condicionentrega_id ?? 0))),
            'penmp_cond_pago' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->condicionpagoCabecera($oc)),
            'penmp_entrega' => RecepcionProveedorAnitaEscrituraSupport::textoSql($lugarentrega !== '' ? $lugarentrega : ' ', 40),
            'penmp_dto' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($oc->descuento ?? 0)),
            'penmp_expreso' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoTransporte((int) ($oc->transporte_id ?? 0))),
            'penmp_cod_mon' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoMonedaAnita($ctx->monedaCabeceraId($oc))),
            'penmp_cotizacion' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($cotizacion),
            'penmp_estado' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->mapEstadoAnitaEntero((string) $oc->estadoordencompra)),
            'penmp_leyenda' => RecepcionProveedorAnitaEscrituraSupport::textoSql(substr($detalle, 0, 40), 40),
            'penmp_requisicion' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->numeroRequisicion((int) ($oc->requisicion_id ?? 0))),
            'penmp_ccosto' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoCentrocosto((int) ($oc->centrocosto_id ?? 0))),
            'penmp_ccosto_dest' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ccDest),
            'penmp_empresa' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoEmpresa((int) ($oc->empresa_id ?? 0))),
            'penmp_es_anticipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($ctx->mapTratamientoAnticipo((string) ($oc->tratamiento ?? '')), 1),
            'penmp_usuario_ini' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->usuarioAnitaCodigo((int) ($oc->creousuario_id ?? 0))),
            'penmp_fecha_ing' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) date('Ymd')),
            'penmp_hora_ing' => RecepcionProveedorAnitaEscrituraSupport::textoSql($ctx->horaActual(), 8),
            'penmp_estado_aprob' => RecepcionProveedorAnitaEscrituraSupport::charFijoSql(' ', 1),
            'penmp_legajo' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(0),
        ];

        if ($incluirClave) {
            $columnas = array_merge([
                'penmp_tipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['tipo'], 3),
                'penmp_letra' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['letra'], 1),
                'penmp_sucursal' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['sucursal']),
                'penmp_nro' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['nro']),
            ], $columnas);
        }

        return $columnas;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array<string, string>
     */
    private static function pendmovpColumnas(
        Ordencompra $oc,
        Ordencompra_Articulo $linea,
        OrdencompraAnitaErpContext $ctx,
        array $clave,
        string $codigoProveedor6,
    ): array {
        $fecha = $ctx->fechaYmd($oc->fecha);
        $fechaEnt = $ctx->fechaYmd($linea->fechaentrega ?? $oc->fechaentrega);
        $desc = trim((string) ($linea->detalle ?? ''));
        if ($desc === '') {
            $desc = ' ';
        }
        $deposito = (int) config('ordencompra_anita.escritura.deposito_default', 1);
        $pg = $ctx->datosPresupuestoLinea((int) ($linea->partidagasto_id ?? 0));

        return [
            'penvp_proveedor' => RecepcionProveedorAnitaEscrituraSupport::proveedorSql($codigoProveedor6),
            'penvp_tipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['tipo'], 3),
            'penvp_letra' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['letra'], 1),
            'penvp_sucursal' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['sucursal']),
            'penvp_nro' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['nro']),
            'penvp_orden' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) ($linea->penvp_orden ?? 0)),
            'penvp_articulo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($ctx->skuArticulo13SinPad((int) $linea->articulo_id), 13),
            'penvp_desc' => RecepcionProveedorAnitaEscrituraSupport::textoSql(substr($desc, 0, 30), 30),
            'penvp_agrupacion' => RecepcionProveedorAnitaEscrituraSupport::agrupacionSql($ctx->agrupacionArticulo((int) $linea->articulo_id)),
            'penvp_unidad_med' => RecepcionProveedorAnitaEscrituraSupport::textoSql($ctx->unidadMedidaArticulo((int) $linea->articulo_id), 3),
            'penvp_cantidad' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($linea->cantidad ?? 0)),
            'penvp_cantentr' => RecepcionProveedorAnitaEscrituraSupport::decimalSql(0),
            'penvp_cantfact' => RecepcionProveedorAnitaEscrituraSupport::decimalSql(0),
            'penvp_precio' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($linea->precio ?? 0)),
            'penvp_dto_art' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($linea->descuento ?? 0)),
            'penvp_deposito' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($deposito),
            'penvp_tipo_iva' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->tipoIvaArticulo((int) $linea->articulo_id)),
            'penvp_fecha' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fecha),
            'penvp_incl_imp' => RecepcionProveedorAnitaEscrituraSupport::textoSql('N', 1),
            'penvp_cod_mon' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoMonedaAnita((int) ($linea->moneda_id ?? 1))),
            'penvp_partida' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) ($pg['partida'] ?? 0)),
            'penvp_fecha_ent' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fechaEnt),
            'penvp_ccosto' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoCentrocosto((int) ($linea->centrocostodestino_id ?? $oc->centrocosto_id))),
            'penvp_requisicion' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->numeroRequisicion((int) ($oc->requisicion_id ?? 0))),
            'penvp_empresa' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoEmpresa((int) ($oc->empresa_id ?? 0))),
            'penvp_nro_interno' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) ($linea->penvp_nro_interno ?? 0)),
        ];
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array<string, string>
     */
    private static function movpresupColumnas(
        Ordencompra $oc,
        Ordencompra_Articulo $linea,
        OrdencompraAnitaErpContext $ctx,
        array $clave,
    ): array {
        $fecha = $ctx->fechaYmd($oc->fecha);
        $pg = $ctx->datosPresupuestoLinea((int) ($linea->partidagasto_id ?? 0));
        $cpx = $ctx->datosCapexLinea((int) ($linea->capex_id ?? 0));
        $cotizacion = (float) ($linea->cotizacion ?? 1);
        if ($cotizacion <= 0) {
            $cotizacion = 1.0;
        }

        return [
            'movp_tipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['tipo'], 3),
            'movp_letra' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['letra'], 1),
            'movp_sucursal' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['sucursal']),
            'movp_nro' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['nro']),
            'movp_nro_interno' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) ($linea->penvp_nro_interno ?? 0)),
            'movp_partida' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) ($pg['partida'] ?? 0)),
            'movp_presupuesto' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) ($pg['presupuesto'] ?? 0)),
            'movp_escenario' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) ($pg['escenario'] ?? 0)),
            'movp_proyecto' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) ($cpx['proyecto'] ?? 0)),
            'movp_mes' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(0),
            'movp_cod_proyecto' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) ($cpx['cod_proyecto'] ?? 0)),
            'movp_importe' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($ctx->importeLinea($linea)),
            'movp_articulo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($ctx->skuArticulo13SinPad((int) $linea->articulo_id), 13),
            'movp_fecha' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fecha),
            'movp_cotizacion' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($cotizacion),
        ];
    }
}
