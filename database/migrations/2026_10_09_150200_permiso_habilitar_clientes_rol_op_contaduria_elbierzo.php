<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso habilitar-clientes en todas las empresas: lo reciben los roles que ya
 * pueden suspender clientes.
 * Solo El Bierzo: rol Op-contaduria (copia de Enc-contaduría, sin ese permiso)
 * y se lo asigna únicamente a Carolina Leone (carolinal), que sale de Enc-contaduría.
 */
return new class extends Migration
{
    private const PERMISO_NOMBRE = 'Habilitar clientes';

    private const PERMISO_SLUG = 'habilitar-clientes';

    private const PERMISO_SUSPENDER = 'suspender-clientes';

    private const MENU_CLIENTE_URL = 'ventas/cliente';

    private const ROL_ENC = 'Enc-contaduría';

    private const ROL_OP = 'Op-contaduria';

    private const USUARIO = 'carolinal';

    public function up(): void
    {
        $permisoId = $this->upsertPermisoHabilitar();
        if ($permisoId > 0) {
            $this->asignarARolesQueSuspenden($permisoId);
        }

        if (EntornoEmpresaSupport::esElBierzo()) {
            $this->asignarRolOpContaduriaElBierzo($permisoId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (EntornoEmpresaSupport::esElBierzo()) {
            $this->revertirRolOpContaduriaElBierzo();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermisoHabilitar(): int
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_URL)->value('id') ?? 0);
        $id = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($id === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO_NOMBRE,
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId > 0 ? $menuId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $id)->update([
            'nombre' => self::PERMISO_NOMBRE,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function asignarARolesQueSuspenden(int $permisoId): void
    {
        $suspenderId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SUSPENDER)->value('id') ?? 0);
        if ($suspenderId <= 0) {
            return;
        }

        $rolIds = DB::table('permiso_rol')
            ->where('permiso_id', $suspenderId)
            ->pluck('rol_id')
            ->unique();

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
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
    }

    private function asignarRolOpContaduriaElBierzo(int $permisoHabilitarId): void
    {
        $rolEnc = DB::table('rol')->where('nombre', self::ROL_ENC)->first();
        if ($rolEnc === null) {
            $rolEnc = DB::table('rol')->where('nombre', 'like', 'Enc-contadur%')->orderBy('id')->first();
        }
        if ($rolEnc === null) {
            return;
        }

        $rolEncId = (int) $rolEnc->id;
        $rolOpId = $this->resolverOCrearRolOp($rolEnc);
        if ($rolOpId <= 0) {
            return;
        }

        $this->copiarPermisosDesdeEnc($rolEncId, $rolOpId, $permisoHabilitarId);
        $this->copiarMenusDesdeEnc($rolEncId, $rolOpId);

        if ($permisoHabilitarId > 0) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolOpId)
                ->where('permiso_id', $permisoHabilitarId)
                ->delete();
        }

        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);
        if ($usuarioId <= 0 || ! Schema::hasTable('usuario_rol')) {
            return;
        }

        if (! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolOpId)->exists()) {
            DB::table('usuario_rol')->insert([
                'usuario_id' => $usuarioId,
                'rol_id' => $rolOpId,
            ]);
        }

        DB::table('usuario_rol')
            ->where('usuario_id', $usuarioId)
            ->where('rol_id', $rolEncId)
            ->delete();
    }

    private function resolverOCrearRolOp(object $rolEnc): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_OP)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $centrocostoId = (int) ($rolEnc->centrocosto_id ?? 0);

        return (int) DB::table('rol')->insertGetId([
            'nombre' => self::ROL_OP,
            'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function copiarPermisosDesdeEnc(int $rolEncId, int $rolOpId, int $permisoHabilitarId): void
    {
        $ya = array_flip(DB::table('permiso_rol')->where('rol_id', $rolOpId)->pluck('permiso_id')->map(fn ($id) => (int) $id)->all());
        $filas = [];
        foreach (DB::table('permiso_rol')->where('rol_id', $rolEncId)->pluck('permiso_id') as $permisoId) {
            $permisoId = (int) $permisoId;
            if ($permisoId <= 0 || $permisoId === $permisoHabilitarId || isset($ya[$permisoId])) {
                continue;
            }
            $filas[] = [
                'rol_id' => $rolOpId,
                'permiso_id' => $permisoId,
            ];
        }

        foreach (array_chunk($filas, 200) as $lote) {
            DB::table('permiso_rol')->insert($lote);
        }
    }

    private function copiarMenusDesdeEnc(int $rolEncId, int $rolOpId): void
    {
        $ya = array_flip(DB::table('menu_rol')->where('rol_id', $rolOpId)->pluck('menu_id')->map(fn ($id) => (int) $id)->all());
        $filas = [];
        foreach (DB::table('menu_rol')->where('rol_id', $rolEncId)->pluck('menu_id') as $menuId) {
            $menuId = (int) $menuId;
            if ($menuId <= 0 || isset($ya[$menuId])) {
                continue;
            }
            $filas[] = [
                'rol_id' => $rolOpId,
                'menu_id' => $menuId,
            ];
        }

        foreach (array_chunk($filas, 200) as $lote) {
            DB::table('menu_rol')->insert($lote);
        }
    }

    private function revertirRolOpContaduriaElBierzo(): void
    {
        $rolEncId = (int) (DB::table('rol')->where('nombre', self::ROL_ENC)->value('id') ?? 0);
        if ($rolEncId <= 0) {
            $rolEncId = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-contadur%')->orderBy('id')->value('id') ?? 0);
        }
        $rolOpId = (int) (DB::table('rol')->where('nombre', self::ROL_OP)->value('id') ?? 0);
        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);

        if ($usuarioId > 0 && $rolEncId > 0 && Schema::hasTable('usuario_rol')) {
            if (! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolEncId)->exists()) {
                DB::table('usuario_rol')->insert([
                    'usuario_id' => $usuarioId,
                    'rol_id' => $rolEncId,
                ]);
            }
        }

        if ($rolOpId <= 0) {
            return;
        }

        if ($usuarioId > 0 && Schema::hasTable('usuario_rol')) {
            DB::table('usuario_rol')
                ->where('usuario_id', $usuarioId)
                ->where('rol_id', $rolOpId)
                ->delete();
        }

        $otros = 0;
        if (Schema::hasTable('usuario_rol')) {
            $otros = (int) DB::table('usuario_rol')->where('rol_id', $rolOpId)->count();
        }
        if ($otros > 0) {
            return;
        }

        DB::table('permiso_rol')->where('rol_id', $rolOpId)->delete();
        DB::table('menu_rol')->where('rol_id', $rolOpId)->delete();
        DB::table('rol')->where('id', $rolOpId)->delete();
    }
};
