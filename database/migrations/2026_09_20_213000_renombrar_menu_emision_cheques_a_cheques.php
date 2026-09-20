<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú caja/cheque: etiqueta operativa «Cheques» (antes «Emisión de cheques»).
 */
return new class extends Migration
{
    private const URL = 'caja/cheque';

    private const NOMBRE_NUEVO = 'Cheques';

    private const NOMBRE_ANTERIOR = 'Emisión de cheques';

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
