<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Models\Ventas\Venta_Impuesto;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Configuracion\PercepcionNoCategorizadoSupport;
use App\Support\Configuracion\RegimenPercepcionSupport;

/**
 * Nota de débito total que revierte una nota de crédito de facturación de mostrador.
 *
 * Copia ítems (precios e IVA de la NC) y las percepciones grabadas en la NC.
 * Si la NC no percibió, la ND no calcula padrón ni percepción IVA.
 * No aplica a POS gastronomía ni estacionamiento.
 */
final class NotaDebitoReversionNotaCreditoSupport
{
    public const FLAG = 'reversion_nota_credito';

    public const FLAG_NC_ID = 'reversion_nota_credito_id';

    /** @var array<string, string> abreviatura NC => abreviatura ND */
    private const PAR_ABREVIATURA = [
        'NCA' => 'NDA',
        'NCB' => 'NDB',
        'NCC' => 'NDC',
        'NCD' => 'NDB',
        'NCE' => 'NER',
        'NCG' => 'NDB',
        'NCI' => 'NDB',
        'NCJ' => 'NDB',
        'NCL' => 'NDB',
        'NCP' => 'NDP',
        'NCR' => 'NDR',
        'CIM' => 'DIM',
    ];

    /** @var array<int, int> tipo AFIP de la NC => tipo AFIP de la ND */
    private const ND_POR_NC_AFIP = [
        3 => 2,
        8 => 7,
        13 => 12,
        21 => 20,
        53 => 52,
        203 => 202,
        208 => 207,
        213 => 212,
    ];

    /** @var array<int, list<Venta_Impuesto>|null> */
    private static array $impuestosCache = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function payloadEsReversion(array $data): bool
    {
        return ! empty($data[self::FLAG]);
    }

    /**
     * @param  array<string, mixed>  $dataCliente
     */
    public static function activa(array $dataCliente): bool
    {
        return ! empty($dataCliente[self::FLAG]);
    }

    /**
     * @param  array<string, mixed>  $datosCliente
     */
    public static function anexarEnDatosCliente(array &$datosCliente, int $notaCreditoId): void
    {
        $datosCliente[self::FLAG] = true;
        $datosCliente[self::FLAG_NC_ID] = $notaCreditoId;
    }

    public static function esNotaCreditoReversible(?Venta $venta): bool
    {
        $tipo = $venta?->tipotransacciones;
        if ($tipo === null || ! $tipo->esNotaCredito()) {
            return false;
        }

        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        if ($abrev !== '' && (isset(self::PAR_ABREVIATURA[$abrev]) || str_starts_with($abrev, 'NC'))) {
            return true;
        }

        return self::codigoAfipNotaDebito($venta) > 0;
    }

