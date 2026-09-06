<?php

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/**
 * Lector del Mayor por concepto que arma los datos desde las tablas del ERP (MySQL) en vez
 * del bridge a Informix, proyectándolos con los nombres de campo de Anita (subd_*, ctav_*,
 * axp_*, ctaco_*, prom_*) para que MayorConceptoPeriodoProcesador no note la diferencia.
 *
 * El driver del reporte es el subdiario, que es el diario operativo de Anita: una fila por
 * pierna contable, con el documento que la generó y el documento referenciado. El ERP no
 * escribe subdiario en Anita (AsientoRepository sólo graba ctamov), pero sí guarda en
 * `asiento` los metadatos equivalentes (anita_tipo/letra/sucursal/nro/sistema/emisor), que
 * es de donde sale acá la cabecera de cada fila.
 *
 * ATENCIÓN — todavía no validado contra Anita. Los períodos con datos completos en el ERP son
 * los que informa `contable:cobertura-erp`; fuera de ahí este lector devuelve de menos, y de
 * menos en un reporte contable se lee como un descuadre, no como un hueco. Por eso el selector
 * (MayorConceptoLectorSelector) sólo lo usa hasta la fecha de corte configurada.
 */
class MayorConceptoErpReader implements MayorConceptoLectorInterface
{
    /** Piernas de gasto: todo lo que no es pasivo/disponibilidad/IVA se imputa. */
    private int $fallosLectura = 0;

    /** @var array<int, array{subdiario: list<object>, ctamov: list<object>, auxpag: list<object>, ctaconc: list<object>, promae: list<object>, errores: list<string>}> */
    private array $periodoCache = [];

    /** @var array<string, list<object>> */
    private array $comCache = [];

    /** @var array<string, ?object> */
    private array $promaeCache = [];

    public function precargarPeriodoEmpresas(array $empresaIds, int $fechaDesde, int $fechaHasta): void
    {
        foreach ($empresaIds as $empresaId) {
            $this->cargarPeriodo((int) $empresaId, $fechaDesde, $fechaHasta);
        }
    }

    public function cargarPeriodo(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        $clave = $empresaId.'|'.$fechaDesde.'|'.$fechaHasta;
        if (isset($this->periodoCache[$clave])) {
            return $this->periodoCache[$clave];
        }

        $errores = [];
        $desde = self::ymdAFecha($fechaDesde);
        $hasta = self::ymdAFecha($fechaHasta);

        $movimientos = $this->movimientosAsiento($empresaId, $desde, $hasta);

        $subdiario = [];
        $ctamov = [];
        foreach ($movimientos as $mov) {
            $subdiario[] = $this->filaSubdiario($mov);
            $ctamov[] = $this->filaCtamov($mov);
        }

        $resultado = [
            'subdiario' => $subdiario,
            'ctamov' => $ctamov,
            'auxpag' => $this->auxpagPeriodo($empresaId, $desde, $hasta),
            'ctaconc' => $this->ctaconc($empresaId),
            'promae' => [],
            'errores' => $errores,
        ];

        return $this->periodoCache[$clave] = $resultado;
    }

