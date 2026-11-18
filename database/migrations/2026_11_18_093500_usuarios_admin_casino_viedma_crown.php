<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: usuarios Viedma con rol admin-casino (mismo alcance que Catriel/LGR/GRO).
 * Sala VIE, empresa Crown, password 12345, mail @casinosdelrio.com.ar.
 * CC según planilla; si trae varios, se toma el primero.
 */
return new class extends Migration
{
    private const ROL = 'admin-casino';

    private const SALA_CODIGO = 'VIE';

    private const EMPRESA_ID = 1;

    private const PASSWORD = '12345';

    private const EMAIL_DOMINIO = '@casinosdelrio.com.ar';

    /** @var list<array{usuario: string, nombre: string, centrocosto_codigo: string}> */
    private const USUARIOS = [
        ['usuario' => 'chuinchul', 'nombre' => 'HUINCHUL CAROLINA YISEL', 'centrocosto_codigo' => '407'],
        ['usuario' => 'czapata', 'nombre' => 'ZAPATA CARLOS ALBERTO', 'centrocosto_codigo' => '1900'],
        ['usuario' => 'jjara', 'nombre' => 'JARA JULIETA MICHELLE', 'centrocosto_codigo' => '1800'],
        ['usuario' => 'moyon', 'nombre' => 'OYON TARRIO MICAELA BELEN', 'centrocosto_codigo' => '1800'],
        ['usuario' => 'gdorrego', 'nombre' => 'DORREGO GABRIELA SOLEDAD', 'centrocosto_codigo' => '1803'],
        ['usuario' => 'ameaurio', 'nombre' => 'MEAURIO ALDANA MICAELA', 'centrocosto_codigo' => '1800'],
        ['usuario' => 'jfernandez', 'nombre' => 'FERNANDEZ JOEL', 'centrocosto_codigo' => '413'],
        ['usuario' => 'griol', 'nombre' => 'RIOL GASTON', 'centrocosto_codigo' => '413'],
        ['usuario' => 'jriol', 'nombre' => 'RIOL JAVIER', 'centrocosto_codigo' => '413'],
        ['usuario' => 'jgonzalez', 'nombre' => 'GONZALEZ JOSE', 'centrocosto_codigo' => '403'],
        ['usuario' => 'ctromer', 'nombre' => 'TROMER CLAUDIO ANTONIO', 'centrocosto_codigo' => '410'],
        ['usuario' => 'pmoreno', 'nombre' => 'MORENO PABLO ALBERTO', 'centrocosto_codigo' => '410'],
        ['usuario' => 'jbarrera', 'nombre' => 'BARRERA JESUS ELIAS', 'centrocosto_codigo' => '410'],
        ['usuario' => 'pbordon', 'nombre' => 'BORDON PABLO ALBERTO', 'centrocosto_codigo' => '410'],
        ['usuario' => 'rguevara', 'nombre' => 'GUEVARA ROXANA MARIELA', 'centrocosto_codigo' => '410'],
        ['usuario' => 'pguenomil', 'nombre' => 'GUENOMIL PABLO DARIO', 'centrocosto_codigo' => '410'],
        ['usuario' => 'golivera', 'nombre' => 'OLIVERA GUSTAVO', 'centrocosto_codigo' => '410'],
        ['usuario' => 'msilva', 'nombre' => 'SILVA MARIELA DEL CARMEN', 'centrocosto_codigo' => '410'],
        ['usuario' => 'nsopranzetti', 'nombre' => 'SOPRANZETTI NATALIA', 'centrocosto_codigo' => '412'],
        ['usuario' => 'dejea', 'nombre' => 'EJEA DEMIAN', 'centrocosto_codigo' => '412'],
        ['usuario' => 'ccalfin', 'nombre' => 'CALFIN CRISTIAN', 'centrocosto_codigo' => '412'],
        ['usuario' => 'arossi', 'nombre' => 'ROSSI ANGEL', 'centrocosto_codigo' => '401'],
        ['usuario' => 'acamperi', 'nombre' => 'CAMPERI ATILIO', 'centrocosto_codigo' => '401'],
        ['usuario' => 'gharvey', 'nombre' => 'HARVEY GUSTAVO', 'centrocosto_codigo' => '401'],
        ['usuario' => 'efernandez', 'nombre' => 'FERNANDEZ DIAZ EVANGELINA', 'centrocosto_codigo' => '401'],
        ['usuario' => 'esantamarina', 'nombre' => 'SANTAMARINA EMANUEL', 'centrocosto_codigo' => '406'],
        ['usuario' => 'dstanoli', 'nombre' => 'STANOLI DANIEL', 'centrocosto_codigo' => '407'],
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
