<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $tipos = [
            [
                'modulo' => 'contable',
                'codigo' => 'apertura_periodo_habilitada',
                'nombre' => 'Apertura de período contable habilitada',
                'descripcion' => 'Aviso al usuario cuando contaduría aprueba una apertura programada.',
                'mail_asunto' => 'Apertura contable habilitada — {alcance}',
                'mail_texto' => "Estimado/a {usuario},\n\nSe habilitó su apertura programada en {empresa}.\n\nAlcance: {alcance}\nFechas de operación: {fecha_desde} a {fecha_hasta}\nVence: {vence_en}\nDuración: {duracion}\n\nMotivo: {motivo}\n\nConsulte el estado en: {link_consulta}",
            ],
            [
                'modulo' => 'contable',
                'codigo' => 'apertura_periodo_recordatorio',
                'nombre' => 'Recordatorio vencimiento apertura contable',
                'descripcion' => 'Recordatorio antes de que venza el permiso temporal de apertura.',
                'mail_asunto' => 'Recordatorio: su apertura contable vence pronto ({alcance})',
                'mail_texto' => "Estimado/a {usuario},\n\nSu apertura programada en {empresa} vencerá el {vence_en}.\n\nAlcance: {alcance}\nFechas de operación: {fecha_desde} a {fecha_hasta}\n\nFinalizado el plazo, el período quedará nuevamente cerrado para su usuario en ese alcance.\n\n{link_consulta}",
            ],
            [
                'modulo' => 'contable',
                'codigo' => 'apertura_periodo_cerrada',
                'nombre' => 'Apertura contable finalizada',
                'descripcion' => 'Aviso cuando expira o se revoca una apertura programada.',
                'mail_asunto' => 'Período contable cerrado nuevamente — {alcance}',
                'mail_texto' => "Estimado/a {usuario},\n\nSu apertura programada en {empresa} finalizó.\n\nAlcance: {alcance}\nYa no puede registrar operaciones en fechas anteriores al cierre vigente en ese módulo, salvo una nueva apertura aprobada.\n\n{link_consulta}",
            ],
        ];

        foreach ($tipos as $tipo) {
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
    }

    public function down(): void
    {
        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'contable')
            ->whereIn('codigo', [
                'apertura_periodo_habilitada',
                'apertura_periodo_recordatorio',
                'apertura_periodo_cerrada',
            ])
            ->delete();
    }
};
