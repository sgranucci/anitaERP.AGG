<?php

namespace App\Support\Ventas;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Explica líneas del analítico gastronomía con precio unitario 0
 * (opcionales de fórmula/promo a $0 o invitación/cortesía).
 *
 * Misma convención que {@see \App\Support\Ventas\Gastronomia\GastronomiaFacturaItemsPayloadSupport}:
 * renglones $0 posteriores a un ítem con precio = componentes de esa promo.
 */
final class GastronomiaAnaliticoPrecioCeroSupport
{
    private const TOLERANCIA_PRECIO = 0.0001;

    /**
     * Completa `observacion_precio` (y metadatos de promo padre si aplica) en filas detalle.
     *
     * @param  Collection<int, object>  $filas
     */
    public static function enriquecer(Collection $filas): void
    {
        $detalle = $filas->filter(
            static fn ($f) => ($f->tipo_fila ?? 'detalle') === 'detalle'
        );

        if ($detalle->isEmpty()) {
            return;
        }

        $cerosSinInvitacion = [];
        foreach ($detalle as $fila) {
            $precio = abs((float) ($fila->precio_unitario ?? 0));
            if ($precio > self::TOLERANCIA_PRECIO) {
                $fila->observacion_precio = '';

                continue;
            }

            $tipoDesc = trim((string) ($fila->tipo_descuento ?? ''));
            if ((int) ($fila->descuento_gastronomia_id ?? 0) > 0 || $tipoDesc !== '') {
                $fila->observacion_precio = $tipoDesc !== ''
                    ? 'Invitación: '.$tipoDesc
                    : 'Invitación / cortesía';

                continue;
            }

            $fila->observacion_precio = '';
            $cerosSinInvitacion[] = $fila;
        }

        if ($cerosSinInvitacion === []) {
            return;
        }

        $ventaIds = collect($cerosSinInvitacion)
            ->map(static fn ($f) => (int) ($f->venta_id ?? 0))
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $padresPorEmisionId = self::resolverPadresPromoPorEmisionId($ventaIds);

        foreach ($cerosSinInvitacion as $fila) {
            $padre = $padresPorEmisionId[(int) ($fila->id ?? 0)] ?? null;
            if ($padre === null) {
                $fila->observacion_precio = 'Precio cero';

                continue;
            }

            $sku = trim((string) ($padre->sku ?? ''));
            $nombre = trim((string) ($padre->descripcion ?? ''));
            $etiqueta = trim($sku.($sku !== '' && $nombre !== '' ? ' — ' : '').$nombre);
            $fila->observacion_precio = $etiqueta !== ''
                ? 'Componente de promo: '.$etiqueta
                : 'Componente de promo';
            $fila->promo_padre_articulo_id = (int) ($padre->articulo_id ?? 0);
            $fila->promo_padre_sku = $sku;
            $fila->promo_padre_descripcion = $nombre;
        }
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, object{articulo_id:int, sku:string, descripcion:string}>
     */
    private static function resolverPadresPromoPorEmisionId(array $ventaIds): array
    {
        if ($ventaIds === []) {
            return [];
        }

        $lineas = DB::table('venta_emision as ve')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->whereIn('ve.venta_id', $ventaIds)
            ->orderBy('ve.venta_id')
            ->orderBy('ve.numeroitem')
            ->orderBy('ve.id')
            ->get([
                've.id',
                've.venta_id',
                've.precio',
                've.articulo_id',
                'a.sku',
                'a.descripcion',
            ]);

        $map = [];
        $ventaActual = null;
        $padreActual = null;

        foreach ($lineas as $linea) {
            $ventaId = (int) $linea->venta_id;
            if ($ventaActual !== $ventaId) {
                $ventaActual = $ventaId;
                $padreActual = null;
            }

            $precio = (float) ($linea->precio ?? 0);
            if ($precio > self::TOLERANCIA_PRECIO) {
                $padreActual = $linea;

                continue;
            }

            if ($padreActual === null) {
                continue;
            }

            $map[(int) $linea->id] = (object) [
                'articulo_id' => (int) ($padreActual->articulo_id ?? 0),
                'sku' => (string) ($padreActual->sku ?? ''),
                'descripcion' => (string) ($padreActual->descripcion ?? ''),
            ];
        }

        return $map;
    }
}