    /**
     * Piernas contables del período con la cabecera del documento que las originó.
     *
     * @return list<object>
     */
    private function movimientosAsiento(int $empresaId, string $desde, string $hasta): array
    {
        return DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->leftJoin('centrocosto as ce', 'ce.id', '=', 'am.centrocosto_id')
            ->leftJoin('moneda as mo', 'mo.id', '=', 'am.moneda_id')
            ->leftJoin('tipoasiento as ta', 'ta.id', '=', 'a.tipoasiento_id')
            ->leftJoin('pagoproveedor as pp', 'pp.id', '=', 'a.pagoproveedor_id')
            ->leftJoin('proveedor as ppr', 'ppr.id', '=', 'pp.proveedor_id')
            ->leftJoin('comprobante_proveedor as cp', 'cp.id', '=', 'a.comprobante_proveedor_id')
            ->leftJoin('proveedor as cpr', 'cpr.id', '=', 'cp.proveedor_id')
            ->where('a.empresa_id', $empresaId)
            ->whereBetween('a.fecha', [$desde, $hasta])
            ->select([
                'a.id as asiento_id',
                'a.empresa_id',
                'a.fecha',
                'a.numeroasiento',
                'a.observacion',
                'a.anita_nro_asiento',
                'a.anita_sistema',
                'a.anita_tipo',
                'a.anita_letra',
                'a.anita_sucursal',
                'a.anita_nro',
                'a.anita_emisor',
                'am.id as movimiento_id',
                'am.monto',
                'am.cotizacion',
                'am.observacion as mov_observacion',
                'cc.codigo as cuenta_codigo',
                'ce.codigo as ccosto_codigo',
                'mo.codigo as moneda_codigo',
                'ta.abreviatura as tipoasiento',
                'pp.tipocomprobante as pp_tipo',
                'pp.letra as pp_letra',
                'pp.sucursal as pp_sucursal',
                'pp.numerotransaccion as pp_nro',
                'ppr.codigo as pp_proveedor',
                'cp.letra as cp_letra',
                'cp.sucursal as cp_sucursal',
                'cp.numerocomprobante as cp_nro',
                'cp.anita_nro_interno as cp_nro_interno',
                'cpr.codigo as cp_proveedor',
            ])
            ->orderBy('a.fecha')
            ->orderBy('a.numeroasiento')
            ->orderBy('am.id')
            ->get()
            ->all();
    }

    /**
     * Fila de subdiario: documento + referencia + pierna contable.
     *
     * La referencia es el documento imputable (la OP que se paga o que se anula). El motor
     * arranca filtrando subd_ref_tipo contra TIPOS_REF_IMPUTABLE, así que sin ref no hay
     * reporte: en una emisión OPP el documento y la referencia son el mismo OP, y en una
     * anulación el documento es el AOP pero la referencia sigue siendo el OP anulado.
     */
    private function filaSubdiario(object $m): object
    {
        $doc = $this->documento($m);
        $monto = (float) $m->monto;

        return (object) [
            'subd_empresa' => (int) $m->empresa_id,
            'subd_sistema' => trim((string) ($m->anita_sistema ?? '')),
            'subd_fecha' => self::fechaAYmd($m->fecha),
            'subd_tipo' => $doc['tipo'],
            'subd_letra' => $doc['letra'],
            'subd_sucursal' => $doc['sucursal'],
            'subd_nro' => $doc['nro'],
            'subd_emisor' => $doc['emisor'],
            'subd_tipo_mov' => $monto >= 0 ? 'D' : 'H',
            'subd_cuenta' => self::soloDigitos($m->cuenta_codigo),
            // El ERP no guarda contrapartida por renglón: el asiento la deja implícita en el
            // conjunto de piernas. El motor la usa sólo para desempatar, no para importes.
            'subd_contrapartida' => 0,
            'subd_nro_operacion' => (int) $m->asiento_id,
            'subd_ref_tipo' => $doc['ref_tipo'],
            'subd_ref_letra' => $doc['ref_letra'],
            'subd_ref_sucursal' => $doc['ref_sucursal'],
            'subd_ref_nro' => $doc['ref_nro'],
            'subd_importe' => abs($monto),
            'subd_cod_mon' => trim((string) ($m->moneda_codigo ?? '')),
            'subd_cotizacion' => (float) ($m->cotizacion ?? 1),
            'subd_nro_asiento' => (int) ($m->anita_nro_asiento ?: $m->numeroasiento),
            'subd_nro_interno' => (int) ($m->cp_nro_interno ?? 0),
            'subd_ccosto_cta' => self::soloDigitos($m->ccosto_codigo),
            'subd_ccosto_con' => self::soloDigitos($m->ccosto_codigo),
            'subd_desc_mov' => trim((string) ($m->mov_observacion ?: $m->observacion)),
        ];
    }

