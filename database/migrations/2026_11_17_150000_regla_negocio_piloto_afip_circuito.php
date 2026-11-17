<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas inactivas AFIP circuito (fiscal: no activar sin validación).
 */
return new class extends Migration
{
    /** @var list<array<string, mixed>> */
    private const REGLAS = [
        [
            'codigo' => 'piloto_afip_letra_e_export',
            'nombre' => 'Piloto AFIP: letra E → exportación',
            'descripcion' => 'Mirrors legacy. Activar solo tras validar matriz fiscal por cliente.',
            'condiciones' => [
                'logic' => 'and',
                'rules' => [
                    ['campo' => 'letra_cliente', 'op' => 'eq', 'valor' => 'E'],
                ],
            ],
            'acciones' => [
                ['tipo' => 'set_dato', 'clave' => 'circuito', 'valor' => 'exportacion'],
            ],
            'prioridad' => 20,
        ],
        [
            'codigo' => 'piloto_afip_doc_pex_export',
            'nombre' => 'Piloto AFIP: doc PEX/FAE/FAF → exportación',
            'descripcion' => 'Aproxima documentoEsExportacion vía contains PEX|FAE|FAF. Ajustar antes de activar.',
            'condiciones' => [
                'logic' => 'or',
                'rules' => [
                    ['campo' => 'codigo_documento', 'op' => 'contains', 'valor' => 'PEX'],
                    ['campo' => 'codigo_documento', 'op' => 'contains', 'valor' => 'FAE'],
                    ['campo' => 'codigo_documento', 'op' => 'contains', 'valor' => 'FAF'],
                ],
            ],
            'acciones' => [
                ['tipo' => 'set_dato', 'clave' => 'circuito', 'valor' => 'exportacion'],
            ],
            'prioridad' => 15,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('regla_negocio')) {
            return;
        }
        $now = now();
        foreach (self::REGLAS as $regla) {
            if (DB::table('regla_negocio')->where('codigo', $regla['codigo'])->exists()) {
                continue;
            }
            DB::table('regla_negocio')->insert([
                'codigo' => $regla['codigo'],
                'nombre' => $regla['nombre'],
                'descripcion' => $regla['descripcion'],
                'dominio' => 'ventas',
                'punto' => 'ventas.afip.circuito',
                'activo' => false,
                'prioridad' => $regla['prioridad'],
                'stop_on_match' => true,
                'condiciones' => json_encode($regla['condiciones'], JSON_UNESCAPED_UNICODE),
                'acciones' => json_encode($regla['acciones'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('regla_negocio')) {
            return;
        }
        DB::table('regla_negocio')
            ->whereIn('codigo', array_column(self::REGLAS, 'codigo'))
            ->delete();
    }
};
