<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activa el aviso de cumplimiento de requisición de compra en instalaciones donde
 * ya se había insertado inactivo. El aviso al generador queda habilitado por defecto.
 */
return new class extends Migration
{
    private const MODULO = 'compras';

    private const CODIGO = 'requisicion_compra_cumplida';

    public function up(): void
    {
        if (! Schema::hasTable('modulo_aviso_tipo')) {
            return;
        }

        DB::table('modulo_aviso_tipo')
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->update([
                'activo' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No se desactiva en el rollback: la activación es la conducta deseada del negocio.
    }
};
