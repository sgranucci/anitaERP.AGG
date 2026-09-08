<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos ModuloAviso: digest al dueño + escalamiento a gerencia (día 20).
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $now = now();
        $this->upsertTipo([
            'codigo' => 'suscripcion_comprobante_faltante',
            'nombre' => 'Suscripción: factura de portal pendiente',
            'descripcion' => 'Digest diario al dueño del servicio cuando hay cargos asociados sin PDF del portal. '
                .'Los destinatarios del ABM no se usan: el mail va al dueño (suscripcion_owner).',
            'mail_asunto' => 'Facturas de portal pendientes ({cantidad}) — {fecha}',
            'mail_texto' => "Tenés comprobantes de suscripción pendientes de subir.\n\n"
                ."Cantidad: {cantidad}\n"
                ."Fecha: {fecha}\n\n"
                ."{pendientes}\n\n"
                ."Cargar: {link_consulta}\n",
        ], $now);

        $escalaId = $this->upsertTipo([
            'codigo' => 'suscripcion_comprobante_escalamiento',
            'nombre' => 'Suscripción: escalamiento factura portal (día 20)',
            'descripcion' => 'Desde el día 20 del mes del período, avisa a gerencia (destinatarios de este tipo) '
                .'si sigue faltando el PDF del portal. Configurable en este ABM.',
            'mail_asunto' => 'Escalamiento: facturas de portal sin cargar ({cantidad}) — {fecha}',
            'mail_texto' => "Hay suscripciones con gasto en tarjeta sin factura de portal cargada "
                ."(umbral día 20 del período).\n\n"
                ."Cantidad: {cantidad}\n"
                ."Fecha: {fecha}\n\n"
                ."{pendientes}\n\n"
                ."Listado: {link_consulta}\n",
        ], $now);

        // Destinatario inicial de escalamiento: mismo contacto de compras si existe.
        if ($escalaId > 0 && Schema::hasTable('modulo_aviso_destinatario')) {
            $email = 'ablanco@grupoagg.com';
            $usuarioId = (int) (DB::table('usuario')->whereRaw('LOWER(email) = ?', [$email])->value('id') ?? 0);
            $existe = DB::table('modulo_aviso_destinatario')
                ->where('modulo_aviso_tipo_id', $escalaId)
                ->where(function ($q) use ($email, $usuarioId) {
                    $q->where('email', $email);
                    if ($usuarioId > 0) {
                        $q->orWhere('usuario_id', $usuarioId);
                    }
                })
                ->exists();
            if (! $existe) {
                DB::table('modulo_aviso_destinatario')->insert([
                    'modulo_aviso_tipo_id' => $escalaId,
                    'email' => $email,
                    'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                    'empresa_id' => null,
                    'centrocosto_id' => null,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }
        foreach (['suscripcion_comprobante_faltante', 'suscripcion_comprobante_escalamiento'] as $codigo) {
            $tipoId = (int) (DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', $codigo)
                ->value('id') ?? 0);
            if ($tipoId > 0 && Schema::hasTable('modulo_aviso_destinatario')) {
                DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $tipoId)->delete();
            }
            DB::table('modulo_aviso_tipo')
                ->where('modulo', self::MODULO)
                ->where('codigo', $codigo)
                ->delete();
        }
    }

    /** @param array{codigo: string, nombre: string, descripcion: string, mail_asunto: string, mail_texto: string} $data */
    private function upsertTipo(array $data, $now): int
    {
        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', $data['codigo'])
            ->value('id') ?? 0);

        $payload = [
            'modulo' => self::MODULO,
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
            'activo' => true,
            'mail_asunto' => $data['mail_asunto'],
            'mail_texto' => $data['mail_texto'],
            'mail_remitente' => null,
            'adjuntar_pdf' => false,
            'incluir_link_consulta' => true,
            'updated_at' => $now,
        ];

        if ($tipoId > 0) {
            DB::table('modulo_aviso_tipo')->where('id', $tipoId)->update($payload);

            return $tipoId;
        }

        return (int) DB::table('modulo_aviso_tipo')->insertGetId(array_merge($payload, ['created_at' => $now]));
    }
};
