<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Destinatarios del aviso pedido_produccion_alarma (lista operativa El Bierzo).
 */
return new class extends Migration
{
    private const MODULO = 'ventas';

    private const CODIGO = 'pedido_produccion_alarma';

    /** @var list<string> */
    private const EMAILS = [
        'maximilianot@elbierzo.com.ar',
        'claudiom@elbierzo.com.ar',
        'mariom@elbierzo.com.ar',
        'fernandod@elbierzo.com.ar',
        'marceloa@elbierzo.com.ar',
        'martinl@elbierzo.com.ar',
        'albertod@elbierzo.com.ar',
        'robertof@elbierzo.com.ar',
        'julietat@elbierzo.com.ar',
        'pablos@elbierzo.com.ar',
        'javiera@elbierzo.com.ar',
        'danielm@elbierzo.com.ar',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId <= 0) {
            return;
        }

        $now = now();

        foreach (self::EMAILS as $email) {
            $emailNorm = strtolower(trim($email));
            $ya = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->whereRaw('LOWER(email) = ?', [$emailNorm])
                ->exists();
            if ($ya) {
                continue;
            }

            $usuarioId = DB::table('usuario')
                ->whereRaw('LOWER(email) = ?', [$emailNorm])
                ->value('id');

            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $emailNorm,
                'usuario_id' => $usuarioId ? (int) $usuarioId : null,
                'empresa_id' => null,
                'centrocosto_id' => null,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId <= 0) {
            return;
        }

        foreach (self::EMAILS as $email) {
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                ->delete();
        }
    }
};
