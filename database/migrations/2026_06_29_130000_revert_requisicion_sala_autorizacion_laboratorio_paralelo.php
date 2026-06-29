<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revierte flujo paralelo de autorización laboratorio (unificado en árbol RS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('requisicion_sala_autorizacion_laboratorio_token');
        Schema::dropIfExists('requisicion_sala_autorizacion_laboratorio');

        DB::table('modulo_aviso_destinatario')
            ->whereIn('modulo_aviso_tipo_id', function ($q) {
                $q->select('id')->from('modulo_aviso_tipo')
                    ->where('modulo', 'sala')
                    ->whereIn('codigo', [
                        'requisicion_sala_laboratorio_pendiente',
                        'requisicion_sala_laboratorio_rechazado',
                    ]);
            })
            ->delete();

        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'sala')
            ->whereIn('codigo', [
                'requisicion_sala_laboratorio_pendiente',
                'requisicion_sala_laboratorio_rechazado',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Restauración manual si hiciera falta: migración 2026_06_29_120000.
    }
};
