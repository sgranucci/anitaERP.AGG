<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: usuarios Las Grutas con rol admin-casino (mismo alcance que Catriel).
 * Sala LGR, empresa Crown, password inicial 12345, mail @casinosdelrio.com.ar.
 * Centros de costo según planilla de relevamiento.
 */
return new class extends Migration
{
    private const ROL = 'admin-casino';

    private const SALA_CODIGO = 'LGR';

    private const EMPRESA_ID = 1;

    private const PASSWORD = '12345';

    private const EMAIL_DOMINIO = '@casinosdelrio.com.ar';

    /** @var list<array{usuario: string, nombre: string, centrocosto_codigo: string}> */
    private const USUARIOS = [
        ['usuario' => 'jvega', 'nombre' => 'VEGA JUAN PABLO', 'centrocosto_codigo' => '302'],
        ['usuario' => 'rmoyano', 'nombre' => 'MOYANO RUBEN LUJAN', 'centrocosto_codigo' => '302'],
        ['usuario' => 'ccullumilla', 'nombre' => 'CULLUMILLA CECILIA', 'centrocosto_codigo' => '312'],
        ['usuario' => 'egarcia', 'nombre' => 'GARCIA EDUARDO', 'centrocosto_codigo' => '307'],
        ['usuario' => 'lsanocki', 'nombre' => 'SANOCKI LEANDRA', 'centrocosto_codigo' => '307'],
        ['usuario' => 'jcapdeville', 'nombre' => 'CAPDEVILLE JORGE', 'centrocosto_codigo' => '310'],
        ['usuario' => 'rgonzalez', 'nombre' => 'GONZALEZ RENZO', 'centrocosto_codigo' => '310'],
        ['usuario' => 'famarillo', 'nombre' => 'AMARILLO FLAVIO', 'centrocosto_codigo' => '1400'],
        ['usuario' => 'spalomo', 'nombre' => 'PALOMO SOLEDAD', 'centrocosto_codigo' => '1400'],
        ['usuario' => 'ccarignano', 'nombre' => 'CARIGNANO CARLOS', 'centrocosto_codigo' => '1400'],
        ['usuario' => 'jtorres', 'nombre' => 'TORRES VILLANUEVA JOSE', 'centrocosto_codigo' => '313'],
        ['usuario' => 'ccastellaro', 'nombre' => 'CASTELLARO CARLOS', 'centrocosto_codigo' => '307'],
        ['usuario' => 'lcruz', 'nombre' => 'CRUZ LUCIANO', 'centrocosto_codigo' => '310'],
        ['usuario' => 'cbollo', 'nombre' => 'BOLLO CRISTIAN', 'centrocosto_codigo' => '307'],
        ['usuario' => 'gsaenz', 'nombre' => 'SAENZ GERMAN', 'centrocosto_codigo' => '312'],
        ['usuario' => 'rgutierrez', 'nombre' => 'GUTIERREZ ROCIO', 'centrocosto_codigo' => '312'],
        ['usuario' => 'dlopez', 'nombre' => 'LOPEZ DANIELA', 'centrocosto_codigo' => '312'],
        ['usuario' => 'dbonardo', 'nombre' => 'BONARDO DAMIAN', 'centrocosto_codigo' => '310'],
        ['usuario' => 'gcontreras', 'nombre' => 'CONTRERAS GUILLERMO', 'centrocosto_codigo' => '310'],
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
