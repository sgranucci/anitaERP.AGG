<?php

namespace App\Support\Compras;

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

    public static function puedeFinalizar(?Ordencompra $oc): bool
    {
        if (! $oc || ! $oc->id) {
            return false;
        }
        $sectorId = (int) ($oc->sector_legajocompra_id ?? 0);

        return OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar($sectorId)
            && self::sectorFinalizadoId() > 0;
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
        if (self::esSectorFinalizado($sectorId)) {
            return false;
        }

        return OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR
        ) > 0;
    }

    /**
     * @return array{
     *     factura: array<string, mixed>|null,
     *     recepciones: list<array<string, mixed>>
     * }
     */
    public static function paqueteParaPortal(Ordencompra $oc, ?string $hash = null): array
    {
        $factura = OrdencompraEnvioCuentasAPagarGateSupport::resolverPrecargaConPdf($oc);
        $hash = trim((string) $hash);
        $urlPdf = null;
        if ($factura && $hash !== '') {
            $urlPdf = route('visualizar_factura_legajo_ordencompra', [
                'id' => (int) $oc->id,
                'hash' => $hash,
            ]).'?inline=1';
        }

        return [
            'factura' => $factura ? self::resumenFactura($factura, $urlPdf) : null,
            'recepciones' => self::resumenRecepciones((int) $oc->id),
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
        $numero = trim(sprintf(
            '%s %s-%s',
            (string) ($factura->letra ?? ''),
            (string) ($factura->sucursal ?? ''),
            (string) ($factura->numerocomprobante ?? '')
        ));

        return [
            'id' => (int) $factura->id,
            'numero' => $numero !== '' ? $numero : ('Precarga #'.$factura->id),
            'fecha' => $factura->fechafactura?->format('d/m/Y'),
            'total' => $factura->total !== null ? (float) $factura->total : null,
            'url_pdf' => $urlPdf,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function resumenRecepciones(int $ordencompraId): array
    {
        if ($ordencompraId <= 0) {
            return [];
        }

        $recepciones = Recepcion_Proveedor::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', '!=', Recepcion_Proveedor::ESTADO_ANULADA)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

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
            $nro = $rec->numerorecepcion ?: $rec->id;
            $filas[] = [
                'id' => (int) $rec->id,
                'numero' => 'COM #'.$nro,
                'fecha' => $rec->fecha?->format('d/m/Y'),
                'estado' => (string) $rec->estado,
                'diferencias' => $diferencias,
                'resumen_diferencias' => $resumen !== '' ? $resumen : null,
            ];
        }

        return $filas;
    }
}
