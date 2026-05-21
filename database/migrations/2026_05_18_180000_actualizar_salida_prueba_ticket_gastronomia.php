<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $script = base_path('bin/gastronomia-print-ticket.sh');
        $comandoEjemplo = $script.' "%s" 127.0.0.1 9100';

        DB::table('salida')
            ->where('nombre', 'Prueba impresión factura gastronomía')
            ->update([
                'comando' => $comandoEjemplo,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('salida')
            ->where('nombre', 'Prueba impresión factura gastronomía')
            ->update([
                'comando' => 'cp "%s" /tmp/anita_prueba_factura_gastronomia.pdf && echo "Impresión prueba gastronomía OK -> /tmp/anita_prueba_factura_gastronomia.pdf"',
                'updated_at' => now(),
            ]);
    }
};
