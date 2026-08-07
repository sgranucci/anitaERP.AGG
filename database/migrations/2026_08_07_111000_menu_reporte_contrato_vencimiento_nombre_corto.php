<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Contratos y OC abiertas por vencer" era el ítem de menú más largo del sistema
 * (34 caracteres) y se cortaba en dos renglones en el sidebar. El título completo
 * se conserva en la pantalla del reporte y en el permiso.
 */
return new class extends Migration
{
    private const URL = 'compras/contrato-vencimiento-reporte';

    private const NOMBRE_CORTO = 'Contratos por vencer';

    private const NOMBRE_LARGO = 'Contratos y OC abiertas por vencer';

    public function up(): void
    {
        $this->renombrar(self::NOMBRE_CORTO);
    }

    public function down(): void
    {
        $this->renombrar(self::NOMBRE_LARGO);
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
