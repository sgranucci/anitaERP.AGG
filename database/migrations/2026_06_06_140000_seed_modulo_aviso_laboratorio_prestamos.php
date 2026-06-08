<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Destinatarios iniciales laboratorio (CC 93) para aviso de requisición de sala.
 * Activa tipos de aviso de préstamos e incorpora avisos al solicitante.
 */
return new class extends Migration
{
    private const CC_LABORATORIO = '93';

    /** Usuarios encargados / supervisor técnica */
    private array $usuariosLaboratorio = [
        'rvaldez',
        'evillagra',
    ];

    public function up(): void
    {
        $tipoRsId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'sala')
            ->where('codigo', 'requisicion_sala_creacion')
            ->value('id') ?? 0);

        $ccId = (int) (DB::table('centrocosto')->where('codigo', self::CC_LABORATORIO)->value('id') ?? 0);
        $usuarioPorLogname = DB::table('usuario')->pluck('id', 'usuario');

        if ($tipoRsId > 0 && $ccId > 0) {
            foreach ($this->usuariosLaboratorio as $logname) {
                $usuarioId = (int) ($usuarioPorLogname[$logname] ?? 0);
                if ($usuarioId <= 0) {
                    continue;
                }
                $existe = DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoRsId)
                    ->where('usuario_id', $usuarioId)
                    ->where('centrocosto_id', $ccId)
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('modulo_aviso_destinatario')->insert([
                    'modulo_aviso_tipo_id' => $tipoRsId,
                    'email' => null,
                    'usuario_id' => $usuarioId,
                    'empresa_id' => null,
                    'centrocosto_id' => $ccId,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $now = now();
        $tiposPrestamo = [
            [
                'modulo' => 'stock',
                'codigo' => 'prestamo_aprobado_solicitante',
                'nombre' => 'Préstamo aprobado (aviso al solicitante)',
                'descripcion' => 'Correo al solicitante cuando el destinatario aprueba la recepción.',
                'activo' => true,
                'mail_asunto' => 'Préstamo {codigo} aprobado por el destinatario',
                'mail_texto' => 'Tu préstamo {codigo} fue aprobado. Los materiales ingresaron al depósito destino {deposito_destino}.',
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'prestamo_rechazado_solicitante',
                'nombre' => 'Préstamo rechazado (aviso al solicitante)',
                'descripcion' => 'Correo al solicitante cuando el destinatario rechaza la recepción.',
                'activo' => true,
                'mail_asunto' => 'Préstamo {codigo} rechazado por el destinatario',
                'mail_texto' => 'Tu préstamo {codigo} fue rechazado por el administrador del depósito destino.',
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
        ];

        foreach ($tiposPrestamo as $tipo) {
            $existe = DB::table('modulo_aviso_tipo')
                ->where('modulo', $tipo['modulo'])
                ->where('codigo', $tipo['codigo'])
                ->exists();
            if (! $existe) {
                DB::table('modulo_aviso_tipo')->insert(array_merge($tipo, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->whereIn('codigo', ['prestamo_solicitud', 'prestamo_recordatorio'])
            ->update([
                'activo' => true,
                'updated_at' => $now,
            ]);

        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->where('codigo', 'prestamo_solicitud')
            ->update([
                'mail_asunto' => 'Préstamo {codigo}: pendiente de aprobación',
                'mail_texto' => 'Recibirás un préstamo de materiales hacia tu depósito. Revisalo y aprobalo o rechazalo desde los enlaces de este correo.',
                'incluir_link_consulta' => true,
                'updated_at' => $now,
            ]);

        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->where('codigo', 'prestamo_recordatorio')
            ->update([
                'mail_asunto' => 'Recordatorio devolución préstamo {codigo}',
                'mail_texto' => 'Te recordamos que tenés materiales pendientes de devolución del préstamo {codigo} (vence {fecha_devolucion}).',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $tipoRsId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'sala')
            ->where('codigo', 'requisicion_sala_creacion')
            ->value('id') ?? 0);
        $ccId = (int) (DB::table('centrocosto')->where('codigo', self::CC_LABORATORIO)->value('id') ?? 0);

        if ($tipoRsId > 0 && $ccId > 0) {
            DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $tipoRsId)
                ->where('centrocosto_id', $ccId)
                ->whereIn('usuario_id', function ($q) {
                    $q->select('id')->from('usuario')->whereIn('usuario', ['rvaldez', 'evillagra']);
                })
                ->delete();
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->whereIn('codigo', ['prestamo_aprobado_solicitante', 'prestamo_rechazado_solicitante'])
            ->delete();
    }
};
