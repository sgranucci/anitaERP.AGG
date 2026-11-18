<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: firmantes de requisiciones por sala/CC (jefes y gerentes).
 *
 * Crea los 5 usuarios faltantes y asegura empresa, sala, CC y rol admin-casino
 * en los 17 firmantes del relevamiento. Password inicial 12345 solo en altas.
 */
return new class extends Migration
{
    private const ROL = 'admin-casino';

    private const EMPRESA_ID = 1;

    private const PASSWORD = '12345';

    private const EMAIL_DOMINIO = '@casinosdelrio.com.ar';

    /**
     * @var list<array{
     *   usuario: string,
     *   nombre: string,
     *   centrocosto_codigo: string,
     *   sala_codigo: string,
     *   crear_si_falta: bool
     * }>
     */
    private const FIRMANTES = [
        ['usuario' => 'priccobon', 'nombre' => 'RICCOBON PABLO', 'centrocosto_codigo' => '110', 'sala_codigo' => 'AC', 'crear_si_falta' => true],
        ['usuario' => 'gramos', 'nombre' => 'RAMOS GUSTAVO RODOLFO', 'centrocosto_codigo' => '201', 'sala_codigo' => 'CIP', 'crear_si_falta' => false],
        ['usuario' => 'jortiz', 'nombre' => 'ORTIZ JAVIER', 'centrocosto_codigo' => '207', 'sala_codigo' => 'CIP', 'crear_si_falta' => false],
        ['usuario' => 'jsandoval', 'nombre' => 'SANDOVAL JAVIER ENRIQUE', 'centrocosto_codigo' => '207', 'sala_codigo' => 'CIP', 'crear_si_falta' => true],
        ['usuario' => 'gmorales', 'nombre' => 'MORALES GUSTAVO ALEJANDRO', 'centrocosto_codigo' => '207', 'sala_codigo' => 'CIP', 'crear_si_falta' => false],
        ['usuario' => 'jlrea', 'nombre' => 'REA JOSE LUIS', 'centrocosto_codigo' => '207', 'sala_codigo' => 'CIP', 'crear_si_falta' => true],
        ['usuario' => 'cbollo', 'nombre' => 'BOLLO CRISTIAN ADRIAN', 'centrocosto_codigo' => '307', 'sala_codigo' => 'LGR', 'crear_si_falta' => false],
        ['usuario' => 'jcapdeville', 'nombre' => 'CAPDEVILLE JORGE GABRIEL', 'centrocosto_codigo' => '310', 'sala_codigo' => 'LGR', 'crear_si_falta' => false],
        ['usuario' => 'efernandez', 'nombre' => 'FERNANDEZ DIAZ EVANGELINA', 'centrocosto_codigo' => '401', 'sala_codigo' => 'VIE', 'crear_si_falta' => false],
        ['usuario' => 'acamperi', 'nombre' => 'CAMPERI ATILIO ANDRES', 'centrocosto_codigo' => '407', 'sala_codigo' => 'VIE', 'crear_si_falta' => false],
        ['usuario' => 'arossi', 'nombre' => 'ROSSI ANGEL PEDRO', 'centrocosto_codigo' => '407', 'sala_codigo' => 'VIE', 'crear_si_falta' => false],
        ['usuario' => 'gharvey', 'nombre' => 'HARVEY GUSTAVO GUILLERMO', 'centrocosto_codigo' => '407', 'sala_codigo' => 'VIE', 'crear_si_falta' => false],
        ['usuario' => 'raltieri', 'nombre' => 'ALTIERI RICARDO', 'centrocosto_codigo' => '507', 'sala_codigo' => 'GRO', 'crear_si_falta' => false],
        ['usuario' => 'ppainecura', 'nombre' => 'PAINECURA PABLO ALEJANDRO', 'centrocosto_codigo' => '507', 'sala_codigo' => 'GRO', 'crear_si_falta' => false],
        ['usuario' => 'pescobar', 'nombre' => 'ESCOBAR PATRICIO EDUARDO', 'centrocosto_codigo' => '507', 'sala_codigo' => 'VRE', 'crear_si_falta' => true],
        ['usuario' => 'galbano', 'nombre' => 'ALBANO GIOBANI', 'centrocosto_codigo' => '507', 'sala_codigo' => 'GRO', 'crear_si_falta' => true],
        ['usuario' => 'mlaffue', 'nombre' => 'LAFFUE SUBIAS MAGALI', 'centrocosto_codigo' => '510', 'sala_codigo' => 'GRO', 'crear_si_falta' => false],
    ];

    /** Logins que esta migración puede borrar en down (solo altas nuevas). */
    private const USUARIOS_ALTA = [
        'priccobon',
        'jsandoval',
        'jlrea',
        'pescobar',
        'galbano',
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
            throw new \RuntimeException('No existe el rol admin-casino. Correr antes la migración Catriel Crown.');
        }

        $this->asegurarFirmantes($rolId);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = $this->rolId(self::ROL);
        foreach (self::USUARIOS_ALTA as $login) {
            $usuarioId = $this->usuarioId($login);
            if ($usuarioId <= 0) {
                continue;
            }
            if (Schema::hasTable('usuario_rol') && $rolId > 0) {
                DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolId)->delete();
            }
            if (Schema::hasTable('usuario_sala')) {
                DB::table('usuario_sala')->where('usuario_id', $usuarioId)->delete();
            }
            if (Schema::hasTable('usuario_empresa')) {
                DB::table('usuario_empresa')->where('usuario_id', $usuarioId)->delete();
            }
            DB::table('usuario')->where('id', $usuarioId)->where('usuario', $login)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarFirmantes(int $rolId): void
    {
        $now = now();
        $passwordHash = Hash::make(self::PASSWORD);
        $salaPorCodigo = DB::table('sala')->pluck('id', 'codigo');
        $ccPorCodigo = DB::table('centrocosto')->pluck('id', 'codigo');

        foreach (self::FIRMANTES as $fila) {
            $centrocostoId = (int) ($ccPorCodigo[$fila['centrocosto_codigo']] ?? 0);
            if ($centrocostoId <= 0) {
                throw new \RuntimeException('Centro de costo inexistente: '.$fila['centrocosto_codigo']);
            }

            $salaId = (int) ($salaPorCodigo[$fila['sala_codigo']] ?? 0);
            if ($salaId <= 0) {
                throw new \RuntimeException('Sala inexistente: '.$fila['sala_codigo']);
            }

            $usuarioId = $this->usuarioId($fila['usuario']);
            if ($usuarioId <= 0) {
                if (! $fila['crear_si_falta']) {
                    throw new \RuntimeException('Usuario firmante inexistente (se esperaba existente): '.$fila['usuario']);
                }
                $usuarioId = (int) DB::table('usuario')->insertGetId([
                    'usuario' => $fila['usuario'],
                    'nombre' => $fila['nombre'],
                    'email' => $fila['usuario'].self::EMAIL_DOMINIO,
                    'password' => $passwordHash,
                    'centrocosto_id' => $centrocostoId,
                    'suspendido' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('usuario')->where('id', $usuarioId)->update([
                    'nombre' => $fila['nombre'],
                    'centrocosto_id' => $centrocostoId,
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

            if (Schema::hasTable('usuario_sala')
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
