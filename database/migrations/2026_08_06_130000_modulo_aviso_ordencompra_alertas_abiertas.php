<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso diario de alertas de órdenes de compra abiertas (cron compras:alertas-ordencompra-abiertas).
 * Destinatarios y plantilla se configuran en Configuración → Avisos por módulo.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'ordencompra_alertas_abiertas';

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $existe = DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->exists();

        if ($existe) {
            return;
        }

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

        DB::table('modulo_aviso_tipo')->insert([
            'modulo' => self::MODULO,
            'codigo' => self::CODIGO,
            'nombre' => 'Alertas de órdenes de compra abiertas',
            'descripcion' => 'Resumen diario por cron: OC sin recepción después de X días, parcialmente recibidas, vencidas y saldos pendientes. Destinatario sin filtro de empresa = todas las empresas.',
            'activo' => true,
            'mail_asunto' => 'Alertas de órdenes de compra abiertas — {fecha}',
            'mail_texto' => $mailTexto,
            'mail_remitente' => null,
            'adjuntar_pdf' => false,
            'incluir_link_consulta' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId > 0 && Schema::hasTable('modulo_aviso_destinatario')) {
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoId)
                ->delete();
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->delete();
    }
};
