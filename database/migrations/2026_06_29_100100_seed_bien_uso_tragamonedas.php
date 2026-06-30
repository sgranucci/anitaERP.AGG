<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        if (! Schema::hasColumn('bien_uso', 'uid')) {
            return;
        }

        $path = database_path('data/bien_uso_tragamonedas.json');
        if (! is_readable($path)) {
            return;
        }

        $filas = json_decode((string) file_get_contents($path), true);
        if (! is_array($filas)) {
            return;
        }

        $now = now();
        $ccMaquinas = (int) (DB::table('centrocosto')->where('codigo', '89')->value('id') ?? 0);
        if ($ccMaquinas <= 0) {
            $ccMaquinas = (int) (DB::table('centrocosto')->orderBy('id')->value('id') ?? 0);
        }

        foreach ($filas as $fila) {
            $uid = trim((string) ($fila['uid'] ?? ''));
            if ($uid === '') {
                continue;
            }

            $payload = [
                'codigo_inventario' => isset($fila['codigo_inventario']) ? (int) $fila['codigo_inventario'] : null,
                'uid' => $uid,
                'hostname' => null,
                'ip' => null,
                'modelo' => $fila['modelo'] ?? null,
                'vendor' => $fila['vendor'] ?? null,
                'tema' => $fila['tema'] ?? null,
                'numero_serie' => $fila['numero_serie'] ?? null,
                'estado' => (string) ($fila['estado'] ?? 'A'),
                'centrocosto_id' => $ccMaquinas,
                'tipo_bien' => (string) ($fila['tipo_bien'] ?? 'M'),
                'empresa_id' => isset($fila['empresa_id']) ? (int) $fila['empresa_id'] : null,
                'observaciones' => null,
                'updated_at' => $now,
            ];

            $existente = DB::table('bien_uso')->where('uid', $uid)->first();
            if ($existente) {
                DB::table('bien_uso')->where('id', $existente->id)->update($payload);

                continue;
            }

            DB::table('bien_uso')->insert(array_merge($payload, [
                'created_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        if (! Schema::hasColumn('bien_uso', 'uid')) {
            return;
        }

        $path = database_path('data/bien_uso_tragamonedas.json');
        if (! is_readable($path)) {
            return;
        }

        $filas = json_decode((string) file_get_contents($path), true);
        if (! is_array($filas)) {
            return;
        }

        $uids = array_values(array_filter(array_map(
            static fn (array $fila): string => trim((string) ($fila['uid'] ?? '')),
            $filas
        )));

        if ($uids !== []) {
            DB::table('bien_uso')->whereIn('uid', $uids)->delete();
        }
    }
};