    private function filaCtamov(object $m): object
    {
        $doc = $this->documento($m);
        $monto = (float) $m->monto;

        return (object) [
            'ctav_empresa' => (int) $m->empresa_id,
            'ctav_nro_asiento' => (int) ($m->anita_nro_asiento ?: $m->numeroasiento),
            'ctav_nro_linea' => (int) $m->movimiento_id,
            'ctav_d_h' => $monto >= 0 ? 'D' : 'H',
            'ctav_cuenta' => self::soloDigitos($m->cuenta_codigo),
            'ctav_fecha' => self::fechaAYmd($m->fecha),
            'ctav_tipo' => $doc['tipo'],
            'ctav_letra' => $doc['letra'],
            'ctav_sucursal' => $doc['sucursal'],
            'ctav_nro' => $doc['nro'],
            'ctav_importe' => abs($monto),
            'ctav_cotizacion' => (float) ($m->cotizacion ?? 1),
            'ctav_cod_mon' => trim((string) ($m->moneda_codigo ?? '')),
            'ctav_sistema' => trim((string) ($m->anita_sistema ?? '')),
            'ctav_tipo_asiento' => trim((string) ($m->tipoasiento ?? '')),
            'ctav_ccosto' => self::soloDigitos($m->ccosto_codigo),
            'ctav_o_compra' => 0,
            'ctav_desc_mov' => trim((string) ($m->mov_observacion ?: $m->observacion)),
        ];
    }

    /**
     * Cabecera del documento. Prioriza el pago y la factura por sobre los metadatos anita_*,
     * porque en los asientos nacidos en el ERP esos metadatos vienen vacíos.
     *
     * @return array{tipo: string, letra: string, sucursal: int, nro: int, emisor: string, ref_tipo: string, ref_letra: string, ref_sucursal: int, ref_nro: int}
     */
    private function documento(object $m): array
    {
        if (filled($m->pp_tipo ?? null)) {
            $tipo = strtoupper(trim((string) $m->pp_tipo));
            $nro = (int) $m->pp_nro;
            $letra = trim((string) ($m->pp_letra ?? ''));
            $suc = (int) ($m->pp_sucursal ?? 0);

            // El AOP referencia al OP anulado: mismo número, tipo original OPP.
            $refTipo = $tipo === 'AOP' ? 'OPP' : $tipo;

            return [
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => $suc,
                'nro' => $nro,
                'emisor' => trim((string) ($m->pp_proveedor ?? '')),
                'ref_tipo' => $refTipo,
                'ref_letra' => $tipo === 'AOP' ? '' : $letra,
                'ref_sucursal' => $suc,
                'ref_nro' => $nro,
            ];
        }

        if (filled($m->cp_nro ?? null)) {
            $tipo = strtoupper(trim((string) ($m->anita_tipo ?? '')));
            $letra = trim((string) ($m->cp_letra ?? ''));
            $suc = (int) ($m->cp_sucursal ?? 0);
            $nro = (int) $m->cp_nro;

            return [
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => $suc,
                'nro' => $nro,
                'emisor' => trim((string) ($m->cp_proveedor ?? '')),
                'ref_tipo' => $tipo,
                'ref_letra' => $letra,
                'ref_sucursal' => $suc,
                'ref_nro' => $nro,
            ];
        }

        $tipo = strtoupper(trim((string) ($m->anita_tipo ?? '')));
        $letra = trim((string) ($m->anita_letra ?? ''));
        $suc = (int) ($m->anita_sucursal ?? 0);
        $nro = (int) ($m->anita_nro ?? 0);

        return [
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $suc,
            'nro' => $nro,
            'emisor' => trim((string) ($m->anita_emisor ?? '')),
            'ref_tipo' => $tipo,
            'ref_letra' => $letra,
            'ref_sucursal' => $suc,
            'ref_nro' => $nro,
        ];
    }

