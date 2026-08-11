<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Ordencompra_Comprobante;
use App\Models\Compras\Ordencompra_Comprobante_Cuota;
use App\Support\Compras\OrdencompraDescuentoSupport;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use App\Support\Stock\SurmarSupport;

/**
 * Arma campos/valores SQL para pendmaep, pendmovp, movpresup, occuota y ocfpagocuota (ERP → Anita).
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
            'penmp_dto' => RecepcionProveedorAnitaEscrituraSupport::decimalSql(
                OrdencompraDescuentoSupport::porcentajeEfectivoDesdeOrdencompra($oc)
            ),
            'penmp_expreso' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoTransporte((int) ($oc->transporte_id ?? 0))),
            'penmp_cod_mon' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->codigoMonedaAnita($ctx->monedaCabeceraId($oc))),
            'penmp_cotizacion' => RecepcionProveedorAnitaEscrituraSupport::decimalSql($cotizacion),
            'penmp_estado' => RecepcionProveedorAnitaEscrituraSupport::textoSql($ctx->mapEstadoAnita((string) $oc->estadoordencompra), 1),
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
            $desc = $ctx->descripcionArticulo((int) $linea->articulo_id);
        }
        if ($desc === '') {
            $desc = ' ';
        }
        $deposito = (int) config('ordencompra_anita.escritura.deposito_default', 1);
        $pg = $ctx->datosPresupuestoLinea((int) ($linea->partidagasto_id ?? 0));

        $columnas = [
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

        // Surmar Informix: campos extra de línea (no se emiten en AGG).
        if (SurmarSupport::esEmpresaSurmar((int) ($oc->empresa_id ?? 0))) {
            $loteRaw = $linea->lote_transferencia ?? null;
            $lote = is_numeric($loteRaw) ? (int) $loteRaw : (int) preg_replace('/\D+/', '', (string) $loteRaw);
            $columnas['penvp_lote_transf'] = RecepcionProveedorAnitaEscrituraSupport::enteroSql($lote);
            $columnas['penvp_peso_unit'] = RecepcionProveedorAnitaEscrituraSupport::decimalSql(
                (float) ($linea->peso_unitario ?? 0)
            );
        }

        return $columnas;
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

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}
     */
    public static function occuotaInsert(
        Ordencompra_Comprobante $comprobante,
        OrdencompraAnitaErpContext $ctx,
        array $clave,
        int $nroCuota,
        ?Ordencompra $oc = null,
    ): array {
        $detalle = trim((string) ($comprobante->detalle ?? ''));
        if ($detalle === '') {
            $detalle = trim((string) ($comprobante->tipocomprobante ?? 'FACTURA'));
        }
        $cuotas = $comprobante->ordencompra_comprobante_cuotas ?? collect();
        $primeraCuota = $cuotas->sortBy('id')->first();
        $medioPago = $ctx->medioPagoAnitaDesdeFormapago(
            $primeraCuota ? (int) ($primeraCuota->formapago_id ?? 0) : null
        );

        $condPago = $ctx->codigoCondicionpago((int) ($comprobante->condicionpago_id ?? 0));
        if ($condPago <= 0 && $oc !== null) {
            $condPago = $ctx->condicionpagoCabecera($oc);
        }

        return RecepcionProveedorAnitaEscrituraSupport::insert([
            'occ_tipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['tipo'], 3),
            'occ_letra' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['letra'], 1),
            'occ_sucursal' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['sucursal']),
            'occ_nro' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['nro']),
            'occ_nro_cuota' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($nroCuota),
            'occ_fecha_vto' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->fechaYmd($comprobante->fechavencimiento)),
            'occ_monto' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($comprobante->monto ?? 0)),
            'occ_cond_pago' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($condPago),
            'occ_medio_pago' => RecepcionProveedorAnitaEscrituraSupport::textoSql($medioPago, 1),
            'occ_detalle' => RecepcionProveedorAnitaEscrituraSupport::textoSql(substr($detalle, 0, 50), 50),
        ]);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}
     */
    public static function ocfpagocuotaInsert(
        Ordencompra_Comprobante_Cuota $cuota,
        OrdencompraAnitaErpContext $ctx,
        array $clave,
        int $nroCuotaOcc,
        int $nroCuotaFpago,
    ): array {
        return RecepcionProveedorAnitaEscrituraSupport::insert([
            'ocfp_tipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['tipo'], 3),
            'ocfp_letra' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['letra'], 1),
            'ocfp_sucursal' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['sucursal']),
            'ocfp_nro' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['nro']),
            'ocfp_nro_cuota' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($nroCuotaOcc),
            'ocfp_cuota_fpago' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($nroCuotaFpago),
            'ocfp_fecha_vto' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->fechaYmd($cuota->fechavencimiento)),
            'ocfp_monto' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($cuota->monto ?? 0)),
        ]);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}
     */
    public static function ocfpagocuotaInsertDesdeArray(
        array $cuota,
        array $clave,
        int $nroCuotaOcc,
        int $nroCuotaFpago,
        OrdencompraAnitaErpContext $ctx,
    ): array {
        return RecepcionProveedorAnitaEscrituraSupport::insert([
            'ocfp_tipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['tipo'], 3),
            'ocfp_letra' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['letra'], 1),
            'ocfp_sucursal' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['sucursal']),
            'ocfp_nro' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['nro']),
            'ocfp_nro_cuota' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($nroCuotaOcc),
            'ocfp_cuota_fpago' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($nroCuotaFpago),
            'ocfp_fecha_vto' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($ctx->fechaYmd($cuota['fechavencimiento'] ?? null)),
            'ocfp_monto' => RecepcionProveedorAnitaEscrituraSupport::decimalSql((float) ($cuota['monto'] ?? 0)),
        ]);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return list<array{campos: string, valores: string}>
     */
    public static function ocvleyInsertsDesdeLinea(
        Ordencompra_Articulo $linea,
        array $clave,
    ): array {
        $texto = trim((string) ($linea->detalle ?? ''));
        if ($texto === '' || mb_strlen($texto) <= 30) {
            return [];
        }

        $nroOrden = (int) ($linea->penvp_orden ?? 0);
        $partes = mb_str_split(mb_substr($texto, 30), 50);
        $inserts = [];
        foreach ($partes as $idx => $parte) {
            if (trim($parte) === '') {
                continue;
            }
            $inserts[] = RecepcionProveedorAnitaEscrituraSupport::insert([
                'ocvl_tipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['tipo'], 3),
                'ocvl_letra' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['letra'], 1),
                'ocvl_sucursal' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['sucursal']),
                'ocvl_nro' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['nro']),
                'ocvl_nro_orden' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($nroOrden),
                'ocvl_linea' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($idx),
                'ocvl_leyenda' => RecepcionProveedorAnitaEscrituraSupport::textoSql($parte, 50),
            ]);
        }

        return $inserts;
    }

    /**
     * @return array{campos: string, valores: string}
     */
    public static function legcompraInsert(
        int $numeroOc,
        OrdencompraAnitaErpContext $ctx,
        string $estadoSector,
        string $observacion,
        ?int $fechaYmd = null,
        ?string $hora = null,
    ): array {
        $fecha = $fechaYmd ?? (int) date('Ymd');
        $horaTxt = $hora ?? $ctx->horaActual();

        return RecepcionProveedorAnitaEscrituraSupport::insert([
            'legc_id' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($numeroOc),
            'legc_fecha' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fecha),
            'legc_hora' => RecepcionProveedorAnitaEscrituraSupport::textoSql($horaTxt, 8),
            'legc_usuario' => RecepcionProveedorAnitaEscrituraSupport::textoSql($ctx->usuarioAnitaLogin(), 15),
            'legc_estado' => RecepcionProveedorAnitaEscrituraSupport::textoSql($estadoSector, 1),
            'legc_observacion' => RecepcionProveedorAnitaEscrituraSupport::textoSql(substr($observacion, 0, 160), 160),
            'legc_id_carga' => RecepcionProveedorAnitaEscrituraSupport::enteroSql(0),
        ]);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{campos: string, valores: string}
     */
    public static function pendfechaInsert(
        array $clave,
        string $proveedor6,
        int $fechaFactura,
        int $fechaPago,
    ): array {
        return RecepcionProveedorAnitaEscrituraSupport::insert([
            'penpf_proveedor' => RecepcionProveedorAnitaEscrituraSupport::proveedorSql($proveedor6),
            'penpf_tipo' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['tipo'], 3),
            'penpf_letra' => RecepcionProveedorAnitaEscrituraSupport::textoSql($clave['letra'], 1),
            'penpf_sucursal' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['sucursal']),
            'penpf_nro' => RecepcionProveedorAnitaEscrituraSupport::enteroSql((int) $clave['nro']),
            'penpf_fecha_fac' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fechaFactura),
            'penpf_fecha_pago' => RecepcionProveedorAnitaEscrituraSupport::enteroSql($fechaPago),
        ]);
    }

    /** Sector legajo Anita compras (LEGC_COMPRAS). */
    public static function sectorLegajoCompras(): string
    {
        return 'C';
    }

    /**
     * @return array{fecha_fac: int, fecha_pago: int}
     */
    public static function fechasPendfechaDesdeOc(
        Ordencompra $oc,
        OrdencompraAnitaErpContext $ctx,
    ): array {
        $fechaFac = $ctx->fechaYmd($oc->fecha);
        $fechaPago = 0;

        foreach ($oc->ordencompra_comprobantes ?? [] as $comprobante) {
            $cuotas = $comprobante->ordencompra_comprobante_cuotas ?? collect();
            if ($cuotas->isEmpty()) {
                $cuotasExpandidas = OrdencompraAnitaOcfpagoCuotaExpander::desdeComprobante($comprobante);
                foreach ($cuotasExpandidas as $cuota) {
                    $fv = $ctx->fechaYmd($cuota['fechavencimiento'] ?? null);
                    if ($fv > 0 && ($fechaPago === 0 || $fv < $fechaPago)) {
                        $fechaPago = $fv;
                    }
                }
            } else {
                foreach ($cuotas as $cuota) {
                    $fv = $ctx->fechaYmd($cuota->fechavencimiento);
                    if ($fv > 0 && ($fechaPago === 0 || $fv < $fechaPago)) {
                        $fechaPago = $fv;
                    }
                }
            }

            if ($fechaPago === 0) {
                $fv = $ctx->fechaYmd($comprobante->fechavencimiento);
                if ($fv > 0 && ($fechaPago === 0 || $fv < $fechaPago)) {
                    $fechaPago = $fv;
                }
            }
        }

        return [
            'fecha_fac' => $fechaFac,
            'fecha_pago' => $fechaPago,
        ];
    }
}
