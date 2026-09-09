<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Sector_Legajocompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Compras\ComprobanteProveedorRecepcionesSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Validaciones obligatorias al enviar un legajo (OC) a CUENTAS A PAGAR.
 */
final class OrdencompraEnvioCuentasAPagarGateSupport
{
    public const SECTOR_CUENTAS_A_PAGAR = 'CUENTAS A PAGAR';

    public const SECTOR_COMPRAS = 'COMPRAS';

    public const SECTOR_PAGOS = 'PAGOS';

    /** @var array<string, int> */
    private static array $sectorIdPorNombreCache = [];

    public static function esSectorCuentasAPagar(int $sectorId): bool
    {
        return $sectorId > 0 && $sectorId === self::sectorIdPorNombre(self::SECTOR_CUENTAS_A_PAGAR);
    }

    public static function sectorIdPorNombre(string $nombre): int
    {
        $clave = strtoupper(trim($nombre));
        if ($clave === '') {
            return 0;
        }
        if (array_key_exists($clave, self::$sectorIdPorNombreCache)) {
            return self::$sectorIdPorNombreCache[$clave];
        }

        return self::$sectorIdPorNombreCache[$clave] = (int) (Sector_Legajocompra::query()
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [$clave])
            ->value('id') ?? 0);
    }

    /**
     * @return array{
     *     ok: bool,
     *     errores: list<string>,
     *     requiere_pdf: bool,
     *     tiene_factura: bool,
     *     tiene_com: bool,
     *     tiene_com_asignada: bool,
     *     exige_com: bool,
     *     es_anticipada: bool,
     *     exige_flujo_empresa: bool,
     *     precarga_id: int|null
     * }
     */
    public static function evaluar(Ordencompra $oc): array
    {
        $oc->loadMissing('empresas:id,codigo,nombre');
        $facturaPdf = self::resolverPrecargaConPdf($oc);
        $precarga = $facturaPdf ?? self::queryPrecargaDelLegajo($oc)->first();
        // Si ya hay precarga, no pegarle a Anita solo para saber si "tiene factura".
        $tieneFactura = $facturaPdf !== null || $precarga !== null;
        if (! $tieneFactura) {
            $tieneFactura = OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc) !== [];
        }
        $tieneCom = self::tieneComDisponible((int) $oc->id);
        $tieneComAsignada = self::tieneComAsignadaAFactura($oc);
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica($oc, $tieneCom);
        $esAnticipada = ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($oc);
        // Legajo anticipado sin COM: la factura se carga antes de la recepción.
        $exigeCom = self::exigeRecepcionComSegunPoliticaYAnticipada($politica, $esAnticipada, $tieneCom);

        $errores = [];
        if (! $tieneFactura) {
            $errores[] = 'Debe asignar una factura (precarga o PDF escaneado) al legajo antes de enviarlo a Cuentas a pagar.';
        }
        $faltanCom = [];
        if ($exigeCom) {
            $faltanCom = self::documentosQueExigenComSinAsignar($oc);
            if ($faltanCom !== []) {
                $errores[] = 'Falta asignar COM a: '.implode(', ', $faltanCom).'.';
            } elseif (! $tieneCom && self::documentosQueExigenCom($oc) !== []) {
                $errores[] = self::mensajeFaltaCom($politica);
            }
        }

        $erroresContrato = app(\App\Services\Compras\ContratoValidacionAbonoService::class)
            ->erroresEnvioCuentasAPagar($oc);
        foreach ($erroresContrato as $errorContrato) {
            $errores[] = $errorContrato;
        }

        $pendientes = self::documentosPendientesCarga($oc);
        $tieneComAsignadaDocs = $exigeCom
            ? $faltanCom === []
            : $tieneComAsignada;

        return [
            'ok' => $errores === [],
            'errores' => $errores,
            'requiere_pdf' => ! $tieneFactura,
            'tiene_factura' => $tieneFactura,
            'tiene_com' => $tieneCom,
            'tiene_com_asignada' => $tieneComAsignadaDocs,
            'exige_com' => $exigeCom,
            'es_anticipada' => $esAnticipada,
            'exige_flujo_empresa' => (bool) ($politica['exige_flujo'] ?? false),
            'precarga_id' => $facturaPdf?->id ?? $precarga?->id,
            'pendientes_carga' => count($pendientes),
            'siguiente_pendiente' => $pendientes[0]['etiqueta'] ?? null,
        ];
    }

    /**
     * Gate al mandar el legajo a Cuentas a pagar (paquete + autorización Gastronomía si aplica).
     *
     * @return array{
     *     ok: bool,
     *     errores: list<string>,
     *     requiere_pdf: bool,
     *     tiene_factura: bool,
     *     tiene_com: bool,
     *     tiene_com_asignada: bool,
     *     exige_com: bool,
     *     es_anticipada: bool,
     *     exige_flujo_empresa: bool,
     *     precarga_id: int|null,
     *     requiere_gastronomia: bool
     * }
     */
    public static function evaluarCuentasAPagar(Ordencompra $oc): array
    {
        $gate = self::evaluar($oc);
        $erroresGastro = OrdencompraLegajoGastronomiaSupport::erroresEnvioCuentasAPagar($oc);
        foreach ($erroresGastro as $errorGastro) {
            $gate['errores'][] = $errorGastro;
        }
        $gate['ok'] = $gate['errores'] === [];
        $gate['requiere_gastronomia'] = OrdencompraLegajoGastronomiaSupport::requiereCircuito($oc);

        return $gate;
    }

    public static function resolverPrecargaConPdf(Ordencompra $oc): ?Precarga_Comprobante_Proveedor
    {
        return self::queryPrecargaDelLegajo($oc)
            ->whereNotNull('rutaalmacenamiento')
            ->where('rutaalmacenamiento', '!=', '')
            ->first();
    }

    public static function precargaDelLegajo(Ordencompra $oc): ?Precarga_Comprobante_Proveedor
    {
        return self::resolverPrecargaConPdf($oc)
            ?? self::queryPrecargaDelLegajo($oc)->first();
    }

    /**
     * ¿El envío del legajo exige COM asociada a la factura?
     *
     * 1) OC anticipada sin COM: no exige (la factura se carga antes de la recepción).
     * 2) Contrato vigente: manda su circuito (con o sin recepción).
     * 3) Si no: configuración de Cuentas a pagar de la empresa
     *    (exige_flujo_oc_com_fac: OC→COM→FAC vs COM optativa).
     * 4) En flujo flexible, si igual hay COM disponible, hay que asociarla.
     *
     * @param  bool|null  $tieneComDisponibles  Si viene informado, no consulta recepciones.
     */
    public static function exigeRecepcionCom(Ordencompra $oc, ?bool $tieneComDisponibles = null): bool
    {
        if ((int) ($oc->id ?? 0) <= 0) {
            return false;
        }
        $tiene = $tieneComDisponibles ?? self::tieneComDisponible((int) $oc->id);
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica($oc, $tiene);
        $esAnticipada = ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($oc);

        return self::exigeRecepcionComSegunPoliticaYAnticipada($politica, $esAnticipada, $tiene);
    }

    /** @param  array<string, mixed>  $politica */
    public static function exigeRecepcionSegunPolitica(array $politica): bool
    {
        return (bool) ($politica['bloquea_sin_com'] ?? false)
            || (bool) ($politica['debe_asignar_com'] ?? false);
    }

    /**
     * Excepción: legajo anticipado sin COM disponible no exige recepción ni asignación.
     *
     * @param  array<string, mixed>  $politica
     */
    public static function exigeRecepcionComSegunPoliticaYAnticipada(
        array $politica,
        bool $esAnticipada,
        bool $tieneComDisponibles
    ): bool {
        if ($esAnticipada && ! $tieneComDisponibles) {
            return false;
        }

        return self::exigeRecepcionSegunPolitica($politica);
    }

    /** @param  array<string, mixed>  $politica */
    private static function mensajeFaltaCom(array $politica): string
    {
        if (! empty($politica['contrato_vigente']) && ($politica['contrato_requiere_recepcion'] ?? false)) {
            return 'El contrato vigente exige recepción COM asociada a la factura.';
        }

        return 'La factura debe tener una recepción COM confirmada asociada (con provisión contable). '
            .'La empresa tiene configurado el flujo OC → COM → factura.';
    }

    /** @param  array<string, mixed>  $politica */
    private static function mensajeFaltaComAsignada(array $politica): string
    {
        if (! empty($politica['contrato_vigente']) && ($politica['contrato_requiere_recepcion'] ?? false)) {
            return 'Debe asignar la recepción COM a la factura del legajo antes de enviarlo a Cuentas a pagar (el contrato lo exige).';
        }

        return 'Debe asignar la recepción COM a la factura del legajo antes de enviarlo a Cuentas a pagar. '
            .'Hay COM disponible pero todavía no está vinculada a la factura.';
    }

    /**
     * Documentos del legajo (precarga con PDF o scan Anita) aún sin comprobante CxP.
     *
     * @return list<array{precarga_id: int|null, anita_id: string|null, tipo: string, etiqueta: string, fecha: string|null, orden: int}>
     */
    public static function documentosPendientesCarga(Ordencompra $oc): array
    {
        $cargadas = self::clavesComprobantesCargados($oc);
        $docs = [];

        foreach (self::queryPrecargaDelLegajo($oc)
            ->with('tipotransaccion_compras:id,abreviatura,codigoafip')
            ->whereNotNull('rutaalmacenamiento')
            ->where('rutaalmacenamiento', '!=', '')
            ->orderBy('fechafactura')
            ->orderBy('id')
            ->get() as $pre) {
            $clave = self::claveNumeroFactura(
                (string) ($pre->letra ?? ''),
                (int) ($pre->sucursal ?? 0),
                (int) ($pre->numerocomprobante ?? 0)
            );
            if ($clave !== '' && isset($cargadas[$clave])) {
                continue;
            }
            $tipo = OrdencompraLegajoDocumentoTipoSupport::desdePrecarga($pre);
            $numero = trim(sprintf(
                '%s %04d-%08d',
                $pre->letra ?: 'FC',
                (int) $pre->sucursal,
                (int) $pre->numerocomprobante
            ));
            $abrev = strtoupper(trim((string) ($pre->tipotransaccion_compras->abreviatura ?? '')));
            $base = $abrev !== '' ? $abrev.' '.$numero : $numero;
            $docs[] = [
                'precarga_id' => (int) $pre->id,
                'anita_id' => null,
                'tipo' => $tipo,
                'etiqueta' => OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipo, $base),
                'fecha' => $pre->fechafactura ? $pre->fechafactura->format('Y-m-d') : null,
                'orden' => OrdencompraLegajoDocumentoTipoSupport::prioridadCarga($tipo),
            ];
        }

        $clavesPrecarga = [];
        foreach ($docs as $d) {
            $c = self::claveDesdeEtiqueta((string) ($d['etiqueta'] ?? ''));
            if ($c !== '') {
                $clavesPrecarga[$c] = true;
            }
        }
        foreach (OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc) as $scan) {
            $clave = self::claveDesdeEtiqueta((string) ($scan['numero'] ?? $scan['etiqueta'] ?? ''));
            if ($clave !== '' && (isset($cargadas[$clave]) || isset($clavesPrecarga[$clave]))) {
                continue;
            }
            $tipo = 'FC';
            $numero = trim((string) ($scan['numero'] ?? $scan['etiqueta'] ?? ''));
            $docs[] = [
                'precarga_id' => null,
                'anita_id' => (string) ($scan['id'] ?? ''),
                'tipo' => $tipo,
                'etiqueta' => OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipo, $numero),
                'fecha' => null,
                'orden' => OrdencompraLegajoDocumentoTipoSupport::prioridadCarga($tipo),
            ];
        }

        usort($docs, static function (array $a, array $b): int {
            $po = ((int) $a['orden']) <=> ((int) $b['orden']);
            if ($po !== 0) {
                return $po;
            }
            $fa = (string) ($a['fecha'] ?? '');
            $fb = (string) ($b['fecha'] ?? '');
            if ($fa !== $fb) {
                return $fa <=> $fb;
            }

            return ((int) ($a['precarga_id'] ?? 0)) <=> ((int) ($b['precarga_id'] ?? 0));
        });

        return array_values($docs);
    }

    /**
     * @return list<string>
     */
    public static function documentosQueExigenCom(Ordencompra $oc): array
    {
        $cargadas = self::clavesComprobantesCargados($oc);
        $out = [];
        foreach (self::queryPrecargaDelLegajo($oc)
            ->with('tipotransaccion_compras:id,abreviatura,codigoafip')
            ->whereNotNull('rutaalmacenamiento')
            ->where('rutaalmacenamiento', '!=', '')
            ->get() as $pre) {
            $clave = self::claveNumeroFactura(
                (string) ($pre->letra ?? ''),
                (int) ($pre->sucursal ?? 0),
                (int) ($pre->numerocomprobante ?? 0)
            );
            if ($clave !== '' && isset($cargadas[$clave])) {
                continue;
            }
            $tipo = OrdencompraLegajoDocumentoTipoSupport::desdePrecarga($pre);
            if (! OrdencompraLegajoDocumentoTipoSupport::exigeCom($tipo)) {
                continue;
            }
            $out[] = self::etiquetaPrecargaCorta($pre, $tipo);
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function documentosQueExigenComSinAsignar(Ordencompra $oc, bool $incluirAnita = true): array
    {
        $cargadas = self::clavesComprobantesCargados($oc);
        if (! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return self::documentosQueExigenCom($oc);
        }
        $out = [];
        $clavesPre = [];
        foreach (self::queryPrecargaDelLegajo($oc)
            ->with('tipotransaccion_compras:id,abreviatura,codigoafip')
            ->whereNotNull('rutaalmacenamiento')
            ->where('rutaalmacenamiento', '!=', '')
            ->get() as $pre) {
            $clave = self::claveNumeroFactura(
                (string) ($pre->letra ?? ''),
                (int) ($pre->sucursal ?? 0),
                (int) ($pre->numerocomprobante ?? 0)
            );
            if ($clave !== '') {
                $clavesPre[$clave] = true;
            }
            // Ya cargada en CxP: no bloquea el envío aunque no tenga COM en la precarga.
            if ($clave !== '' && isset($cargadas[$clave])) {
                continue;
            }
            $tipo = OrdencompraLegajoDocumentoTipoSupport::desdePrecarga($pre);
            if (! OrdencompraLegajoDocumentoTipoSupport::exigeCom($tipo)) {
                continue;
            }
            $tiene = DB::table('precarga_comprobante_proveedor_recepcion')
                ->where('precarga_comprobante_proveedor_id', (int) $pre->id)
                ->exists();
            if (! $tiene) {
                $out[] = self::etiquetaPrecargaCorta($pre, $tipo);
            }
        }

        if (! $incluirAnita) {
            return $out;
        }

        // Scan Anita sin precarga materializada: solo si aún no está cargada en CxP.
        foreach (OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc) as $scan) {
            $clave = self::claveDesdeEtiqueta((string) ($scan['numero'] ?? $scan['etiqueta'] ?? ''));
            if ($clave !== '' && (isset($clavesPre[$clave]) || isset($cargadas[$clave]))) {
                continue;
            }
            $out[] = OrdencompraLegajoDocumentoTipoSupport::numeroConTipo(
                'FC',
                trim((string) ($scan['numero'] ?? $scan['etiqueta'] ?? 'scan Anita'))
            );
        }

        return $out;
    }

    /**
     * Validación rápida para el botón de envío (sin Anita ni pendientes de carga).
     * El POST de envío sigue usando {@see evaluarCuentasAPagar}.
     *
     * @return array{
     *     ok: bool,
     *     errores: list<string>,
     *     requiere_pdf: bool,
     *     tiene_factura: bool,
     *     tiene_com: bool,
     *     tiene_com_asignada: bool,
     *     exige_com: bool,
     *     es_anticipada: bool,
     *     exige_flujo_empresa: bool,
     *     precarga_id: int|null,
     *     pendientes_carga: int,
     *     siguiente_pendiente: null,
     *     requiere_gastronomia: bool,
     *     paquete_ok: bool,
     *     paquete_errores: list<string>
     * }
     */
    public static function preflightCuentasAPagar(Ordencompra $oc): array
    {
        $oc->loadMissing('empresas:id,codigo,nombre');
        $facturaPdf = self::resolverPrecargaConPdf($oc);
        $precarga = $facturaPdf ?? self::queryPrecargaDelLegajo($oc)->first();
        $tieneFactura = $facturaPdf !== null || $precarga !== null;
        $tieneCom = self::tieneComDisponible((int) $oc->id);
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica($oc, $tieneCom);
        $esAnticipada = ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($oc);
        $exigeCom = self::exigeRecepcionComSegunPoliticaYAnticipada($politica, $esAnticipada, $tieneCom);

        $errores = [];
        if (! $tieneFactura) {
            $errores[] = 'Debe asignar una factura (precarga o PDF escaneado) al legajo antes de enviarlo a Cuentas a pagar.';
        }
        $faltanCom = [];
        if ($exigeCom) {
            $faltanCom = self::documentosQueExigenComSinAsignar($oc, false);
            if ($faltanCom !== []) {
                $errores[] = 'Falta asignar COM a: '.implode(', ', $faltanCom).'.';
            } elseif (! $tieneCom && self::documentosQueExigenCom($oc) !== []) {
                $errores[] = self::mensajeFaltaCom($politica);
            }
        }

        $erroresContrato = app(\App\Services\Compras\ContratoValidacionAbonoService::class)
            ->erroresEnvioCuentasAPagar($oc);
        foreach ($erroresContrato as $errorContrato) {
            $errores[] = $errorContrato;
        }

        $erroresGastro = OrdencompraLegajoGastronomiaSupport::erroresEnvioCuentasAPagar($oc);
        foreach ($erroresGastro as $errorGastro) {
            $errores[] = $errorGastro;
        }

        $ok = $errores === [];

        return [
            'ok' => $ok,
            'errores' => $errores,
            'requiere_pdf' => ! $tieneFactura,
            'tiene_factura' => $tieneFactura,
            'tiene_com' => $tieneCom,
            'tiene_com_asignada' => ! $exigeCom || $faltanCom === [],
            'exige_com' => $exigeCom,
            'es_anticipada' => $esAnticipada,
            'exige_flujo_empresa' => (bool) ($politica['exige_flujo'] ?? false),
            'precarga_id' => $facturaPdf?->id ?? $precarga?->id,
            'pendientes_carga' => 0,
            'siguiente_pendiente' => null,
            'requiere_gastronomia' => OrdencompraLegajoGastronomiaSupport::requiereCircuito($oc),
            'paquete_ok' => $ok,
            'paquete_errores' => $errores,
        ];
    }

    private static function etiquetaPrecargaCorta(Precarga_Comprobante_Proveedor $pre, string $tipo): string
    {
        $numero = trim(sprintf(
            '%s %04d-%08d',
            $pre->letra ?: 'FC',
            (int) $pre->sucursal,
            (int) $pre->numerocomprobante
        ));

        return OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipo, $numero);
    }

    /**
     * @return array<string, true>
     */
    private static function clavesComprobantesCargados(Ordencompra $oc): array
    {
        $cargadas = [];
        $precargaIds = self::queryPrecargaDelLegajo($oc)->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $queryCp = Comprobante_Proveedor::query()
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->where(function ($q) use ($oc, $precargaIds) {
                $q->where('ordencompra_id', (int) $oc->id);
                if ($precargaIds !== []) {
                    $q->orWhereIn('precarga_comprobante_proveedor_id', $precargaIds);
                }
            });
        foreach ($queryCp->get(['letra', 'sucursal', 'numerocomprobante']) as $cp) {
            $clave = self::claveNumeroFactura(
                (string) ($cp->letra ?? ''),
                (int) ($cp->sucursal ?? 0),
                (int) ($cp->numerocomprobante ?? 0)
            );
            if ($clave !== '') {
                $cargadas[$clave] = true;
            }
        }

        return $cargadas;
    }

    /**
     * Números de factura del paquete que todavía no están cargados como comprobante en CxP.
     *
     * @return list<string>
     */
    public static function facturasSinCargarEnCxp(Ordencompra $oc): array
    {
        return array_values(array_map(
            static fn (array $d) => (string) ($d['etiqueta'] ?? ''),
            self::documentosPendientesCarga($oc)
        ));
    }

    private static function claveNumeroFactura(string $letra, int $sucursal, int $numero): string
    {
        $letra = strtoupper(trim($letra));
        if ($letra === '' && $numero <= 0) {
            return '';
        }

        return ($letra !== '' ? $letra : 'FC').'|'.$sucursal.'|'.$numero;
    }

    private static function claveDesdeEtiqueta(string $etiqueta): string
    {
        $etiqueta = strtoupper(trim($etiqueta));
        if (preg_match('/([A-Z])\s+(\d{1,5})-(\d{1,8})/', $etiqueta, $m)) {
            return $m[1].'|'.((int) $m[2]).'|'.((int) $m[3]);
        }

        return '';
    }

    private static function etiquetaDesdeClave(string $clave): string
    {
        $partes = explode('|', $clave);
        if (count($partes) !== 3) {
            return $clave;
        }

        return sprintf('%s %04d-%08d', $partes[0], (int) $partes[1], (int) $partes[2]);
    }

    public static function tieneComDisponible(int $ordencompraId): bool
    {
        if ($ordencompraId <= 0) {
            return false;
        }

        // Exists local rápido: no sincronizar Anita ni hidratar relaciones (el gate de UI no puede esperar eso).
        return Recepcion_Proveedor::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->tap(fn ($q) => ComprobanteProveedorRecepcionesSupport::aplicarCriterioAsignable($q))
            ->exists();
    }

    /**
     * ¿Hay al menos una COM vinculada a alguna precarga/factura del legajo?
     */
    public static function tieneComAsignadaAFactura(Ordencompra $oc): bool
    {
        if (! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return false;
        }

        $precargaIds = self::queryPrecargaDelLegajo($oc)->pluck('id')->map(static fn ($id) => (int) $id)->all();
        if ($precargaIds === []) {
            return false;
        }

        return DB::table('precarga_comprobante_proveedor_recepcion')
            ->whereIn('precarga_comprobante_proveedor_id', $precargaIds)
            ->exists();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Compras\Precarga_Comprobante_Proveedor>
     */
    private static function queryPrecargaDelLegajo(Ordencompra $oc)
    {
        $numero = trim((string) ($oc->numeroordencompra ?? ''));
        $empresaId = (int) ($oc->empresa_id ?? 0);
        $query = Precarga_Comprobante_Proveedor::query()->whereRaw('1 = 0');
        if ($numero === '' || $empresaId <= 0) {
            return $query;
        }

        return Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroordencompra', $numero)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->orderByDesc('id');
    }

    /**
     * Precargas del legajo (con o sin PDF) para reutilizar al adjuntar archivo.
     */
    public static function precargaDelLegajoSinPdf(Ordencompra $oc): ?Precarga_Comprobante_Proveedor
    {
        $numero = trim((string) ($oc->numeroordencompra ?? ''));
        $empresaId = (int) ($oc->empresa_id ?? 0);
        if ($numero === '' || $empresaId <= 0) {
            return null;
        }

        return Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroordencompra', $numero)
            ->where(function ($q) {
                $q->whereNull('rutaalmacenamiento')->orWhere('rutaalmacenamiento', '');
            })
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->orderByDesc('id')
            ->first();
    }

    public static function tipotransaccionCompraDefaultId(): int
    {
        $id = (int) (DB::table('tipotransaccion_compra')->where('abreviatura', 'FGA')->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('tipotransaccion_compra')->orderBy('id')->value('id') ?? 0);
    }

    /**
     * Tipo de factura según CC destino de la OC (listaConcepto). Si no se puede resolver, FGA.
     */
    public static function tipotransaccionCompraIdParaOrdencompra(Ordencompra $oc, string $tipoComprobante = 'FC'): int
    {
        $id = PrecargaProveedor\PrecargaProveedorAbreviaturaTipoSupport::tipotransaccionIdDesdeOrdencompra(
            $oc,
            $tipoComprobante
        );
        if ($id > 0) {
            return $id;
        }

        return self::tipotransaccionCompraDefaultId();
    }

    public static function tipotransaccionCompraIdPorCodigoAfip(string $codigoAfip): int
    {
        $norm = ComprobanteProveedorUnicidadSupport::normalizarCodigoAfip($codigoAfip);
        $ids = ComprobanteProveedorUnicidadSupport::tipotransaccionIdsPorCodigoAfip($norm);
        if ($ids === []) {
            return 0;
        }

        $prefer = (int) (DB::table('tipotransaccion_compra')
            ->whereIn('id', $ids)
            ->whereIn('abreviatura', ['FGA', 'FGB', 'FGC', 'NDA', 'NDB', 'NDC', 'NCA', 'NCB', 'NCC'])
            ->orderBy('id')
            ->value('id') ?? 0);

        return $prefer > 0 ? $prefer : (int) $ids[0];
    }
}
