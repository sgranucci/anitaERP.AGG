<?php

use App\Models\Admin\Rol;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Usuarios marketing AGG:
 * - Laura Suarez → solo enc-Marketing y CAC
 * - Paula Schell → solo Sup-Marketing
 * - Rodrigo D'Angiolo (rdangiolo) → alta Op-Marketing, password 12345
 *
 * CC Marketing y CAC (96); empresas BIYEMAS / KANDIKO / REBISCO.
 * Password solo al crear; existentes no se tocan.
 */
return new class extends Migration
{
    private const EMPRESAS = ['BIYEMAS S.A.', 'KANDIKO S.A.', 'REBISCO S.A.'];

    private const ROLES_MARKETING = [
        'enc-Marketing y CAC',
        'Sup-Marketing',
        'Op-Marketing',
        'Promo-Marketing',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $centrocostoId = (int) (Centrocosto::where('codigo', '96')->value('id') ?? 0);
        if ($centrocostoId === 0) {
            throw new \RuntimeException('No se encontró el centro de costo Marketing (código 96).');
        }

        $rolEncId = (int) (Rol::where('nombre', 'enc-Marketing y CAC')->value('id') ?? 0);
        $rolSupId = (int) (Rol::where('nombre', 'Sup-Marketing')->value('id') ?? 0);
        $rolOpId = (int) (Rol::where('nombre', 'Op-Marketing')->value('id') ?? 0);
        if ($rolEncId === 0 || $rolSupId === 0 || $rolOpId === 0) {
            throw new \RuntimeException('Faltan roles de marketing (enc / Sup / Op).');
        }

        $empresaIds = [];
        foreach (self::EMPRESAS as $nombreEmpresa) {
            $id = (int) (DB::table('empresa')->where('nombre', $nombreEmpresa)->value('id') ?? 0);
            if ($id === 0) {
                throw new \RuntimeException("No se encontró la empresa $nombreEmpresa.");
            }
            $empresaIds[] = $id;
        }

        $rolesMarketingIds = Rol::whereIn('nombre', self::ROLES_MARKETING)->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($centrocostoId, $rolEncId, $rolSupId, $rolOpId, $empresaIds, $rolesMarketingIds) {
            $laura = Usuario::where('usuario', 'lsuarez')->first()
                ?? Usuario::where('email', 'lsuarez@grupoagg.com')->first();
            if (! $laura) {
                throw new \RuntimeException('No se encontró el usuario Laura Suarez (lsuarez).');
            }
            $this->asegurarCentroYEmpresas($laura, $centrocostoId, $empresaIds);
            $this->reemplazarRolesMarketing($laura, $rolesMarketingIds, [$rolEncId]);

            $paula = Usuario::where('usuario', 'pschell')->first()
                ?? Usuario::where('email', 'pschell@grupoagg.com')->first();
            if (! $paula) {
                throw new \RuntimeException('No se encontró el usuario Paula Schell (pschell).');
            }
            $this->asegurarCentroYEmpresas($paula, $centrocostoId, $empresaIds);
            $this->reemplazarRolesMarketing($paula, $rolesMarketingIds, [$rolSupId]);

            $rodrigo = Usuario::where('usuario', 'rdangiolo')->first()
                ?? Usuario::where('email', 'rdangiolo@grupoagg.com')->first();
            if (! $rodrigo) {
                $rodrigo = Usuario::create([
                    'usuario' => 'rdangiolo',
                    'nombre' => "Rodrigo D'Angiolo",
                    'email' => 'rdangiolo@grupoagg.com',
                    'password' => '12345',
                    'centrocosto_id' => $centrocostoId,
                    'suspendido' => false,
                ]);
            } else {
                $this->asegurarCentroYEmpresas($rodrigo, $centrocostoId, $empresaIds);
            }
            $this->asegurarCentroYEmpresas($rodrigo, $centrocostoId, $empresaIds);
            $this->reemplazarRolesMarketing($rodrigo, $rolesMarketingIds, [$rolOpId]);
        });
    }

    public function down(): void
    {
        // Sin rollback: altas/ajustes operativos de usuarios.
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function asegurarCentroYEmpresas(Usuario $usuario, int $centrocostoId, array $empresaIds): void
    {
        if ((int) $usuario->centrocosto_id !== $centrocostoId) {
            $usuario->centrocosto_id = $centrocostoId;
            $usuario->save();
        }

        foreach ($empresaIds as $empresaId) {
            if (! $usuario->usuario_empresas()->where('empresa.id', $empresaId)->exists()) {
                $usuario->usuario_empresas()->attach($empresaId);
            }
        }
    }

    /**
     * @param  list<int>  $rolesMarketingIds
     * @param  list<int>  $rolesDeseados
     */
    private function reemplazarRolesMarketing(Usuario $usuario, array $rolesMarketingIds, array $rolesDeseados): void
    {
        $actuales = $usuario->roles()->whereIn('rol.id', $rolesMarketingIds)->pluck('rol.id')->map(fn ($id) => (int) $id)->all();
        $quitar = array_values(array_diff($actuales, $rolesDeseados));
        if ($quitar !== []) {
            $usuario->roles()->detach($quitar);
        }
        foreach ($rolesDeseados as $rolId) {
            if (! $usuario->roles()->where('rol.id', $rolId)->exists()) {
                $usuario->roles()->attach($rolId);
            }
        }
    }
};
