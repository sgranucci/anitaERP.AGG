<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repara árboles de Requisiciones donde el firmante de umbral alto (p. ej. Beta ≥ 5M)
 * quedó en el mismo nivel que el área (Angel/Erika), y el área cierra con APROBADA.
 *
 * Efecto:
 * - Área (desdemonto &lt; 5M, con usuario) → nivel 2, doble=S, estado EN ARBOL APROBACION
 * - Umbrales altos → nivel 3+ (por desdemonto), doble=S, mantienen APROBADA
 * - Nivel 1 (EN COMPRAS / sin usuario de firma) se conserva
 *
 * Así, con monto homogeneizado a PES (p. ej. USD×cotización ≥ 5M), Angel firma primero
 * y recién después avanza a Beta.
 */
return new class extends Migration
{
    private const UMBRAL_ALTO = 5000000.0;

    private const ESTADO_INTERMEDIO = 'EN ARBOL APROBACION';

    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion')
            || ! Schema::hasTable('arbolaprobacion_nivel')
            || ! Schema::hasColumn('arbolaprobacion_nivel', 'doble_aprobacion')) {
            return;
        }

        $arbolIds = DB::table('arbolaprobacion')
            ->where('tipoarbol', 'Requisiciones')
            ->when(Schema::hasColumn('arbolaprobacion', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->pluck('id');

        if ($arbolIds->isEmpty()) {
            return;
        }

        $query = DB::table('arbolaprobacion_nivel')
            ->whereIn('arbolaprobacion_id', $arbolIds);
        if (Schema::hasColumn('arbolaprobacion_nivel', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $filas = $query
            ->orderBy('arbolaprobacion_id')
            ->orderBy('centrocosto_id')
            ->orderBy('nivel')
            ->orderBy('id')
            ->get();

        if ($filas->isEmpty()) {
            return;
        }

        $now = now();
        $porArbolCc = $filas->groupBy(static function ($n) {
            return (int) $n->arbolaprobacion_id.'|'.(string) ($n->centrocosto_id ?? 'null');
        });

        foreach ($porArbolCc as $nivelesCc) {
            $conUsuario = $nivelesCc->filter(static fn ($n) => ! empty($n->usuario_id));
            $area = $conUsuario->filter(static fn ($n) => (float) ($n->desdemonto ?? 0) < self::UMBRAL_ALTO);
            $altos = $conUsuario->filter(static fn ($n) => (float) ($n->desdemonto ?? 0) >= self::UMBRAL_ALTO);

            if ($area->isEmpty() || $altos->isEmpty()) {
                continue;
            }

            $nivel1 = $nivelesCc->filter(static function ($n) {
                return (int) $n->nivel === 1
                    || strtoupper(trim((string) ($n->documento_estado_al_aprobar ?? ''))) === 'EN COMPRAS';
            });

            foreach ($nivel1 as $n) {
                DB::table('arbolaprobacion_nivel')->where('id', $n->id)->update([
                    'nivel' => 1,
                    'doble_aprobacion' => 'S',
                    'updated_at' => $now,
                ]);
            }

            foreach ($area as $n) {
                if ($nivel1->contains('id', $n->id)) {
                    continue;
                }
                DB::table('arbolaprobacion_nivel')->where('id', $n->id)->update([
                    'nivel' => 2,
                    'doble_aprobacion' => 'S',
                    'documento_estado_al_aprobar' => self::ESTADO_INTERMEDIO,
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
                    if ($nivel1->contains('id', $n->id)) {
                        continue;
                    }
                    DB::table('arbolaprobacion_nivel')->where('id', $n->id)->update([
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
        // No se revierte: la config previa dejaba umbrales altos en el mismo nivel que el área.
    }
};
