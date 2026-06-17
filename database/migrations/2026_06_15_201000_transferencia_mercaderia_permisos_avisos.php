<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permisos = [
            ['nombre' => 'Listar transferencias pendientes', 'slug' => 'listar-transferencias-pendientes'],
            ['nombre' => 'Aprobar transferencia de mercadería', 'slug' => 'aprobar-transferencia-mercaderia'],
        ];

        foreach ($permisos as $perm) {
            if (! DB::table('permiso')->where('slug', $perm['slug'])->exists()) {
                DB::table('permiso')->insert(array_merge($perm, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        $tipos = [
            [
                'modulo' => 'stock',
                'codigo' => 'transferencia_pendiente_aprobacion',
                'nombre' => 'Transferencia pendiente de aprobación',
                'descripcion' => 'Aviso al encargado del depósito destino (y destinatarios del módulo de avisos) con enlaces para aprobar o rechazar.',
                'mail_asunto' => 'Transferencia {codigo} pendiente de recepción',
                'mail_texto' => "Transferencia de mercadería pendiente de su aprobación\n\nCódigo: {codigo}\nFecha: {fecha}\nOrigen: {deposito_origen}\nDestino: {deposito_destino}\nEnviada por: {usuario_origen}\n\nÍtems:\n{detalle_lineas}\n\nAprobar: {link_aprobar}\nRechazar: {link_rechazar}\nConsultar: {link_consulta}",
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'transferencia_confirmada',
                'nombre' => 'Transferencia confirmada',
                'descripcion' => 'Aviso al remitente y destinatarios configurados cuando la transferencia se confirma.',
                'mail_asunto' => 'Transferencia {codigo} confirmada',
                'mail_texto' => "La transferencia fue confirmada\n\nCódigo: {codigo}\nFecha: {fecha}\nOrigen: {deposito_origen}\nDestino: {deposito_destino}\n\nÍtems:\n{detalle_lineas}\n\nConsulta: {link_consulta}",
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'transferencia_rechazada',
                'nombre' => 'Transferencia rechazada',
                'descripcion' => 'Aviso al remitente cuando el depósito destino rechaza la transferencia.',
                'mail_asunto' => 'Transferencia {codigo} rechazada',
                'mail_texto' => "La transferencia fue rechazada\n\nCódigo: {codigo}\nMotivo: {motivo_rechazo}\nOrigen: {deposito_origen}\nDestino: {deposito_destino}\n\nÍtems:\n{detalle_lineas}",
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => false,
            ],
        ];

        foreach ($tipos as $tipo) {
            $exists = DB::table('modulo_aviso_tipo')
                ->where('modulo', $tipo['modulo'])
                ->where('codigo', $tipo['codigo'])
                ->exists();

            if (! $exists) {
                DB::table('modulo_aviso_tipo')->insert(array_merge($tipo, [
                    'activo' => true,
                    'mail_remitente' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->whereIn('codigo', [
                'transferencia_pendiente_aprobacion',
                'transferencia_confirmada',
                'transferencia_rechazada',
            ])
            ->delete();

        DB::table('permiso')->whereIn('slug', [
            'listar-transferencias-pendientes',
            'aprobar-transferencia-mercaderia',
        ])->delete();
    }
};
