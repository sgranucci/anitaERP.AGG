<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El aviso de precarga prorrateada no mostraba los centros de costo, los tipos finos origen
 * ni los pesos: sin eso el mail parecía una factura común con tipo FPB.
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

    private function textoAnterior(): string
    {
        return "Ingresó una precarga de factura con tipo prorrateado multi-CC.\n\n"
            ."Empresa: {empresa}\n"
            ."Proveedor: {proveedor}\n"
            ."Comprobante: {comprobante}\n"
            ."Tipo: {tipo}\n"
            ."Fecha: {fecha}\n"
            ."OC: {oc}\n"
            ."Subtotal: {subtotal}\n"
            ."Total: {total}\n\n"
            ."Conceptos:\n{conceptos}\n\n"
            ."Revisar la precarga: {link_consulta}\n";
    }
};
