<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Etiqueta definitiva del reporte en el menú, elegida por el área de Compras.
 * El título completo ("Contratos y OC abiertas por vencer") sigue en la pantalla,
 * en el PDF/Excel y en el nombre del permiso.
 */
return new class extends Migration
{
    private const URL = 'compras/contrato-vencimiento-reporte';

    private const NOMBRE_NUEVO = 'Contratos y OC abiertas';

    private const NOMBRE_ANTERIOR = 'Contratos por vencer';

    public function up(): void
    {
        $this->renombrar(self::NOMBRE_NUEVO);
    }

    public function down(): void
    {
        $this->renombrar(self::NOMBRE_ANTERIOR);
    }

    private function renombrar(string $nombre): void
    {
        $afectadas = DB::table('menu')
            ->where('url', self::URL)
            ->update(['nombre' => $nombre, 'updated_at' => now()]);

        if ($afectadas > 0) {
            SuitecrmPermiso::flushCachePermisos();
        }
    }
};
