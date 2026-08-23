<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orden de medios en posición financiera (mismo número = mismo lugar en todas las empresas).
 */
return new class extends Migration
{
    private const PERMISO_SLUG = 'configurar-orden-posicion-financiera';

    private const PERMISO_NOMBRE = 'Configurar orden de conceptos de posición financiera';

    public function up(): void
    {
        if (Schema::hasTable('cuentacaja') && ! Schema::hasColumn('cuentacaja', 'orden')) {
            Schema::table('cuentacaja', function (Blueprint $table) {
                $table->unsignedInteger('orden')->default(0)->after('descripcion_operaciones');
            });
        }

        $this->sembrarOrdenBiyemas();
        $this->crearPermiso();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
            SuitecrmPermiso::flushCachePermisos();
        }

        if (Schema::hasTable('cuentacaja') && Schema::hasColumn('cuentacaja', 'orden')) {
            Schema::table('cuentacaja', function (Blueprint $table) {
                $table->dropColumn('orden');
            });
        }
    }

    private function sembrarOrdenBiyemas(): void
    {
        if (! Schema::hasTable('cuentacaja') || ! Schema::hasColumn('cuentacaja', 'orden')) {
            return;
        }

        $porCodigo = [
            '113010' => 10,
            '11301012' => 20,
            '100' => 30,
            '200' => 30,
            '300' => 30,
            'CTG' => 40,
            'GMEP' => 50,
            '25' => 90,
            'MMEP' => 50,
            'M0QR' => 60,
            '11301011' => 80,
            '11105033' => 40,
            '1112' => 100,
            '2112' => 100,
            '3112' => 100,
            '121' => 20,
            '221' => 20,
            '321' => 20,
            '122' => 30,
            '222' => 30,
            '322' => 30,
        ];

        foreach ($porCodigo as $codigo => $orden) {
            DB::table('cuentacaja')
                ->where('codigo', $codigo)
                ->where(function ($query) {
                    $query->whereNull('orden')->orWhere('orden', 0);
                })
                ->update(['orden' => $orden]);
        }
    }

    private function crearPermiso(): void
    {
        if (! Schema::hasTable('permiso')) {
            return;
        }

        $permisoListar = DB::table('permiso')->where('slug', 'listar-posicion-financiera')->first();
        if ($permisoListar === null) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO_NOMBRE,
                'slug' => self::PERMISO_SLUG,
                'menu_id' => (int) $permisoListar->menu_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('permiso_rol')
            ->where('permiso_id', (int) $permisoListar->id)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
