<?php

use App\Support\Sueldos\SueldosAsientoSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puente corrida de sueldos ↔ solicitud de pago (fase 3).
 * Permiso para generar la SP desde la liquidación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudpago', function (Blueprint $table) {
            if (! Schema::hasColumn('solicitudpago', 'liquidacion_sueldos_id')) {
                $table->unsignedBigInteger('liquidacion_sueldos_id')->nullable()->after('solicitudpago_madre_id');
                $table->foreign('liquidacion_sueldos_id')
                    ->references('id')
                    ->on('liquidacion_sueldos')
                    ->nullOnDelete();
                $table->unique('liquidacion_sueldos_id', 'solicitudpago_liquidacion_sueldos_unique');
            }
        });

        Schema::table('liquidacion_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('liquidacion_sueldos', 'solicitudpago_id')) {
                $table->unsignedBigInteger('solicitudpago_id')->nullable()->after('asiento_id');
                $table->foreign('solicitudpago_id')
                    ->references('id')
                    ->on('solicitudpago')
                    ->nullOnDelete();
            }
        });

        $menuId = (int) (DB::table('menu')->where('url', 'sueldos/liquidacion')->value('id') ?? 0);
        $slug = SueldosAsientoSupport::PERMISO_GENERAR_SP;
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => 'Generar solicitud de pago de haberes',
            'slug' => $slug,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update($payload);
        } else {
            $payload['created_at'] = now();
            $permisoId = (int) DB::table('permiso')->insertGetId($payload);
        }

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        Schema::table('liquidacion_sueldos', function (Blueprint $table) {
            if (Schema::hasColumn('liquidacion_sueldos', 'solicitudpago_id')) {
                $table->dropForeign(['solicitudpago_id']);
                $table->dropColumn('solicitudpago_id');
            }
        });
        Schema::table('solicitudpago', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudpago', 'liquidacion_sueldos_id')) {
                $table->dropUnique('solicitudpago_liquidacion_sueldos_unique');
                $table->dropForeign(['liquidacion_sueldos_id']);
                $table->dropColumn('liquidacion_sueldos_id');
            }
        });

        $permisoId = (int) (DB::table('permiso')->where('slug', SueldosAsientoSupport::PERMISO_GENERAR_SP)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->orWhere('nombre', 'like', '%ontadur%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }
};
