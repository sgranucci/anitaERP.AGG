<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aviso de producción al grabar pedidos con artículos marcados con enviaalarma (El Bierzo).
 */
return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('modulo_aviso_tipo')
            ->where('modulo', 'ventas')
            ->where('codigo', 'pedido_produccion_alarma')
            ->exists();

        if ($existe) {
            return;
        }

        $now = now();
        DB::table('modulo_aviso_tipo')->insert([
            'modulo' => 'ventas',
            'codigo' => 'pedido_produccion_alarma',
            'nombre' => 'Pedido con aviso a producción',
            'descripcion' => 'Correo al registrar o ampliar un pedido que incluye artículos con «Envía alarma» activo en el maestro de artículos (El Bierzo).',
            'activo' => false,
            'mail_asunto' => 'Pedido Nº {numero} — aviso a producción ({cliente})',
            'mail_texto' => 'Se registró el pedido Nº {numero} del cliente {cliente} (fecha {fecha}, entrega {fecha_entrega}).'
                ."\n\nArtículos con alarma a producción:\n{articulos_alarma}"
                ."\n\nRegistrado por {usuario}. Consultá el pedido desde el enlace del correo.",
            'mail_remitente' => null,
            'adjuntar_pdf' => true,
            'incluir_link_consulta' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', 'ventas')
            ->where('codigo', 'pedido_produccion_alarma')
            ->value('id') ?? 0);

        if ($tipoId > 0) {
            DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $tipoId)->delete();
            DB::table('modulo_aviso_tipo')->where('id', $tipoId)->delete();
        }
    }
};
