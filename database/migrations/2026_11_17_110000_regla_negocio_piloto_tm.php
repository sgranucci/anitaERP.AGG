<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2 — plantillas inactivas para stock.tm.requiere_aprobacion.
 * No cambian comportamiento hasta activar regla + motor.
 */
return new class extends Migration
{
    /** @var list<array<string, mixed>> */
    private const REGLAS = [
        [
            'codigo' => 'piloto_tm_ferli_tra_aprobacion',
            'nombre' => 'Piloto TM: Ferli TRA → requiere aprobación',
            'descripcion' => 'Plantilla F2. Si entorno Ferli y tipo TRA, setea requiere_aprobacion=true. Activar solo tras validar vs legacy (flags del tipo / aviso_opcional).',
            'dominio' => 'stock',
            'punto' => 'stock.tm.requiere_aprobacion',
            'prioridad' => 20,
            'stop_on_match' => true,
            'condiciones' => [
                'logic' => 'and',
                'rules' => [
                    ['campo' => 'es_ferli', 'op' => 'eq', 'valor' => true],
                    ['campo' => 'tipo', 'op' => 'eq', 'valor' => 'TRA'],
                ],
            ],
            'acciones' => [
                ['tipo' => 'require_approval', 'mensaje' => 'TM Ferli TRA pendiente de recepción/aprobación'],
            ],
        ],
        [
            'codigo' => 'piloto_tm_ferli_no_tra_inmediata',
            'nombre' => 'Piloto TM: Ferli no-TRA → inmediata',
            'descripcion' => 'Plantilla F2. En Ferli, tipos distintos de TRA no quedan pendientes (setea requiere_aprobacion=false). Prioridad más alta que la de TRA.',
            'dominio' => 'stock',
            'punto' => 'stock.tm.requiere_aprobacion',
            'prioridad' => 10,
            'stop_on_match' => true,
            'condiciones' => [
                'logic' => 'and',
                'rules' => [
                    ['campo' => 'es_ferli', 'op' => 'eq', 'valor' => true],
                    ['campo' => 'tipo', 'op' => 'neq', 'valor' => 'TRA'],
                ],
            ],
            'acciones' => [
                ['tipo' => 'set_dato', 'clave' => 'requiere_aprobacion', 'valor' => false],
            ],
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
                'dominio' => $regla['dominio'],
                'punto' => $regla['punto'],
                'activo' => false,
                'prioridad' => $regla['prioridad'],
                'stop_on_match' => $regla['stop_on_match'],
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
