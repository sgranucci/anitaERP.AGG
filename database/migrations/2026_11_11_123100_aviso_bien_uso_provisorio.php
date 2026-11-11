<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso al crear bienes de uso provisorios desde factura.
 * Destinatarios: Configuración → Avisos por módulo (sin seed de mails).
 */
return new class extends Migration
{
    private const MODULO = 'contable';

    private const CODIGO = 'bien_uso_provisorio';

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $existe = DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->exists();
        if ($existe) {
            return;
        }

        $now = now();
        DB::table('modulo_aviso_tipo')->insert([
            'modulo' => self::MODULO,
            'codigo' => self::CODIGO,
            'nombre' => 'Bien de uso provisorio desde factura',
            'descripcion' => 'Se dispara al cargar bienes de uso en estado provisorio desde un comprobante '
                .'de proveedor (tipos con Genera bien de uso). Complete los destinatarios en este ABM.',
            'activo' => true,
            'mail_asunto' => 'AnitaERP: bienes de uso provisorios — {factura} ({cantidad})',
            'mail_texto' => "Se cargaron bienes de uso en estado provisorio desde la factura {factura}.\n\n"
                ."Proveedor: {proveedor}\n"
                ."Fecha factura: {fecha}\n"
                ."Cantidad: {cantidad}\n\n"
                ."Bienes:\n{bienes}\n\n"
                ."Listado de provisorios: {link_consulta}\n"
                ."Complete rubro, cuentas y datos faltantes en el ABM y pase el estado a Activo.\n",
            'mail_remitente' => null,
            'adjuntar_pdf' => false,
            'incluir_link_consulta' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $tipoId = (int) (DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->value('id') ?? 0);

        if ($tipoId > 0 && Schema::hasTable('modulo_aviso_destinatario')) {
            DB::table('modulo_aviso_destinatario')->where('modulo_aviso_tipo_id', $tipoId)->delete();
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->delete();
    }
};
