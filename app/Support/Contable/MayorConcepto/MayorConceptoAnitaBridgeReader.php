<?php

namespace App\Support\Contable\MayorConcepto;

use App\ApiAnita;
use Illuminate\Support\Facades\Cache;

/**
 * Carga tablas Anita vía bridge HTTP en memoria para un período acotado (≈1 mes).
 *
 * Multiempresa: precargarPeriodoEmpresas() lee subdiario/ctamov/auxpag/ctaconc una vez
 * con IN (...); cargarPeriodo() reutiliza el slice por empresa (memoria o file cache).
 */
class MayorConceptoAnitaBridgeReader
{
    private const PERIODO_FILE_TTL_HOURS = 2;

    /**
     * @var array<int, array{
     *   subdiario: list<object>,
     *   ctamov: list<object>,
     *   auxpag: list<object>,
     *   ctaconc: list<object>,
     *   promae: list<object>,
     *   errores: list<string>
     * }>
     */
    private array $periodoPorEmpresa = [];

    private ?string $periodoCacheFirma = null;

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
        private readonly MayorConceptoTCompSupport $tcompSupport = new MayorConceptoTCompSupport(),
    ) {
    }

    /**
     * Precarga el período para varias empresas en 4 lecturas bridge (IN).
     * Idempotente: si ya está en memoria/file cache, no vuelve a llamar al bridge.
     *
     * @param  list<int>  $empresaIds
     */
    public function precargarPeriodoEmpresas(array $empresaIds, int $fechaDesde, int $fechaHasta): void
    {
        $empresaIds = array_values(array_unique(array_filter(
            array_map('intval', $empresaIds),
            fn (int $id) => $id > 0,
        )));
        sort($empresaIds);

        if ($empresaIds === [] || $fechaDesde <= 0 || $fechaHasta <= 0) {
            return;
        }

        if (count($empresaIds) === 1) {
            $this->cargarPeriodo($empresaIds[0], $fechaDesde, $fechaHasta);

            return;
        }

        $firma = $this->firmaPeriodo($empresaIds, $fechaDesde, $fechaHasta);
        if ($this->periodoCacheFirma === $firma && $this->periodoPorEmpresa !== []) {
            return;
        }

        $fromFile = Cache::store('file')->get($this->periodoFileCacheKey($firma));
        if (is_array($fromFile) && isset($fromFile['por_empresa']) && is_array($fromFile['por_empresa'])) {
            $this->periodoPorEmpresa = $fromFile['por_empresa'];
            $this->periodoCacheFirma = $firma;

            return;
        }

        $inList = implode(',', $empresaIds);
        $errores = [];

        $subdiario = $this->listar(
            'contab',
            'subdiario',
            $this->camposSubdiarioPeriodo(),
            ' WHERE subd_empresa IN ('.$inList.')'
            .' AND subd_fecha>='.$fechaDesde
            .' AND subd_fecha<='.$fechaHasta,
            $errores,
            'subdiario-multi',
        );

        $ctamov = $this->listar(
            'contab',
            'ctamov',
            $this->camposCtamovPeriodo(),
            ' WHERE ctav_empresa IN ('.$inList.')'
            .' AND ctav_fecha>='.$fechaDesde
            .' AND ctav_fecha<='.$fechaHasta,
            $errores,
            'ctamov-multi',
        );

        $auxpag = $this->listar(
            'che_ban',
            'auxpag',
            $this->camposAuxpagPeriodo(),
            ' WHERE axp_empresa IN ('.$inList.')'
            .' AND axp_fecha>='.$fechaDesde
            .' AND axp_fecha<='.$fechaHasta,
            $errores,
            'auxpag-multi',
        );

        $ctaconc = $this->listar(
            'contab',
            'ctaconc',
            $this->camposCtaconc(),
            ' WHERE ctaco_empresa IN ('.$inList.')',
            $errores,
            'ctaconc-multi',
        );

        $porEmpresa = [];
        foreach ($empresaIds as $empresaId) {
            $porEmpresa[$empresaId] = [
                'subdiario' => [],
                'ctamov' => [],
                'auxpag' => [],
                'ctaconc' => [],
                'promae' => [],
                'errores' => $errores,
            ];
        }

        foreach ($subdiario as $fila) {
            $empresaId = (int) ($fila->subd_empresa ?? 0);
            if (isset($porEmpresa[$empresaId])) {
                $porEmpresa[$empresaId]['subdiario'][] = $fila;
            }
        }
        foreach ($ctamov as $fila) {
            $empresaId = (int) ($fila->ctav_empresa ?? 0);
            if (isset($porEmpresa[$empresaId])) {
                $porEmpresa[$empresaId]['ctamov'][] = $fila;
            }
        }
        foreach ($auxpag as $fila) {
            $empresaId = (int) ($fila->axp_empresa ?? 0);
            if (isset($porEmpresa[$empresaId])) {
                $porEmpresa[$empresaId]['auxpag'][] = $fila;
            }
        }
        foreach ($ctaconc as $fila) {
            $empresaId = (int) ($fila->ctaco_empresa ?? 0);
            if (isset($porEmpresa[$empresaId])) {
                $porEmpresa[$empresaId]['ctaconc'][] = $fila;
            }
        }

        $this->periodoPorEmpresa = $porEmpresa;
        $this->periodoCacheFirma = $firma;
        Cache::store('file')->put(
            $this->periodoFileCacheKey($firma),
            ['por_empresa' => $porEmpresa],
            now()->addHours(self::PERIODO_FILE_TTL_HOURS),
        );
    }

    /**
     * @return array{
     *   subdiario: list<object>,
     *   ctamov: list<object>,
     *   auxpag: list<object>,
     *   ctaconc: list<object>,
     *   promae: list<object>,
     *   errores: list<string>
     * }
     */
    public function cargarPeriodo(int $empresaId, int $fechaDesde, int $fechaHasta): array
    {
        if ($empresaId > 0 && $this->periodoCacheHit($empresaId, $fechaDesde, $fechaHasta)) {
            return $this->periodoPorEmpresa[$empresaId];
        }

        $errores = [];

        $subdiario = $this->listar(
            'contab',
            'subdiario',
            $this->camposSubdiarioPeriodo(),
            ' WHERE subd_empresa='.$empresaId
            .' AND subd_fecha>='.$fechaDesde
            .' AND subd_fecha<='.$fechaHasta,
            $errores,
            'subdiario'
        );

        $ctamov = $this->listar(
            'contab',
            'ctamov',
            $this->camposCtamovPeriodo(),
            ' WHERE ctav_empresa='.$empresaId
            .' AND ctav_fecha>='.$fechaDesde
            .' AND ctav_fecha<='.$fechaHasta,
            $errores,
            'ctamov'
        );

        $auxpag = $this->listar(
            'che_ban',
            'auxpag',
            $this->camposAuxpagPeriodo(),
            ' WHERE axp_empresa='.$empresaId
            .' AND axp_fecha>='.$fechaDesde
            .' AND axp_fecha<='.$fechaHasta,
            $errores,
            'auxpag'
        );

        $ctaconc = $this->listar(
            'contab',
            'ctaconc',
            $this->camposCtaconc(),
            ' WHERE ctaco_empresa='.$empresaId,
            $errores,
            'ctaconc'
        );

        $resultado = [
            'subdiario' => $subdiario,
            'ctamov' => $ctamov,
            'auxpag' => $auxpag,
            'ctaconc' => $ctaconc,
            'promae' => [],
            'errores' => $errores,
        ];

        $this->periodoPorEmpresa[$empresaId] = $resultado;
        $this->periodoCacheFirma = $this->firmaPeriodo([$empresaId], $fechaDesde, $fechaHasta);

        return $resultado;
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function firmaPeriodo(array $empresaIds, int $fechaDesde, int $fechaHasta): string
    {
        $ids = $empresaIds;
        sort($ids);

        return implode('-', $ids).'_'.$fechaDesde.'_'.$fechaHasta;
    }

    private function periodoFileCacheKey(string $firma): string
    {
        return 'mayor_concepto_periodo_bridge_'.$firma;
    }

    private function periodoCacheHit(int $empresaId, int $fechaDesde, int $fechaHasta): bool
    {
        if (! isset($this->periodoPorEmpresa[$empresaId]) || $this->periodoCacheFirma === null) {
            return false;
        }

        return str_ends_with($this->periodoCacheFirma, '_'.$fechaDesde.'_'.$fechaHasta);
    }

    /**
     * `*_desc_mov` va último en cada lista: el bridge parte el CSV por `|` sin respetar el escape,
     * así que una descripción con `|` corre los campos siguientes (cotización, moneda, sistema).
     */
    private function camposSubdiarioPeriodo(): string
    {
        return 'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_emisor,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_nro_operacion,subd_ref_tipo,subd_ref_letra,subd_ref_sucursal,subd_ref_nro,subd_importe,subd_cod_mon,subd_cotizacion,subd_nro_asiento,subd_nro_interno,subd_ccosto_cta,subd_ccosto_con,subd_desc_mov';
    }

    private function camposCtamovPeriodo(): string
    {
        return 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_cuenta,ctav_fecha,ctav_tipo,ctav_letra,ctav_sucursal,ctav_nro,ctav_importe,ctav_cotizacion,ctav_cod_mon,ctav_sistema,ctav_tipo_asiento,ctav_ccosto,ctav_o_compra,ctav_desc_mov';
    }

    private function camposAuxpagPeriodo(): string
    {
        return 'axp_pro,axp_fecha,axp_rec,axp_tipo,axp_nro,axp_tipo_ap,axp_monto_ap,axp_cod_mon_co,axp_sucursal,axp_empresa,axp_letra_comp,axp_nro_interno,axp_banco,axp_concepto';
    }

    private function camposCtaconc(): string
    {
        return 'ctaco_empresa,ctaco_cuenta,ctaco_concepto';
    }

    /**
     * Carga datos mínimos para simular un único comprobante de pago.
     *
     * @return array<string, mixed>
     */
    public function cargarParaPago(int $empresaId, string $tipo, string $letra, int $sucursal, int $nro, int $fecha): array
    {
        $errores = [];
        $letraSql = $this->sqlChar($letra);

        $subdiario = $this->listar(
            'contab',
            'subdiario',
            'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_nro_operacion,subd_ref_tipo,subd_ref_letra,subd_ref_sucursal,subd_ref_nro,subd_importe,subd_nro_interno,subd_cod_mon,subd_cotizacion,subd_desc_mov',
            ' WHERE subd_empresa='.$empresaId
            .' AND subd_ref_tipo="'.$tipo.'"'
            .' AND subd_ref_sucursal='.$sucursal
            .' AND subd_ref_nro='.$nro
            .' AND subd_ref_letra='.$letraSql
            .' AND subd_fecha='.$fecha,
            $errores,
            'subdiario-pago'
        );

        $auxpag = $this->listar(
            'che_ban',
            'auxpag',
            'axp_pro,axp_fecha,axp_rec,axp_tipo,axp_nro,axp_tipo_ap,axp_monto_ap,axp_sucursal,axp_empresa,axp_letra_comp,axp_nro_interno,axp_banco',
            ' WHERE axp_empresa='.$empresaId
            .' AND axp_tipo="'.$tipo.'"'
            .' AND axp_rec='.$nro
            .' AND axp_fecha='.$fecha,
            $errores,
            'auxpag-pago'
        );

        if ($auxpag === []) {
            $proveedor = '';
            foreach ($subdiario as $linea) {
                $prov = trim((string) ($linea->subd_emisor ?? ''));
                if ($prov !== '') {
                    $proveedor = $prov;
                    break;
                }
            }
            $auxpag = $this->cargarAuxpagHistorico(
                $empresaId,
                $tipo,
                $nro,
                $fecha,
                $proveedor,
                $sucursal,
                $errores,
            );
        }

        $this->tcompSupport->cargar($errores);

        $proveedores = [];
        foreach ($auxpag as $fila) {
            $prov = trim((string) ($fila->axp_pro ?? ''));
            if ($prov !== '') {
                $proveedores[$prov] = true;
            }
        }

        $aplicped = [];
        $recepmov = [];
        $comSubdiario = [];

        foreach ($auxpag as $fila) {
            if (! $this->tcompSupport->esFacturaAplicada($fila)) {
                continue;
            }

            $prov = trim((string) $fila->axp_pro);
            $tipoAp = trim((string) $fila->axp_tipo_ap);
            $letraAp = trim((string) ($fila->axp_letra_comp ?? ' '));
            $sucAp = (int) ($fila->axp_sucursal ?? 0);
            $nroAp = (int) ($fila->axp_nro ?? 0);

            $aplicaciones = $this->listar(
                'compras',
                'aplicped',
                'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
                ' WHERE aplp_proveedor="'.$prov.'"'
                .' AND aplp_tipo="'.$tipoAp.'"'
                .' AND aplp_letra='.$this->sqlChar($letraAp)
                .' AND aplp_sucursal='.$sucAp
                .' AND aplp_nro='.$nroAp,
                $errores,
                'aplicped'
            );

            $comDeEstaFactura = false;

            foreach ($aplicaciones as $apl) {
                $aplicped[] = $apl;
                if (trim((string) ($apl->aplp_ref_tipo ?? '')) !== 'COM') {
                    continue;
                }

                $comDeEstaFactura = true;
                $comTipo = trim((string) $apl->aplp_ref_tipo);
                $comLetra = trim((string) ($apl->aplp_ref_letra ?? ' '));
                $comSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
                $comNro = (int) ($apl->aplp_ref_nro ?? 0);

                $comSubdiario = array_merge(
                    $comSubdiario,
                    $this->cargarComSubdiario($empresaId, $comTipo, $comLetra, $comSuc, $comNro, $errores),
                );

                $recepmov = array_merge(
                    $recepmov,
                    $this->listar(
                        'compras',
                        'recepmov',
                        'recv_proveedor,recv_tipo,recv_letra,recv_sucursal,recv_nro,recv_orden,recv_cantidad,recv_precio,recv_dto_art,recv_tipo_iva,recv_empresa',
                        ' WHERE recv_proveedor="'.$prov.'"'
                        .' AND recv_tipo="'.$comTipo.'"'
                        .' AND recv_letra='.$this->sqlChar($comLetra)
                        .' AND recv_sucursal='.$comSuc
                        .' AND recv_nro='.$comNro,
                        $errores,
                        'recepmov'
                    )
                );
            }

            if (strtoupper($tipoAp) === 'FIS' && ! $comDeEstaFactura) {
                $comSubdiario = array_merge(
                    $comSubdiario,
                    $this->cargarSubdiarioFacturaCompras(
                        $empresaId,
                        $tipoAp,
                        $letraAp,
                        $sucAp,
                        $nroAp,
                        (int) ($fila->axp_nro_interno ?? 0),
                        $prov,
                        $errores,
                    ),
                );
            }
        }

        $promae = [];
        foreach (array_keys($proveedores) as $prov) {
            $filas = $this->listar(
                'compras',
                'promae',
                'prom_proveedor,prom_nombre,prom_cuit,prom_cond_iva',
                ' WHERE prom_proveedor="'.$prov.'"',
                $errores,
                'promae'
            );
            $promae = array_merge($promae, $filas);
        }

        $ctaconc = $this->listar(
            'contab',
            'ctaconc',
            'ctaco_empresa,ctaco_cuenta,ctaco_concepto',
            ' WHERE ctaco_empresa='.$empresaId,
            $errores,
            'ctaconc'
        );

        return [
            'subdiario' => $subdiario,
            'auxpag' => $auxpag,
            'aplicped' => $aplicped,
            'com_subdiario' => $comSubdiario,
            'recepmov' => $recepmov,
            'promae' => $promae,
            'ctaconc' => $ctaconc,
            'errores' => $errores,
        ];
    }

    /**
     * @return list<object>
     */
    private function listar(
        string $sistema,
        string $tabla,
        string $campos,
        string $whereArmado,
        array &$errores,
        string $etiqueta,
    ): array {
        $payload = [
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
        ];

        $raw = (string) $this->api->apiCall($payload);
        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            $errores[] = $etiqueta.': '.$msg;

            return [];
        }

        return ApiAnita::decodificarListaFilas($raw);
    }

    /**
     * Fallback de aplicaciones de pago para OPs anuladas: al anular un OP, sus
     * aplicaciones (facturas, retenciones, cheques) se mueven de `auxpag` a
     * `axphist`. Se consulta por tipo+rec (+fecha, proveedor, sucursal OP) y
     * se valida empresa vía subdiario del comprobante aplicado.
     *
     * `axphist` no tiene columna de empresa: se acota con proveedor/sucursal del
     * OP y, para facturas, verificando que el subdiario del comprobante pertenezca
     * a la empresa del reporte.
     *
     * @return list<object>
     */
    public function cargarAuxpagHistorico(
        int $empresaId,
        string $tipo,
        int $rec,
        int $fecha,
        string $proveedor,
        int $sucursalOp,
        array &$errores,
    ): array {
        $tipo = trim($tipo);
        $proveedor = trim($proveedor);
        if ($tipo === '' || $rec <= 0 || $empresaId <= 0) {
            return [];
        }

        $filas = $this->consultarAuxpagHistoricoFilas($tipo, $rec, $fecha, $proveedor, $sucursalOp, $errores);

        if ($filas === [] && $fecha > 0) {
            $filas = $this->consultarAuxpagHistoricoFilas($tipo, $rec, 0, $proveedor, $sucursalOp, $errores);
        }

        $remapeadas = [];
        foreach ($filas as $fila) {
            if (! $this->auxpagHistoricoPerteneceEmpresa($empresaId, $fila, $errores)) {
                continue;
            }

            $remapeadas[] = (object) [
                'axp_pro' => $fila->axph_pro ?? '',
                'axp_fecha' => $fila->axph_fecha ?? 0,
                'axp_rec' => $fila->axph_rec ?? 0,
                'axp_tipo' => $fila->axph_tipo ?? '',
                'axp_nro' => $fila->axph_nro ?? 0,
                'axp_tipo_ap' => $fila->axph_tipo_apli ?? '',
                'axp_monto_ap' => $fila->axph_monto_ap ?? 0,
                'axp_cod_mon_co' => $fila->axph_cod_mon_co ?? '',
                'axp_sucursal' => $fila->axph_sucursal_comp ?? 0,
                'axp_empresa' => $empresaId,
                'axp_letra_comp' => $fila->axph_letra_comp ?? ' ',
                'axp_nro_interno' => $fila->axph_nro_interno ?? 0,
                'axp_banco' => $fila->axph_banco ?? '',
                'axp_concepto' => null,
                'axp_origen_historico' => true,
            ];
        }

        return $remapeadas;
    }

    /**
     * @return list<object>
     */
    private function consultarAuxpagHistoricoFilas(
        string $tipo,
        int $rec,
        int $fecha,
        string $proveedor,
        int $sucursalOp,
        array &$errores,
    ): array {
        $where = ' WHERE axph_tipo="'.addslashes($tipo).'" AND axph_rec='.$rec;
        if ($fecha > 0) {
            $where .= ' AND axph_fecha='.$fecha;
        }
        if ($proveedor !== '') {
            $where .= ' AND axph_pro="'.addslashes($proveedor).'"';
        }
        if ($sucursalOp > 0) {
            $where .= ' AND axph_sucursal='.$sucursalOp;
        }

        return $this->listar(
            'che_ban',
            'axphist',
            'axph_pro,axph_fecha,axph_rec,axph_tipo,axph_nro,axph_tipo_apli,axph_monto_ap,axph_cod_mon_co,axph_banco,axph_letra_comp,axph_sucursal_comp,axph_letra,axph_sucursal,axph_nro_interno',
            $where,
            $errores,
            'axphist',
        );
    }

    private function auxpagHistoricoPerteneceEmpresa(int $empresaId, object $fila, array &$errores): bool
    {
        $nroInterno = (int) ($fila->axph_nro_interno ?? 0);
        if ($nroInterno <= 0) {
            return true;
        }

        $tipoAp = trim((string) ($fila->axph_tipo_apli ?? ''));
        if ($tipoAp === '') {
            return false;
        }

        $letra = trim((string) ($fila->axph_letra_comp ?? ' '));
        $suc = (int) ($fila->axph_sucursal_comp ?? 0);
        $candidatosNro = array_values(array_unique(array_filter([
            (int) ($fila->axph_nro ?? 0),
            $nroInterno,
        ], fn ($n) => $n > 0)));

        foreach ($candidatosNro as $nro) {
            $sub = $this->listar(
                'contab',
                'subdiario',
                'subd_empresa',
                ' WHERE subd_empresa='.$empresaId
                .' AND subd_tipo="'.addslashes($tipoAp).'"'
                .' AND subd_letra='.$this->sqlChar($letra)
                .' AND subd_sucursal='.$suc
                .' AND subd_nro='.$nro
                .' LIMIT 1',
                $errores,
                'axphist-empresa',
            );

            if ($sub !== []) {
                return true;
            }

            $subInterno = $this->listar(
                'contab',
                'subdiario',
                'subd_empresa',
                ' WHERE subd_empresa='.$empresaId
                .' AND subd_tipo="'.addslashes($tipoAp).'"'
                .' AND subd_nro_interno='.$nroInterno
                .' LIMIT 1',
                $errores,
                'axphist-empresa-interno',
            );

            if ($subInterno !== []) {
                return true;
            }
        }

        // Sin subdiario verificable en bridge: no descartar; el OP ya viene
        // acotado por empresa (subdiario del período) y proveedor/sucursal.
        return true;
    }

    public function cargarComSubdiario(int $empresaId, string $tipo, string $letra, int $sucursal, int $nro, array &$errores): array
    {
        $where = ' WHERE subd_tipo="'.$tipo.'"'
            .' AND subd_letra='.$this->sqlChar($letra)
            .' AND subd_sucursal='.$sucursal
            .' AND subd_nro='.$nro;
        if ($empresaId > 0) {
            $where .= ' AND subd_empresa='.$empresaId;
        }

        $filas = $this->listar(
            'contab',
            'subdiario',
            $this->camposSubdiarioFacturaCompras(),
            $where,
            $errores,
            'subdiario-com'
        );

        if ($filas !== []) {
            return $filas;
        }

        // Período cerrado: Anita mueve el comprobante a subhist (lee_subd → Amksubhist4).
        return $this->cargarComSubhist($empresaId, $tipo, $letra, $sucursal, $nro, $errores);
    }

    /**
     * COM en subhist (período cerrado). Columnas nativas subh_*; se remapean a subd_* para el motor.
     *
     * @return list<object>
     */
    public function cargarComSubhist(int $empresaId, string $tipo, string $letra, int $sucursal, int $nro, array &$errores): array
    {
        $tipo = trim($tipo);
        if ($tipo === '' || $nro <= 0) {
            return [];
        }

        $where = ' WHERE subh_tipo="'.addslashes($tipo).'"'
            .' AND subh_letra='.$this->sqlChar($letra)
            .' AND subh_sucursal='.$sucursal
            .' AND subh_nro='.$nro;
        if ($empresaId > 0) {
            $where .= ' AND subh_empresa='.$empresaId;
        }

        $filas = $this->listar(
            'contab',
            'subhist',
            $this->camposSubhistFacturaCompras(),
            $where,
            $errores,
            'subhist-com'
        );

        return array_map(fn ($fila) => $this->remapearSubhistComoSubdiario($fila), $filas);
    }

    private function camposSubhistFacturaCompras(): string
    {
        return 'subh_empresa,subh_sistema,subh_fecha,subh_tipo,subh_letra,subh_sucursal,subh_nro,subh_emisor,subh_tipo_mov,subh_cuenta,subh_contrapartida,subh_importe,subh_nro_operacion,subh_nro_interno,subh_cod_mon,subh_cotizacion,subh_nro_asiento,subh_desc_mov';
    }

    /**
     * @return object
     */
    private function remapearSubhistComoSubdiario(object $fila): object
    {
        $out = [];
        foreach ((array) $fila as $clave => $valor) {
            if (str_starts_with($clave, 'subh_')) {
                $out['subd_'.substr($clave, 5)] = $valor;
            } else {
                $out[$clave] = $valor;
            }
        }

        $out['subd_ref_tipo'] = $out['subd_ref_tipo'] ?? '';
        $out['subd_ref_letra'] = $out['subd_ref_letra'] ?? ' ';
        $out['subd_ref_sucursal'] = $out['subd_ref_sucursal'] ?? 0;
        $out['subd_ref_nro'] = $out['subd_ref_nro'] ?? 0;
        $out['subd_origen_subhist'] = true;

        return (object) $out;
    }

    /**
     * Subdiario del comprobante de compra (FIS, FGA, FDT…) sin acotar por mes.
     * El FIS puede registrarse en un mes distinto al del pago (ej. factura abril, OP junio).
     * Si hay nro_interno, desambigua reutilizaciones del mismo nro visible.
     *
     * @return list<object>
     */
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
        $tipo = trim($tipo);
        if ($tipo === '' || $empresaId <= 0) {
            return [];
        }

        $campos = $this->camposSubdiarioFacturaCompras();
        $proveedor = trim($proveedor);

        if ($nroInterno > 0) {
            $filas = $this->listar(
                'contab',
                'subdiario',
                $campos,
                ' WHERE subd_empresa='.$empresaId
                .' AND subd_tipo="'.addslashes($tipo).'"'
                .' AND subd_nro_interno='.$nroInterno,
                $errores,
                'subdiario-factura-interno',
            );

            if ($filas !== []) {
                return $this->ampliarSubdiarioConAsientoCompleto($empresaId, $filas, $campos, $errores);
            }
        }

        if ($nro > 0) {
            $where = ' WHERE subd_empresa='.$empresaId
                .' AND subd_tipo="'.addslashes($tipo).'"'
                .' AND subd_letra='.$this->sqlChar($letra)
                .' AND subd_sucursal='.$sucursal
                .' AND subd_nro='.$nro;
            if ($proveedor !== '') {
                $where .= ' AND subd_emisor="'.addslashes($proveedor).'"';
            }

            $filas = $this->listar(
                'contab',
                'subdiario',
                $campos,
                $where,
                $errores,
                'subdiario-factura',
            );

            if ($nroInterno > 0) {
                $filas = array_values(array_filter(
                    $filas,
                    fn ($fila) => (int) ($fila->subd_nro_interno ?? 0) === $nroInterno,
                ));
            }

            if ($filas !== []) {
                return $this->ampliarSubdiarioConAsientoCompleto($empresaId, $filas, $campos, $errores);
            }
        }

        if ($nroInterno > 0) {
            $filas = $this->listar(
                'contab',
                'subdiario',
                $campos,
                ' WHERE subd_empresa='.$empresaId
                .' AND subd_tipo="'.addslashes($tipo).'"'
                .' AND subd_nro='.$nroInterno,
                $errores,
                'subdiario-factura-nro-interno',
            );

            if ($filas !== []) {
                return $this->ampliarSubdiarioConAsientoCompleto($empresaId, $filas, $campos, $errores);
            }
        }

        return [];
    }

    /**
     * Ctamov del asiento contable del comprobante (sin filtro de mes).
     *
     * @return list<object>
     */
    public function cargarCtamovPorAsiento(int $empresaId, int $nroAsiento, array &$errores): array
    {
        if ($empresaId <= 0 || $nroAsiento <= 0) {
            return [];
        }

        return $this->listar(
            'contab',
            'ctamov',
            'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_cuenta,ctav_fecha,ctav_tipo,ctav_letra,ctav_sucursal,ctav_nro,ctav_importe,ctav_cotizacion,ctav_cod_mon,ctav_sistema,ctav_tipo_asiento,ctav_ccosto,ctav_o_compra,ctav_desc_mov',
            ' WHERE ctav_empresa='.$empresaId
            .' AND ctav_nro_asiento='.$nroAsiento,
            $errores,
            'ctamov-asiento',
        );
    }

    private function camposSubdiarioFacturaCompras(): string
    {
        return 'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_emisor,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_importe,subd_nro_operacion,subd_nro_interno,subd_cod_mon,subd_cotizacion,subd_desc_mov';
    }

    /**
     * Cuenta de gasto de un COM histórico con el mismo pasivo proveedor (y opcionalmente emisor/importe).
     * Usado por EFE gastronomía para remapar restos FIS → concepto 20 sin cargar meses enteros.
     */
    public function buscarCuentaGastoComPorPasivo(
        int $empresaId,
        int $pasivo,
        string $emisor,
        float $importe,
        array &$errores,
    ): ?int {
        if ($empresaId <= 0 || $pasivo <= 0) {
            return null;
        }

        $where = ' WHERE subd_empresa='.$empresaId
            .' AND subd_tipo="COM"'
            .' AND subd_contrapartida='.$pasivo
            .' AND subd_cuenta>=500000000';
        // Anita no filtra bien por subd_emisor; se acota por importe y se elige en PHP.
        if ($importe > 0) {
            $where .= ' AND subd_importe='.(abs($importe - round($importe)) < 0.001
                ? (string) (int) round($importe)
                : sprintf('%.2f', $importe));
        }

        $filas = $this->listar(
            'contab',
            'subdiario',
            'subd_cuenta,subd_importe,subd_fecha,subd_emisor,subd_nro',
            $where,
            $errores,
            'subdiario-com-gasto-pasivo',
        );

        $emisor = trim($emisor);
        $mejor = null;
        $mejorNro = -1;
        foreach ($filas as $fila) {
            $cuenta = (int) ($fila->subd_cuenta ?? 0);
            if ($cuenta < 500000000) {
                continue;
            }
            if ($emisor !== '' && trim((string) ($fila->subd_emisor ?? '')) !== $emisor) {
                continue;
            }
            $nro = (int) ($fila->subd_nro ?? 0);
            if ($nro >= $mejorNro) {
                $mejorNro = $nro;
                $mejor = $cuenta;
            }
        }

        return $mejor;
    }

    /**
     * Trae todas las piernas del asiento del comprobante (p. ej. FIS con 521xxx en el mismo nro_operacion).
     *
     * @param  list<object>  $semillas
     * @return list<object>
     */
    private function ampliarSubdiarioConAsientoCompleto(
        int $empresaId,
        array $semillas,
        string $campos,
        array &$errores,
    ): array {
        $porClave = [];

        foreach ($semillas as $fila) {
            $clave = $this->claveLineaSubdiario($fila);
            if ($clave !== '') {
                $porClave[$clave] = $fila;
            }
        }

        $asientos = [];
        foreach ($semillas as $fila) {
            $asi = (int) ($fila->subd_nro_operacion ?? 0);
            if ($asi > 0) {
                $asientos[$asi] = true;
            }
        }

        foreach (array_keys($asientos) as $nroAsiento) {
            foreach ($this->cargarSubdiarioPorAsiento($empresaId, $nroAsiento, $campos, $errores) as $linea) {
                $clave = $this->claveLineaSubdiario($linea);
                if ($clave !== '') {
                    $porClave[$clave] = $linea;
                }
            }
        }

        return array_values($porClave);
    }

    /**
     * @return list<object>
     */
    private function cargarSubdiarioPorAsiento(int $empresaId, int $nroAsiento, string $campos, array &$errores): array
    {
        if ($empresaId <= 0 || $nroAsiento <= 0) {
            return [];
        }

        return $this->listar(
            'contab',
            'subdiario',
            $campos,
            ' WHERE subd_empresa='.$empresaId
            .' AND subd_nro_operacion='.$nroAsiento,
            $errores,
            'subdiario-asiento',
        );
    }

    private function claveLineaSubdiario(object $fila): string
    {
        return implode('|', [
            (int) ($fila->subd_nro_operacion ?? 0),
            trim((string) ($fila->subd_tipo ?? '')),
            trim((string) ($fila->subd_letra ?? ' ')),
            (int) ($fila->subd_sucursal ?? 0),
            (int) ($fila->subd_nro ?? 0),
            (int) ($fila->subd_cuenta ?? 0),
            strtoupper(trim((string) ($fila->subd_tipo_mov ?? ''))),
            number_format((float) ($fila->subd_importe ?? 0), 4, '.', ''),
        ]);
    }

    public function cargarAplicpedFactura(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        array &$errores,
    ): array {
        return $this->listar(
            'compras',
            'aplicped',
            'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
            ' WHERE aplp_proveedor="'.$proveedor.'"'
            .' AND aplp_tipo="'.$tipo.'"'
            .' AND aplp_letra='.$this->sqlChar($letra)
            .' AND aplp_sucursal='.$sucursal
            .' AND aplp_nro='.$nro,
            $errores,
            'aplicped'
        );
    }

    /**
     * Aplicaciones que referencian una OC (PEP) u otro documento — ej. COM→PEP 218505.
     *
     * @return list<object>
     */
    public function cargarAplicpedPorReferencia(
        string $refTipo,
        string $refLetra,
        int $refSucursal,
        int $refNro,
        string $proveedor,
        array &$errores,
    ): array {
        if ($refNro <= 0) {
            return [];
        }

        $where = ' WHERE aplp_ref_tipo="'.$refTipo.'"'
            .' AND aplp_ref_letra='.$this->sqlChar($refLetra)
            .' AND aplp_ref_sucursal='.$refSucursal
            .' AND aplp_ref_nro='.$refNro;

        if ($proveedor !== '') {
            $where .= ' AND aplp_proveedor="'.addslashes($proveedor).'"';
        }

        return $this->listar(
            'compras',
            'aplicped',
            'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
            $where,
            $errores,
            'aplicped-ref',
        );
    }

    /**
     * Batch: aplicaciones que referencian COM por nro (sin filtrar letra/sucursal).
     *
     * @param  list<int>  $nrosCom
     * @return list<object>
     */
    public function cargarAplicpedPorReferenciasCom(array $nrosCom, array &$errores): array
    {
        return $this->cargarAplicpedPorReferenciasTipo('COM', $nrosCom, $errores);
    }

    /**
     * @param  list<int>  $nros
     * @return list<object>
     */
    public function cargarAplicpedPorReferenciasTipo(string $refTipo, array $nros, array &$errores): array
    {
        $refTipo = strtoupper(trim($refTipo));
        $nros = array_values(array_unique(array_filter(array_map(
            static fn ($n) => (int) $n,
            $nros,
        ), static fn (int $n) => $n > 0)));

        if ($refTipo === '' || $nros === []) {
            return [];
        }

        $filas = [];
        foreach (array_chunk($nros, 80) as $lote) {
            $in = implode(',', $lote);
            $filas = array_merge(
                $filas,
                $this->listar(
                    'compras',
                    'aplicped',
                    'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
                    ' WHERE aplp_ref_tipo="'.$refTipo.'" AND aplp_ref_nro IN ('.$in.')',
                    $errores,
                    'aplicped-ref-'.strtolower($refTipo).'-batch',
                ),
            );
        }

        return $filas;
    }

    /**
     * Líneas de OC Anita (pendmovp) por número de orden.
     *
     * @param  list<int>  $nrosOc
     * @return list<object>
     */
    public function cargarPendmovpPorNrosOc(array $nrosOc, array &$errores): array
    {
        $nros = array_values(array_unique(array_filter(array_map(
            static fn ($n) => (int) $n,
            $nrosOc,
        ), static fn (int $n) => $n > 0)));

        if ($nros === []) {
            return [];
        }

        $tabla = (string) config('ordencompra_anita.tablas.linea', 'pendmovp');
        $filas = [];
        foreach (array_chunk($nros, 80) as $lote) {
            $in = implode(',', $lote);
            $filas = array_merge(
                $filas,
                $this->listar(
                    'compras',
                    $tabla,
                    'penvp_nro,penvp_tipo,penvp_articulo,penvp_desc,penvp_cantidad,penvp_partida',
                    ' WHERE penvp_nro IN ('.$in.') AND penvp_tipo IN ("PEP","COM")',
                    $errores,
                    'pendmovp-nros-oc',
                ),
            );
        }

        return $filas;
    }

    /**
     * Concepto de compras desde OC (PEP) vía artículo → ctaconc (busca_concepto_oc en l-mayorconc.c).
     */
    public function conceptoDesdeOrdenCompra(int $empresaId, int $nroOc, array &$errores): int
    {
        if ($nroOc <= 0) {
            return 0;
        }

        $lineasOc = $this->listar(
            'compras',
            'pendmovp,stkmae',
            'penvp_articulo,penvp_empresa,stkm_cta_contable_c',
            ' WHERE penvp_tipo="PEP"'
            .' AND penvp_letra="X"'
            .' AND penvp_sucursal=0'
            .' AND penvp_nro='.$nroOc
            .' AND penvp_articulo=stkm_articulo',
            $errores,
            'penvp-oc',
        );

        foreach ($lineasOc as $lineaOc) {
            $cuenta = (int) ($lineaOc->stkm_cta_contable_c ?? 0);
            if ($cuenta <= 0) {
                continue;
            }

            $ctaco = $this->listar(
                'contab',
                'ctaconc',
                'ctaco_concepto',
                ' WHERE ctaco_empresa='.$empresaId
                .' AND ctaco_cuenta='.$cuenta,
                $errores,
                'ctaconc-oc',
            );

            $concepto = (int) ($ctaco[0]->ctaco_concepto ?? 0);
            if ($concepto > 0) {
                return $concepto;
            }
        }

        return 0;
    }

    public function cargarPromae(string $proveedor, array &$errores): ?object
    {
        $filas = $this->listar(
            'compras',
            'promae',
            'prom_proveedor,prom_nombre,prom_cuit,prom_cond_iva',
            ' WHERE prom_proveedor="'.$proveedor.'"',
            $errores,
            'promae'
        );

        return $filas[0] ?? null;
    }

    /**
     * Carga aplicped solo para comprobantes concretos (evita traer todo el histórico del proveedor).
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: int, 4: int}>  $facturas  [proveedor, tipo, letra, sucursal, nro]
     * @return list<object>
     */
    public function cargarAplicpedPorFacturas(array $facturas, array &$errores): array
    {
        $facturas = array_values(array_filter($facturas, function (array $f): bool {
            return trim($f[0] ?? '') !== ''
                && trim($f[1] ?? '') !== ''
                && (int) ($f[4] ?? 0) > 0;
        }));

        if ($facturas === []) {
            return [];
        }

        $filas = [];
        foreach (array_chunk($facturas, 30) as $lote) {
            $conds = [];
            foreach ($lote as [$prov, $tipo, $letra, $suc, $nro]) {
                $conds[] = '(aplp_proveedor="'.addslashes($prov).'"'
                    .' AND aplp_tipo="'.addslashes($tipo).'"'
                    .' AND aplp_letra='.$this->sqlChar($letra)
                    .' AND aplp_sucursal='.(int) $suc
                    .' AND aplp_nro='.(int) $nro.')';
            }

            $filas = array_merge(
                $filas,
                $this->listar(
                    'compras',
                    'aplicped',
                    'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
                    ' WHERE '.implode(' OR ', $conds),
                    $errores,
                    'aplicped-facturas',
                ),
            );
        }

        return $filas;
    }

    /**
     * @deprecated Usar cargarAplicpedPorFacturas; carga histórico completo del proveedor.
     *
     * @param  list<string>  $proveedores
     * @return list<object>
     */
    public function cargarAplicpedPorProveedores(array $proveedores, array &$errores): array
    {
        $proveedores = array_values(array_unique(array_filter(array_map(
            fn ($p) => trim((string) $p),
            $proveedores,
        ), fn ($p) => $p !== '')));

        if ($proveedores === []) {
            return [];
        }

        $filas = [];
        foreach (array_chunk($proveedores, 80) as $lote) {
            $in = implode(',', array_map(
                fn ($p) => '"'.addslashes($p).'"',
                $lote,
            ));
            $filas = array_merge(
                $filas,
                $this->listar(
                    'compras',
                    'aplicped',
                    'aplp_proveedor,aplp_tipo,aplp_letra,aplp_sucursal,aplp_nro,aplp_ref_tipo,aplp_ref_letra,aplp_ref_sucursal,aplp_ref_nro,aplp_orden,aplp_cantfact',
                    ' WHERE aplp_proveedor IN ('.$in.')',
                    $errores,
                    'aplicped-bulk',
                ),
            );
        }

        return $filas;
    }

    /**
     * @param  list<string>  $proveedores
     * @return list<object>
     */
    public function cargarPromaePorProveedores(array $proveedores, array &$errores): array
    {
        $proveedores = array_values(array_unique(array_filter(array_map(
            fn ($p) => trim((string) $p),
            $proveedores,
        ), fn ($p) => $p !== '')));

        if ($proveedores === []) {
            return [];
        }

        $filas = [];
        foreach (array_chunk($proveedores, 80) as $lote) {
            $in = implode(',', array_map(
                fn ($p) => '"'.addslashes($p).'"',
                $lote,
            ));
            $filas = array_merge(
                $filas,
                $this->listar(
                    'compras',
                    'promae',
                    'prom_proveedor,prom_nombre,prom_cuit,prom_cond_iva',
                    ' WHERE prom_proveedor IN ('.$in.')',
                    $errores,
                    'promae-bulk',
                ),
            );
        }

        return $filas;
    }

    /**
     * Carga subdiario de comprobantes COM en lotes (clave: tipo|letra|sucursal|nro).
     *
     * @param  list<string>  $clavesCom  ej. COM| |1|12345
     * @return array<string, list<object>>
     */
    public function cargarComSubdiarioLote(int $empresaId, array $clavesCom, array &$errores): array
    {
        $clavesCom = array_values(array_unique(array_filter($clavesCom, fn ($c) => trim($c) !== '')));
        if ($clavesCom === []) {
            return [];
        }

        $porClave = [];
        foreach ($clavesCom as $clave) {
            $porClave[$clave] = [];
        }

        $campos = 'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_importe,subd_nro_operacion,subd_cod_mon,subd_cotizacion,subd_desc_mov';

        foreach (array_chunk($clavesCom, 40) as $lote) {
            $condiciones = [];
            foreach ($lote as $clave) {
                [$tipo, $letra, $suc, $nro] = array_pad(explode('|', $clave, 4), 4, '');
                $tipo = trim($tipo);
                if ($tipo === '' || (int) $nro <= 0) {
                    continue;
                }
                $condiciones[] = '(subd_tipo="'.$tipo.'" AND subd_letra='.$this->sqlChar($letra)
                    .' AND subd_sucursal='.(int) $suc.' AND subd_nro='.(int) $nro.')';
            }

            if ($condiciones === []) {
                continue;
            }

            $where = ' WHERE ('.implode(' OR ', $condiciones).')';
            if ($empresaId > 0) {
                $where .= ' AND subd_empresa='.$empresaId;
            }

            $filas = $this->listar(
                'contab',
                'subdiario',
                $campos,
                $where,
                $errores,
                'subdiario-com-bulk',
            );

            foreach ($filas as $fila) {
                if ($empresaId > 0 && (int) ($fila->subd_empresa ?? 0) !== $empresaId) {
                    continue;
                }
                $clave = $this->claveComDesdeSubdiario($fila);
                if ($clave === '') {
                    continue;
                }
                $porClave[$clave][] = $fila;
            }
        }

        $faltantes = array_values(array_filter(
            $clavesCom,
            fn ($clave) => ($porClave[$clave] ?? []) === [],
        ));

        if ($faltantes !== []) {
            foreach ($this->cargarComSubhistLote($empresaId, $faltantes, $errores) as $clave => $lineas) {
                if ($lineas !== []) {
                    $porClave[$clave] = $lineas;
                }
            }
        }

        return $porClave;
    }

    /**
     * Fallback masivo a subhist para COM de períodos cerrados.
     *
     * @param  list<string>  $clavesCom
     * @return array<string, list<object>>
     */
    public function cargarComSubhistLote(int $empresaId, array $clavesCom, array &$errores): array
    {
        $clavesCom = array_values(array_unique(array_filter($clavesCom, fn ($c) => trim($c) !== '')));
        if ($clavesCom === []) {
            return [];
        }

        $porClave = [];
        foreach ($clavesCom as $clave) {
            $porClave[$clave] = [];
        }

        $campos = $this->camposSubhistFacturaCompras();

        foreach (array_chunk($clavesCom, 40) as $lote) {
            $condiciones = [];
            foreach ($lote as $clave) {
                [$tipo, $letra, $suc, $nro] = array_pad(explode('|', $clave, 4), 4, '');
                $tipo = trim($tipo);
                if ($tipo === '' || (int) $nro <= 0) {
                    continue;
                }
                $condiciones[] = '(subh_tipo="'.$tipo.'" AND subh_letra='.$this->sqlChar($letra)
                    .' AND subh_sucursal='.(int) $suc.' AND subh_nro='.(int) $nro.')';
            }

            if ($condiciones === []) {
                continue;
            }

            $where = ' WHERE ('.implode(' OR ', $condiciones).')';
            if ($empresaId > 0) {
                $where .= ' AND subh_empresa='.$empresaId;
            }

            $filas = $this->listar(
                'contab',
                'subhist',
                $campos,
                $where,
                $errores,
                'subhist-com-bulk',
            );

            foreach ($filas as $fila) {
                $remap = $this->remapearSubhistComoSubdiario($fila);
                if ($empresaId > 0 && (int) ($remap->subd_empresa ?? 0) !== $empresaId) {
                    continue;
                }
                $clave = $this->claveComDesdeSubdiario($remap);
                if ($clave === '' || ! isset($porClave[$clave])) {
                    continue;
                }
                $porClave[$clave][] = $remap;
            }
        }

        return $porClave;
    }

    private function claveComDesdeSubdiario(object $fila): string
    {
        $tipo = trim((string) ($fila->subd_tipo ?? ''));
        $nro = (int) ($fila->subd_nro ?? 0);
        if ($tipo === '' || $nro <= 0) {
            return '';
        }

        return implode('|', [
            $tipo,
            trim((string) ($fila->subd_letra ?? ' ')),
            (int) ($fila->subd_sucursal ?? 0),
            $nro,
        ]);
    }

    private function sqlChar(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '" "';
        }

        return '"'.addslashes($valor).'"';
    }
}
