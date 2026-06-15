<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $tipo = [
            'modulo' => 'contable',
            'codigo' => 'apertura_periodo_solicitud_pendiente',
            'nombre' => 'Solicitud de apertura contable pendiente',
            'descripcion' => 'Aviso a encargados con permiso habilitar-apertura-periodo-contable.',
            'mail_asunto' => 'Solicitud de apertura contable pendiente — {empresa}',
            'mail_texto' => "Hay una solicitud de apertura de período contable pendiente de habilitación.\n\n"
                ."Empresa: {empresa}\n"
                ."Solicitante: {solicitante}\n"
                ."Usuario a habilitar: {usuario}\n"
                ."Alcance: {alcance}\n"
                ."Fechas de operación: {fecha_desde} a {fecha_hasta}\n"
                ."Duración solicitada: {duracion}\n"
                ."Motivo: {motivo}\n\n"
                ."Habilitar directamente (sin entrar al módulo):\n{link_habilitar}\n\n"
                ."Ver listado de aperturas:\n{link_consulta}",
        ];

        $existe = DB::table('modulo_aviso_tipo')
            ->where('modulo', $tipo['modulo'])
            ->where('codigo', $tipo['codigo'])
            ->exists();

        if ($existe) {
            DB::table('modulo_aviso_tipo')
                ->where('modulo', $tipo['modulo'])
                ->where('codigo', $tipo['codigo'])
                ->update([
                    'nombre' => $tipo['nombre'],
                    'descripcion' => $tipo['descripcion'],
                    'mail_asunto' => $tipo['mail_asunto'],
                    'mail_texto' => $tipo['mail_texto'],
                    'activo' => true,
                    'incluir_link_consulta' => true,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('modulo_aviso_tipo')->insert([
                'modulo' => $tipo['modulo'],
                'codigo' => $tipo['codigo'],
                'nombre' => $tipo['nombre'],
                'descripcion' => $tipo['descripcion'],
                'activo' => true,
                'mail_asunto' => $tipo['mail_asunto'],
                'mail_texto' => $tipo['mail_texto'],
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'contable')
            ->where('codigo', 'apertura_periodo_solicitud_pendiente')
            ->delete();
    }
};
