<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->where('codigo', 'recepcion_proveedor_precio_diferencia')
            ->value('id') ?? 0);

        if ($tipoId === 0) {
            $tipoId = (int) DB::table('modulo_aviso_tipo')->insertGetId([
                'modulo' => 'stock',
                'codigo' => 'recepcion_proveedor_precio_diferencia',
                'nombre' => 'Recepción proveedor con diferencia de precio',
                'descripcion' => 'Aviso a compras cuando el precio de recepción difiere del de la orden de compra.',
                'activo' => true,
                'mail_asunto' => 'Recepción Nº {numero_recepcion} - diferencia de precio OC {numero_oc}',
                'mail_texto' => 'La recepción Nº {numero_recepcion} del proveedor {proveedor} (OC {numero_oc}) registró precios distintos a los de la orden de compra.'
                    .' Comentario: {comentario_precio}. Consulte el detalle en el sistema.',
                'mail_remitente' => null,
                'adjuntar_pdf' => false,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $existeDest = DB::table('modulo_aviso_destinatario')
            ->where('modulo_aviso_tipo_id', $tipoId)
            ->where('email', 'ablanco@grupoagg.com')
            ->exists();

        if (! $existeDest) {
            DB::table('modulo_aviso_destinatario')->insert([
                'modulo_aviso_tipo_id' => $tipoId,
                'email' => 'ablanco@grupoagg.com',
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
        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'stock')
            ->where('codigo', 'recepcion_proveedor_precio_diferencia')
            ->value('id') ?? 0);

        if ($tipoId > 0) {
            DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $tipoId)->delete();
            DB::table('modulo_aviso_tipo')->where('id', $tipoId)->delete();
        }
    }
};
