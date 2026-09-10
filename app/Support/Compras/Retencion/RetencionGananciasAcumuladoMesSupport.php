<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Compras\Retencionganancia;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Acumulados mensuales de Ganancias (RG 830 / ARCA) para forma de cálculo S/O.
 *
 * Período: mes calendario del pago. Si el régimen tiene cantidadperiodoacumula > 1,
 * mira hacia atrás esa cantidad de meses (inclusive el mes del pago).
 *
 * Neto previo: suma de detalle_calculo.neto_pago (fallback base_calculo).
 * Retenido previo: suma de importe de retenciones G del período.
 *
 * Respaldo híbrido: retmov Anita (clave tipo|letra|sucursal|nro|empresa) para OPs
 * que no están en el ERP. Si Anita no responde, queda solo el acumulado ERP.
 */
final class RetencionGananciasAcumuladoMesSupport
{
    /** @var array<int, int> */
    private array $cacheEmpresaAnita = [];

    public function __construct(
        private readonly RetencionGananciasAcumuladoAnitaRespaldoSupport $anitaRespaldo = new RetencionGananciasAcumuladoAnitaRespaldoSupport,
    ) {
    }

    /**
     * @return array{
     *     neto: float,
     *     retenido: float,
     *     desde: string,
     *     hasta: string,
     *     pagos: int,
     *     pagos_anita: int,
     *     detalle_pagos: list<array{pagoproveedor_id: int, fecha: string|null, neto: float, retenido: float, nro: string|null, origen?: string}>
     * }
     */
    public function acumular(
        int $proveedorId,
        string $fechaPago,
        ?int $empresaId = null,
        ?int $retenciongananciaId = null,
        ?int $excluirPagoproveedorId = null,
        ?int $cantidadPeriodos = null,
    ): array {
        $fecha = Carbon::parse($fechaPago)->startOfDay();
        $periodos = $cantidadPeriodos;
        if ($periodos === null && $retenciongananciaId) {
            $periodos = (int) (Retencionganancia::query()->whereKey($retenciongananciaId)->value('cantidadperiodoacumula') ?? 0);
        }
        $periodos = max(1, (int) ($periodos ?: 1));

        $hasta = $fecha->copy()->endOfMonth();
        $desde = $fecha->copy()->startOfMonth()->subMonths($periodos - 1);

        $query = Pagoproveedor_Retencion::query()
            ->select([
                'pagoproveedor_retencion.id',
                'pagoproveedor_retencion.pagoproveedor_id',
                'pagoproveedor_retencion.importe',
                'pagoproveedor_retencion.base_calculo',
                'pagoproveedor_retencion.detalle_calculo',
                'pagoproveedor_retencion.retencionganancia_id',
                'pagoproveedor_retencion.nro_certificado',
                'pp.fecha as pp_fecha',
                'pp.empresa_id as pp_empresa',
                'pp.tipocomprobante as pp_tipo',
                'pp.letra as pp_letra',
                'pp.sucursal as pp_sucursal',
                'pp.numerotransaccion as pp_numero',
            ])
            ->join('pagoproveedor as pp', 'pp.id', '=', 'pagoproveedor_retencion.pagoproveedor_id')
            ->where('pagoproveedor_retencion.tiporetencion', Pagoproveedor_Retencion::TIPO_GANANCIAS)
            ->where('pp.proveedor_id', $proveedorId)
            ->whereBetween('pp.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('pp.estado', '!=', 'ANULADA')
            ->where('pagoproveedor_retencion.importe', '>', 0);

        if ($empresaId && $empresaId > 0) {
            $query->where('pp.empresa_id', $empresaId);
        }
        if ($retenciongananciaId && $retenciongananciaId > 0) {
            $query->where('pagoproveedor_retencion.retencionganancia_id', $retenciongananciaId);
        }
        if ($excluirPagoproveedorId && $excluirPagoproveedorId > 0) {
            $query->where('pp.id', '!=', $excluirPagoproveedorId);
        }

        // Solo pagos anteriores o del mismo día con id menor (estable al reeditar).
        $query->where(function ($q) use ($fecha, $excluirPagoproveedorId) {
            $q->where('pp.fecha', '<', $fecha->toDateString());
            if ($excluirPagoproveedorId && $excluirPagoproveedorId > 0) {
                $q->orWhere(function ($q2) use ($fecha, $excluirPagoproveedorId) {
                    $q2->whereDate('pp.fecha', $fecha->toDateString())
                        ->where('pp.id', '<', $excluirPagoproveedorId);
                });
            } else {
                $q->orWhereDate('pp.fecha', $fecha->toDateString());
            }
        });

        $filas = $query->orderBy('pp.fecha')->orderBy('pp.id')->get();
        $neto = 0.0;
        $retenido = 0.0;
        $porPago = [];
        $clavesOcupadas = [];
        $certificadosOcupados = [];

        foreach ($filas as $fila) {
            $detalle = is_array($fila->detalle_calculo) ? $fila->detalle_calculo : [];
            $netoPago = (float) ($detalle['neto_pago'] ?? 0);
            if ($netoPago <= 0) {
                $netoPago = (float) ($fila->base_calculo ?? 0);
            }
            $importe = (float) $fila->importe;
            $neto = round($neto + $netoPago, 2);
            $retenido = round($retenido + $importe, 2);
            $pagoId = (int) $fila->pagoproveedor_id;
            $letra = (string) ($fila->pp_letra ?? '');
            $suc = (int) ($fila->pp_sucursal ?? 0);
            $num = (int) ($fila->pp_numero ?? 0);
            $tipo = (string) ($fila->pp_tipo ?? 'OPP');
            $empresaAnita = $this->empresaAnita((int) ($fila->pp_empresa ?? 0), $empresaId);
            foreach (RetencionGananciasAcumuladoAnitaClaveSupport::clavesOcupacionDesdeErp(
                $tipo, $letra, $suc > 0 ? $suc : 1, $num, $empresaAnita
            ) as $clave) {
                $clavesOcupadas[$clave] = true;
            }
            $cert = (int) preg_replace('/\D+/', '', (string) ($fila->nro_certificado ?? '0'));
            if ($cert > 0) {
                $certificadosOcupados[$cert] = true;
            }
            if (! isset($porPago[$pagoId])) {
                $nro = null;
                if ($letra !== '' || $suc > 0 || $num > 0) {
                    $nro = RetencionGananciasAcumuladoAnitaClaveSupport::etiqueta($tipo, $letra, $suc, $num);
                }
                $porPago[$pagoId] = [
                    'pagoproveedor_id' => $pagoId,
                    'fecha' => $fila->pp_fecha ? (string) $fila->pp_fecha : null,
                    'neto' => 0.0,
                    'retenido' => 0.0,
                    'nro' => $nro,
                    'origen' => 'erp',
                ];
            }
            $porPago[$pagoId]['neto'] = round($porPago[$pagoId]['neto'] + $netoPago, 2);
            $porPago[$pagoId]['retenido'] = round($porPago[$pagoId]['retenido'] + $importe, 2);
        }

        $this->ocuparClavesPagoExcluido($clavesOcupadas, $excluirPagoproveedorId, $empresaId);
        $this->ocuparClavesPagosAnulados(
            $clavesOcupadas,
            $proveedorId,
            $desde->toDateString(),
            $hasta->toDateString(),
            $empresaId,
        );

        $hastaCorte = min($fecha->toDateString(), $hasta->toDateString());
        $pagosAnita = 0;
        try {
            $faltantes = $this->anitaRespaldo->listarFaltantes(
                $proveedorId,
                $desde->toDateString(),
                $hastaCorte,
                $empresaId,
                $retenciongananciaId,
                $clavesOcupadas,
                $certificadosOcupados,
            );
            foreach ($faltantes as $anita) {
                $neto = round($neto + (float) $anita['neto'], 2);
                $retenido = round($retenido + (float) $anita['retenido'], 2);
                $pagosAnita++;
                $porPago['anita:'.($anita['clave'] ?? $pagosAnita)] = $anita;
            }
        } catch (\Throwable $e) {
            Log::warning('pagoproveedor.acumulado_ganancias.anita_respaldo', [
                'proveedor_id' => $proveedorId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'neto' => $neto,
            'retenido' => $retenido,
            'desde' => $desde->toDateString(),
            'hasta' => $hastaCorte,
            'pagos' => count($porPago),
            'pagos_anita' => $pagosAnita,
            'detalle_pagos' => array_values($porPago),
        ];
    }

    /**
     * @param  array<string, true>  $clavesOcupadas
     */
    private function ocuparClavesPagoExcluido(array &$clavesOcupadas, ?int $pagoId, ?int $empresaId): void
    {
        if (! $pagoId || $pagoId <= 0) {
            return;
        }
        $pago = Pagoproveedor::query()->whereKey($pagoId)->first([
            'empresa_id', 'tipocomprobante', 'letra', 'sucursal', 'numerotransaccion',
        ]);
        if ($pago === null) {
            return;
        }
        $this->marcarClavesErp(
            $clavesOcupadas,
            (string) ($pago->tipocomprobante ?: 'OPP'),
            (string) ($pago->letra ?? ''),
            (int) ($pago->sucursal ?: 0),
            (int) $pago->numerotransaccion,
            $this->empresaAnita((int) $pago->empresa_id, $empresaId),
        );
    }

    /**
     * @param  array<string, true>  $clavesOcupadas
     */
    private function ocuparClavesPagosAnulados(
        array &$clavesOcupadas,
        int $proveedorId,
        string $desdeIso,
        string $hastaIso,
        ?int $empresaId,
    ): void {
        $q = Pagoproveedor::query()
            ->where('proveedor_id', $proveedorId)
            ->whereBetween('fecha', [$desdeIso, $hastaIso])
            ->where('estado', 'ANULADA');
        if ($empresaId && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }
        foreach ($q->get(['empresa_id', 'tipocomprobante', 'letra', 'sucursal', 'numerotransaccion']) as $pago) {
            $this->marcarClavesErp(
                $clavesOcupadas,
                (string) ($pago->tipocomprobante ?: 'OPP'),
                (string) ($pago->letra ?? ''),
                (int) ($pago->sucursal ?: 0),
                (int) $pago->numerotransaccion,
                $this->empresaAnita((int) $pago->empresa_id, $empresaId),
            );
        }
    }

    /**
     * @param  array<string, true>  $clavesOcupadas
     */
    private function marcarClavesErp(
        array &$clavesOcupadas,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        int $empresaAnita,
    ): void {
        foreach (RetencionGananciasAcumuladoAnitaClaveSupport::clavesOcupacionDesdeErp(
            $tipo, $letra, $sucursal > 0 ? $sucursal : 1, $nro, $empresaAnita
        ) as $clave) {
            $clavesOcupadas[$clave] = true;
        }
    }

    private function empresaAnita(int $empresaErp, ?int $empresaIdFiltro): int
    {
        $id = $empresaErp > 0 ? $empresaErp : (int) ($empresaIdFiltro ?? 0);
        if ($id <= 0) {
            return 0;
        }
        if (isset($this->cacheEmpresaAnita[$id])) {
            return $this->cacheEmpresaAnita[$id];
        }
        $anita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($id);

        return $this->cacheEmpresaAnita[$id] = ($anita > 0 ? $anita : $id);
    }

    /**
     * Resuelve régimen efectivo del proveedor (para saber si acumula y cuántos períodos).
     */
    public function regimenIdDesdeProveedor(?int $retenciongananciaIdPago, ?int $retenciongananciaIdProveedor): ?int
    {
        if ($retenciongananciaIdPago && $retenciongananciaIdPago > 0) {
            return $retenciongananciaIdPago;
        }
        if ($retenciongananciaIdProveedor && $retenciongananciaIdProveedor > 0) {
            return $retenciongananciaIdProveedor;
        }

        return null;
    }

    public function regimenTomaAcumulados(?int $retenciongananciaId): bool
    {
        if (! $retenciongananciaId) {
            return false;
        }
        $forma = strtoupper(trim((string) Retencionganancia::query()
            ->whereKey($retenciongananciaId)
            ->value('formacalculo')));

        return in_array($forma, ['S', 'O'], true);
    }
}
