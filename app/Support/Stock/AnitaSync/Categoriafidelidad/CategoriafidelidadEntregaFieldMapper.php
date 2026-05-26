<?php

namespace App\Support\Stock\AnitaSync\Categoriafidelidad;

use App\Models\Ventas\CategoriafidelidadGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use Carbon\Carbon;

/**
 * Mapeo campo a campo: clicatent (Anita) → categoriafidelidad_entrega_gastronomia (ERP).
 */
final class CategoriafidelidadEntregaFieldMapper
{
    public static function mapDocumento(object $row): string
    {
        return trim((string) ($row->clcent_documento ?? ''));
    }

    public static function mapTarjeta(object $row): string
    {
        return trim((string) ($row->clcent_tarjeta ?? ''));
    }

    public static function mapCodigoCategoria(object $row): ?string
    {
        $codigo = (int) ($row->clcent_categoria ?? 0);
        if ($codigo <= 0) {
            $codigo = (int) config('categoriafidelidad_gastronomia_anita.entrega_categoria_codigo_default', 3);
        }

        return $codigo > 0 ? (string) $codigo : null;
    }

    public static function mapSkuAnita(object $row): string
    {
        return trim((string) ($row->clcent_articulo ?? ''));
    }

    public static function mapApellido(object $row): string
    {
        return trim((string) ($row->clcent_apellido ?? ''));
    }

    public static function mapNombre(object $row): string
    {
        return trim((string) ($row->clcent_nombre ?? ''));
    }

    public static function mapFechaAnita(object $row): ?int
    {
        $fecha = (int) ($row->clcent_fecha ?? 0);

        return $fecha > 0 ? $fecha : null;
    }

    public static function mapFechacanje(object $row): ?string
    {
        $fecha = self::mapFechaAnita($row);
        if ($fecha === null) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Ymd', (string) $fecha)->startOfDay()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function resolverCategoriaId(object $row): ?int
    {
        $codigo = self::mapCodigoCategoria($row);
        if ($codigo === null) {
            return null;
        }

        $categoria = CategoriafidelidadGastronomia::query()->where('codigo', $codigo)->first();
        if ($categoria) {
            return (int) $categoria->id;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            $categoria = CategoriafidelidadGastronomia::query()->where('codigo', $alt)->first();

            return $categoria ? (int) $categoria->id : null;
        }

        return null;
    }

    public static function resolverArticuloId(object $row): ?int
    {
        return CategoriafidelidadArticuloFieldMapper::resolverArticuloId(self::mapSkuAnita($row));
    }

    public static function resolverVentaId(object $row): ?int
    {
        $sucursal = (int) ($row->clcent_sucursal ?? 0);
        $nro = (int) ($row->clcent_nro ?? 0);
        if ($sucursal <= 0 || $nro <= 0) {
            return null;
        }

        $codigosPv = array_values(array_unique(array_filter([
            Puntoventa::normalizarCodigoArca((string) $sucursal),
            str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT),
            (string) $sucursal,
        ], static fn ($v) => $v !== null && $v !== '')));

        $puntoventaId = null;
        foreach ($codigosPv as $codigoPv) {
            $pv = Puntoventa::query()->where('codigo', $codigoPv)->first();
            if ($pv) {
                $puntoventaId = (int) $pv->id;
                break;
            }
        }

        if ($puntoventaId === null) {
            return null;
        }

        $venta = Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('numerocomprobante', $nro)
            ->orderByDesc('id')
            ->first();

        return $venta ? (int) $venta->id : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function mapAll(object $row): ?array
    {
        $documento = self::mapDocumento($row);
        $fechacanje = self::mapFechacanje($row);
        $categoriafidelidadId = self::resolverCategoriaId($row);
        $articuloId = self::resolverArticuloId($row);
        $ventaId = self::resolverVentaId($row);

        if ($documento === '' || $fechacanje === null || $categoriafidelidadId === null) {
            return null;
        }

        return [
            'categoriafidelidad_id' => $categoriafidelidadId,
            'documento' => substr($documento, 0, 20),
            'tarjeta' => substr(self::mapTarjeta($row), 0, 30),
            'trackdata' => null,
            'fechacanje' => $fechacanje,
            'articulo_id' => $articuloId,
            'venta_id' => $ventaId,
            'apellido' => substr(self::mapApellido($row), 0, 40),
            'nombre' => substr(self::mapNombre($row), 0, 40),
        ];
    }
}
