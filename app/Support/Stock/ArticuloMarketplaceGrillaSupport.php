<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Ventas\ArticuloMarketplace;
use App\Models\Ventas\Canal;
use App\Models\Ventas\Marketplace;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Grilla de marketplaces del artículo (canal Local). No replica al bridge Anita.
 */
final class ArticuloMarketplaceGrillaSupport
{
    public static function uiActiva(): bool
    {
        return ArticuloEstadoCanalSupport::uiFerliActiva() && Schema::hasTable('articulo_marketplace');
    }

    /**
     * @return array{lineas:\Illuminate\Support\Collection,combinaciones:list<array{id:int,codigo:string,nombre:string}>}
     */
    public static function datosParaFormulario(?Articulo $producto): array
    {
        $vacio = [
            'lineas' => collect(),
            'combinaciones' => [],
        ];
        if (! self::uiActiva() || ! $producto || ! $producto->exists) {
            return $vacio;
        }

        $lineas = ArticuloMarketplace::query()
            ->with(['marketplace:id,codigo,nombre', 'combinacion:id,codigo,nombre'])
            ->where('articulo_id', $producto->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $combinaciones = Combinacion::query()
            ->where('articulo_id', $producto->id)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn (Combinacion $c) => [
                'id' => (int) $c->id,
                'codigo' => (string) ($c->codigo ?? ''),
                'nombre' => (string) ($c->nombre ?? ''),
            ])
            ->values()
            ->all();

        return [
            'lineas' => $lineas,
            'combinaciones' => $combinaciones,
        ];
    }

    public static function articuloTieneCanalLocal(?Articulo $producto): bool
    {
        if (! $producto || ! $producto->exists) {
            return false;
        }
        if ($producto->relationLoaded('canales')) {
            return $producto->canales->contains(
                fn ($canal) => strtoupper((string) $canal->codigo) === Canal::CODIGO_LOCAL
            );
        }

        return $producto->canales()->where('canal.codigo', Canal::CODIGO_LOCAL)->exists();
    }

    public static function sincronizarDesdeRequest(Request $request, int $articuloId): void
    {
        if (! self::uiActiva() || $articuloId <= 0 || ! $request->boolean('articulo_marketplace_sync')) {
            return;
        }

        $ids = (array) $request->input('am_id', []);
        $marketplaceIds = (array) $request->input('am_marketplace_id', []);
        $marketplaceCodigos = (array) $request->input('am_marketplace_codigo', []);
        $ordenes = (array) $request->input('am_orden', []);
        $combinacionIds = (array) $request->input('am_combinacion_id', []);
        $combinacionCodigos = (array) $request->input('am_combinacion_codigo', []);

        $cantidad = max(
            count($marketplaceIds),
            count($marketplaceCodigos),
            count($ordenes),
            count($combinacionIds),
            count($combinacionCodigos)
        );

        $conservar = [];
        $claves = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $marketplaceId = (int) ($marketplaceIds[$i] ?? 0);
            $codigoMarketplace = trim((string) ($marketplaceCodigos[$i] ?? ''));
            $ordenRaw = trim((string) ($ordenes[$i] ?? ''));
            $combinacionId = (int) ($combinacionIds[$i] ?? 0);
            $codigoCombinacion = mb_substr(trim((string) ($combinacionCodigos[$i] ?? '')), 0, 6);
            $lineaId = (int) ($ids[$i] ?? 0);

            if ($marketplaceId <= 0 && $codigoMarketplace === '' && $codigoCombinacion === '' && $ordenRaw === '') {
                continue;
            }

            $marketplace = self::resolverMarketplace($marketplaceId, $codigoMarketplace, $i + 1);
            $combinacion = self::resolverCombinacion($articuloId, $combinacionId, $codigoCombinacion, $i + 1);
            $combinacionId = $combinacion['id'];
            $codigoCombinacion = $combinacion['codigo'];
            $orden = $ordenRaw === '' ? 0 : (int) $ordenRaw;
            if ($orden < 0) {
                throw new RuntimeException('Fila '.($i + 1).': el orden no puede ser negativo.');
            }

            $clave = $marketplace->id.'|'.$codigoCombinacion.'|'.$orden;
            if (isset($claves[$clave])) {
                throw new RuntimeException('Fila '.($i + 1).': marketplace, combinación y orden repetidos.');
            }
            $claves[$clave] = true;

            $payload = [
                'articulo_id' => $articuloId,
                'marketplace_id' => (int) $marketplace->id,
                'combinacion_id' => $combinacionId,
                'codigo_combinacion' => $codigoCombinacion,
                'orden' => $orden,
            ];

            $linea = $lineaId > 0
                ? ArticuloMarketplace::query()->where('articulo_id', $articuloId)->whereKey($lineaId)->first()
                : null;
            if ($linea) {
                $linea->fill($payload);
                $linea->save();
            } else {
                $linea = ArticuloMarketplace::query()->create($payload);
            }
            $conservar[] = (int) $linea->id;
        }

        EloquentAuditDeleteSupport::exceptIds(
            ArticuloMarketplace::query()->where('articulo_id', $articuloId),
            $conservar
        );
    }

    private static function resolverMarketplace(int $id, string $codigo, int $fila): Marketplace
    {
        $marketplace = null;
        if ($id > 0) {
            $marketplace = Marketplace::query()->whereKey($id)->where('activo', true)->first();
        }
        if (! $marketplace && $codigo !== '') {
            $numero = (int) preg_replace('/\D+/', '', $codigo);
            if ($numero > 0) {
                $marketplace = Marketplace::query()->where('codigo', $numero)->where('activo', true)->first();
            }
        }
        if (! $marketplace) {
            throw new RuntimeException('Fila '.$fila.': marketplace inexistente o inactivo.');
        }

        return $marketplace;
    }

    /**
     * @return array{id:?int,codigo:string}
     */
    private static function resolverCombinacion(int $articuloId, int $id, string $codigo, int $fila): array
    {
        if ($id <= 0 && $codigo === '') {
            return ['id' => null, 'codigo' => ''];
        }

        $query = Combinacion::query()->where('articulo_id', $articuloId);
        if ($id > 0) {
            $comb = (clone $query)->whereKey($id)->first(['id', 'codigo']);
            if (! $comb) {
                throw new RuntimeException('Fila '.$fila.': la combinación no pertenece al artículo.');
            }
            if ($codigo !== '' && strcasecmp(trim((string) $comb->codigo), $codigo) !== 0
                && ltrim(strtoupper($codigo), '0') !== ltrim(strtoupper(trim((string) $comb->codigo)), '0')) {
                throw new RuntimeException('Fila '.$fila.': el código de combinación no coincide con la elegida.');
            }

            return [
                'id' => (int) $comb->id,
                'codigo' => mb_substr(trim((string) $comb->codigo), 0, 6),
            ];
        }

        $norm = strtoupper($codigo);
        $sinCeros = ltrim($norm, '0');
        $comb = $query->where(function ($q) use ($norm, $sinCeros) {
            $q->whereRaw('UPPER(TRIM(codigo)) = ?', [$norm]);
            if ($sinCeros !== '' && $sinCeros !== $norm) {
                $q->orWhereRaw('UPPER(TRIM(codigo)) = ?', [$sinCeros]);
            }
        })->orderBy('id')->first(['id', 'codigo']);
        if (! $comb) {
            return ['id' => null, 'codigo' => mb_substr($codigo, 0, 6)];
        }

        return [
            'id' => (int) $comb->id,
            'codigo' => mb_substr(trim((string) $comb->codigo), 0, 6),
        ];
    }
}
