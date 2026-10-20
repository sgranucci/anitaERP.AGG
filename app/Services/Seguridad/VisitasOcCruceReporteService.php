<?php

namespace App\Services\Seguridad;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Pagoproveedor;
use App\Models\Seguridad\IngresoProveedor;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Compras\CircuitoComprasDocumentosRelacionadosSupport;
use App\Support\Seguridad\IngresoProveedorEstados;
use App\Support\Seguridad\IngresoProveedorVisitanteSupport;
use Illuminate\Support\Collection;

class VisitasOcCruceReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, kpis: array<string, int>}
     */
    public function generar(array $filtros): array
    {
        $tickets = $this->consultarTickets($filtros);
        $ocIds = $tickets->pluck('ordencompra_id')->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values()->all();
        $circuito = $this->cargarCircuitoPorOc($ocIds);

        $filas = [];
        foreach ($tickets as $ticket) {
            $ocId = (int) ($ticket->ordencompra_id ?? 0);
            $docs = $circuito[$ocId] ?? $this->circuitoVacio();
            $filas[] = $this->fila($ticket, $docs);
        }

        return [
            'filas' => $filas,
            'kpis' => [
                'visitas' => count($filas),
                'con_oc' => count(array_filter($filas, fn ($f) => ($f['oc_id'] ?? 0) > 0)),
                'con_requisicion' => count(array_filter($filas, fn ($f) => ($f['requisicion'] ?? '') !== '')),
                'con_factura' => count(array_filter($filas, fn ($f) => ($f['facturas'] ?? '') !== '')),
                'con_op' => count(array_filter($filas, fn ($f) => ($f['ordenes_pago'] ?? '') !== '')),
                'sin_oc' => count(array_filter($filas, fn ($f) => ($f['oc_id'] ?? 0) <= 0)),
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function permisosEnlacesPantalla(): array
    {
        return [
            'en_pantalla' => true,
            'puede_ver_ticket' => can('editar-ingreso-proveedor', false) || can('listar-ingreso-proveedor', false),
            'puede_ver_oc' => can('editar-ordencompra', false) || can('listar-ordencompra', false),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'puede_ver_empresa' => can('editar-empresas', false) || can('listar-empresas', false),
            'puede_ver_requisicion' => can('listar-requisicion', false) || can('editar-requisicion', false),
            'puede_ver_factura' => can('listar-comprobante-proveedor', false) || can('editar-comprobante-proveedor', false),
            'puede_ver_op' => can('listar-pagoproveedor', false) || can('editar-pagoproveedor', false),
            'puede_ver_com' => can('listar-recepcion-proveedor', false) || can('editar-recepcion-proveedor', false),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, IngresoProveedor>
     */
    private function consultarTickets(array $filtros): Collection
    {
        $query = IngresoProveedor::query()
            ->with([
                'proveedores:id,codigo,nombre',
                'ordencompras:id,numeroordencompra,requisicion_id',
                'motivos:id,nombre',
                'empresas:id,nombre',
            ]);

        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'ingreso_proveedor.empresa_id');

        if (! empty($filtros['empresa_id'])) {
            $query->where('ingreso_proveedor.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('ingreso_proveedor.fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('ingreso_proveedor.fecha', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['proveedor_id'])) {
            $query->where('ingreso_proveedor.proveedor_id', (int) $filtros['proveedor_id']);
        }
        if (! empty($filtros['ordencompra_id'])) {
            $query->where('ingreso_proveedor.ordencompra_id', (int) $filtros['ordencompra_id']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('ingreso_proveedor.estado', $filtros['estado']);
        }
        if ($filtros['solo_con_oc'] ?? true) {
            $query->whereNotNull('ingreso_proveedor.ordencompra_id')
                ->where('ingreso_proveedor.ordencompra_id', '>', 0);
        }

        return $query->orderBy('ingreso_proveedor.fecha')->orderBy('ingreso_proveedor.id')->get();
    }

    /**
     * @param  list<int>  $ocIds
     * @return array<int, array<string, mixed>>
     */
    private function cargarCircuitoPorOc(array $ocIds): array
    {
        if ($ocIds === []) {
            return [];
        }

        $ocs = Ordencompra::query()
            ->with('requisiciones:id,numerorequisicion')
            ->whereIn('id', $ocIds)
            ->get()
            ->keyBy('id');

        $coms = Recepcion_Proveedor::query()
            ->whereIn('ordencompra_id', $ocIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('ordencompra_id');

        $facturas = Comprobante_Proveedor::query()
            ->with('tipotransaccion_compras:id,abreviatura')
            ->whereIn('ordencompra_id', $ocIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('ordencompra_id');

        $facturaIds = $facturas->flatten(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $opsPorFactura = $this->opsPorFacturaIds($facturaIds);

        $out = [];
        foreach ($ocIds as $ocId) {
            $oc = $ocs->get($ocId);
            $req = $oc?->requisiciones;
            $comsOc = $coms->get($ocId, collect());
            $factsOc = $facturas->get($ocId, collect());

            $ops = [];
            foreach ($factsOc as $fact) {
                foreach ($opsPorFactura[(int) $fact->id] ?? [] as $op) {
                    $ops[$op['id']] = $op;
                }
            }

            $out[$ocId] = [
                'oc' => $oc ? CircuitoComprasDocumentosRelacionadosSupport::bloqueOrdencompra($oc) : null,
                'requisicion' => $req ? CircuitoComprasDocumentosRelacionadosSupport::bloqueRequisicion($req) : null,
                'coms' => CircuitoComprasDocumentosRelacionadosSupport::bloquesComDesdeColeccion($comsOc),
                'facturas' => $factsOc->map(fn ($f) => CircuitoComprasDocumentosRelacionadosSupport::bloqueFactura($f))->values()->all(),
                'ops' => array_values($ops),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $facturaIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function opsPorFacturaIds(array $facturaIds): array
    {
        $map = [];
        foreach ($facturaIds as $facturaId) {
            $opIds = CircuitoComprasDocumentosRelacionadosSupport::pagoproveedorIdsPorComprobante((int) $facturaId);
            if ($opIds === []) {
                $map[(int) $facturaId] = [];
                continue;
            }
            $pagos = Pagoproveedor::query()->whereIn('id', $opIds)->orderByDesc('id')->get();
            $map[(int) $facturaId] = $pagos
                ->map(fn ($p) => CircuitoComprasDocumentosRelacionadosSupport::bloqueOp($p))
                ->values()
                ->all();
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $docs
     * @return array<string, mixed>
     */
    private function fila(IngresoProveedor $ticket, array $docs): array
    {
        $oc = $docs['oc'] ?? null;
        $req = $docs['requisicion'] ?? null;
        $coms = $docs['coms'] ?? [];
        $facts = $docs['facturas'] ?? [];
        $ops = $docs['ops'] ?? [];

        $tipo = ((string) ($ticket->visitante_tipo ?? '')) === IngresoProveedorVisitanteSupport::VISITANTE
            || empty($ticket->proveedor_id)
            ? 'Visitante'
            : 'Proveedor';

        return [
            'ticket_id' => (int) $ticket->id,
            'fecha' => substr((string) $ticket->fecha, 0, 10),
            'estado' => IngresoProveedorEstados::etiqueta((string) $ticket->estado),
            'estado_codigo' => (string) $ticket->estado,
            'empresa_id' => (int) ($ticket->empresa_id ?? 0),
            'nombreempresa' => (string) ($ticket->empresas->nombre ?? ''),
            'tipo' => $tipo,
            'proveedor_id' => (int) ($ticket->proveedor_id ?? 0),
            'proveedor_codigo' => (string) ($ticket->proveedores->codigo ?? ''),
            'proveedor_nombre' => (string) ($ticket->proveedores->nombre ?? $ticket->visitante_nombre ?? ''),
            'motivo' => (string) ($ticket->motivos->nombre ?? ''),
            'oc_id' => (int) ($oc['id'] ?? $ticket->ordencompra_id ?? 0),
            'oc_numero' => (string) ($oc['numero'] ?? $ticket->ordencompras->numeroordencompra ?? ''),
            'oc_etiqueta' => (string) ($oc['etiqueta'] ?? ''),
            'oc_url' => $oc['url_ver'] ?? null,
            'requisicion' => (string) ($req['etiqueta'] ?? ''),
            'requisicion_url' => $req['url_ver'] ?? null,
            'coms' => implode(', ', array_map(fn ($b) => (string) ($b['etiqueta'] ?? ''), $coms)),
            'coms_detalle' => $coms,
            'facturas' => implode(', ', array_map(fn ($b) => (string) ($b['etiqueta'] ?? ''), $facts)),
            'facturas_detalle' => $facts,
            'ordenes_pago' => implode(', ', array_map(fn ($b) => (string) ($b['etiqueta'] ?? ''), $ops)),
            'ops_detalle' => $ops,
            'titulo' => (string) ($ticket->titulo ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private function circuitoVacio(): array
    {
        return [
            'oc' => null,
            'requisicion' => null,
            'coms' => [],
            'facturas' => [],
            'ops' => [],
        ];
    }
}
