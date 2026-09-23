<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - REBISCO CH: Beta (id 318) cierra en APROBADA.
 * - KANDIKO Técnica: Angel solo cubría 0–5M; montos ≥5M (o USD equivalentes) no tenían
 *   firmante y el circuito podía cerrar sin aprobación. Activa 2ª firma (Beta nivel 3).
 */
return new class extends Migration
{
    private const ARBOL_KANDIKO = 4;

    private const CC_TECNICA = 9;

    private const USUARIO_ANGEL = 91;

    private const USUARIO_BETA = 81;

    private const NIVEL_ANGEL = 297;

    private const NIVEL1 = 293;

    private const BETA_REBISCO_CH = 318;

    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        $now = now();

        DB::table('arbolaprobacion_nivel')
            ->where('id', self::BETA_REBISCO_CH)
            ->update([
                'documento_estado_al_aprobar' => 'APROBADA',
                'updated_at' => $now,
            ]);

        if (! DB::table('arbolaprobacion')->where('id', self::ARBOL_KANDIKO)->exists()) {
            return;
        }

        DB::table('arbolaprobacion_nivel')->where('id', self::NIVEL1)->update([
            'doble_aprobacion' => 'S',
            'updated_at' => $now,
        ]);

        DB::table('arbolaprobacion_nivel')->where('id', self::NIVEL_ANGEL)->update([
            'doble_aprobacion' => 'S',
            'documento_estado_al_aprobar' => 'APROBADA',
            'updated_at' => $now,
        ]);

        $yaBeta = DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', self::ARBOL_KANDIKO)
            ->where('centrocosto_id', self::CC_TECNICA)
            ->where('usuario_id', self::USUARIO_BETA)
            ->exists();

        if (! $yaBeta) {
            DB::table('arbolaprobacion_nivel')->insert([
                'arbolaprobacion_id' => self::ARBOL_KANDIKO,
                'centrocosto_id' => self::CC_TECNICA,
                'nivel' => 3,
                'usuario_id' => self::USUARIO_BETA,
                'usuario_orig_id' => null,
                'desdemonto' => 5000000,
                'hastamonto' => 99999999999999999,
                'moneda_id' => 1,
                'documento_estado_al_aprobar' => 'APROBADA',
                'doble_aprobacion' => 'S',
                'rama' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', self::ARBOL_KANDIKO)
            ->where('centrocosto_id', self::CC_TECNICA)
            ->where('usuario_id', self::USUARIO_BETA)
            ->where('nivel', 3)
            ->delete();

        DB::table('arbolaprobacion_nivel')->where('id', self::NIVEL1)->update([
            'doble_aprobacion' => 'N',
            'updated_at' => now(),
        ]);

        DB::table('arbolaprobacion_nivel')->where('id', self::NIVEL_ANGEL)->update([
            'doble_aprobacion' => 'N',
            'updated_at' => now(),
        ]);
    }
};
