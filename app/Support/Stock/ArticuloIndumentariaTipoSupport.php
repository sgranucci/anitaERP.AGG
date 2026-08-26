<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Categoria;
use App\Models\Stock\Tipoarticulo;
use App\Services\Stock\ArticuloAnitaSyncService;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Tipo INDUMENTARIA (B en precarga, no bien de uso) para prendas.
 */
final class ArticuloIndumentariaTipoSupport
{
    public const NOMBRE_TIPO = 'INDUMENTARIA';

    public static function tipoId(): ?int
    {
        $id = Tipoarticulo::query()
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [self::NOMBRE_TIPO])
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function servicioId(): ?int
    {
        $id = Tipoarticulo::query()
            ->where('abreviatura', 'S')
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function categoriaEsIndumentaria(?object $categoria): bool
    {
        if (! $categoria) {
            return false;
        }

        $nombre = mb_strtoupper(trim((string) ($categoria->nombre ?? '')));

        return $nombre !== '' && str_contains($nombre, 'INDUMENTARIA');
    }

    public static function esServicio(?int $tipoarticuloId): bool
    {
        $servicioId = self::servicioId();

        return $servicioId !== null && $tipoarticuloId !== null && (int) $tipoarticuloId === $servicioId;
    }

    /**
     * Si la categoría es indumentaria y el tipo no es servicio, fuerza INDUMENTARIA.
     *
     * @return array{tipoarticulo_id?: int}
     */
    public static function mergeTipoForzado(mixed $categoriaId, mixed $tipoarticuloId): array
    {
        $catId = (int) $categoriaId;
        if ($catId <= 0) {
            return [];
        }

        $categoria = Categoria::query()->find($catId);
        if (! self::categoriaEsIndumentaria($categoria)) {
            return [];
        }

        $tipoActual = $tipoarticuloId === null || $tipoarticuloId === '' ? null : (int) $tipoarticuloId;
        if (self::esServicio($tipoActual)) {
            return [];
        }

        $tipoId = self::tipoId();
        if ($tipoId === null) {
            return [];
        }

        return ['tipoarticulo_id' => $tipoId];
    }

    /**
     * @return Builder<Articulo>
     */
    public static function queryArticulosACorregir(): Builder
    {
        $tipoId = self::tipoId();
        $servicioId = self::servicioId();

        return Articulo::query()
            ->whereHas('categorias', function ($q) {
                $q->whereRaw('UPPER(TRIM(nombre)) LIKE ?', ['%INDUMENTARIA%']);
            })
            ->when($tipoId !== null, fn (Builder $q) => $q->where('tipoarticulo_id', '!=', $tipoId))
            ->when($servicioId !== null, fn (Builder $q) => $q->where('tipoarticulo_id', '!=', $servicioId));
    }

    /**
     * @return array{categorias:int, articulos:int, anita_ok:int, anita_fail:int, anita_skip:int}
     */
    public static function corregir(bool $aplicar, bool $syncAnita = false): array
    {
        $tipoId = self::tipoId();
        if ($tipoId === null) {
            throw new \RuntimeException('No existe el tipo de artículo INDUMENTARIA.');
        }

        $categorias = 0;
        if ($aplicar) {
            $categorias = Categoria::query()
                ->whereRaw('UPPER(TRIM(nombre)) LIKE ?', ['%INDUMENTARIA%'])
                ->where(function ($q) use ($tipoId) {
                    $q->whereNull('tipoarticulo_id')->orWhere('tipoarticulo_id', '!=', $tipoId);
                })
                ->update(['tipoarticulo_id' => $tipoId]);
        } else {
            $categorias = Categoria::query()
                ->whereRaw('UPPER(TRIM(nombre)) LIKE ?', ['%INDUMENTARIA%'])
                ->where(function ($q) use ($tipoId) {
                    $q->whereNull('tipoarticulo_id')->orWhere('tipoarticulo_id', '!=', $tipoId);
                })
                ->count();
        }

        $articulosQuery = self::queryArticulosACorregir();
        $articulos = (int) $articulosQuery->count();
        $anitaOk = 0;
        $anitaFail = 0;
        $anitaSkip = 0;

        if ($aplicar && $articulos > 0) {
            $ids = $articulosQuery->pluck('id');
            Articulo::query()->whereIn('id', $ids)->update(['tipoarticulo_id' => $tipoId]);

            if ($syncAnita) {
                $abreviatura = (string) Tipoarticulo::query()->where('id', $tipoId)->value('abreviatura');
                $sync = app(ArticuloAnitaSyncService::class);
                Articulo::query()->whereIn('id', $ids)->orderBy('id')->each(function (Articulo $articulo) use (
                    $abreviatura,
                    $sync,
                    &$anitaOk,
                    &$anitaFail,
                    &$anitaSkip
                ) {
                    $resultado = self::actualizarTipoEnAnita($articulo->sku, $abreviatura, $sync);
                    if ($resultado === 'ok') {
                        $anitaOk++;
                    } elseif ($resultado === 'skip') {
                        $anitaSkip++;
                    } else {
                        $anitaFail++;
                    }
                });
            }
        }

        return [
            'categorias' => $categorias,
            'articulos' => $articulos,
            'anita_ok' => $anitaOk,
            'anita_fail' => $anitaFail,
            'anita_skip' => $anitaSkip,
        ];
    }

    public static function actualizarTipoEnAnita(string $sku, string $abreviatura, ?ArticuloAnitaSyncService $sync = null): string
    {
        $sku = trim($sku);
        $abreviatura = trim($abreviatura);
        if ($sku === '' || $abreviatura === '') {
            return 'skip';
        }

        $codigo = str_pad($sku, 13, '0', STR_PAD_LEFT);
        $abrSql = str_replace("'", "''", $abreviatura);
        $codigoSql = str_replace("'", "''", $codigo);
        $payload = [
            'acc' => 'update',
            'tabla' => 'stkmae',
            'sistema' => 'ventas',
            'valores' => " stkm_tipo_articulo = '{$abrSql}'",
            'whereArmado' => " WHERE stkm_articulo = '{$codigoSql}' ",
        ];

        try {
            (new ApiAnita)->apiCallEscritura($payload, 'stkmae tipo indumentaria');
        } catch (Throwable) {
            $sync ??= app(ArticuloAnitaSyncService::class);
            try {
                $resuelto = $sync->resolverCodigoAnitaPorSku($sku);
            } catch (Throwable) {
                return 'fail';
            }
            if ($resuelto === null || $resuelto === '' || $resuelto === $codigo) {
                return 'fail';
            }
            $payload['whereArmado'] = " WHERE stkm_articulo = '".str_replace("'", "''", $resuelto)."' ";
            try {
                (new ApiAnita)->apiCallEscritura($payload, 'stkmae tipo indumentaria');
            } catch (Throwable) {
                return 'fail';
            }
        }

        return 'ok';
    }
}
