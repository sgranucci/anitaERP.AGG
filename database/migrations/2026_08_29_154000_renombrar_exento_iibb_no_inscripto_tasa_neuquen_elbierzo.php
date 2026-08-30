<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anita ibrxprov: la 3ª columna (ibrxp_porc_exento / p_no_inscr) no es “no percibir”.
 * El catálogo se llamaba Exento; el sync Anita '1' cae ahí. Neuquén cobra 2 %.
 */
return new class extends Migration
{
    private const NOMBRE_ANTERIOR = 'Exento';

    private const NOMBRE_NUEVO = 'No inscripto';

    private const JUR_NEUQUEN = 915;

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $now = now();
        $condicionId = $this->renombrarCondicion($now);
        if ($condicionId <= 0) {
            return;
        }

        $provinciaId = (int) (DB::table('provincia')
            ->where('jurisdiccion', self::JUR_NEUQUEN)
            ->value('id') ?? 0);
        if ($provinciaId <= 0) {
            return;
        }

        $usuarioId = (int) (DB::table('usuario')->orderBy('id')->value('id') ?? 1);
        $fila = DB::table('provincia_tasaiibb')
            ->where('provincia_id', $provinciaId)
            ->where('condicioniibb_id', $condicionId)
            ->first();

        $payload = [
            'tasa' => 2.0,
            'minimoneto' => 0,
            'minimopercepcion' => 50,
            'updated_at' => $now,
        ];
        if ($fila) {
            DB::table('provincia_tasaiibb')->where('id', $fila->id)->update($payload);
        } else {
            DB::table('provincia_tasaiibb')->insert(array_merge($payload, [
                'provincia_id' => $provinciaId,
                'condicioniibb_id' => $condicionId,
                'creousuario_id' => $usuarioId,
                'created_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $condicionId = (int) (DB::table('condicionIIBB')
            ->where('nombre', self::NOMBRE_NUEVO)
            ->value('id') ?? 0);
        if ($condicionId > 0) {
            DB::table('condicionIIBB')
                ->where('id', $condicionId)
                ->update(['nombre' => self::NOMBRE_ANTERIOR, 'updated_at' => now()]);
        }

        $provinciaId = (int) (DB::table('provincia')
            ->where('jurisdiccion', self::JUR_NEUQUEN)
            ->value('id') ?? 0);
        if ($provinciaId > 0 && $condicionId > 0) {
            DB::table('provincia_tasaiibb')
                ->where('provincia_id', $provinciaId)
                ->where('condicioniibb_id', $condicionId)
                ->delete();
        }
    }

    private function renombrarCondicion($now): int
    {
        $condicion = DB::table('condicionIIBB')
            ->whereIn('nombre', [self::NOMBRE_ANTERIOR, self::NOMBRE_NUEVO])
            ->orderByRaw("CASE WHEN nombre = ? THEN 0 ELSE 1 END", [self::NOMBRE_ANTERIOR])
            ->first();
        if ($condicion === null) {
            return 0;
        }
        if ($condicion->nombre === self::NOMBRE_ANTERIOR) {
            DB::table('condicionIIBB')
                ->where('id', $condicion->id)
                ->update(['nombre' => self::NOMBRE_NUEVO, 'updated_at' => $now]);
        }

        return (int) $condicion->id;
    }
};
