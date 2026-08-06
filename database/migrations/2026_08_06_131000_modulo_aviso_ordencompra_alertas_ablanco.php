<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plantilla estándar + destinatario ablanco@grupoagg.com (todas las empresas)
 * para alertas de OC abiertas.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'ordencompra_alertas_abiertas';

    private const EMAIL = 'ablanco@grupoagg.com';

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId <= 0) {
            return;
        }

        $mailAsunto = 'Alertas de órdenes de compra abiertas — {fecha}';
        $mailTexto = "Resumen de órdenes de compra abiertas al {fecha}.\n\n"
            ."Se listan OC con saldo pendiente de recepción, agrupadas por tipo de alerta.\n\n"
            ."1) OC sin recepción después de {dias_sin_recepcion} días ({cantidad_sin_recepcion})\n"
            ."Órdenes aprobadas sin COM confirmada y con antigüedad mayor al umbral configurado.\n"
            ."{oc_sin_recepcion}\n\n"
            ."2) OC parcialmente recibidas ({cantidad_parciales})\n"
            ."Órdenes con recepción parcial y cantidad aún pendiente.\n"
            ."{oc_parcialmente_recibidas}\n\n"
            ."3) OC vencidas ({cantidad_vencidas})\n"
            ."Órdenes con fecha de entrega vencida y saldo pendiente.\n"
            ."{oc_vencidas}\n\n"
            ."4) Saldos pendientes ({cantidad_saldos_pendientes})\n"
            ."Detalle consolidado de OC abiertas o cumplidas con cantidad pendiente de recepción.\n"
            ."{saldos_pendientes}\n\n"
            ."Podés consultar el reporte de OC en el ERP:\n"
            .'{link_consulta}';

        DB::table('modulo_aviso_tipo')
            ->where('id', $tipoId)
            ->update([
                'activo' => true,
                'mail_asunto' => $mailAsunto,
                'mail_texto' => $mailTexto,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'descripcion' => 'Resumen diario por cron: OC sin recepción después de X días, parcialmente recibidas, vencidas y saldos pendientes. Destinatario sin filtro de empresa = todas las empresas.',
                'updated_at' => $now,
            ]);

        if (! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $usuarioId = (int) (DB::table('usuario')
            ->whereRaw('LOWER(email) = ?', [self::EMAIL])
            ->value('id') ?? 0);

        $ya = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where(function ($q) use ($usuarioId) {
                $q->where('email', self::EMAIL);
                if ($usuarioId > 0) {
                    $q->orWhere('usuario_id', $usuarioId);
                }
            })
            ->exists();

        if ($ya) {
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->where(function ($q) use ($usuarioId) {
                    $q->where('email', self::EMAIL);
                    if ($usuarioId > 0) {
                        $q->orWhere('usuario_id', $usuarioId);
                    }
                })
                ->update([
                    'email' => self::EMAIL,
                    'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                    'empresa_id' => null,
                    'centrocosto_id' => null,
                    'activo' => true,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('modulo_aviso_destinatario')->insert([
            'modulo_aviso_tipo_id' => $tipoId,
            'email' => self::EMAIL,
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'empresa_id' => null,
            'centrocosto_id' => null,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_destinatario') || ! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId <= 0) {
            return;
        }

        DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('email', self::EMAIL)
            ->delete();
    }
};
