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

        $path = database_path('data/bien_uso_paginas_1_7.json');
        if (! is_readable($path)) {
            return;
        }

        $filas = json_decode((string) file_get_contents($path), true);
        if (! is_array($filas)) {
            return;
        }

        $now = now();
        $ccPorCodigo = DB::table('centrocosto')->pluck('id', 'codigo');
        $usaCentrocostoId = Schema::hasColumn('bien_uso', 'centrocosto_id');

        foreach ($filas as $fila) {
            $codigo = (int) ($fila['codigo_inventario'] ?? 0);
            if ($codigo <= 0) {
                continue;
            }

            $ccCodigo = (string) ($fila['centrocosto_codigo'] ?? '92');
            $centroCostoChar = 'S';
            if ($ccCodigo === 'S') {
                $ccCodigo = '92';
            } elseif ($ccCodigo === 'M') {
                $ccCodigo = '89';
                $centroCostoChar = 'M';
            }
            $centrocostoId = (int) ($ccPorCodigo[$ccCodigo] ?? $ccPorCodigo['92'] ?? 0);

            $payload = [
                'hostname' => (string) ($fila['hostname'] ?? ''),
                'ip' => $fila['ip'] ?? null,
                'modelo' => $fila['modelo'] ?? null,
                'numero_serie' => $fila['numero_serie'] ?? null,
                'estado' => (string) ($fila['estado'] ?? 'A'),
                'tipo_bien' => (string) ($fila['tipo_bien'] ?? 'P'),
                'updated_at' => $now,
            ];

            if ($usaCentrocostoId) {
                $payload['centrocosto_id'] = $centrocostoId > 0 ? $centrocostoId : null;
            } elseif (Schema::hasColumn('bien_uso', 'centro_costo')) {
                $payload['centro_costo'] = $centroCostoChar;
            }

            $existente = DB::table('bien_uso')->where('codigo_inventario', $codigo)->first();
            if ($existente) {
                DB::table('bien_uso')->where('id', $existente->id)->update($payload);

                continue;
            }

            DB::table('bien_uso')->insert(array_merge($payload, [
                'codigo_inventario' => $codigo,
                'created_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        // No revertir carga masiva sobre datos operativos.
    }
};
