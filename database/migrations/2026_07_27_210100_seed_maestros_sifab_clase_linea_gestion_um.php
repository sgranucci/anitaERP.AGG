<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Carga inicial maestros SIFAB (clase/línea/gestión) + actualiza U.M. desde seed JSON.
 * Solo INTERFORMING.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'INTERFORMING') {
            return;
        }

        $path = database_path('data/sifab_maestros_interforming.json');
        if (! File::isFile($path)) {
            return;
        }

        $data = json_decode(File::get($path), true);
        if (! is_array($data)) {
            return;
        }

        $now = now();

        if (Schema::hasTable('clasematerial')) {
            foreach ($data['clasematerial'] ?? [] as $row) {
                if (! array_key_exists('codigoInternoItem', $row) || $row['codigoInternoItem'] === null || $row['codigoInternoItem'] === '') {
                    continue;
                }
                $codigoInterno = (int) $row['codigoInternoItem'];
                $payload = [
                    'codigo' => (string) ($row['codigoItem'] ?? ''),
                    'nombre' => mb_substr((string) ($row['descripcion'] ?? ''), 0, 150),
                    'habilitado' => (bool) ($row['habilitado'] ?? true),
                    'updated_at' => $now,
                ];
                $existe = DB::table('clasematerial')->where('codigo_interno_sifab', $codigoInterno)->exists();
                if ($existe) {
                    DB::table('clasematerial')->where('codigo_interno_sifab', $codigoInterno)->update($payload);
                } else {
                    DB::table('clasematerial')->insert(array_merge($payload, [
                        'codigo_interno_sifab' => $codigoInterno,
                        'created_at' => $now,
                    ]));
                }
            }
        }

        if (Schema::hasTable('lineamaterial')) {
            foreach ($data['lineamaterial'] ?? [] as $row) {
                $codigoInterno = (int) ($row['codigoInternoItem'] ?? 0);
                if ($codigoInterno === 0) {
                    continue;
                }
                $payload = [
                    'codigo' => (string) ($row['codigoItem'] ?? ''),
                    'nombre' => mb_substr((string) ($row['descripcion'] ?? ''), 0, 150),
                    'habilitado' => (bool) ($row['habilitado'] ?? true),
                    'updated_at' => $now,
                ];
                $existe = DB::table('lineamaterial')->where('codigo_interno_sifab', $codigoInterno)->exists();
                if ($existe) {
                    DB::table('lineamaterial')->where('codigo_interno_sifab', $codigoInterno)->update($payload);
                } else {
                    DB::table('lineamaterial')->insert(array_merge($payload, [
                        'codigo_interno_sifab' => $codigoInterno,
                        'created_at' => $now,
                    ]));
                }
            }
        }

        if (Schema::hasTable('gestioncompra')) {
            foreach ($data['gestioncompra'] ?? [] as $row) {
                $codigoInterno = (int) ($row['codigoItem'] ?? 0);
                if ($codigoInterno === 0) {
                    continue;
                }
                $payload = [
                    'codigo' => (string) ($row['descripcionItemCorta'] ?? $row['codigoItem'] ?? ''),
                    'nombre' => mb_substr((string) ($row['descripcionItem'] ?? ''), 0, 150),
                    'habilitado' => (bool) ($row['habilitado'] ?? true),
                    'updated_at' => $now,
                ];
                $existe = DB::table('gestioncompra')->where('codigo_interno_sifab', $codigoInterno)->exists();
                if ($existe) {
                    DB::table('gestioncompra')->where('codigo_interno_sifab', $codigoInterno)->update($payload);
                } else {
                    DB::table('gestioncompra')->insert(array_merge($payload, [
                        'codigo_interno_sifab' => $codigoInterno,
                        'created_at' => $now,
                    ]));
                }
            }
        }

        // Unidades de medida SIFAB (GenericaS codigoTabla=15). codigo = codigoItem SIFAB.
        if (! Schema::hasTable('unidadmedida')) {
            return;
        }

        $nextId = max(9001, ((int) DB::table('unidadmedida')->max('id')) + 1);
        foreach ($data['unidadmedida'] ?? [] as $row) {
            $codigo = (string) ($row['codigoItem'] ?? '');
            if ($codigo === '') {
                continue;
            }
            $nombre = mb_substr((string) ($row['descripcionItem'] ?? $codigo), 0, 50);
            $abrev = mb_substr((string) ($row['descripcionItemCorta'] ?? $nombre), 0, 10);
            $existeId = DB::table('unidadmedida')->where('codigo', $codigo)->value('id');
            if ($existeId) {
                DB::table('unidadmedida')->where('id', $existeId)->update([
                    'nombre' => $nombre,
                    'abreviatura' => $abrev,
                    'updated_at' => $now,
                ]);

                continue;
            }
            while (DB::table('unidadmedida')->where('id', $nextId)->exists()) {
                $nextId++;
            }
            DB::table('unidadmedida')->insert([
                'id' => $nextId,
                'nombre' => $nombre,
                'abreviatura' => $abrev,
                'codigo' => $codigo,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $nextId++;
        }
    }

    public function down(): void
    {
        // No borra datos operativos al revertir.
    }
};