    /**
     * Aplicaciones de pago del período (equivalente de auxpag).
     *
     * @return list<object>
     */
    private function auxpagPeriodo(int $empresaId, string $desde, string $hasta): array
    {
        $filas = DB::table('proveedor_cuentacorriente_aplicacion as ap')
            ->join('pagoproveedor as pp', 'pp.id', '=', 'ap.pagoproveedor_id')
            ->join('proveedor as pr', 'pr.id', '=', 'pp.proveedor_id')
            ->leftJoin('comprobante_proveedor as cp', 'cp.id', '=', 'ap.comprobante_proveedor_aplicado_id')
            ->leftJoin('tipotransaccion_compra as tc', 'tc.id', '=', 'cp.tipotransaccion_compra_id')
            ->leftJoin('moneda as mo', 'mo.id', '=', 'ap.moneda_id')
            ->where('pp.empresa_id', $empresaId)
            ->whereBetween('pp.fecha', [$desde, $hasta])
            ->select([
                'pr.codigo as proveedor',
                'pp.fecha',
                'pp.numerotransaccion',
                'pp.tipocomprobante',
                'pp.empresa_id',
                'ap.total',
                'cp.letra as cp_letra',
                'cp.sucursal as cp_sucursal',
                'cp.numerocomprobante as cp_nro',
                'cp.anita_nro_interno as cp_nro_interno',
                'cp.conceptogasto_id as cp_concepto',
                'tc.abreviatura as cp_tipo',
                'mo.codigo as moneda_codigo',
            ])
            // Una OP puede tener varias líneas de cuenta de caja; con join se duplicarían las
            // aplicaciones y el importe aplicado saldría al doble. Subconsulta escalar.
            ->selectSub(
                DB::table('caja_movimiento_cuentacaja as mc')
                    ->join('cuentacaja as cj', 'cj.id', '=', 'mc.cuentacaja_id')
                    ->join('cuentacontable as cb', 'cb.id', '=', 'cj.cuentacontable_id')
                    ->whereColumn('mc.caja_movimiento_id', 'pp.caja_movimiento_id')
                    ->select('cb.codigo')
                    ->limit(1),
                'caja_codigo',
            )
            ->get();

        $salida = [];
        foreach ($filas as $f) {
            $salida[] = (object) [
                'axp_pro' => trim((string) $f->proveedor),
                'axp_fecha' => self::fechaAYmd($f->fecha),
                'axp_rec' => (int) $f->numerotransaccion,
                'axp_tipo' => strtoupper(trim((string) $f->tipocomprobante)),
                'axp_nro' => (int) ($f->cp_nro ?? 0),
                'axp_tipo_ap' => strtoupper(trim((string) ($f->cp_tipo ?? ''))),
                'axp_monto_ap' => (float) $f->total,
                'axp_cod_mon_co' => trim((string) ($f->moneda_codigo ?? '')),
                'axp_sucursal' => (int) ($f->cp_sucursal ?? 0),
                'axp_empresa' => (int) $f->empresa_id,
                'axp_letra_comp' => trim((string) ($f->cp_letra ?? '')),
                'axp_nro_interno' => (int) ($f->cp_nro_interno ?? 0),
                'axp_banco' => self::soloDigitos($f->caja_codigo),
                'axp_concepto' => (int) ($f->cp_concepto ?? 0),
            ];
        }

        return $salida;
    }

    /**
     * Concepto de cash-flow por cuenta contable.
     *
     * @return list<object>
     */
    private function ctaconc(int $empresaId): array
    {
        $filas = DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('conceptogasto_id')
            ->select(['codigo', 'conceptogasto_id'])
            ->get();

        $salida = [];
        foreach ($filas as $f) {
            $salida[] = (object) [
                'ctaco_empresa' => $empresaId,
                'ctaco_cuenta' => self::soloDigitos($f->codigo),
                'ctaco_concepto' => (int) $f->conceptogasto_id,
            ];
        }

        return $salida;
    }

    public function cargarAuxpagHistorico(
        int $empresaId,
        string $tipo,
        int $rec,
        int $fecha,
        string $proveedor,
        int $sucursalOp,
        array &$errores,
    ): array {
        // Anita mueve las aplicaciones saldadas a axphist; el ERP no archiva, las deja en
        // proveedor_cuentacorriente_aplicacion. La lectura del período ya las trae.
        return [];
    }

    public function cargarComSubdiario(int $empresaId, string $tipo, string $letra, int $sucursal, int $nro, array &$errores): array
    {
        $clave = $tipo.'|'.$letra.'|'.$sucursal.'|'.$nro;
        $lote = $this->cargarComSubdiarioLote($empresaId, [$clave], $errores);

        return $lote[$clave] ?? [];
    }

