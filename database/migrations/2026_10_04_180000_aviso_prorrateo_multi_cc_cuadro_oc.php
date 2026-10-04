<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El aviso de precarga prorrateada listaba "pesos por fino" sin mostrar de qué línea
 * de la OC sale cada importe. El cuadro explica ese peso.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO_AVISO = 'precarga_prorrateo_multi_cc';

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO_AVISO)
            ->update([
                'mail_texto' => $this->textoNuevo(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO_AVISO)
            ->update([
                'mail_texto' => $this->textoAnterior(),
                'updated_at' => now(),
            ]);
    }

    private function textoNuevo(): string
    {
        return "Ingresó una precarga con tipo prorrateado: la orden de compra tiene centros con distinto tratamiento de IVA y el comprobante los cubre juntos.\n\n"
            ."Empresa: {empresa}\n"
            ."Proveedor: {proveedor}\n"
            ."Comprobante: {comprobante}\n"
            ."Tipo: {tipo}\n"
            ."Fecha: {fecha}\n"
            ."OC: {oc}\n"
            ."Subtotal: {subtotal}\n"
            ."Total: {total}\n"
            ."Alerta: {alerta}\n\n"
            ."Conceptos de la precarga:\n{conceptos}\n\n"
            ."El total de arriba es el del comprobante. El cuadro es la orden de compra: cada importe es el peso de ese centro. Si el comprobante trae IVA, solo ese IVA se reparte con esos pesos. El neto gravado queda en una sola línea. Por eso los importes del cuadro no tienen que coincidir con el total de la precarga.\n\n"
            ."{cuadro_oc}\n\n"
            ."Revisar la precarga: {link_consulta}\n";
    }

    private function textoAnterior(): string
    {
        return "Ingresó una precarga de factura con tipo prorrateado multi-CC.\n\n"
            ."Empresa: {empresa}\n"
            ."Proveedor: {proveedor}\n"
            ."Comprobante: {comprobante}\n"
            ."Tipo: {tipo}\n"
            ."Fecha: {fecha}\n"
            ."OC: {oc}\n"
            ."CC destino: {centros}\n"
            ."Tipos origen: {tipos_origen}\n"
            ."Pesos por fino: {pesos}\n"
            ."Subtotal: {subtotal}\n"
            ."Total: {total}\n"
            ."Alerta: {alerta}\n\n"
            ."Conceptos:\n{conceptos}\n\n"
            ."Revisar la precarga: {link_consulta}\n";
    }
};
