<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $destino = storage_path('app/gastronomia/tickets/ultimo-ticket.bin');
        $comando = 'cp "%s" '.$destino.' && echo "OK -> '.$destino.'"';

        DB::table('salida')
            ->where('nombre', 'Prueba impresión factura gastronomía')
            ->update([
                'comando' => $comando,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('salida')
            ->where('nombre', 'Prueba impresión factura gastronomía')
            ->update([
                'comando' => 'cp "%s" /tmp/anita_prueba_factura_gastronomia.bin && echo "OK -> /tmp/anita_prueba_factura_gastronomia.bin"',
                'updated_at' => now(),
            ]);
    }
};
