<?php

namespace App\Services\Seguridad;

use App\Models\Compras\Ordencompra;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Seguridad\IngresoProveedorConsultaSupport;

class IngresoProveedorAbonoReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, kpis: array<string, int>}
     */
    public function generar(array $filtros): array
    {
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $resultadoFiltro = (string) ($filtros['resultado'] ?? '');

        $query = Ordencompra::query()
            ->with(['proveedores:id,codigo,nombre', 'empresas:id,nombre'])
            ->where('es_contrato', true)
            ->whereIn('estadoordencompra', [OrdencompraEstados::APROBADA, OrdencompraEstados::CUMPLIDA]);

        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'ordencompra.empresa_id');
        if ($empresaId > 0) {
            $query->where('ordencompra.empresa_id', $empresaId);
        }
        $query->where(function ($q) use ($desde, $hasta) {
            $q->where(function ($v) use ($desde, $hasta) {
                $v->whereNull('contrato_vigencia_desde')
                    ->orWhereDate('contrato_vigencia_desde', '<=', $hasta);
            })->where(function ($v) use ($desde) {
                $v->whereNull('contrato_vigencia_hasta')
                    ->orWhereDate('contrato_vigencia_hasta', '>=', $desde);
            });
        });

        $filas = [];
        foreach ($query->orderBy('numeroordencompra')->get() as $oc) {
            $tickets = IngresoProveedorConsultaSupport::cantidadTicketsFinalizadosEnPeriodo(
                (int) $oc->proveedor_id,
                $desde,
                $hasta,
                (int) $oc->empresa_id ?: null,
                (int) $oc->id
            );
            $minimo = (int) ($oc->contrato_minimo_ingresos ?? 0);
            $umbral = $minimo > 0 ? $minimo : 1;
            $okFila = $tickets >= $umbral;
            if ($resultadoFiltro === 'OK' && ! $okFila) {
                continue;
            }
            if ($resultadoFiltro === 'REVISAR' && $okFila) {
                continue;
            }
            $filas[] = [
                'oc_id' => (int) $oc->id,
                'oc_numero' => (string) $oc->numeroordencompra,
                'proveedor_id' => (int) $oc->proveedor_id,
                'proveedor_codigo' => (string) ($oc->proveedores->codigo ?? ''),
                'proveedor' => (string) ($oc->proveedores->nombre ?? ''),
                'empresa_id' => (int) $oc->empresa_id,
                'nombreempresa' => (string) ($oc->empresas->nombre ?? ''),
                'estado_oc' => (string) $oc->estadoordencompra,
                'vigencia_desde' => optional($oc->contrato_vigencia_desde)->format('d/m/Y'),
                'vigencia_hasta' => optional($oc->contrato_vigencia_hasta)->format('d/m/Y'),
                'minimo' => $minimo,
                'tickets_finalizados' => $tickets,
                'resultado' => $okFila ? 'OK' : 'Sin ingresos - revisar',
                'resultado_codigo' => $okFila ? 'OK' : 'REVISAR',
            ];
        }

        $ok = 0;
        $revisar = 0;
        foreach ($filas as $fila) {
            if (($fila['resultado_codigo'] ?? '') === 'OK') {
                $ok++;
            } else {
                $revisar++;
            }
        }

        return [
            'filas' => $filas,
            'kpis' => [
                'contratos' => count($filas),
                'ok' => $ok,
                'revisar' => $revisar,
            ],
        ];
    }
}
