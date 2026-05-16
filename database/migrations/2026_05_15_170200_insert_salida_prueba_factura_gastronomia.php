<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $nombre = 'Prueba impresión factura gastronomía';

        if (DB::table('salida')->where('nombre', $nombre)->exists()) {
            return;
        }

        DB::table('salida')->insert([
            'nombre' => $nombre,
            'ubicacion' => 'tmp',
            /** %s = ruta del PDF (mismo patrón que ArticuloController::sprintf salida) */
            'comando' => 'cp "%s" /tmp/anita_prueba_factura_gastronomia.pdf && echo "Impresión prueba gastronomía OK -> /tmp/anita_prueba_factura_gastronomia.pdf"',
        ]);
    }

    public function down(): void
    {
        DB::table('salida')->where('nombre', 'Prueba impresión factura gastronomía')->delete();
    }
};
