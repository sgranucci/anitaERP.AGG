<?php

use App\Models\Configuracion\ModuloAvisoDestinatario;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Los seeds de avisos de AGG grabaron mails @grupoagg.com en todas las
 * instalaciones. En El Bierzo esos destinatarios no corresponden: cada
 * recepción de laboratorio (y el resto de avisos del ABM) les llegaba
 * desde sistema@elbierzo.ar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo() || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $query = ModuloAvisoDestinatario::query()->where(function ($q) {
            $q->whereRaw('LOWER(TRIM(email)) LIKE ?', ['%@grupoagg.com'])
                ->orWhereIn('usuario_id', function ($sub) {
                    $sub->select('id')
                        ->from('usuario')
                        ->whereRaw('LOWER(TRIM(email)) LIKE ?', ['%@grupoagg.com']);
                });
        });

        EloquentAuditDeleteSupport::each($query);
    }

    public function down(): void
    {
        // No se restauran: volver a cargar mails de AGG en El Bierzo es el error.
    }
};
