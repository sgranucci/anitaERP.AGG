<?php

namespace App\Support\Compras\AnitaSync\Surmar;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaErpContext;
use App\Support\Compras\OrdencompraDescuentoSupport;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;

/**
 * Arma campos/valores SQL ERP → Anita Surmar (solo pendmaep / pendmovp).
 * No incluye columnas AGG (penmp_ccosto, movpresup, legcompra, etc.).
 */
final class OrdencompraSurmarAnitaEscrituraSupport
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
        $cotizacion = $ctx->cotizacionCabecera($oc);
        $razonSusp = '';
        if (strtoupper(trim((string) ($oc->estadoordencompra ?? ''))) === OrdencompraEstados::SUSPENDIDA) {
            $razonSusp = substr(trim((string) ($oc->comentario ?? $detalle)), 0, 40);
        }

        $columnas = [
            'penmp_proveedor' => RecepcionProveedorAnitaEscrituraSupport::proveedorSql($ctx->codigoProveedor6((int) $oc->proveedor_id)),
            'penmp_fecha' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fecha),
            'penmp_fecha_ent' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fechaEnt),
            'penmp_cond_compra' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoCondicioncompra((int) ($oc->condicioncompra_id ?? 0))),
            'penmp_cond_entrega' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoCondicionentrega((int) ($oc->condicionentrega_id ?? 0))),
            'penmp_cond_pago' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->condicionpagoCabecera($oc)),
            'penmp_entrega' => RecepcionProveedorAnitaEscrituraSupport::textoSql($lugarentrega !== '' ? $lugarentrega : ' ', 40),
            'penmp_dto' => RecepcionProveedorAnitaEscrituraSupport::decimalSql(
                OrdencompraDescuentoSupport::porcentajeEfectivoDesdeOrdencompra($oc)
            ),
            'penmp_expreso' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoTransporte((int) ($oc->transporte_id ?? 0))),
            'penmp_razon_susp' => RecepcionProveedorAnitaEscrituraSupport::textoSql($razonSusp !== '' ? $razonSusp : ' ', 40),
            'penmp_cod_mon' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoMonedaAnita($ctx->monedaCabeceraId($oc))),
            'penmp_cotizacion' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($cotizacion),
            'penmp_fecha_ing' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) date('Ymd')),
            'penmp_hora_ing' => RecepcionProveedorAnitaEscrituraSupport::textoSql($ctx->horaActual(), 8),
            'penmp_estado' => RecepcionProveedorAnitaEscrituraSupport::textoSql($ctx->mapEstadoAnita((string) $oc->estadoordencompra), 1),
            'penmp_leyenda' => RecepcionProveedorAnitaEscrituraSupport::textoSql(substr($detalle, 0, 40), 40),
            'penmp_requisicion' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->numeroRequisicion((int) ($oc->requisicion_id ?? 0))),
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
            $desc = $ctx->descripcionArticulo((int) $linea->articulo_id);
        }
        if ($desc === '') {
            $desc = ' ';
        }
        $deposito = (int) config('ordencompra_anita.escritura.deposito_default', 1);
        $pg = $ctx->datosPresupuestoLinea((int) ($linea->partidagasto_id ?? 0));
        $loteRaw = $linea->lote_transferencia ?? null;
        $lote = is_numeric($loteRaw) ? (int) $loteRaw : (int) preg_replace('/\D+/', '', (string) $loteRaw);

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
            'penvp_ccosto' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(
                $ctx->codigoCentrocosto((int) ($linea->centrocostodestino_id ?? $oc->centrocosto_id))
            ),
            'penvp_requisicion' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->numeroRequisicion((int) ($oc->requisicion_id ?? 0))),
            'penvp_empresa' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoEmpresa((int) ($oc->empresa_id ?? 0))),
            'penvp_nro_interno' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) ($linea->penvp_nro_interno ?? 0)),
            'penvp_lote_transf' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($lote),
            'penvp_peso_unit' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($linea->peso_unitario ?? 0)),
            'penvp_estado' => RecepcionProveedorAnitaEscrituraSupport::textoSql(' ', 1),
        ];
    }
}
