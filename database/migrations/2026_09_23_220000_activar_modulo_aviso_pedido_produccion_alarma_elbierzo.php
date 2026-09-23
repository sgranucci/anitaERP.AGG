<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Activa y alinea al legacy Anita (envia_mail_alarma_stock.fc) el aviso de
 * artículos con stkm_envia_alarma / articulo.enviaalarma al grabar pedidos PE.
 * Solo El Bierzo. Destinatarios: configurar en ABM (lista maildest 1 del Anita).
 */
return new class extends Migration
{
    private const MODULO = 'ventas';

    private const CODIGO = 'pedido_produccion_alarma';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId <= 0) {
            $now = now();
            DB::table('modulo_aviso_tipo')->insert([
                'modulo' => self::MODULO,
                'codigo' => self::CODIGO,
                'nombre' => 'Pedido con aviso a producción',
                'descripcion' => 'Correo al registrar o ampliar un pedido (PE) que incluye artículos con «Envía alarma» / stkm_envia_alarma (El Bierzo). Equivale a envia_mail_alarma_stock.fc.',
                'activo' => true,
                'mail_asunto' => 'Artículos marcados vendedor {vendedor} Pedido Nro.: {numero}',
                'mail_texto' => "Se pidieron artículos especiales (marca Envía alarma) en el pedido Nº {numero}.\n"
                    ."Cliente: {cliente}\n"
                    ."Vendedor: {vendedor}\n"
                    ."Fecha: {fecha} — Entrega: {fecha_entrega}\n"
                    ."Registrado por: {usuario}\n\n"
                    ."Artículos:\n{articulos_alarma}",
                'mail_remitente' => null,
                'adjuntar_pdf' => true,
                'incluir_link_consulta' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('modulo_aviso_tipo')
            ->where('id', $tipoId)
            ->update([
                'activo' => true,
                'descripcion' => 'Correo al registrar o ampliar un pedido (PE) que incluye artículos con «Envía alarma» / stkm_envia_alarma (El Bierzo). Equivale a envia_mail_alarma_stock.fc.',
                'mail_asunto' => 'Artículos marcados vendedor {vendedor} Pedido Nro.: {numero}',
                'mail_texto' => "Se pidieron artículos especiales (marca Envía alarma) en el pedido Nº {numero}.\n"
                    ."Cliente: {cliente}\n"
                    ."Vendedor: {vendedor}\n"
                    ."Fecha: {fecha} — Entrega: {fecha_entrega}\n"
                    ."Registrado por: {usuario}\n\n"
                    ."Artículos:\n{articulos_alarma}",
                'adjuntar_pdf' => true,
                'incluir_link_consulta' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->update([
                'activo' => false,
                'updated_at' => now(),
            ]);
    }
};
