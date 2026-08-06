<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use Illuminate\Support\Collection;

/**
 * Búsqueda de artículos con descripción similar (alta de artículo / alerta de duplicados).
 */
final class ArticuloSimilaresDescripcionSupport
{
    public const LIMITE = 25;

    /**
     * @return Collection<int, array{
     *     id: int,
     *     sku: string,
     *     descripcion: string,
     *     estado: string,
     *     url_consultar: string|null
     * }>
     */
    public static function buscar(string $descripcion, int $excluirArticuloId = 0): Collection
    {
        $descripcion = trim($descripcion);
        $minLen = CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO;

        if (mb_strlen($descripcion) < $minLen) {
            return collect();
        }

        $like = '%'.CoincidenciaFlexibleTexto::escapeLike($descripcion).'%';

        $query = Articulo::query()
            ->select(['id', 'sku', 'descripcion', 'estado'])
            ->where(function ($q) use ($descripcion, $like) {
                $q->where('descripcion', 'like', $like);
                CoincidenciaFlexibleTexto::aplicar(
                    $q,
                    'articulo.descripcion',
                    $descripcion,
                    true,
                    CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO,
                );
            })
            ->orderByRaw('CASE WHEN LOWER(descripcion) = ? THEN 0 WHEN LOWER(descripcion) LIKE ? THEN 1 ELSE 2 END', [
                mb_strtolower($descripcion),
                mb_strtolower($descripcion).'%',
            ])
            ->orderBy('descripcion')
            ->limit(self::LIMITE);

        if ($excluirArticuloId > 0) {
            $query->where('id', '!=', $excluirArticuloId);
        }

        $puedeConsultar = ArticuloConsultaDesdeModal::puedeConsultar();

        return $query->get()->map(static function (Articulo $articulo) use ($puedeConsultar) {
            $id = (int) $articulo->id;

            return [
                'id' => $id,
                'sku' => (string) ($articulo->sku ?? ''),
                'descripcion' => (string) ($articulo->descripcion ?? ''),
                'estado' => (string) ($articulo->estado ?? ''),
                'url_consultar' => $puedeConsultar && $id > 0
                    ? ArticuloConsultaDesdeModal::urlEditar($id)
                    : null,
            ];
        });
    }
}
