<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activa doble aprobación por CC en el árbol de Requisiciones AGG:
 * - Área (firmantes con desdemonto &lt; 5.000.000) en nivel menor.
 * - Umbrales altos (desdemonto &gt;= 5.000.000) en niveles siguientes.
 * - CC solo con firmante de monto alto (sin área) quedan doble_aprobacion = N.
 *
 * Por debajo del umbral alto el matching sigue siendo exclusivo (sin cambio operativo &lt; 5M).
 */
return new class extends Migration
{
    private const EMPRESA_ID = 1;

    private const UMBRAL_ALTO = 5000000.0;

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        if (! Schema::hasTable('arbolaprobacion')
            || ! Schema::hasTable('arbolaprobacion_nivel')
            || ! Schema::hasColumn('arbolaprobacion_nivel', 'doble_aprobacion')) {
            return;
        }

        $arbol = DB::table('arbolaprobacion')
            ->where('tipoarbol', 'Requisiciones')
            ->where('empresa_id', self::EMPRESA_ID)
            ->whereNull('deleted_at')
            ->first();

        if (! $arbol) {
            return;
        }

        $filas = DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', $arbol->id)
            ->whereNull('deleted_at')
            ->orderBy('centrocosto_id')
            ->orderBy('nivel')
            ->orderBy('id')
            ->get();

        if ($filas->isEmpty()) {
            return;
        }

        $now = now();
        $porCc = $filas->groupBy('centrocosto_id');

        foreach ($porCc as $centrocostoId => $nivelesCc) {
            $nivel1 = $nivelesCc->filter(static function ($n) {
                return (int) $n->nivel === 1
                    || strtoupper(trim((string) ($n->documento_estado_al_aprobar ?? ''))) === 'EN COMPRAS';
            });
            $resto = $nivelesCc->reject(static function ($n) use ($nivel1) {
                return $nivel1->contains('id', $n->id);
            });

            $area = $resto->filter(static function ($n) {
                return (float) ($n->desdemonto ?? 0) < self::UMBRAL_ALTO;
            });
            $altos = $resto->filter(static function ($n) {
                return (float) ($n->desdemonto ?? 0) >= self::UMBRAL_ALTO;
            });

            if ($area->isEmpty() || $altos->isEmpty()) {
                foreach ($nivelesCc as $n) {
                    DB::table('arbolaprobacion_nivel')
                        ->where('id', $n->id)
                        ->update([
                            'doble_aprobacion' => 'N',
                            'updated_at' => $now,
                        ]);
                }

                continue;
            }

            foreach ($nivel1 as $n) {
                DB::table('arbolaprobacion_nivel')
                    ->where('id', $n->id)
                    ->update([
                        'nivel' => 1,
                        'doble_aprobacion' => 'S',
                        'updated_at' => $now,
                    ]);
            }

            foreach ($area as $n) {
                DB::table('arbolaprobacion_nivel')
                    ->where('id', $n->id)
                    ->update([
                        'nivel' => 2,
                        'doble_aprobacion' => 'S',
                        'updated_at' => $now,
                    ]);
            }

            $gruposAlto = $altos->groupBy(static function ($n) {
                return (string) ((float) ($n->desdemonto ?? 0));
            })->sortKeysUsing(static function ($a, $b) {
                return ((float) $a) <=> ((float) $b);
            });

            $nivelAlto = 3;
            foreach ($gruposAlto as $grupo) {
                foreach ($grupo as $n) {
                    DB::table('arbolaprobacion_nivel')
                        ->where('id', $n->id)
                        ->update([
                            'nivel' => $nivelAlto,
                            'doble_aprobacion' => 'S',
                            'updated_at' => $now,
                        ]);
                }
                $nivelAlto++;
            }
        }
    }

    public function down(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        if (! Schema::hasTable('arbolaprobacion')
            || ! Schema::hasTable('arbolaprobacion_nivel')
            || ! Schema::hasColumn('arbolaprobacion_nivel', 'doble_aprobacion')) {
            return;
        }

        $arbolIds = DB::table('arbolaprobacion')
            ->where('tipoarbol', 'Requisiciones')
            ->where('empresa_id', self::EMPRESA_ID)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($arbolIds->isEmpty()) {
            return;
        }

        DB::table('arbolaprobacion_nivel')
            ->whereIn('arbolaprobacion_id', $arbolIds)
            ->whereNull('deleted_at')
            ->update([
                'doble_aprobacion' => 'N',
                'updated_at' => now(),
            ]);
    }
};
