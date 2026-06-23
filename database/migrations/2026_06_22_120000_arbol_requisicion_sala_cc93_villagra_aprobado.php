<?php

use App\Models\Configuracion\Arbolaprobacion_Nivel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EMPRESA_ID = 1;

    private const TIPO_ARBOL = 'Requisiciones de sala';

    private const CC_TECNICA = '93';

    private const USUARIO_NIVEL_1 = 'evillagra';

    private const ESTADO_AL_APROBAR = 'APROBADO';

    /**
     * CC 93 Técnica: primer nivel Ezequiel Villagra → APROBADO (mail + link portal).
     */
    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $arbol = DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->where('empresa_id', self::EMPRESA_ID)
            ->whereNull('deleted_at')
            ->first();

        if (! $arbol) {
            return;
        }

        $centroCostoId = (int) DB::table('centrocosto')->where('codigo', self::CC_TECNICA)->value('id');
        if ($centroCostoId <= 0) {
            return;
        }

        $usuarioId = (int) DB::table('usuario')->where('usuario', self::USUARIO_NIVEL_1)->value('id');
        if ($usuarioId <= 0) {
            throw new \RuntimeException('Árbol RS CC 93: usuario '.self::USUARIO_NIVEL_1.' inexistente.');
        }

        $monedaPesos = (int) DB::table('moneda')->where('nombre', 'PESOS')->value('id');
        if (! $monedaPesos) {
            throw new \RuntimeException('No se encontró la moneda PESOS.');
        }

        $now = now()->toDateTimeString();
        DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', $arbol->id)
            ->where('centrocosto_id', $centroCostoId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);

        Arbolaprobacion_Nivel::create([
            'arbolaprobacion_id' => $arbol->id,
            'centrocosto_id' => $centroCostoId,
            'nivel' => 1,
            'usuario_id' => $usuarioId,
            'desdemonto' => 0,
            'hastamonto' => 999999999,
            'moneda_id' => $monedaPesos,
            'documento_estado_al_aprobar' => self::ESTADO_AL_APROBAR,
        ]);
    }

    public function down(): void
    {
        // Restauración manual: volver a correr la migración matriz original si hiciera falta.
    }
};
