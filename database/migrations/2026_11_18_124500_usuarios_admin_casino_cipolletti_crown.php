<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: usuarios Cipolletti con rol admin-casino (mismo alcance que Catriel/LGR/GRO/VIE).
 * Sala CIP, empresa Crown, password 12345, mail @casinosdelrio.com.ar.
 * CC según planilla de relevamiento.
 *
 * Nota: bsepulveda ya existía (Tecnico de Tecnología, varias salas); se le asigna
 * admin-casino y se actualiza CC a 115. El down no lo borra si tiene otros roles.
 */
return new class extends Migration
{
    private const ROL = 'admin-casino';

    private const SALA_CODIGO = 'CIP';

    private const EMPRESA_ID = 1;

    private const PASSWORD = '12345';

    private const EMAIL_DOMINIO = '@casinosdelrio.com.ar';

    /** @var list<array{usuario: string, nombre: string, centrocosto_codigo: string}> */
    private const USUARIOS = [
        ['usuario' => 'aenggist', 'nombre' => 'ENGGIST ANIBAL', 'centrocosto_codigo' => '213'],
        ['usuario' => 'rfernandois', 'nombre' => 'FERNANDOIS ROBERTO PABLO', 'centrocosto_codigo' => '202'],
        ['usuario' => 'ccenteno', 'nombre' => 'CENTENO CARLOS NICOLAS', 'centrocosto_codigo' => '202'],
        ['usuario' => 'mayala', 'nombre' => 'AYALA MARILINA SELENE', 'centrocosto_codigo' => '1200'],
        ['usuario' => 'dnieto', 'nombre' => 'NIETO DIEGO GABRIEL', 'centrocosto_codigo' => '202'],
        ['usuario' => 'gmorales', 'nombre' => 'MORALES GUSTAVO ALEJANDRO', 'centrocosto_codigo' => '210'],
        ['usuario' => 'sdelrio', 'nombre' => 'DEL RIO FERNANDEZ SOL EYLEEN', 'centrocosto_codigo' => '204'],
        ['usuario' => 'gramos', 'nombre' => 'RAMOS GUSTAVO RODOLFO', 'centrocosto_codigo' => '201'],
        ['usuario' => 'cmardones', 'nombre' => 'MARDONES CESAR GUSTAVO', 'centrocosto_codigo' => '202'],
        ['usuario' => 'fgonzalez', 'nombre' => 'GONZALEZ FACUNDO', 'centrocosto_codigo' => '107'],
        ['usuario' => 'gmariano', 'nombre' => 'MARIANO GUSTAVO MANUEL', 'centrocosto_codigo' => '202'],
        ['usuario' => 'fgutierrez', 'nombre' => 'GUTIERREZ FABIAN GUILLERMO', 'centrocosto_codigo' => '212'],
        ['usuario' => 'wfuentealba', 'nombre' => 'FUENTEALBA WALTER MARTIN', 'centrocosto_codigo' => '107'],
        ['usuario' => 'mgutierrez', 'nombre' => 'GUTIERREZ LAFQUEN MARLEN', 'centrocosto_codigo' => '207'],
        ['usuario' => 'jortiz', 'nombre' => 'ORTIZ JAVIER', 'centrocosto_codigo' => '201'],
        ['usuario' => 'fduran', 'nombre' => 'DURAN FERNANDO ARIEL', 'centrocosto_codigo' => '210'],
        ['usuario' => 'dnicocia', 'nombre' => 'NICOCIA DANIELA FERNANDA', 'centrocosto_codigo' => '212'],
        ['usuario' => 'bsepulveda', 'nombre' => 'SEPULVEDA BRUNO ALEXIS', 'centrocosto_codigo' => '115'],
        ['usuario' => 'mcarrera', 'nombre' => 'CARRERA MARIA JULIA', 'centrocosto_codigo' => '1200'],
        ['usuario' => 'vbascal', 'nombre' => 'BASCAL VANESSA PAOLA', 'centrocosto_codigo' => '202'],
        ['usuario' => 'fsantos', 'nombre' => 'SANTOS FERNANDA GISELLE', 'centrocosto_codigo' => '202'],
        ['usuario' => 'gmerussi', 'nombre' => 'MERUSSI GERARDO HECTOR', 'centrocosto_codigo' => '202'],
        ['usuario' => 'caviles', 'nombre' => 'AVILES CARLOS ANIBAL', 'centrocosto_codigo' => '203'],
        ['usuario' => 'lbenavides', 'nombre' => 'BENAVIDES LUIS ALBERTO', 'centrocosto_codigo' => '202'],
        ['usuario' => 'mabella', 'nombre' => 'ABELLA MATIAS OSCAR', 'centrocosto_codigo' => '202'],
        ['usuario' => 'vfunes', 'nombre' => 'FUNES BALMACEDA VALERIA', 'centrocosto_codigo' => '202'],
        ['usuario' => 'nzabala', 'nombre' => 'NOELIA PATRICIA ZABALA', 'centrocosto_codigo' => '207'],
        ['usuario' => 'jaguilar', 'nombre' => 'AGUILAR BASCOPE JHULY', 'centrocosto_codigo' => '207'],
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

            if (Schema::hasTable('usuario_rol') && $rolId > 0) {
                DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolId)->delete();
            }

            $otrosRoles = Schema::hasTable('usuario_rol')
                ? (int) DB::table('usuario_rol')->where('usuario_id', $usuarioId)->count()
                : 0;

            // No borrar usuarios preexistentes con otros roles (ej. bsepulveda).
            if ($otrosRoles > 0) {
                continue;
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
