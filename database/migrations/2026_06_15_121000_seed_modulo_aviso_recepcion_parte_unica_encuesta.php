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
                'modulo' => 'stock',
                'codigo' => 'recepcion_proveedor_parte_unica',
                'nombre' => 'Recepción con partes únicas (NPU)',
                'descripcion' => 'Aviso al confirmar recepción con artículos que generan número de parte único.',
                'activo' => true,
                'mail_asunto' => 'Recepción COM {com_anita} — partes únicas',
                'mail_texto' => 'Se confirmó la recepción Nº {numero_recepcion} del proveedor {proveedor} (OC {numero_oc}) con partes únicas generadas.',
                'adjuntar_pdf' => true,
                'incluir_link_consulta' => true,
            ],
            [
                'modulo' => 'stock',
                'codigo' => 'recepcion_proveedor_encuesta',
                'nombre' => 'Encuesta a proveedor post-recepción',
                'descripcion' => 'Envía al solicitante de la requisición el link para completar la encuesta de proveedores.',
                'activo' => true,
                'mail_asunto' => 'Recepción COM {com_anita} — encuesta de proveedor',
                'mail_texto' => "Encuesta de proveedores\n\nFecha recepción: {fecha}\nProveedor: {proveedor}\nOC: {numero_oc}\n\nComplete la encuesta en el siguiente enlace:\n{link_encuesta}",
                'adjuntar_pdf' => true,
                'incluir_link_consulta' => false,
            ],
        ];

        foreach ($tipos as $tipo) {
            $existe = DB::table('modulo_aviso_tipo')
                ->where('modulo', $tipo['modulo'])
                ->where('codigo', $tipo['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('modulo_aviso_tipo')->insert(array_merge($tipo, [
                'mail_remitente' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $tipoPuId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->where('codigo', 'recepcion_proveedor_parte_unica')
            ->value('id') ?? 0);

        if ($tipoPuId > 0) {
            foreach (['gdominick@grupoagg.com', 'eguevara@grupoagg.com', 'gsurace@grupoagg.com', 'ablanco@grupoagg.com'] as $email) {
                $ya = DB::table('modulo_aviso_destinatario')
                    ->where('modulo_aviso_tipo_id', $tipoPuId)
                    ->where('email', $email)
                    ->exists();
                if (! $ya) {
                    DB::table('modulo_aviso_destinatario')->insert([
                        'modulo_aviso_tipo_id' => $tipoPuId,
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
        }
    }

    public function down(): void
    {
        $codigos = ['recepcion_proveedor_parte_unica', 'recepcion_proveedor_encuesta'];
        $ids = DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->whereIn('codigo', $codigos)
            ->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('modulo_aviso_destinatario')->whereIn('modulo_aviso_tipo_id', $ids)->delete();
            DB::table('modulo_aviso_tipo')->whereIn('id', $ids)->delete();
        }
    }
};