    /**
     * Renglones de imputación de gasto de cada factura.
     *
     * En Anita el gasto vive en el registro COM (la recepción); en el ERP vive en las piernas
     * del asiento de la propia factura, que es el mismo dato con otra forma.
     */
    public function cargarComSubdiarioLote(int $empresaId, array $clavesCom, array &$errores): array
    {
        $salida = [];
        $pendientes = [];
        foreach ($clavesCom as $clave) {
            if (isset($this->comCache[$empresaId.'|'.$clave])) {
                $salida[$clave] = $this->comCache[$empresaId.'|'.$clave];

                continue;
            }
            $salida[$clave] = [];
            $pendientes[] = $clave;
        }

        if ($pendientes === []) {
            return $salida;
        }

        $query = DB::table('comprobante_proveedor as cp')
            ->join('asiento as a', 'a.id', '=', 'cp.asiento_id')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->leftJoin('centrocosto as ce', 'ce.id', '=', 'am.centrocosto_id')
            ->leftJoin('moneda as mo', 'mo.id', '=', 'am.moneda_id')
            ->leftJoin('tipotransaccion_compra as tc', 'tc.id', '=', 'cp.tipotransaccion_compra_id')
            ->where('cp.empresa_id', $empresaId);

        $query->where(function ($q) use ($pendientes) {
            foreach ($pendientes as $clave) {
                [$tipo, $letra, $sucursal, $nro] = array_pad(explode('|', (string) $clave), 4, '');
                $q->orWhere(function ($sub) use ($tipo, $letra, $sucursal, $nro) {
                    $sub->where('tc.abreviatura', $tipo)
                        ->where('cp.letra', $letra)
                        ->where('cp.sucursal', (int) $sucursal)
                        ->where('cp.numerocomprobante', (int) $nro);
                });
            }
        });

        $filas = $query->select([
            'tc.abreviatura as tipo',
            'cp.letra',
            'cp.sucursal',
            'cp.numerocomprobante as nro',
            'cp.anita_nro_interno as nro_interno',
            'cp.empresa_id',
            'a.fecha',
            'a.anita_sistema',
            'am.monto',
            'am.cotizacion',
            'am.observacion',
            'cc.codigo as cuenta_codigo',
            'ce.codigo as ccosto_codigo',
            'mo.codigo as moneda_codigo',
        ])->get();

        foreach ($filas as $f) {
            $clave = trim((string) $f->tipo).'|'.trim((string) $f->letra).'|'.((int) $f->sucursal).'|'.((int) $f->nro);
            $monto = (float) $f->monto;
            $salida[$clave][] = (object) [
                'subd_empresa' => (int) $f->empresa_id,
                'subd_sistema' => trim((string) ($f->anita_sistema ?? '')),
                'subd_fecha' => self::fechaAYmd($f->fecha),
                'subd_tipo' => trim((string) $f->tipo),
                'subd_letra' => trim((string) $f->letra),
                'subd_sucursal' => (int) $f->sucursal,
                'subd_nro' => (int) $f->nro,
                'subd_tipo_mov' => $monto >= 0 ? 'D' : 'H',
                'subd_cuenta' => self::soloDigitos($f->cuenta_codigo),
                'subd_contrapartida' => 0,
                'subd_importe' => abs($monto),
                'subd_nro_interno' => (int) ($f->nro_interno ?? 0),
                'subd_cod_mon' => trim((string) ($f->moneda_codigo ?? '')),
                'subd_cotizacion' => (float) ($f->cotizacion ?? 1),
                'subd_ccosto_cta' => self::soloDigitos($f->ccosto_codigo),
                'subd_ccosto_con' => self::soloDigitos($f->ccosto_codigo),
                'subd_desc_mov' => trim((string) ($f->observacion ?? '')),
            ];
        }

        foreach ($pendientes as $clave) {
            $this->comCache[$empresaId.'|'.$clave] = $salida[$clave] ?? [];
        }

        return $salida;
    }

    public function cargarSubdiarioFacturaCompras(
        int $empresaId,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        int $nroInterno,
        string $proveedor,
        array &$errores,
    ): array {
        return $this->cargarComSubdiario($empresaId, $tipo, $letra, $sucursal, $nro, $errores);
    }

