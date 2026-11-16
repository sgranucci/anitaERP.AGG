<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa destinatarios de caja.cheques_aging desde CHEQUE_AGING_AVISO_EMAILS
 * cuando el catálogo quedó vacío (seed previo con env sin emails).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo') || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'caja')
            ->where('codigo', 'cheques_aging')
            ->value('id') ?? 0);
        if ($tipoId <= 0) {
            return;
        }

        $yaHay = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('activo', true)
            ->exists();
        if ($yaHay) {
            return;
        }

        $emails = array_filter(array_map(
            'trim',
            explode(',', (string) config('cheque.aging_aviso.emails', ''))
        ));
        $now = now();
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $email = strtolower($email);
            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where('email', $email)
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => $email,
                'usuario_id' => null,
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
        // No borra destinatarios: pueden haber sido editados en pantalla.
    }
};
