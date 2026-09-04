<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Configuracion\Arbolaprobacion_OcTrigger;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use App\Support\Configuracion\OcArbolTriggerCatalog;

/**
 * Circuito extra del legajo de Gastronomía (CC del árbol OC, sector GASTRONOMIA).
 */
final class OrdencompraLegajoGastronomiaSupport
{
    public const SECTOR_GASTRONOMIA = 'GASTRONOMIA';

    public const SECTOR_FINALIZADO = 'FINALIZADO';

    public const CIRCUITO_SECTOR = 'sector';

    /**
     * @return array{
     *     aplica: bool,
     *     arbol_id: int,
     *     centrocosto_id: int,
     *     sector_disparo_id: int,
     *     sector_destino_id: int
     * }
     */
    public static function circuitoDeEmpresa(int $empresaId): array
    {
        $vacio = [
            'aplica' => false,
            'arbol_id' => 0,
            'centrocosto_id' => 0,
            'sector_disparo_id' => 0,
            'sector_destino_id' => 0,
        ];
        if ($empresaId <= 0) {
            return $vacio;
        }

        $arbol = app(ArbolaprobacionService::class)->arbolOrdencompraActivoParaEmpresa($empresaId);
        if (! $arbol) {
            return $vacio;
        }

        $ccId = (int) ($arbol->oc_sector_cambio_centrocosto_id ?? 0);
        if ($ccId <= 0) {
            return $vacio;
        }

        $disparoId = (int) ($arbol->oc_sector_disparo_aprobacion_id ?? 0);
        if ($disparoId <= 0) {
            $disparoId = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(self::SECTOR_GASTRONOMIA);
        }

        $destinoId = (int) ($arbol->oc_sector_destino_aprobacion_id ?? 0);
        if ($destinoId <= 0) {
            $destinoId = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
                OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR
            );
        }

