<?php

namespace App\Support\Stock\Surmar;

use App\ApiAnita;
use App\Models\Stock\UnidadmedidaSurmar;
use App\Support\Stock\Surmar\RecepcionProveedorSurmarAnitaBridgeSupport;

/**
 * Unidades «en que separa» de Anita Surmar (stkumd).
 * El Bierzo usa otra tabla/ids (unidadmedida): no reutilizar.
 */
final class SurmarUnidadmedidaSeparaSupport
{
    /**
     * @return list<array{id:int, abreviatura:string, nombre:string}>
     */
    public static function listadoParaSelector(bool $sincronizarSiVacio = true): array
    {
        if ($sincronizarSiVacio && UnidadmedidaSurmar::query()->count() === 0) {
            self::sincronizarDesdeAnita();
        }

        return UnidadmedidaSurmar::query()
            ->orderBy('id')
            ->get(['id', 'abreviatura', 'nombre'])
            ->map(fn (UnidadmedidaSurmar $u) => [
                'id' => (int) $u->id,
                'abreviatura' => trim((string) $u->abreviatura),
                'nombre' => trim((string) $u->nombre),
            ])
            ->values()
            ->all();
    }

    public static function abreviatura(?int $id): string
    {
        $id = (int) ($id ?? 0);
        if ($id <= 0) {
            return 'UN';
        }
        $abr = UnidadmedidaSurmar::query()->whereKey($id)->value('abreviatura');

        return $abr !== null && trim((string) $abr) !== '' ? trim((string) $abr) : 'UN';
    }

    public static function existe(int $id): bool
    {
        return $id > 0 && UnidadmedidaSurmar::query()->whereKey($id)->exists();
    }

    /** Default Surmar: BIN (id 2) si existe; si no, primera. */
    public static function idDefault(): int
    {
        if (self::existe(2)) {
            return 2;
        }
        $first = (int) (UnidadmedidaSurmar::query()->orderBy('id')->value('id') ?? 0);

        return $first > 0 ? $first : 2;
    }

    /**
     * @return array{insertados:int, actualizados:int, total:int}
     */
    public static function sincronizarDesdeAnita(): array
    {
        $bridge = RecepcionProveedorSurmarAnitaBridgeSupport::parametrosBridge();
        $api = new ApiAnita();
        $raw = $api->apiCall(array_merge($bridge, [
            'acc' => 'list',
            'tabla' => 'stkumd',
            'campos' => 'stkum_umd,stkum_desc,stkum_abreviatura',
            'sistema' => 'ventas',
        ]));
        $rows = json_decode((string) $raw);
        if (! is_array($rows)) {
            return ['insertados' => 0, 'actualizados' => 0, 'total' => 0];
        }

        $ins = 0;
        $upd = 0;
        foreach ($rows as $r) {
            $id = (int) ($r->stkum_umd ?? 0);
            if ($id <= 0) {
                continue;
            }
            $payload = [
                'abreviatura' => mb_substr(trim((string) ($r->stkum_abreviatura ?? '')), 0, 10) ?: 'UM'.$id,
                'nombre' => mb_substr(trim((string) ($r->stkum_desc ?? '')), 0, 60) ?: ('UM '.$id),
            ];
            $existente = UnidadmedidaSurmar::query()->find($id);
            if ($existente) {
                $existente->update($payload);
                $upd++;
            } else {
                UnidadmedidaSurmar::query()->create(array_merge(['id' => $id], $payload));
                $ins++;
            }
        }

        return ['insertados' => $ins, 'actualizados' => $upd, 'total' => count($rows)];
    }
}
