<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso al técnico cuando Administración de tickets le asigna una tarea.
 * El destinatario principal es el usuario vinculado al técnico (dinámico).
 * Destinatarios extra del ABM se envían como copia. Editable en configuracion/modulo-aviso.
 */
return new class extends Migration
{
    private const MODULO = 'ticket';

    private const CODIGO = 'asignacion_tecnico';

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

        DB::table('modulo_aviso_tipo')->insert([
            'modulo' => self::MODULO,
            'codigo' => self::CODIGO,
            'nombre' => 'Asignación de ticket a técnico',
            'descripcion' => 'Aviso cuando Administración de tickets asigna o cambia el técnico de una tarea. '
                .'El mail principal va al usuario vinculado al técnico. '
                .'Los destinatarios de este ABM se envían como copia adicional.',
            'activo' => true,
            'mail_asunto' => 'Te asignaron el ticket #{id} — {titulo}',
            'mail_texto' => "Te asignaron una tarea en un ticket.\n\n"
                ."Ticket: #{id}\n"
                ."Título: {titulo}\n"
                ."Tarea: {tarea}\n"
                ."Técnico: {tecnico}\n"
                ."Asignado por: {asignado_por}\n"
                ."Solicitante: {usuario}\n"
                ."Sala: {sala}\n"
                ."Sector: {sector}\n"
                ."Área: {area}\n"
                ."Programación: {fechaprogramacion}\n"
                ."Estado: {estado}\n\n"
                ."Comentario:\n{comentario}\n\n"
                .'Abrí el ticket: {link_consulta}',
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
