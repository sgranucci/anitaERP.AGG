<?php

namespace App\Support\Caja;

use Illuminate\Http\Request;

/**
 * Filtros del aging de cheques en cartera.
 */
final class ChequeAgingListadoFiltros
{
    /**
     * @return array{empresa_id: ?int, bucket: string, texto: string, hasta: string, para_depositar: bool}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        $bucket = trim((string) $request->input('bucket', ''));
        if ($bucket !== '' && ! in_array($bucket, ChequeCarteraAgingSupport::BUCKETS, true)) {
            $bucket = '';
        }

        $hasta = trim((string) $request->input('hasta', ''));
        if ($hasta !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = '';
        }
        if ($hasta === '') {
            $hasta = date('Y-m-d');
        }

        $paraDepositar = $request->boolean('para_depositar');
        if ($paraDepositar) {
            // Vista depósito: no mezcla con bucket de aging.
            $bucket = '';
        }

        return [
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'bucket' => $bucket,
            'texto' => trim((string) $request->input('texto', '')),
            'hasta' => $hasta,
            'para_depositar' => $paraDepositar,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $out['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (($filtros['bucket'] ?? '') !== '') {
            $out['bucket'] = (string) $filtros['bucket'];
        }
        if (($filtros['texto'] ?? '') !== '') {
            $out['texto'] = (string) $filtros['texto'];
        }
        if (($filtros['hasta'] ?? '') !== '') {
            $out['hasta'] = (string) $filtros['hasta'];
        }
        if (! empty($filtros['para_depositar'])) {
            $out['para_depositar'] = 1;
        }

        return $out;
    }
}
