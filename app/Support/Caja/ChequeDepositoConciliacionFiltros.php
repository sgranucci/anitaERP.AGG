<?php

namespace App\Support\Caja;

use Illuminate\Http\Request;

final class ChequeDepositoConciliacionFiltros
{
    /**
     * @return array{empresa_id:?int, cuentacaja_id:?int, estado:string, boleta:string, texto:string, desde:string, hasta:string}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        $cuentaId = (int) $request->input('cuentacaja_id', 0);
        $estado = trim((string) $request->input('estado', ''));
        if ($estado !== '' && ! in_array($estado, ChequeDepositoConciliacionSupport::ESTADOS, true)) {
            $estado = '';
        }

        $desde = trim((string) $request->input('desde', ''));
        if ($desde !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = '';
        }
        $hasta = trim((string) $request->input('hasta', ''));
        if ($hasta !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = '';
        }

        return [
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'cuentacaja_id' => $cuentaId > 0 ? $cuentaId : null,
            'estado' => $estado,
            'boleta' => trim((string) $request->input('boleta', '')),
            'texto' => trim((string) $request->input('texto', '')),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['empresa_id', 'cuentacaja_id'] as $k) {
            if ((int) ($filtros[$k] ?? 0) > 0) {
                $out[$k] = (int) $filtros[$k];
            }
        }
        foreach (['estado', 'boleta', 'texto', 'desde', 'hasta'] as $k) {
            if (($filtros[$k] ?? '') !== '') {
                $out[$k] = (string) $filtros[$k];
            }
        }

        return $out;
    }
}
