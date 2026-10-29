<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\SolicitudRetiroCrownPreferencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recuerda la secuencia Interbanking y el CBU origen entre generaciones.
 */
final class SolicitudRetiroCrownPreferenciaSupport
{
    /**
     * @return array{ultima_secuencia:int, cbu_origen:string, secuencia_sugerida:int}
     */
    public static function leer(): array
    {
        $vacio = ['ultima_secuencia' => 0, 'cbu_origen' => '', 'secuencia_sugerida' => 1];
        if (! Schema::hasTable('solicitud_retiro_crown_preferencia')) {
            return $vacio;
        }

        $row = SolicitudRetiroCrownPreferencia::query()->orderBy('id')->first();
        if ($row === null) {
            return $vacio;
        }

        $ultima = max(0, (int) $row->ultima_secuencia);

        return [
            'ultima_secuencia' => $ultima,
            'cbu_origen' => CbuSupport::normalizar((string) $row->cbu_origen),
            'secuencia_sugerida' => $ultima + 1,
        ];
    }

    public static function registrar(int $secuencia, string $cbu): void
    {
        if (! Schema::hasTable('solicitud_retiro_crown_preferencia')) {
            return;
        }

        DB::transaction(static function () use ($secuencia, $cbu): void {
            $row = SolicitudRetiroCrownPreferencia::query()->orderBy('id')->lockForUpdate()->first();
            if ($row === null) {
                $row = new SolicitudRetiroCrownPreferencia;
            }
            $row->ultima_secuencia = max(0, $secuencia);
            $row->cbu_origen = CbuSupport::normalizar($cbu);
            $row->save();
        });
    }
}
