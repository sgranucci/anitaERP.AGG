<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\Tiendanube\TiendanubeUsoCuentacajaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: completa uso TIENDA NUBE con cuentas del Excel/Facturante (610 GO CUOTAS, 4781/5 transferencia).
 */
return new class extends Migration
{
    private const CODIGOS = [
        '610',      // GO CUOTAS
        '4781/5',   // FRANCES SUC. LUGANO (transferencia / offline)
        '11310112', // NUBE BOA (por si faltaba el código Anita)
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('usocuentacaja') || ! Schema::hasTable('cuentacaja_usocuentacaja')) {
            return;
        }

        $usoId = TiendanubeUsoCuentacajaSupport::asegurarId();

        $cuentaIds = DB::table('cuentacaja')
            ->whereIn('codigo', self::CODIGOS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $orden = (int) (DB::table('cuentacaja_usocuentacaja')
            ->where('usocuentacaja_id', $usoId)
            ->max('orden') ?? 0);

        foreach ($cuentaIds as $cuentaId) {
            $existe = DB::table('cuentacaja_usocuentacaja')
                ->where('usocuentacaja_id', $usoId)
                ->where('cuentacaja_id', $cuentaId)
                ->exists();
            if ($existe) {
                continue;
            }
            $orden++;
            DB::table('cuentacaja_usocuentacaja')->insert([
                'cuentacaja_id' => $cuentaId,
                'usocuentacaja_id' => $usoId,
                'orden' => $orden,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('usocuentacaja') || ! Schema::hasTable('cuentacaja_usocuentacaja')) {
            return;
        }

        $usoId = (int) (DB::table('usocuentacaja')
            ->where('nombre', TiendanubeUsoCuentacajaSupport::NOMBRE_DEFAULT)
            ->value('id') ?? 0);
        if ($usoId <= 0) {
            return;
        }

        $cuentaIds = DB::table('cuentacaja')
            ->whereIn('codigo', self::CODIGOS)
            ->pluck('id');
        if ($cuentaIds->isEmpty()) {
            return;
        }

        DB::table('cuentacaja_usocuentacaja')
            ->where('usocuentacaja_id', $usoId)
            ->whereIn('cuentacaja_id', $cuentaIds)
            ->delete();
    }
};
