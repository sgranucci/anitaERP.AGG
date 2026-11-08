<?php

namespace App\Support\Caja\Flash;

use Illuminate\Http\Request;

final class FlashCrownListadoFiltros
{
    /**
     * @return array{fecha: ?string, sala_id: int, consultar: bool}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $fecha = trim((string) $request->input('fecha', ''));
        if ($fecha === '') {
            $fecha = now()->subDay()->toDateString();
        }
        try {
            $fecha = \Carbon\Carbon::parse($fecha)->toDateString();
        } catch (\Throwable) {
            $fecha = now()->subDay()->toDateString();
        }

        return [
            'fecha' => $fecha,
            'sala_id' => (int) $request->input('sala_id', 0),
            'consultar' => $request->boolean('consultar') || $request->filled('fecha'),
        ];
    }

    /**
     * @param  array{fecha: ?string, sala_id: int, consultar: bool}  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'fecha' => (string) ($filtros['fecha'] ?? ''),
            'consultar' => 1,
        ];
        if ((int) ($filtros['sala_id'] ?? 0) > 0) {
            $out['sala_id'] = (int) $filtros['sala_id'];
        }

        return $out;
    }
}
