<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El aviso de vencimiento de certificados ARCA se sembró con mails de AGG
 * en todas las instalaciones. En El Bierzo Guillermo Surace no es destinatario.
 */
return new class extends Migration
{
    private const MODULO = 'arca';

    private const CODIGO = 'certificado_vencimiento';

    private const EMAIL = 'gsurace@grupoagg.com';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo() || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $tipoId = $this->tipoId();
        if ($tipoId <= 0) {
            return;
        }

        DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->whereRaw('LOWER(email) = ?', [self::EMAIL])
            ->delete();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo() || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $tipoId = $this->tipoId();
        if ($tipoId <= 0) {
            return;
        }

        $existe = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->whereRaw('LOWER(email) = ?', [self::EMAIL])
            ->exists();
        if ($existe) {
            return;
        }

        $now = now();
        DB::table('modulo_aviso_destinatario')->insert([
            'modulo_aviso_tipo_id' => $tipoId,
            'email' => self::EMAIL,
            'usuario_id' => null,
            'empresa_id' => null,
            'centrocosto_id' => null,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function tipoId(): int
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return 0;
        }

        return (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);
    }
};