    public function cargarCtamovPorAsiento(int $empresaId, int $nroAsiento, array &$errores): array
    {
        $movimientos = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->leftJoin('centrocosto as ce', 'ce.id', '=', 'am.centrocosto_id')
            ->leftJoin('moneda as mo', 'mo.id', '=', 'am.moneda_id')
            ->leftJoin('tipoasiento as ta', 'ta.id', '=', 'a.tipoasiento_id')
            ->leftJoin('pagoproveedor as pp', 'pp.id', '=', 'a.pagoproveedor_id')
            ->leftJoin('proveedor as ppr', 'ppr.id', '=', 'pp.proveedor_id')
            ->leftJoin('comprobante_proveedor as cp', 'cp.id', '=', 'a.comprobante_proveedor_id')
            ->leftJoin('proveedor as cpr', 'cpr.id', '=', 'cp.proveedor_id')
            ->where('a.empresa_id', $empresaId)
            ->where(function ($q) use ($nroAsiento) {
                $q->where('a.anita_nro_asiento', $nroAsiento)
                    ->orWhere(function ($sub) use ($nroAsiento) {
                        $sub->whereNull('a.anita_nro_asiento')->where('a.numeroasiento', $nroAsiento);
                    });
            })
            ->select([
                'a.id as asiento_id', 'a.empresa_id', 'a.fecha', 'a.numeroasiento', 'a.observacion',
                'a.anita_nro_asiento', 'a.anita_sistema', 'a.anita_tipo', 'a.anita_letra',
                'a.anita_sucursal', 'a.anita_nro', 'a.anita_emisor',
                'am.id as movimiento_id', 'am.monto', 'am.cotizacion', 'am.observacion as mov_observacion',
                'cc.codigo as cuenta_codigo', 'ce.codigo as ccosto_codigo', 'mo.codigo as moneda_codigo',
                'ta.abreviatura as tipoasiento',
                'pp.tipocomprobante as pp_tipo', 'pp.letra as pp_letra', 'pp.sucursal as pp_sucursal',
                'pp.numerotransaccion as pp_nro', 'ppr.codigo as pp_proveedor',
                'cp.letra as cp_letra', 'cp.sucursal as cp_sucursal', 'cp.numerocomprobante as cp_nro',
                'cp.anita_nro_interno as cp_nro_interno', 'cpr.codigo as cp_proveedor',
            ])
            ->get();

        $salida = [];
        foreach ($movimientos as $m) {
            $salida[] = $this->filaCtamov($m);
        }

        return $salida;
    }

    public function cargarAplicpedFactura(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        array &$errores,
    ): array {
        return $this->cargarAplicpedPorFacturas([[$proveedor, $tipo, $letra, $sucursal, $nro]], $errores);
    }

    public function cargarAplicpedPorReferencia(
        string $refTipo,
        string $refLetra,
        int $refSucursal,
        int $refNro,
        string $proveedor,
        array &$errores,
    ): array {
        return $this->cargarAplicpedPorFacturas([[$proveedor, $refTipo, $refLetra, $refSucursal, $refNro]], $errores);
    }

