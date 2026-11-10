<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: «Avisos de auditoría» compartía orden=10 con «Flash» bajo Tesorería Crown,
 * y el aside a veces los invertía. Deja el aviso después del Flash.
 */
return new class extends Migration
{
    private const URL_PADRE = '#tesoreria-crown';

    private const URL_AVISO = 'caja/tesoreria-crown/aviso';

    private const URL_FLASH = '#flash';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $padreId = (int) (DB::table('menu')->where('url', self::URL_PADRE)->value('id') ?? 0);
        if ($padreId <= 0) {
            return;
        }

        $flashId = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('url', self::URL_FLASH)
            ->value('id') ?? 0);
        $avisoId = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('url', self::URL_AVISO)
            ->value('id') ?? 0);

        if ($avisoId <= 0) {
            return;
        }

        $ordenFlash = $flashId > 0
            ? (int) (DB::table('menu')->where('id', $flashId)->value('orden') ?? 0)
            : (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0);

        $ordenAviso = $ordenFlash + 1;

        DB::table('menu')->where('id', $avisoId)->update([
            'orden' => $ordenAviso,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $padreId = (int) (DB::table('menu')->where('url', self::URL_PADRE)->value('id') ?? 0);
        if ($padreId <= 0) {
            return;
        }

        $flashOrden = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('url', self::URL_FLASH)
            ->value('orden') ?? 0);

        if ($flashOrden <= 0) {
            return;
        }

        DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('url', self::URL_AVISO)
            ->update([
                'orden' => $flashOrden,
                'updated_at' => now(),
            ]);
    }
};
