<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: copia menu_rol de Enc-contaduría a Sup-contaduria
 * y asigna el rol Sup-contaduria a rfogliatti.
 */
return new class extends Migration
{
    private const ROL_ENC = 'Enc-contaduría';

    private const ROL_SUP = 'Sup-contaduria';

    private const USUARIO = 'rfogliatti';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolEncId = $this->resolverRolEncId();
        $rolSupId = $this->resolverOCrearRolSup();

        if ($rolEncId <= 0 || $rolSupId <= 0) {
            return;
        }

        $this->copiarMenuRolDesdeEnc($rolEncId, $rolSupId);
        $this->asignarUsuario($rolSupId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolEncId = $this->resolverRolEncId();
        $rolSupId = $this->resolverRolSupId();

        if ($rolEncId > 0 && $rolSupId > 0) {
            $menuIdsEnc = DB::table('menu_rol')->where('rol_id', $rolEncId)->pluck('menu_id')->all();
            if ($menuIdsEnc !== []) {
                DB::table('menu_rol')
                    ->where('rol_id', $rolSupId)
                    ->whereIn('menu_id', $menuIdsEnc)
                    ->delete();
            }
        }

        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);
        if ($usuarioId > 0 && $rolSupId > 0 && Schema::hasTable('usuario_rol')) {
            DB::table('usuario_rol')
                ->where('usuario_id', $usuarioId)
                ->where('rol_id', $rolSupId)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverRolEncId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_ENC)->value('id') ?? 0);

        return $id > 0
            ? $id
            : (int) (DB::table('rol')->where('nombre', 'like', 'Enc-contadur%')->orderBy('id')->value('id') ?? 0);
    }

    private function resolverRolSupId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_SUP)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('rol')->where('nombre', 'like', 'Sup-contadur%')->orderBy('id')->value('id') ?? 0);
    }

    private function resolverOCrearRolSup(): int
    {
        $id = $this->resolverRolSupId();
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('rol')->insertGetId([
            'nombre' => self::ROL_SUP,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function copiarMenuRolDesdeEnc(int $rolEncId, int $rolSupId): void
    {
        foreach (DB::table('menu_rol')->where('rol_id', $rolEncId)->pluck('menu_id') as $menuId) {
            $mid = (int) $menuId;
            if ($mid <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolSupId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $mid,
                    'rol_id' => $rolSupId,
                ]);
            }
        }
    }

    private function asignarUsuario(int $rolSupId): void
    {
        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);
        if ($usuarioId <= 0 || ! Schema::hasTable('usuario_rol')) {
            return;
        }

        if (! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolSupId)->exists()) {
            DB::table('usuario_rol')->insert([
                'usuario_id' => $usuarioId,
                'rol_id' => $rolSupId,
            ]);
        }
    }
};
