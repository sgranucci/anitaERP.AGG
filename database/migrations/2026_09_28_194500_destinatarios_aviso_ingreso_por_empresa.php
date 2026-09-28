<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aviso de ticket de ingreso por empresa (sala).
 * Wilde = Kandiko, Avellaneda = Biyemas, Varela = Rebisco.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const CODIGOS = [
        'ingreso_proveedor_creado',
        'ingreso_proveedor_recordatorio',
    ];

    /**
     * empresa_id de la tabla empresa → usuarios (guardia, encargado, supervisores).
     *
     * @var array<int, list<string>>
     */
    private const DESTINATARIOS = [
        1 => ['guardia2.biy', 'afernandez', 'seguridad.biy'],
        2 => ['guardia2.kan', 'pcastellani', 'seguridad.kan'],
        3 => ['guardia2.reb', 'macuna', 'seguridad.reb'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! DB::getSchemaBuilder()->hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $now = now();
        foreach (self::CODIGOS as $codigo) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', 'seguridad')
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($tipoId <= 0) {
                continue;
            }
            foreach (self::DESTINATARIOS as $empresaId => $usuarios) {
                foreach ($usuarios as $login) {
                    $this->asegurar($tipoId, $login, (int) $empresaId, $now);
                }
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! DB::getSchemaBuilder()->hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $encargados = ['afernandez', 'pcastellani', 'macuna'];
        $supervisores = ['seguridad.biy', 'seguridad.kan', 'seguridad.reb'];
        $guardias = ['guardia2.biy', 'guardia2.kan', 'guardia2.reb'];

        foreach (self::CODIGOS as $codigo) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', 'seguridad')
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($tipoId <= 0) {
                continue;
            }

            $this->quitarPorLogin($tipoId, $supervisores);
            if ($codigo === 'ingreso_proveedor_creado') {
                $this->quitarPorLogin($tipoId, $encargados);
            }
            if ($codigo === 'ingreso_proveedor_recordatorio') {
                $this->quitarPorLogin($tipoId, $guardias);
                DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoId)
                    ->whereIn('usuario_id', function ($q) use ($encargados) {
                        $q->select('id')->from('usuario')->whereIn('usuario', $encargados);
                    })
                    ->update([
                        'empresa_id' => null,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    private function asegurar(int $tipoId, string $login, int $empresaId, $now): void
    {
        $usuario = DB::table('usuario')->where('usuario', $login)->first(['id', 'email']);
        if ($usuario === null) {
            return;
        }

        $email = strtolower(trim((string) $usuario->email));
        $usuarioId = (int) $usuario->id;

        $conEmpresa = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('usuario_id', $usuarioId)
            ->where('empresa_id', $empresaId)
            ->orderBy('id')
            ->first();

        if ($conEmpresa !== null) {
            DB::table('modulo_aviso_destinatario')->where('id', $conEmpresa->id)->update([
                'email' => $email !== '' ? $email : $conEmpresa->email,
                'activo' => true,
                'updated_at' => $now,
            ]);
            $this->desactivarSinEmpresa($tipoId, $usuarioId, (int) $conEmpresa->id, $now);

            return;
        }

        $sinEmpresa = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('usuario_id', $usuarioId)
            ->whereNull('empresa_id')
            ->orderBy('id')
            ->first();

        if ($sinEmpresa !== null) {
            DB::table('modulo_aviso_destinatario')->where('id', $sinEmpresa->id)->update([
                'email' => $email !== '' ? $email : $sinEmpresa->email,
                'empresa_id' => $empresaId,
                'activo' => true,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('modulo_aviso_destinatario')->insert([
            'modulo_aviso_tipo_id' => $tipoId,
            'email' => $email !== '' ? $email : null,
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'centrocosto_id' => null,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function desactivarSinEmpresa(int $tipoId, int $usuarioId, int $conservarId, $now): void
    {
        DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('usuario_id', $usuarioId)
            ->whereNull('empresa_id')
            ->where('id', '!=', $conservarId)
            ->update([
                'activo' => false,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param  list<string>  $logins
     */
    private function quitarPorLogin(int $tipoId, array $logins): void
    {
        DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->whereIn('usuario_id', function ($q) use ($logins) {
                $q->select('id')->from('usuario')->whereIn('usuario', $logins);
            })
            ->delete();
    }
};
