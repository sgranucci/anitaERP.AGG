<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Permiso revertir-remesa (tesorería) y anular-remesa solo administrador.
 * - FKs asiento: jornada_gastronomia_id, rendicion_estacionamiento_caja_id, transferencia_mercaderia_id.
 */
return new class extends Migration
{
    private const ROLES_TESORERIA = [
        'administrador',
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
    ];

    public function up(): void
    {
        $this->seedPermisoRevertirYRecortarAnular();
        $this->agregarFksAsiento();
        $this->rellenarFksHistoricas();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (Schema::hasTable('asiento')) {
            Schema::table('asiento', function (Blueprint $table) {
                foreach ([
                    'jornada_gastronomia_id',
                    'rendicion_estacionamiento_caja_id',
                    'transferencia_mercaderia_id',
                ] as $col) {
                    if (Schema::hasColumn('asiento', $col)) {
                        $table->dropIndex('idx_asiento_'.$col);
                        $table->dropColumn($col);
                    }
                }
            });
        }

        $pid = (int) (DB::table('permiso')->where('slug', 'revertir-remesa')->value('id') ?? 0);
        if ($pid > 0) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }
    }

    private function seedPermisoRevertirYRecortarAnular(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', 'caja/remesa')->value('id') ?? 0);

        $revertirId = (int) (DB::table('permiso')->where('slug', 'revertir-remesa')->value('id') ?? 0);
        if ($revertirId === 0) {
            $revertirId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Revertir remesas',
                'slug' => 'revertir-remesa',
                'menu_id' => $menuId > 0 ? $menuId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $revertirId)->update([
                'nombre' => 'Revertir remesas',
                'menu_id' => $menuId > 0 ? $menuId : null,
                'updated_at' => now(),
            ]);
        }

        $rolIdsTesoreria = DB::table('rol')
            ->whereIn('nombre', self::ROLES_TESORERIA)
            ->pluck('id')
            ->all();

        foreach ($rolIdsTesoreria as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $revertirId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $revertirId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        // anular-remesa: solo administrador
        $anularId = (int) (DB::table('permiso')->where('slug', 'anular-remesa')->value('id') ?? 0);
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($anularId > 0 && $adminId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $anularId)->delete();
            DB::table('permiso_rol')->insert([
                'permiso_id' => $anularId,
                'rol_id' => $adminId,
            ]);
        }
    }

    private function agregarFksAsiento(): void
    {
        if (! Schema::hasTable('asiento')) {
            return;
        }

        if (! Schema::hasColumn('asiento', 'jornada_gastronomia_id')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->unsignedBigInteger('jornada_gastronomia_id')->nullable()->after('remesa_id');
                $table->index('jornada_gastronomia_id', 'idx_asiento_jornada_gastronomia_id');
            });
        }
        if (! Schema::hasColumn('asiento', 'rendicion_estacionamiento_caja_id')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->unsignedBigInteger('rendicion_estacionamiento_caja_id')->nullable()->after('jornada_gastronomia_id');
                $table->index('rendicion_estacionamiento_caja_id', 'idx_asiento_rendicion_estacionamiento_caja_id');
            });
        }
        if (! Schema::hasColumn('asiento', 'transferencia_mercaderia_id')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->unsignedBigInteger('transferencia_mercaderia_id')->nullable()->after('rendicion_estacionamiento_caja_id');
                $table->index('transferencia_mercaderia_id', 'idx_asiento_transferencia_mercaderia_id');
            });
        }
    }

    private function rellenarFksHistoricas(): void
    {
        if (! Schema::hasTable('asiento')) {
            return;
        }

        if (Schema::hasTable('transferencia_mercaderia') && Schema::hasColumn('transferencia_mercaderia', 'asiento_id')) {
            DB::statement('
                UPDATE asiento a
                INNER JOIN transferencia_mercaderia tm ON tm.asiento_id = a.id
                SET a.transferencia_mercaderia_id = tm.id
                WHERE a.transferencia_mercaderia_id IS NULL
                  AND tm.asiento_id IS NOT NULL
            ');
        }

        if (Schema::hasTable('rendicion_estacionamiento_caja') && Schema::hasColumn('rendicion_estacionamiento_caja', 'asiento_id')) {
            // Grupo: varias rendiciones → un asiento; tomar MIN(id) como representativa.
            DB::statement('
                UPDATE asiento a
                INNER JOIN (
                    SELECT asiento_id, MIN(id) AS rendicion_id
                    FROM rendicion_estacionamiento_caja
                    WHERE asiento_id IS NOT NULL
                    GROUP BY asiento_id
                ) r ON r.asiento_id = a.id
                SET a.rendicion_estacionamiento_caja_id = r.rendicion_id
                WHERE a.rendicion_estacionamiento_caja_id IS NULL
            ');
        }

        if (! Schema::hasTable('gastronomia_cierre_jornada_proceso_snapshot')) {
            return;
        }

        DB::table('gastronomia_cierre_jornada_proceso_snapshot')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $payload = json_decode((string) ($row->payload ?? ''), true);
                    if (! is_array($payload)) {
                        continue;
                    }
                    $asientos = $payload['asientos_proceso_grabacion']['asientos'] ?? null;
                    if (! is_array($asientos)) {
                        continue;
                    }
                    $jornadaId = (int) ($row->jornada_gastronomia_id ?? 0);
                    if ($jornadaId <= 0) {
                        continue;
                    }
                    foreach ($asientos as $asi) {
                        $asientoId = (int) ($asi['asiento_id'] ?? 0);
                        if ($asientoId <= 0) {
                            continue;
                        }
                        DB::table('asiento')
                            ->where('id', $asientoId)
                            ->whereNull('jornada_gastronomia_id')
                            ->update(['jornada_gastronomia_id' => $jornadaId]);
                    }
                }
            });
    }
};