    public static function errorAlAbrir(?Venta $venta): ?string
    {
        $error = self::errorNotaCreditoMostrador($venta);
        if ($error !== null) {
            return $error;
        }

        if (self::tipoNotaDebitoPara($venta) === null) {
            $abrev = (string) ($venta->tipotransacciones->abreviatura ?? '');

            return 'No hay un tipo de nota de débito activo para revertir la nota de crédito'
                .($abrev !== '' ? ' '.$abrev : '').'.';
        }

        return self::errorSiYaRevertida((int) $venta->id, (string) ($venta->codigo ?? ''));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function errorAlEmitir(int $notaCreditoId, int $tipoNotaDebitoId, array $data = []): ?string
    {
        $venta = $notaCreditoId > 0
            ? Venta::query()->with(['tipotransacciones', 'gastronomiaEmision', 'estacionamientoEmision'])->find($notaCreditoId)
            : null;
        $error = self::errorAlAbrir($venta);
        if ($error !== null || $venta === null) {
            return $error ?? 'La nota de crédito a revertir no existe.';
        }

        $tipoNd = self::tipoNotaDebitoPara($venta);
        if ($tipoNotaDebitoId > 0 && $tipoNd !== null && $tipoNotaDebitoId !== (int) $tipoNd->id) {
            return 'La reversión debe emitirse con el tipo '.$tipoNd->abreviatura.' ('.$tipoNd->nombre.').';
        }

        return self::errorSiCantidadNoEsTotal($data, (int) $venta->id);
    }

    public static function tipoNotaDebitoPara(?Venta $venta): ?Tipotransaccion
    {
        if ($venta === null || $venta->tipotransacciones === null || ! $venta->tipotransacciones->esNotaCredito()) {
            return null;
        }

        $activas = Tipotransaccion::query()->where('estado', 'A')->get();
        $candidatas = [];
        foreach ($activas as $tipo) {
            if ($tipo->esNotaDebito()) {
                $candidatas[] = $tipo;
            }
        }
        if ($candidatas === []) {
            return null;
        }

        $objetivoAfip = self::codigoAfipNotaDebito($venta);
        $stockNd = self::stockQueDeshace((string) ($venta->tipotransacciones->operacionstock ?? 'O'));
        $par = self::PAR_ABREVIATURA[strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? '')))] ?? '';

        if ($par !== '') {
            foreach ($candidatas as $tipo) {
                if (strtoupper(trim((string) $tipo->abreviatura)) !== $par) {
                    continue;
                }
                if ($objetivoAfip > 0 && ! self::tipoEmiteAfip($tipo, $venta, $objetivoAfip)) {
                    continue;
                }
                if (strtoupper((string) $tipo->operacionstock) === $stockNd) {
                    return $tipo;
                }
            }
        }

        foreach ($candidatas as $tipo) {
            if ($objetivoAfip > 0 && ! self::tipoEmiteAfip($tipo, $venta, $objetivoAfip)) {
                continue;
            }
            if (strtoupper((string) $tipo->operacionstock) === $stockNd) {
                return $tipo;
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, string> id de NC => código de la ND que ya la revirtió
     */
    public static function codigosNdPorNotaCredito(array $ventaIds): array
    {
        $ventaIds = self::idsPositivos($ventaIds);
        if ($ventaIds === []) {
            return [];
        }

        $hijas = Venta::query()
            ->with('tipotransacciones:id,abreviatura,codigo,operacion')
            ->whereIn('venta_origen_id', $ventaIds)
            ->get(['id', 'codigo', 'venta_origen_id', 'tipotransaccion_id']);

        $out = [];
        foreach ($hijas as $hija) {
            $origenId = (int) ($hija->venta_origen_id ?? 0);
            if ($origenId <= 0 || isset($out[$origenId])) {
                continue;
            }
            if ($hija->tipotransacciones && $hija->tipotransacciones->esNotaDebito()) {
                $out[$origenId] = (string) ($hija->codigo ?? '');
            }
        }

        return $out;
    }

    /**
     * Notas de crédito emitidas por POS gastronomía o estacionamiento.
     *
     * @param  list<int>  $ventaIds
     * @return array<int, true>
     */
    public static function idsEmisionPos(array $ventaIds): array
    {
        $ventaIds = self::idsPositivos($ventaIds);
        if ($ventaIds === []) {
            return [];
        }

        $ids = [];
        foreach (VentaGastronomiaEmision::query()->whereIn('venta_id', $ventaIds)->pluck('venta_id') as $id) {
            $ids[(int) $id] = true;
        }
        foreach (VentaEstacionamientoEmision::query()->whereIn('venta_id', $ventaIds)->pluck('venta_id') as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }

    /**
     * IIBB literal de la NC. Lista vacía = la NC no percibió: no calcular padrón.
     *
     * @param  array<string, mixed>  $dataCliente
     * @return list<array<string, mixed>>
     */
    public static function filasIibb(array $dataCliente): array
    {
        $filas = NotaCreditoPercepcionIibbSupport::extraerFilasIibb(self::impuestosDeNc($dataCliente));

        return self::filasAbsolutasConJurisdiccion($filas);
    }

    /**
     * Percepción IVA y no categorizado grabadas en la NC. Vacío si no las tuvo.
     *
     * @param  array<string, mixed>  $dataCliente
     * @return list<array<string, mixed>>
     */
    public static function filasPercepcionNacional(array $dataCliente): array
    {
        $out = [];
        foreach (self::impuestosDeNc($dataCliente) as $item) {
            $fila = self::filaComoArray($item);
            $concepto = trim((string) ($fila['concepto'] ?? ''));
            if ($concepto === '') {
                continue;
            }
            if (! RegimenPercepcionSupport::esConceptoPiva($concepto)
                && ! PercepcionNoCategorizadoSupport::esConcepto($concepto)) {
                continue;
            }
            $importe = round(abs((float) ($fila['importe'] ?? 0)), 2);
            if ($importe < 0.01) {
                continue;
            }
            $out[] = [
                'concepto' => $concepto,
                'tasa' => (float) ($fila['tasa'] ?? 0),
                'baseimponible' => abs((float) ($fila['baseimponible'] ?? 0)),
                'importe' => $importe,
                'impuesto_id' => (int) ($fila['impuesto_id'] ?? 0) ?: null,
            ];
        }

        return $out;
    }

    private static function errorNotaCreditoMostrador(?Venta $venta): ?string
    {
        if ($venta === null) {
            return 'La nota de crédito a revertir no existe.';
        }

        if (! self::esNotaCreditoReversible($venta)) {
            return 'Solo se puede revertir una nota de crédito.';
        }

        $venta->loadMissing(['gastronomiaEmision', 'estacionamientoEmision']);
        if ($venta->gastronomiaEmision !== null || $venta->estacionamientoEmision !== null) {
            return 'Esta nota de crédito es de facturación POS. La reversión con nota de débito es solo de mostrador.';
        }

        return null;
    }

    private static function errorSiYaRevertida(int $notaCreditoId, string $codigoNc): ?string
    {
        $codigoNd = self::codigosNdPorNotaCredito([$notaCreditoId])[$notaCreditoId] ?? '';
        if ($codigoNd === '') {
            return null;
        }

        $nc = trim($codigoNc) !== '' ? trim($codigoNc) : 'indicada';

        return 'La nota de crédito '.$nc.' ya fue revertida por la nota de débito '.$codigoNd.'.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function errorSiCantidadNoEsTotal(array $data, int $notaCreditoId): ?string
    {
        $cantidades = $data['cantidades'] ?? null;
        if (! is_array($cantidades) || $cantidades === []) {
            return null;
        }

        $sumaOrigen = 0.0;
        foreach (Venta_Emision::query()->where('venta_id', $notaCreditoId)->pluck('cantidad') as $cantidad) {
            $sumaOrigen += abs((float) $cantidad);
        }
        $sumaNd = 0.0;
        foreach ($cantidades as $cantidad) {
            $sumaNd += abs(self::aFloat($cantidad));
        }
        if (abs($sumaOrigen - $sumaNd) > 0.02) {
            return 'La nota de débito debe copiar la nota de crédito completa, con todas las cantidades.';
        }

        return null;
    }

    private static function codigoAfipNotaDebito(Venta $venta): int
    {
        $asoc = ArcaFceNcMostradorSupport::parsearCodigoComprobante(trim((string) ($venta->codigo ?? '')));
        $afipNc = (int) ($asoc['tipo'] ?? 0);
        if ($afipNc <= 0) {
            $letra = self::letraDesdeCodigo((string) ($venta->codigo ?? ''));
            $afipNc = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
                (string) ($venta->tipotransacciones->codigo ?? ''),
                $letra !== '' ? $letra : 'A'
            );
        }

        return self::ND_POR_NC_AFIP[$afipNc] ?? 0;
    }

    private static function tipoEmiteAfip(Tipotransaccion $tipo, Venta $venta, int $objetivoAfip): bool
    {
        $letra = self::letraDesdeCodigo((string) ($venta->codigo ?? ''));
        if ($letra === '') {
            $letra = 'A';
        }
        $emitido = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision((string) ($tipo->codigo ?? ''), $letra);
        if ($emitido === $objetivoAfip) {
            return true;
        }

        return ArcaFceNcMostradorSupport::codigoAfipDesdeTipo($tipo) === $objetivoAfip;
    }

    private static function letraDesdeCodigo(string $codigo): string
    {
        if (preg_match('/^[A-Z]{3}\s+([A-Z])-/i', trim($codigo), $m) === 1) {
            return strtoupper($m[1]);
        }

        return '';
    }

    private static function stockQueDeshace(string $stockNc): string
    {
        return match (strtoupper(trim($stockNc))) {
            'E' => 'S',
            'S' => 'E',
            'N' => 'N',
            default => 'O',
        };
    }

    /**
     * @param  array<string, mixed>  $dataCliente
     * @return list<Venta_Impuesto>
     */
    private static function impuestosDeNc(array $dataCliente): array
    {
        $notaCreditoId = (int) ($dataCliente[self::FLAG_NC_ID] ?? 0);
        if ($notaCreditoId <= 0) {
            return [];
        }
        if (! array_key_exists($notaCreditoId, self::$impuestosCache)) {
            self::$impuestosCache[$notaCreditoId] = Venta_Impuesto::query()
                ->where('venta_id', $notaCreditoId)
                ->get()
                ->all();
        }

        return self::$impuestosCache[$notaCreditoId] ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private static function filasAbsolutasConJurisdiccion(array $filas): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['provincia_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $juris = $ids === []
            ? collect()
            : Provincia::query()->whereIn('id', array_values($ids))->pluck('jurisdiccion', 'id');

        $out = [];
        foreach ($filas as $fila) {
            $importe = round(abs((float) ($fila['importe'] ?? 0)), 2);
            if ($importe < 0.01) {
                continue;
            }
            $provinciaId = (int) ($fila['provincia_id'] ?? 0);
            $out[] = [
                'concepto' => (string) ($fila['concepto'] ?? ''),
                'tasa' => (float) ($fila['tasa'] ?? 0),
                'baseimponible' => abs((float) ($fila['baseimponible'] ?? 0)),
                'jurisdiccion' => $fila['jurisdiccion'] ?? ($provinciaId > 0 ? $juris->get($provinciaId) : null),
                'provincia_id' => $provinciaId > 0 ? $provinciaId : null,
                'importe' => $importe,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaComoArray(mixed $fila): array
    {
        if (is_array($fila)) {
            return $fila;
        }
        if (is_object($fila) && method_exists($fila, 'toArray')) {
            return $fila->toArray();
        }

        return [];
    }

    private static function aFloat(mixed $valor): float
    {
        if (is_string($valor)) {
            $valor = str_replace([' ', ','], ['', '.'], $valor);
        }

        return (float) $valor;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function idsPositivos(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $ids),
            static fn (int $id) => $id > 0
        )));
    }
}
