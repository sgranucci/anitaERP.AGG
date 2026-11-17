<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: usuarios Gral. Roca con rol admin-casino (mismo alcance que Catriel/Las Grutas).
 * Sala GRO, empresa Crown, password 12345, mail @casinosdelrio.com.ar.
 * CC según planilla. Pelletieri trae 507/502/503 → se asigna 507 (Administración).
 */
return new class extends Migration
{
    private const ROL = 'admin-casino';

    private const SALA_CODIGO = 'GRO';

    private const EMPRESA_ID = 1;

    private const PASSWORD = '12345';

    private const EMAIL_DOMINIO = '@casinosdelrio.com.ar';

    /** @var list<array{usuario: string, nombre: string, centrocosto_codigo: string}> */
    private const USUARIOS = [
        ['usuario' => 'hbetanzo', 'nombre' => 'BETANZO HUGO', 'centrocosto_codigo' => '502'],
        ['usuario' => 'mcandia', 'nombre' => 'CANDIA SOTO MARIANELA', 'centrocosto_codigo' => '502'],
        ['usuario' => 'dgalvan', 'nombre' => 'GALVAN DAIANA GABRIELA', 'centrocosto_codigo' => '502'],
        ['usuario' => 'ppesan', 'nombre' => 'PESAN PAMELA DAIANA', 'centrocosto_codigo' => '502'],
        ['usuario' => 'rgarcia', 'nombre' => 'GARCIA ROMINA MARISOL', 'centrocosto_codigo' => '502'],
        ['usuario' => 'vtrofa', 'nombre' => 'TROFA VICTORIA ALEJANDRA', 'centrocosto_codigo' => '507'],
        ['usuario' => 'lraco', 'nombre' => 'RACO LAURA ALICIA', 'centrocosto_codigo' => '507'],
        ['usuario' => 'ppelletieri', 'nombre' => 'PELLETIERI PABLO JAVIER', 'centrocosto_codigo' => '507'],
        ['usuario' => 'erivas', 'nombre' => 'RIVAS MORALES ENRIQUE NICOLAS', 'centrocosto_codigo' => '115'],
        ['usuario' => 'lappiolaza', 'nombre' => 'APPIOLAZA LEONARDO ALEJANDRO', 'centrocosto_codigo' => '115'],
        ['usuario' => 'mburgos', 'nombre' => 'BURGOS MARISA ELENA', 'centrocosto_codigo' => '504'],
        ['usuario' => 'raltieri', 'nombre' => 'ALTIERI RICARDO', 'centrocosto_codigo' => '501'],
        ['usuario' => 'ppainecura', 'nombre' => 'PAINECURA PABLO ALEJANDRO', 'centrocosto_codigo' => '501'],
        ['usuario' => 'mlaffue', 'nombre' => 'LAFFUE SUBIAS MAGALI', 'centrocosto_codigo' => '501'],
        ['usuario' => 'aostapczuk', 'nombre' => 'OSTAPCZUK ARIEL JUAN MARCOS', 'centrocosto_codigo' => '514'],
        ['usuario' => 'smoreno', 'nombre' => 'MORENO BETANCOURT SEBASTIAN', 'centrocosto_codigo' => '1500'],
        ['usuario' => 'valcon', 'nombre' => 'ALCON VALERIA', 'centrocosto_codigo' => '1500'],
        ['usuario' => 'lsabbadini', 'nombre' => 'SABBADINI LUIS EMILIO', 'centrocosto_codigo' => '513'],
        ['usuario' => 'cpereyra', 'nombre' => 'PEREYRA CRISTIAN', 'centrocosto_codigo' => '113'],
        ['usuario' => 'mvillar', 'nombre' => 'VILLAR NUÑEZ MARIA FLORENCIA', 'centrocosto_codigo' => '504'],
        ['usuario' => 'fbascur', 'nombre' => 'BASCUR FANNY', 'centrocosto_codigo' => '504'],
        ['usuario' => 'dullua', 'nombre' => 'ULLUA DAMIAN ANDRES', 'centrocosto_codigo' => '506'],
        ['usuario' => 'jmarchetto', 'nombre' => 'MARCHETTO JUAN MANUEL', 'centrocosto_codigo' => '506'],
        ['usuario' => 'rvalenzuela', 'nombre' => 'VALENZUELA RUBEN VICENTE', 'centrocosto_codigo' => '506'],
        ['usuario' => 'ptojo', 'nombre' => 'TOJO PABLO RAUL', 'centrocosto_codigo' => '512'],
        ['usuario' => 'ppamich', 'nombre' => 'PAMICH PATRICIA LAURA', 'centrocosto_codigo' => '512'],
        ['usuario' => 'ppalma', 'nombre' => 'PALMA PABLO ANDRES', 'centrocosto_codigo' => '512'],
        ['usuario' => 'msagal', 'nombre' => 'SAGAL MARIELA ELIZABETH', 'centrocosto_codigo' => '512'],
        ['usuario' => 'cdominguez', 'nombre' => 'DOMINGUEZ CARLOS ALBERTO', 'centrocosto_codigo' => '512'],
        ['usuario' => 'ggonzalez', 'nombre' => 'GONZALEZ FERREYRA GUILLERMO', 'centrocosto_codigo' => '510'],
        ['usuario' => 'oletelle', 'nombre' => 'LETELLE OSCAR EDUARDO', 'centrocosto_codigo' => '510'],
        ['usuario' => 'jriquelme', 'nombre' => 'RIQUELME JUAN LUIS', 'centrocosto_codigo' => '510'],
        ['usuario' => 'llescano', 'nombre' => 'LESCANO LUIS ALFREDO', 'centrocosto_codigo' => '510'],
        ['usuario' => 'mmartinez', 'nombre' => 'MARTINEZ CASTRO MATIAS', 'centrocosto_codigo' => '510'],
        ['usuario' => 'galmada', 'nombre' => 'ALMADA GABRIELA ELSA', 'centrocosto_codigo' => '514'],
        ['usuario' => 'gduscovich', 'nombre' => 'DUSCOVICH GUILLERMO', 'centrocosto_codigo' => '514'],
        ['usuario' => 'jschwarzenberg', 'nombre' => 'SCHWARZENBERG JUAN PABLO', 'centrocosto_codigo' => '514'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('rol') || ! Schema::hasTable('usuario')) {
            return;
        }

        $rolId = $this->rolId(self::ROL);
        if ($rolId <= 0) {
            return;
        }

        $this->crearUsuarios($rolId);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = $this->rolId(self::ROL);
        foreach (self::USUARIOS as $fila) {
            $usuarioId = $this->usuarioId($fila['usuario']);
            if ($usuarioId <= 0) {
                continue;
            }
            if (Schema::hasTable('usuario_rol')) {
                DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolId)->delete();
            }
            if (Schema::hasTable('usuario_sala')) {
                DB::table('usuario_sala')->where('usuario_id', $usuarioId)->delete();
            }
            if (Schema::hasTable('usuario_empresa')) {
                DB::table('usuario_empresa')->where('usuario_id', $usuarioId)->delete();
            }
            DB::table('usuario')->where('id', $usuarioId)->where('usuario', $fila['usuario'])->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function crearUsuarios(int $rolId): void
    {
        $salaId = (int) (DB::table('sala')->where('codigo', self::SALA_CODIGO)->value('id') ?? 0);
        $now = now();
        $passwordHash = Hash::make(self::PASSWORD);

        foreach (self::USUARIOS as $fila) {
            $centrocostoId = (int) (DB::table('centrocosto')
                ->where('codigo', $fila['centrocosto_codigo'])
                ->value('id') ?? 0);

            $usuarioId = $this->usuarioId($fila['usuario']);
            if ($usuarioId <= 0) {
                $usuarioId = (int) DB::table('usuario')->insertGetId([
                    'usuario' => $fila['usuario'],
                    'nombre' => $fila['nombre'],
                    'email' => $fila['usuario'].self::EMAIL_DOMINIO,
                    'password' => $passwordHash,
                    'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
                    'suspendido' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('usuario')->where('id', $usuarioId)->update([
                    'nombre' => $fila['nombre'],
                    'email' => $fila['usuario'].self::EMAIL_DOMINIO,
                    'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
                    'suspendido' => 0,
                    'updated_at' => $now,
                ]);
            }

            if ($usuarioId <= 0) {
                continue;
            }

            if (Schema::hasTable('usuario_rol')
                && ! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolId)->exists()
            ) {
                DB::table('usuario_rol')->insert([
                    'usuario_id' => $usuarioId,
                    'rol_id' => $rolId,
                ]);
            }

            if ($salaId > 0 && Schema::hasTable('usuario_sala')
                && ! DB::table('usuario_sala')->where('usuario_id', $usuarioId)->where('sala_id', $salaId)->exists()
            ) {
                DB::table('usuario_sala')->insert([
                    'usuario_id' => $usuarioId,
                    'sala_id' => $salaId,
                ]);
            }

            if (Schema::hasTable('usuario_empresa')
                && ! DB::table('usuario_empresa')->where('usuario_id', $usuarioId)->where('empresa_id', self::EMPRESA_ID)->exists()
            ) {
                DB::table('usuario_empresa')->insert([
                    'usuario_id' => $usuarioId,
                    'empresa_id' => self::EMPRESA_ID,
                ]);
            }
        }
    }

    private function rolId(string $nombre): int
    {
        return (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
    }

    private function usuarioId(string $usuario): int
    {
        return (int) (DB::table('usuario')->where('usuario', $usuario)->value('id') ?? 0);
    }
};
