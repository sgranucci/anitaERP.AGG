<?php

use App\Models\Admin\Rol;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cajeros tesorería (CC 98, rol Op-tesoreria) según planilla
 * "Cajeros Grupo Agg.xlsx" — columnas Apellido y nombre / Usuario Anita actual.
 *
 * Empresas: REBISCO S.A., KANDIKO S.A., BIYEMAS S.A.
 * password = 12345 (solo al crear)
 */
return new class extends Migration
{
    /** Login Excel => login histórico en Anita (misma persona). */
    private const LOGIN_LEGACY = [
        'asoria' => 'asoriarosa',
        'scortez' => 'dcortez',
        'renrique' => 'menriqueroque',
        'sgonzalez' => 'sgonzalezcarranza',
    ];

    /** @var list<array{0: string, 1: string, 2: string}> empresa, nombre (Apellido y nombre), login */
    private const PERSONAS = [
        ['REBISCO S.A.', 'Nizzo Gabriel', 'gnizzo'],
        ['KANDIKO S.A.', 'Cruz Cristian', 'ccruz'],
        ['BIYEMAS S.A.', 'Urzi Daniela', 'durzi'],
        ['REBISCO S.A.', 'Romano Rosa', 'rromano'],
        ['KANDIKO S.A.', 'Palavecino Romina', 'rpalavecino'],
        ['BIYEMAS S.A.', 'Bordon Veronica', 'vbordon'],
        ['REBISCO S.A.', 'Ragosa Ariel', 'aragosa'],
        ['KANDIKO S.A.', 'Garay Hernan', 'hgaray'],
        ['BIYEMAS S.A.', 'Golzalez Lourdes', 'lgonzalez'],
        ['REBISCO S.A.', 'Melgar Angel', 'amelgar'],
        ['KANDIKO S.A.', 'Biasotti Martin', 'mbiasotti'],
        ['BIYEMAS S.A.', 'Szmyr Cinthia', 'cszmyr'],
        ['REBISCO S.A.', 'Enrique Roque', 'renrique'],
        ['KANDIKO S.A.', 'Castillo Uriel', 'ucastillo'],
        ['BIYEMAS S.A.', 'Padron Estefania', 'epadron'],
        ['REBISCO S.A.', 'Carrizo Jonathan', 'jcarrizo'],
        ['KANDIKO S.A.', 'Diaz Jesica', 'jdiaz'],
        ['BIYEMAS S.A.', 'Soria Ayelen', 'asoria'],
        ['REBISCO S.A.', 'Espindola Camila', 'cespindola'],
        ['KANDIKO S.A.', 'Cortez Silvina', 'scortez'],
        ['BIYEMAS S.A.', 'Baustian Alan', 'abaustian'],
        ['REBISCO S.A.', 'Ortiz Lucas', 'lortiz'],
        ['KANDIKO S.A.', 'Fernandez Marcelo', 'mfernandez'],
        ['BIYEMAS S.A.', 'Vela Luisa', 'lvela'],
        ['REBISCO S.A.', 'Gonzalez Alejandro', 'agonzalez'],
        ['BIYEMAS S.A.', 'Carballo Damian', 'dcarballo'],
        ['REBISCO S.A.', 'Gomez Roxana', 'rgomez'],
        ['BIYEMAS S.A.', 'Serafini Maria Rosa', 'mserafini'],
        ['REBISCO S.A.', 'Llanos Matias', 'mllanos'],
        ['BIYEMAS S.A.', 'Jimenez Juliana', 'jjimenez'],
        ['REBISCO S.A.', 'Di Bartolo Martin', 'mdibartolo'],
        ['BIYEMAS S.A.', 'Baez Bianca', 'bbaez'],
        ['BIYEMAS S.A.', 'Arce Camila', 'carce'],
        ['BIYEMAS S.A.', 'Suchetti Romina', 'rsuchetti'],
        ['BIYEMAS S.A.', 'Gonzalez Sabrina', 'sgonzalez'],
        ['BIYEMAS S.A.', 'Rojas Claudio', 'crojas'],
    ];

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $centrocostoId = (int) (Centrocosto::where('codigo', 98)->value('id') ?? 0);
        if ($centrocostoId === 0) {
            throw new \RuntimeException('No se encontró el centro de costo con código 98.');
        }

        $rolOpId = (int) (Rol::where('nombre', 'Op-tesoreria')->value('id') ?? 0);
        if ($rolOpId === 0) {
            throw new \RuntimeException('No se encontró el rol Op-tesoreria.');
        }

        $empresaIds = [];
        foreach (['BIYEMAS S.A.', 'KANDIKO S.A.', 'REBISCO S.A.'] as $nombreEmpresa) {
            $id = (int) (DB::table('empresa')->where('nombre', $nombreEmpresa)->value('id') ?? 0);
            if ($id === 0) {
                throw new \RuntimeException("No se encontró la empresa $nombreEmpresa.");
            }
            $empresaIds[$nombreEmpresa] = $id;
        }

        DB::transaction(function () use ($centrocostoId, $rolOpId, $empresaIds) {
            foreach (self::PERSONAS as [$nombreEmpresa, $nombrePlanilla, $login]) {
                $login = strtolower(trim($login));
                $empresaId = $empresaIds[$nombreEmpresa];
                $email = $login.'@grupoagg.com';

                $usuario = Usuario::where('usuario', $login)->first();

                if (! $usuario && isset(self::LOGIN_LEGACY[$login])) {
                    $legacyLogin = self::LOGIN_LEGACY[$login];
                    $usuario = Usuario::where('usuario', $legacyLogin)->first();
                    if ($usuario && ! Usuario::where('usuario', $login)->exists()) {
                        $usuario->usuario = $login;
                        $usuario->email = $email;
                        $usuario->save();
                    }
                }

                if (! $usuario) {
                    $usuario = Usuario::create([
                        'usuario' => $login,
                        'nombre' => $nombrePlanilla,
                        'email' => $email,
                        'password' => '12345',
                        'centrocosto_id' => $centrocostoId,
                    ]);
                } else {
                    if ((int) $usuario->centrocosto_id !== $centrocostoId) {
                        $usuario->centrocosto_id = $centrocostoId;
                        $usuario->save();
                    }
                }

                if (! $usuario->roles()->where('rol.id', $rolOpId)->exists()) {
                    $usuario->roles()->attach($rolOpId);
                }

                if (! $usuario->usuario_empresas()->where('empresa.id', $empresaId)->exists()) {
                    $usuario->usuario_empresas()->attach($empresaId);
                }
            }
        });
    }

    public function down(): void
    {
        // Sin rollback: altas operativas de usuarios.
    }
};
