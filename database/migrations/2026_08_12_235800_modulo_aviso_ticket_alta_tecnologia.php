<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso al crear un ticket destinado a Tecnología.
 * Destinatario inicial: soporte@grupoagg.com. Editable en configuracion/modulo-aviso.
 */
return new class extends Migration
{
    private const MODULO = 'ticket';

    private const CODIGO = 'alta_tecnologia';

    private const EMAIL_DEFAULT = 'soporte@grupoagg.com';

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

        if ($tipoId === 0) {
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId([
                'modulo' => self::MODULO,
                'codigo' => self::CODIGO,
                'nombre' => 'Alta de ticket de Tecnología',
                'descripcion' => 'Aviso cuando se crea un ticket destinado al área Tecnología (Carga o Administración). Destinatarios editables en este ABM.',
                'activo' => true,
                'mail_asunto' => 'Nuevo ticket de Tecnología #{id} — {titulo}',
                'mail_texto' => "Se creó un ticket destinado a Tecnología.\n\n"
                    ."Ticket: #{id}\n"
                    ."Título: {titulo}\n"
                    ."Sala: {sala}\n"
                    ."Sector: {sector}\n"
                    ."Categoría: {categoria}\n"
                    ."Subcategoría: {subcategoria}\n"
                    ."Generó usuario: {usuario}\n"
                    ."Cargado por: {cargado_por}\n"
                    ."Estado: {estado}\n"
                    ."Fecha: {fecha}\n\n"
                    ."Comentario:\n{comentario}\n\n"
                    .'Abrí el ticket: {link_consulta}',
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($tipoId <= 0 || ! Schema::hasTable('modulo_aviso_destinatario')) {
            return;
        }

        $email = strtolower(self::EMAIL_DEFAULT);
        $ya = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('email', $email)
            ->exists();

        if (! $ya) {
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