    /**
     * Relación factura -> orden de compra. En el ERP sale del FK ordencompra_id del
     * comprobante, no de una tabla de aplicación como el aplicped de Anita.
     */
    public function cargarAplicpedPorFacturas(array $facturas, array &$errores): array
    {
        if ($facturas === []) {
            return [];
        }

        $query = DB::table('comprobante_proveedor as cp')
            ->join('proveedor as pr', 'pr.id', '=', 'cp.proveedor_id')
            ->join('ordencompra as oc', 'oc.id', '=', 'cp.ordencompra_id')
            ->leftJoin('tipotransaccion_compra as tc', 'tc.id', '=', 'cp.tipotransaccion_compra_id');

        $query->where(function ($q) use ($facturas) {
            foreach ($facturas as $f) {
                $q->orWhere(function ($sub) use ($f) {
                    $sub->where('pr.codigo', trim((string) ($f[0] ?? '')))
                        ->where('tc.abreviatura', trim((string) ($f[1] ?? '')))
                        ->where('cp.letra', trim((string) ($f[2] ?? '')))
                        ->where('cp.sucursal', (int) ($f[3] ?? 0))
                        ->where('cp.numerocomprobante', (int) ($f[4] ?? 0));
                });
            }
        });

        $filas = $query->select([
            'pr.codigo as proveedor',
            'tc.abreviatura as tipo',
            'cp.letra',
            'cp.sucursal',
            'cp.numerocomprobante as nro',
            'oc.numeroordencompra as oc_numero',
        ])->get();

        $salida = [];
        foreach ($filas as $f) {
            $salida[] = (object) [
                'apli_proveedor' => trim((string) $f->proveedor),
                'apli_tipo' => trim((string) ($f->tipo ?? '')),
                'apli_letra' => trim((string) $f->letra),
                'apli_sucursal' => (int) $f->sucursal,
                'apli_nro' => (int) $f->nro,
                'apli_o_compra' => (int) ($f->oc_numero ?? 0),
            ];
        }

        return $salida;
    }

    public function cargarPromae(string $proveedor, array &$errores): ?object
    {
        $prov = trim($proveedor);
        if (array_key_exists($prov, $this->promaeCache)) {
            return $this->promaeCache[$prov];
        }

        $lista = $this->cargarPromaePorProveedores([$prov], $errores);

        return $this->promaeCache[$prov] = ($lista[0] ?? null);
    }

    public function cargarPromaePorProveedores(array $proveedores, array &$errores): array
    {
        $codigos = array_values(array_filter(array_map('trim', $proveedores), fn ($c) => $c !== ''));
        if ($codigos === []) {
            return [];
        }

        $filas = DB::table('proveedor as pr')
            ->leftJoin('condicioniva as ci', 'ci.id', '=', 'pr.condicioniva_id')
            ->whereIn('pr.codigo', $codigos)
            ->select(['pr.codigo', 'pr.nombre', 'pr.nroinscripcion', 'ci.coniva as cond_iva'])
            ->get();

        $salida = [];
        foreach ($filas as $f) {
            $fila = (object) [
                'prom_proveedor' => trim((string) $f->codigo),
                'prom_nombre' => trim((string) $f->nombre),
                'prom_cuit' => trim((string) ($f->nroinscripcion ?? '')),
                'prom_cond_iva' => trim((string) ($f->cond_iva ?? '')),
            ];
            $salida[] = $fila;
            $this->promaeCache[$fila->prom_proveedor] = $fila;
        }

        return $salida;
    }

    public function conceptoDesdeOrdenCompra(int $empresaId, int $nroOc, array &$errores): int
    {
        if ($nroOc <= 0) {
            return 0;
        }

        // Los renglones de la OC del ERP no imputan cuenta contable (la imputación aparece
        // recién en la factura), así que sólo se puede resolver el contrato, que sí la lleva.
        // 0 = no resuelto, igual que Anita cuando no encuentra la OC.
        $concepto = DB::table('ordencompra as oc')
            ->join('cuentacontable as cc', 'cc.id', '=', 'oc.contrato_cuentacontable_id')
            ->where('oc.empresa_id', $empresaId)
            ->where('oc.numeroordencompra', $nroOc)
            ->whereNotNull('cc.conceptogasto_id')
            ->value('cc.conceptogasto_id');

        return (int) ($concepto ?? 0);
    }

    public function fallosLectura(): int
    {
        return $this->fallosLectura;
    }

    private static function soloDigitos(mixed $valor): int
    {
        return (int) preg_replace('/\D/', '', (string) ($valor ?? ''));
    }

    private static function fechaAYmd(mixed $fecha): int
    {
        $txt = trim((string) ($fecha ?? ''));
        if ($txt === '') {
            return 0;
        }

        return (int) preg_replace('/\D/', '', substr($txt, 0, 10));
    }

    private static function ymdAFecha(int $ymd): string
    {
        $txt = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($txt, 0, 4).'-'.substr($txt, 4, 2).'-'.substr($txt, 6, 2);
    }
}
