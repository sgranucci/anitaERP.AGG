<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipo de aviso configurable: cumplimiento de requisición de compra.
 * Se envía al generador de la requisición y a los destinatarios configurados.
 * Se crea activo: el aviso al generador queda habilitado por defecto.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'requisicion_compra_cumplida';

    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        $existe = DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->exists();
        if ($existe) {
            return;
        }

        DB::table('modulo_aviso_tipo')->insert([
            'modulo' => self::MODULO,
            'codigo' => self::CODIGO,
            'nombre' => 'Cumplimiento de requisición de compra',
            'descripcion' => 'Aviso al generador de la requisición cuando se registra un cumplimiento (transferencia de mercadería). Incluye enlace a consulta.',
            'activo' => true,
            'mail_asunto' => 'Requisición de compra Nº {numero} cumplida',
            'mail_texto' => 'Se registró un cumplimiento (Nº {cumplimiento_numero}) para la requisición de compra Nº {numero} solicitada por {solicitante} ({empresa}, CC {centro_costo}).'
                .' Podés consultarla desde el enlace del correo.',
            'mail_remitente' => null,
            'adjuntar_pdf' => false,
            'incluir_link_consulta' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->delete();
    }
};