        return [
            'aplica' => $disparoId > 0,
            'arbol_id' => (int) $arbol->id,
            'centrocosto_id' => $ccId,
            'sector_disparo_id' => $disparoId,
            'sector_destino_id' => $destinoId,
        ];
    }

    public static function requiereCircuito(?Ordencompra $oc): bool
    {
        if (! $oc || ! $oc->id) {
            return false;
        }
        $circuito = self::circuitoDeEmpresa((int) ($oc->empresa_id ?? 0));
        if (! $circuito['aplica']) {
            return false;
        }

        return (int) ($oc->centrocosto_id ?? 0) === $circuito['centrocosto_id'];
    }

    public static function esSectorGastronomia(int $sectorId): bool
    {
        if ($sectorId <= 0) {
            return false;
        }
        $nombre = \App\Models\Compras\Sector_Legajocompra::query()->whereKey($sectorId)->value('nombre');

        return strtoupper(trim((string) $nombre)) === self::SECTOR_GASTRONOMIA;
    }

    public static function sectorGastronomiaId(): int
    {
        return OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(self::SECTOR_GASTRONOMIA);
    }

    public static function sectorFinalizadoId(): int
    {
        return OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(self::SECTOR_FINALIZADO);
    }

    public static function esSectorFinalizado(int $sectorId): bool
    {
        if ($sectorId <= 0) {
            return false;
        }

        $nombre = \App\Models\Compras\Sector_Legajocompra::query()->whereKey($sectorId)->value('nombre');

        return strtoupper(trim((string) $nombre)) === self::SECTOR_FINALIZADO;
    }

    /** @return list<int> */
    public static function centrocostoIdsCircuito(): array
    {
        $ids = \App\Models\Configuracion\Arbolaprobacion::query()
            ->where('oc_sector_cambio_centrocosto_id', '>', 0)
            ->pluck('oc_sector_cambio_centrocosto_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        if ($ids !== []) {
            return $ids;
        }
        $cc85 = (int) (\App\Models\Contable\Centrocosto::query()->where('codigo', '85')->value('id') ?? 0);

        return $cc85 > 0 ? [$cc85] : [];
    }

    public static function diasEnUbicacion(?\DateTimeInterface $desde, ?\DateTimeInterface $ahora = null): int
    {
        if ($desde === null) {
            return 0;
        }
        $fin = $ahora ?? now();

        return max(0, (int) $desde->diff($fin)->days);
    }

    public static function enlaceVencido(?\DateTimeInterface $fechaEnvio, ?int $dias = null, ?\DateTimeInterface $ahora = null): bool
    {
        if ($fechaEnvio === null) {
            return false;
        }
        $plazo = $dias ?? (int) config('compras.legajo.link_dias_vencimiento', 3);
        if ($plazo <= 0) {
            return false;
        }
        $limite = \Carbon\Carbon::instance($fechaEnvio)->addDays($plazo);
        $ref = $ahora ? \Carbon\Carbon::instance($ahora) : now();

        return $ref->greaterThan($limite);
    }

    public static function sectorPagosId(): int
    {
        return OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_PAGOS
        );
    }

    public static function esSectorPagos(int $sectorId): bool
    {
        if ($sectorId <= 0) {
            return false;
        }
        $nombre = \App\Models\Compras\Sector_Legajocompra::query()->whereKey($sectorId)->value('nombre');

        return strtoupper(trim((string) $nombre)) === OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_PAGOS;
    }

    public static function tieneFacturaCargada(?Ordencompra $oc): bool
    {
        if (! $oc || ! $oc->id) {
            return false;
        }

        return Comprobante_Proveedor::query()
            ->where('ordencompra_id', (int) $oc->id)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', '!=', ComprobanteProveedorEstados::ANULADO);
            })
            ->exists();
    }

    public static function puedeMostrarEnviarPagos(?Ordencompra $oc): bool
    {
        if (! $oc || ! $oc->id) {
            return false;
        }
        $sectorId = (int) ($oc->sector_legajocompra_id ?? 0);
        if (! OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar($sectorId)) {
            return false;
        }
        if (self::sectorPagosId() <= 0) {
            return false;
        }

        return self::tieneFacturaCargada($oc);
    }

    public static function puedeDevolverACuentasAPagar(?Ordencompra $oc): bool
    {
        if (! $oc || ! $oc->id) {
            return false;
        }
        $sectorId = (int) ($oc->sector_legajocompra_id ?? 0);

        return self::esSectorPagos($sectorId)
            && OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
                OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR
            ) > 0;
    }

    public static function puedeDevolverACompras(?Ordencompra $oc): bool
    {
        if (! $oc || ! $oc->id) {
            return false;
        }
        $sectorId = (int) ($oc->sector_legajocompra_id ?? 0);

        return OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar($sectorId)
            && OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
                OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_COMPRAS
            ) > 0;
    }

    public static function puedeFinalizar(?Ordencompra $oc): bool
    {
        if (! $oc || ! $oc->id) {
            return false;
        }
        $sectorId = (int) ($oc->sector_legajocompra_id ?? 0);

        return self::esSectorPagos($sectorId) && self::sectorFinalizadoId() > 0;
    }

    /** Sectores disponibles para el combo de cambio (FINALIZADO se usa solo desde el botón). */
    public static function sectoresParaCambio(): \Illuminate\Support\Collection
    {
        return \App\Models\Compras\Sector_Legajocompra::query()
            ->orderBy('nombre')
            ->get()
            ->filter(fn ($s) => ! self::esSectorFinalizado((int) $s->id))
            ->values();
    }

    /**
     * @param  list<string>  $estadosCronologicosDesc  Más reciente primero.
     */
    public static function autorizacionCompletaDesdeEstados(array $estadosCronologicosDesc): bool
    {
        $pendiente = self::nombreEstado('P');
        $aprobado = self::nombreEstado('A');
        $rechazado = self::nombreEstado('R');

        foreach ($estadosCronologicosDesc as $estado) {
            $e = (string) $estado;
            if ($e === $pendiente) {
                return false;
            }
            if ($e === $aprobado) {
                return true;
            }
            if ($e === $rechazado) {
                return false;
            }
        }

        return false;
    }

    public static function autorizacionCompleta(int $ordencompraId): bool
    {
        return self::autorizacionCompletaDesdeEstados(self::estadosCircuitoSector($ordencompraId));
    }

    public static function tienePendiente(int $ordencompraId): bool
    {
        $pendiente = self::nombreEstado('P');
        foreach (self::estadosCircuitoSector($ordencompraId) as $estado) {
            if ($estado === $pendiente) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function erroresEnvioCuentasAPagar(Ordencompra $oc): array
    {
        if (! self::requiereCircuito($oc)) {
            return [];
        }

        $sectorActual = (int) ($oc->sector_legajocompra_id ?? 0);
        $enGastronomia = self::esSectorGastronomia($sectorActual)
            || (self::circuitoDeEmpresa((int) ($oc->empresa_id ?? 0))['sector_disparo_id'] === $sectorActual);

        if ($enGastronomia && self::autorizacionCompleta((int) $oc->id)) {
            return [];
        }

        return [
            'Este legajo es de Gastronomía: debe enviarse al referente y quedar autorizado antes de Cuentas a pagar.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function erroresEnvioGastronomia(Ordencompra $oc): array
    {
        if (! self::requiereCircuito($oc)) {
            return ['Esta orden de compra no corresponde al circuito de Gastronomía.'];
        }
        $circuito = self::circuitoDeEmpresa((int) ($oc->empresa_id ?? 0));
        $sectorActual = (int) ($oc->sector_legajocompra_id ?? 0);
        if ($circuito['sector_disparo_id'] > 0 && $sectorActual === $circuito['sector_disparo_id']) {
            return ['El legajo ya está en Gastronomía.'];
        }
        if (OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar($sectorActual)) {
            return ['El legajo ya está en Cuentas a pagar.'];
        }

        return [];
    }

    public static function puedeMostrarEnviar(?Ordencompra $oc): bool
    {
        if (! self::requiereCircuito($oc)) {
            return false;
        }
        $errores = self::erroresEnvioGastronomia($oc);

        return $errores === [];
    }

    public static function puedeMostrarEnviarCuentasAPagar(?Ordencompra $oc): bool
    {
        if (! $oc || ! $oc->id) {
            return false;
        }
        $sectorId = (int) ($oc->sector_legajocompra_id ?? 0);
        if (OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar($sectorId)) {
            return false;
        }
        if (self::esSectorPagos($sectorId) || self::esSectorFinalizado($sectorId)) {
            return false;
        }

        return OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR
        ) > 0;
    }

    /**
     * @return array{
     *     cabecera: array<string, mixed>,
     *     factura: array<string, mixed>|null,
     *     ordencompra: array<string, mixed>,
     *     recepciones: list<array<string, mixed>>,
     *     url_pdf_oc: string|null,
     *     importe_total_con_iva: float|null
     * }
     */
    public static function paqueteParaPortal(Ordencompra $oc, ?string $hash = null): array
    {
        $oc->loadMissing([
            'empresas:id,nombre',
            'proveedores:id,codigo,nombre',
            'centrocostos:id,codigo,nombre',
            'usuarios:id,nombre',
            'requisiciones:id,numerorequisicion,creousuario_id',
            'requisiciones.usuarios:id,nombre',
            'ordencompra_articulos.articulos:id,sku,descripcion',
        ]);

        $factura = OrdencompraEnvioCuentasAPagarGateSupport::resolverPrecargaConPdf($oc);
        if ($factura) {
            $factura->loadMissing([
                'proveedores:id,nombre',
            ]);
        }

        $hash = trim((string) $hash);
        $ocId = (int) $oc->id;
        $urlPdf = null;
        $urlPdfOc = null;
        if ($hash !== '') {
            if ($factura) {
                $urlPdf = route('visualizar_factura_legajo_ordencompra', [
                    'id' => $ocId,
                    'hash' => $hash,
                ]).'?inline=1';
            }
            $urlPdfOc = route('visualizar_oc_pdf_legajo_ordencompra', [
                'id' => $ocId,
                'hash' => $hash,
            ]).'?inline=1';
        }

        $resumenFactura = $factura ? self::resumenFactura($factura, $urlPdf) : null;
        $subtotalOc = self::subtotalItemsOc($oc);
        $importeTotal = $resumenFactura['total'] ?? $subtotalOc;

        return [
            'cabecera' => [
                'numero_oc' => (string) ($oc->numeroordencompra ?? ''),
                'proveedor' => (string) (optional($oc->proveedores)->nombre ?? '—'),
                'empresa' => (string) (optional($oc->empresas)->nombre ?? '—'),
                'centrocosto' => trim(
                    (string) (optional($oc->centrocostos)->codigo ?? '').' - '.
                    (string) (optional($oc->centrocostos)->nombre ?? '')
                ) ?: '—',
                'estado_badge' => 'GASTRONOMÍA — PENDIENTE DE AUTORIZAR',
                'importe_total_con_iva' => $importeTotal,
            ],
            'factura' => $resumenFactura,
            'ordencompra' => self::resumenOrdencompra($oc, $subtotalOc, $urlPdfOc),
            'recepciones' => self::resumenRecepciones($ocId, $hash !== '' ? $hash : null),
            'url_pdf_oc' => $urlPdfOc,
            'importe_total_con_iva' => $importeTotal,
        ];
    }

    /**
     * Datos extra para el mail del árbol cuando es circuito legajo Gastronomía.
     *
     * @return array<string, mixed>
     */
    public static function extrasMailLegajo(Ordencompra $oc, float $montoItems = 0.0, string $monedaAbrev = ''): array
    {
        if (! self::requiereCircuito($oc)) {
            return ['es_legajo_gastronomia' => false];
        }

        $oc->loadMissing(['proveedores:id,nombre', 'centrocostos:id,codigo,nombre']);
        $factura = OrdencompraEnvioCuentasAPagarGateSupport::resolverPrecargaConPdf($oc);
        $totalFactura = $factura && $factura->total !== null ? (float) $factura->total : null;

        return [
            'es_legajo_gastronomia' => true,
            'proveedor_nombre' => (string) (optional($oc->proveedores)->nombre ?? '—'),
            'centrocosto_corto' => trim((string) (optional($oc->centrocostos)->nombre ?? 'Gastronomía')) ?: 'Gastronomía',
            'monto_items' => $montoItems,
            'moneda_abrev_items' => $monedaAbrev,
            'total_factura' => $totalFactura,
            'total_factura_fmt' => $totalFactura !== null
                ? number_format($totalFactura, 2, ',', '.')
                : null,
            'alerta_importe' => $totalFactura !== null ? $totalFactura : $montoItems,
            'alerta_importe_fmt' => number_format(
                $totalFactura !== null ? $totalFactura : $montoItems,
                2,
                ',',
                '.'
            ),
            'alerta_con_iva' => $totalFactura !== null,
        ];
    }

    public static function hashVisualizarValido(int $ordencompraId, string $hash): bool
    {
        $hash = ArbolAprobacionEnlaceSupport::normalizarHashRecibido($hash);
        if ($ordencompraId <= 0 || $hash === '') {
            return false;
        }

        $movimientos = Arbolaprobacion_Movimiento::query()
            ->where('ordencompra_id', $ordencompraId)
            ->whereNotNull('hashvisualizar')
            ->get(['hashvisualizar']);

        foreach ($movimientos as $mov) {
            if (ArbolAprobacionEnlaceSupport::hashesCoinciden($hash, (string) $mov->hashvisualizar)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function estadosCircuitoSector(int $ordencompraId): array
    {
        if ($ordencompraId <= 0) {
            return [];
        }

        $triggerIds = Arbolaprobacion_OcTrigger::query()
            ->where('evento', OcArbolTriggerCatalog::EVENTO_CAMBIO_SECTOR)
            ->pluck('id')
            ->all();

        $query = Arbolaprobacion_Movimiento::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where(function ($q) use ($triggerIds) {
                $q->where('circuito_oc', self::CIRCUITO_SECTOR);
                if ($triggerIds !== []) {
                    $q->orWhereIn('arbolaprobacion_oc_trigger_id', $triggerIds);
                }
            })
            ->orderByDesc('id');

        return $query->pluck('estado')->map(fn ($e) => (string) $e)->all();
    }

    private static function nombreEstado(string $valor): string
    {
        $idx = array_search($valor, array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'), true);

        return $idx === false
            ? $valor
            : (string) Arbolaprobacion_Movimiento::$enumEstado[$idx]['nombre'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resumenFactura(Precarga_Comprobante_Proveedor $factura, ?string $urlPdf): array
    {
        $suc = ltrim((string) ($factura->sucursal ?? ''), '0');
        $nro = ltrim((string) ($factura->numerocomprobante ?? ''), '0');
        $numeroCorto = trim($suc.'-'.$nro, '-');
        $numero = $numeroCorto !== '' ? 'FAC '.$numeroCorto : ('Precarga #'.$factura->id);

        $neto = $factura->subtotal !== null ? (float) $factura->subtotal : null;
        $total = $factura->total !== null ? (float) $factura->total : null;
        $iva = null;
        $ivaLabel = 'IVA';
        if ($neto !== null && $total !== null && $total >= $neto) {
            $iva = round($total - $neto, 2);
            if ($neto > 0.0001) {
                $alicuota = round(($iva / $neto) * 100);
                if ($alicuota > 0) {
                    $ivaLabel = 'IVA '.$alicuota.'%';
                }
            }
        }

        $cuit = trim((string) ($factura->identificacion_proveedor_cuit ?? ''));

        return [
            'id' => (int) $factura->id,
            'numero' => $numero,
            'fecha' => $factura->fechafactura?->format('d/m/Y'),
            'cuit' => $cuit !== '' ? $cuit : null,
            'neto' => $neto,
            'iva' => $iva,
            'iva_label' => $ivaLabel,
            'total' => $total,
            'url_pdf' => $urlPdf,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resumenOrdencompra(Ordencompra $oc, float $subtotal, ?string $urlPdfOc): array
    {
        $req = $oc->requisiciones;
        $reqLabel = null;
        if ($req) {
            $reqNro = (string) ($req->numerorequisicion ?? $req->id);
            $reqUser = trim((string) (optional($req->usuarios)->nombre ?? ''));
            $reqLabel = $reqUser !== '' ? $reqNro.' — '.$reqUser : $reqNro;
        }

        $itemResumen = null;
        $articulos = $oc->ordencompra_articulos ?? collect();
        if ($articulos->isNotEmpty()) {
            $primero = $articulos->first();
            $desc = trim((string) (optional($primero->articulos)->descripcion ?? $primero->detalle ?? ''));
            $cant = (float) ($primero->cantidad ?? 0);
            $itemResumen = $desc !== ''
                ? ($desc.' — '.number_format($cant, 2, ',', '.').' u.')
                : null;
            if ($articulos->count() > 1) {
                $itemResumen = ($itemResumen ?? 'Ítems').' (+'.($articulos->count() - 1).' más)';
            }
        }

        return [
            'numero' => 'OC '.((string) ($oc->numeroordencompra ?? $oc->id)),
            'fecha' => $oc->fecha ? date('d/m/Y', strtotime((string) $oc->fecha)) : null,
            'solicitante' => (string) (optional($oc->usuarios)->nombre ?? '—'),
            'requisicion' => $reqLabel,
            'detalle' => trim((string) ($oc->detalle ?? '')) !== '' ? (string) $oc->detalle : null,
            'item_resumen' => $itemResumen,
            'subtotal' => $subtotal,
            'url_pdf' => $urlPdfOc,
        ];
    }

    private static function subtotalItemsOc(Ordencompra $oc): float
    {
        $suma = 0.0;
        foreach ($oc->ordencompra_articulos ?? [] as $linea) {
            $suma += (float) ($linea->cantidad ?? 0) * (float) ($linea->precio ?? 0);
        }

        return round($suma, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function resumenRecepciones(int $ordencompraId, ?string $hash = null): array
    {
        if ($ordencompraId <= 0) {
            return [];
        }

        $recepciones = Recepcion_Proveedor::query()
            ->with([
                'creousuarios:id,nombre',
                'recepcion_proveedor_articulos:id,recepcion_proveedor_id,cantidad,cantidad_oc',
            ])
            ->where('ordencompra_id', $ordencompraId)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', '!=', Recepcion_Proveedor::ESTADO_ANULADA)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $hash = trim((string) $hash);
        $filas = [];
        foreach ($recepciones as $rec) {
            $diferencias = [];
            if ($rec->fl_diferencia_cantidad) {
                $diferencias[] = 'Cantidad';
            }
            if ($rec->fl_precio_diferencia) {
                $diferencias[] = 'Precio';
            }
            if ($rec->fl_articulo_extra) {
                $diferencias[] = 'Artículo extra';
            }
            if ($rec->fl_faltante_oc) {
                $diferencias[] = 'Faltante OC';
            }
            $resumen = trim((string) ($rec->resumen_diferencias ?? ''));
            $numero = self::etiquetaCom($rec);
            $urlPdf = null;
            if ($hash !== '') {
                $urlPdf = route('visualizar_com_legajo_ordencompra', [
                    'id' => $ordencompraId,
                    'hash' => $hash,
                    'recepcion' => (int) $rec->id,
                ]).'?inline=1';
            }

            $cantOc = 0.0;
            $cantRec = 0.0;
            foreach ($rec->recepcion_proveedor_articulos as $linea) {
                $cantOc += (float) ($linea->cantidad_oc ?? 0);
                $cantRec += (float) ($linea->cantidad ?? 0);
            }

            $filas[] = [
                'id' => (int) $rec->id,
                'numero' => $numero,
                'fecha' => $rec->fecha?->format('d/m/Y'),
                'estado' => (string) $rec->estado,
                'usuario' => (string) (optional($rec->creousuarios)->nombre ?? '—'),
                'cantidad_oc' => $cantOc,
                'cantidad_recibida' => $cantRec,
                'diferencias' => $diferencias,
                'resumen_diferencias' => $resumen !== '' ? $resumen : null,
                'sin_diferencias' => $diferencias === [] && $resumen === '',
                'url_pdf' => $urlPdf,
            ];
        }

        return $filas;
    }

    private static function etiquetaCom(Recepcion_Proveedor $rec): string
    {
        $anitaTipo = trim((string) ($rec->anita_tipo ?? ''));
        $anitaLetra = trim((string) ($rec->anita_letra ?? ''));
        $anitaSuc = ltrim((string) ($rec->anita_sucursal ?? ''), '0');
        $anitaNro = ltrim((string) ($rec->anita_nro ?? ''), '0');
        if ($anitaNro !== '') {
            $pref = $anitaTipo !== '' ? $anitaTipo : 'COM';
            $medio = trim($anitaLetra.' '.$anitaSuc, ' ');
            if ($medio !== '') {
                return $pref.' '.$medio.'-'.$anitaNro;
            }

            return $pref.' '.$anitaNro;
        }

        $nro = $rec->numerorecepcion ?: $rec->id;

        return 'COM #'.$nro;
    }
}
