<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INTERFORMING: actividad AFIP 222090 (principal del CUIT 30-55251927-9)
 * y vínculo a PV electrónicos 00004 / 00005 para el modal de facturación.
 */
return new class extends Migration
{
    private const CODIGO_ARCA = '222090';

    private const NOMBRE = 'Fabricación de productos plásticos en formas básicas y artículos de plástico n.c.p., excepto muebles';

    /** @var list<string> */
    private const PV_CODIGOS = ['00004', '00005'];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        if (! Schema::hasTable('actividad_arca') || ! Schema::hasTable('puntoventa')) {
            return;
        }

        $ahora = now();
        $actividadId = (int) (DB::table('actividad_arca')
            ->where('codigoarca', self::CODIGO_ARCA)
            ->value('id') ?? 0);

        if ($actividadId <= 0) {
            $actividadId = (int) DB::table('actividad_arca')->insertGetId([
                'nombre' => self::NOMBRE,
                'codigoarca' => self::CODIGO_ARCA,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        } else {
            DB::table('actividad_arca')->where('id', $actividadId)->update([
                'nombre' => self::NOMBRE,
                'updated_at' => $ahora,
            ]);
        }

        if ($actividadId <= 0) {
            return;
        }

        DB::table('puntoventa')
            ->whereIn('codigo', self::PV_CODIGOS)
            ->whereNull('actividad_arca_id')
            ->update([
                'actividad_arca_id' => $actividadId,
                'updated_at' => $ahora,
            ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        if (! Schema::hasTable('actividad_arca') || ! Schema::hasTable('puntoventa')) {
            return;
        }

        $actividadId = (int) (DB::table('actividad_arca')
            ->where('codigoarca', self::CODIGO_ARCA)
            ->value('id') ?? 0);

        if ($actividadId <= 0) {
            return;
        }

        DB::table('puntoventa')
            ->where('actividad_arca_id', $actividadId)
            ->whereIn('codigo', self::PV_CODIGOS)
            ->update([
                'actividad_arca_id' => null,
                'updated_at' => now(),
            ]);

        $enUso = DB::table('puntoventa')->where('actividad_arca_id', $actividadId)->exists()
            || (Schema::hasTable('venta') && DB::table('venta')->where('actividad_arca_id', $actividadId)->exists());

        if (! $enUso) {
            DB::table('actividad_arca')->where('id', $actividadId)->delete();
        }
    }
};
