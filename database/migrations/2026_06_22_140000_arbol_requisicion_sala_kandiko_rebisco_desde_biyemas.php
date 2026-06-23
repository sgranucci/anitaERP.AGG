<?php

use App\Models\Configuracion\Arbolaprobacion_Nivel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EMPRESA_ORIGEN = 1;

    /** @var list<int> */
    private const EMPRESAS_DESTINO = [2, 3];

    private const TIPO_ARBOL = 'Requisiciones de sala';

    /**
     * Replica el árbol de requisiciones de sala de BIYEMAS en KANDIKO y REBISCO.
     */
    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $arbolOrigen = DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->where('empresa_id', self::EMPRESA_ORIGEN)
            ->whereNull('deleted_at')
            ->first();

        if (! $arbolOrigen) {
            throw new \RuntimeException('No se encontró el árbol de requisiciones de sala de BIYEMAS (empresa '.self::EMPRESA_ORIGEN.').');
        }

        $nivelesOrigen = DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', $arbolOrigen->id)
            ->whereNull('deleted_at')
            ->orderBy('centrocosto_id')
            ->orderBy('nivel')
            ->get();

        if ($nivelesOrigen->isEmpty()) {
            throw new \RuntimeException('El árbol de requisiciones de sala de BIYEMAS no tiene niveles activos.');
        }

        $now = now()->toDateTimeString();

        foreach (self::EMPRESAS_DESTINO as $empresaId) {
            $arbolDestino = DB::table('arbolaprobacion')
                ->where('tipoarbol', self::TIPO_ARBOL)
                ->where('empresa_id', $empresaId)
                ->whereNull('deleted_at')
                ->first();

            if (! $arbolDestino) {
                $arbolDestinoId = (int) DB::table('arbolaprobacion')->insertGetId([
                    'nombre' => $arbolOrigen->nombre,
                    'tipoarbol' => $arbolOrigen->tipoarbol,
                    'empresa_id' => $empresaId,
                    'recordatorio' => $arbolOrigen->recordatorio,
                    'diasinrespuesta' => $arbolOrigen->diasinrespuesta,
                    'diavencimientorecordatorio' => $arbolOrigen->diavencimientorecordatorio,
                    'estado' => $arbolOrigen->estado,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $arbolDestinoId = (int) $arbolDestino->id;
                DB::table('arbolaprobacion')
                    ->where('id', $arbolDestinoId)
                    ->update([
                        'nombre' => $arbolOrigen->nombre,
                        'recordatorio' => $arbolOrigen->recordatorio,
                        'diasinrespuesta' => $arbolOrigen->diasinrespuesta,
                        'diavencimientorecordatorio' => $arbolOrigen->diavencimientorecordatorio,
                        'estado' => $arbolOrigen->estado,
                        'updated_at' => $now,
                    ]);
            }

            DB::table('arbolaprobacion_nivel')
                ->where('arbolaprobacion_id', $arbolDestinoId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            foreach ($nivelesOrigen as $nivel) {
                Arbolaprobacion_Nivel::create([
                    'arbolaprobacion_id' => $arbolDestinoId,
                    'centrocosto_id' => $nivel->centrocosto_id,
                    'nivel' => $nivel->nivel,
                    'usuario_id' => $nivel->usuario_id,
                    'desdemonto' => $nivel->desdemonto,
                    'hastamonto' => $nivel->hastamonto,
                    'moneda_id' => $nivel->moneda_id,
                    'documento_estado_al_aprobar' => $nivel->documento_estado_al_aprobar,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $now = now()->toDateTimeString();

        foreach (self::EMPRESAS_DESTINO as $empresaId) {
            $arbol = DB::table('arbolaprobacion')
                ->where('tipoarbol', self::TIPO_ARBOL)
                ->where('empresa_id', $empresaId)
                ->whereNull('deleted_at')
                ->first();

            if (! $arbol) {
                continue;
            }

            DB::table('arbolaprobacion_nivel')
                ->where('arbolaprobacion_id', $arbol->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            DB::table('arbolaprobacion')
                ->where('id', $arbol->id)
                ->update(['deleted_at' => $now, 'updated_at' => $now]);
        }
    }
};
