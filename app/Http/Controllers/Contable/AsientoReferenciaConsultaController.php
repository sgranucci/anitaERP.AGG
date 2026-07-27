<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Ventas\Venta;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Http\Request;

/**
 * Consulta F1/Enter de referencias opcionales del asiento manual (OC, CP, venta).
 */
class AsientoReferenciaConsultaController extends Controller
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function consultaOrdencompra(Request $request)
    {
        $this->assertPuedeConsultarAsiento();

        $empresaId = (int) $request->input('empresa_id', 0);
        $consulta = trim((string) $request->input('consulta', ''));

        $query = Ordencompra::query()
            ->with(['proveedores:id,nombre', 'empresas:id,nombre'])
            ->orderByDesc('numeroordencompra')
            ->limit(80);

        $this->aplicarFiltroEmpresaOrdencompra($query, $empresaId);

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('numeroordencompra', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%')
                    ->orWhereHas('proveedores', function ($pq) use ($consulta) {
                        $pq->where('nombre', 'like', '%'.$consulta.'%')
                            ->orWhere('codigo', 'like', '%'.$consulta.'%');
                    });
            });
        }

        $puedeAbm = can('listar-ordencompra', false) || can('editar-ordencompra', false);
        $html = '';
        $flSin = true;

        foreach ($query->get() as $row) {
            $flSin = false;
            $desc = trim('OC '.$row->numeroordencompra.' · '.($row->proveedores->nombre ?? ''));
            $html .= '<tr>';
            $html .= '<td class="ordencompra_id">'.(int) $row->id.'</td>';
            $html .= '<td class="numeroordencompra">'.e((string) $row->numeroordencompra).'</td>';
            $html .= '<td class="proveedor_nombre">'.e((string) ($row->proveedores->nombre ?? '')).'</td>';
            $html .= '<td class="fecha_oc">'.e($this->formatearFecha($row->fecha)).'</td>';
            $html .= '<td class="descripcion_oc d-none">'.e($desc).'</td>';
            $html .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsulta-asiento-oc">Elegir</a>';
            if ($puedeAbm) {
                $url = route('editar_ordencompra', [
                    'id' => $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        if ($flSin) {
            $html = '<tr><td colspan="5">Sin resultados</td></tr>';
        }

        return response()->json(['data' => $html]);
    }

    public function resolverOrdencompra(Request $request)
    {
        $this->assertPuedeConsultarAsiento();

        $empresaId = (int) $request->input('empresa_id', 0);
        $valor = trim((string) $request->input('valor', $request->input('codigo', '')));
        if ($valor === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Ingrese número de OC']);
        }

        $query = Ordencompra::query()->with(['proveedores:id,nombre']);
        $this->aplicarFiltroEmpresaOrdencompra($query, $empresaId);

        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('numeroordencompra', (int) $valor)
                    ->orWhere('id', (int) $valor);
            });
        } else {
            $query->where('numeroordencompra', 'like', '%'.$valor.'%');
        }

        $oc = $query->orderByDesc('numeroordencompra')->first();
        if (! $oc) {
            return response()->json(['ok' => false, 'mensaje' => 'Orden de compra no encontrada']);
        }

        return response()->json(['ok' => true, 'item' => $this->payloadOrdencompra($oc)]);
    }

    public function consultaComprobanteProveedor(Request $request)
    {
        $this->assertPuedeConsultarAsiento();

        $empresaId = (int) $request->input('empresa_id', 0);
        $consulta = trim((string) $request->input('consulta', ''));

        $query = Comprobante_Proveedor::query()
            ->with([
                'proveedores:id,nombre',
                'tipotransaccion_compras:id,abreviatura',
                'ordencompras:id,numeroordencompra',
            ])
            ->orderByDesc('id')
            ->limit(80);

        $this->aplicarFiltroEmpresaComprobante($query, $empresaId);

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('numerocomprobante', 'like', '%'.$consulta.'%')
                    ->orWhere('letra', 'like', '%'.$consulta.'%')
                    ->orWhere('sucursal', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%')
                    ->orWhereHas('proveedores', function ($pq) use ($consulta) {
                        $pq->where('nombre', 'like', '%'.$consulta.'%')
                            ->orWhere('codigo', 'like', '%'.$consulta.'%');
                    });
            });
        }

        $puedeAbm = can('listar-comprobante-proveedor', false) || can('editar-comprobante-proveedor', false);
        $html = '';
        $flSin = true;

        foreach ($query->get() as $row) {
            $flSin = false;
            $codigo = $this->codigoComprobanteProveedor($row);
            $desc = trim($codigo.' · '.($row->proveedores->nombre ?? ''));
            $html .= '<tr>';
            $html .= '<td class="comprobante_proveedor_id">'.(int) $row->id.'</td>';
            $html .= '<td class="comprobante_codigo">'.e($codigo).'</td>';
            $html .= '<td class="proveedor_nombre">'.e((string) ($row->proveedores->nombre ?? '')).'</td>';
            $html .= '<td class="fecha_cp">'.e($this->formatearFecha($row->fechacomprobante)).'</td>';
            $html .= '<td class="ordencompra_id d-none">'.(int) ($row->ordencompra_id ?? 0).'</td>';
            $html .= '<td class="numeroordencompra d-none">'.e((string) ($row->ordencompras->numeroordencompra ?? '')).'</td>';
            $html .= '<td class="descripcion_cp d-none">'.e($desc).'</td>';
            $html .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsulta-asiento-cp">Elegir</a>';
            if ($puedeAbm) {
                $url = route('editar_comprobante_proveedor', [
                    'id' => $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        if ($flSin) {
            $html = '<tr><td colspan="5">Sin resultados</td></tr>';
        }

        return response()->json(['data' => $html]);
    }

    public function resolverComprobanteProveedor(Request $request)
    {
        $this->assertPuedeConsultarAsiento();

        $empresaId = (int) $request->input('empresa_id', 0);
        $valor = trim((string) $request->input('valor', $request->input('codigo', '')));
        if ($valor === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Ingrese comprobante']);
        }

        $query = Comprobante_Proveedor::query()
            ->with([
                'proveedores:id,nombre',
                'tipotransaccion_compras:id,abreviatura',
                'ordencompras:id,numeroordencompra',
            ]);
        $this->aplicarFiltroEmpresaComprobante($query, $empresaId);

        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('id', (int) $valor)
                    ->orWhere('numerocomprobante', (int) $valor);
            });
        } else {
            $partes = preg_split('/[\s\-\/]+/', $valor) ?: [];
            $query->where(function ($q) use ($valor, $partes) {
                $q->where('numerocomprobante', 'like', '%'.$valor.'%');
                if (count($partes) >= 3) {
                    $q->orWhere(function ($q2) use ($partes) {
                        $q2->where('letra', $partes[0])
                            ->where('sucursal', (int) $partes[1])
                            ->where('numerocomprobante', (int) $partes[2]);
                    });
                }
            });
        }

        $cp = $query->orderByDesc('id')->first();
        if (! $cp) {
            return response()->json(['ok' => false, 'mensaje' => 'Comprobante de proveedor no encontrado']);
        }

        return response()->json(['ok' => true, 'item' => $this->payloadComprobanteProveedor($cp)]);
    }

    public function consultaVenta(Request $request)
    {
        $this->assertPuedeConsultarAsiento();

        $empresaId = (int) $request->input('empresa_id', 0);
        $consulta = trim((string) $request->input('consulta', ''));

        $query = Venta::query()
            ->with([
                'clientes:id,nombre',
                'tipotransacciones:id,abreviatura',
                'puntoventas:id,codigo,empresa_id',
            ])
            ->orderByDesc('id')
            ->limit(80);

        $this->aplicarFiltroEmpresaVenta($query, $empresaId);

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('numerocomprobante', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%')
                    ->orWhere('nombre', 'like', '%'.$consulta.'%')
                    ->orWhereHas('clientes', function ($cq) use ($consulta) {
                        $cq->where('nombre', 'like', '%'.$consulta.'%')
                            ->orWhere('codigo', 'like', '%'.$consulta.'%');
                    });
            });
        }

        $puedeAbm = can('listar-factura', false) || can('editar-factura', false) || can('facturar', false);
        $html = '';
        $flSin = true;

        foreach ($query->get() as $row) {
            $flSin = false;
            $codigo = $this->codigoVenta($row);
            $cliente = (string) ($row->clientes->nombre ?? $row->nombre ?? '');
            $desc = trim($codigo.' · '.$cliente);
            $html .= '<tr>';
            $html .= '<td class="venta_id">'.(int) $row->id.'</td>';
            $html .= '<td class="venta_codigo">'.e($codigo).'</td>';
            $html .= '<td class="cliente_nombre">'.e($cliente).'</td>';
            $html .= '<td class="fecha_venta">'.e($this->formatearFecha($row->fecha)).'</td>';
            $html .= '<td class="descripcion_venta d-none">'.e($desc).'</td>';
            $html .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsulta-asiento-venta">Elegir</a>';
            if ($puedeAbm) {
                $url = route('editar_factura', [
                    'id' => $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        if ($flSin) {
            $html = '<tr><td colspan="5">Sin resultados</td></tr>';
        }

        return response()->json(['data' => $html]);
    }

    public function resolverVenta(Request $request)
    {
        $this->assertPuedeConsultarAsiento();

        $empresaId = (int) $request->input('empresa_id', 0);
        $valor = trim((string) $request->input('valor', $request->input('codigo', '')));
        if ($valor === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Ingrese factura']);
        }

        $query = Venta::query()
            ->with([
                'clientes:id,nombre',
                'tipotransacciones:id,abreviatura',
                'puntoventas:id,codigo,empresa_id',
            ]);
        $this->aplicarFiltroEmpresaVenta($query, $empresaId);

        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('id', (int) $valor)
                    ->orWhere('numerocomprobante', (int) $valor);
            });
        } else {
            $partes = preg_split('/[\s\-\/]+/', $valor) ?: [];
            $query->where(function ($q) use ($valor, $partes) {
                $q->where('numerocomprobante', 'like', '%'.$valor.'%');
                if (count($partes) >= 2) {
                    $nro = (int) end($partes);
                    $pv = (int) ($partes[count($partes) - 2] ?? 0);
                    $q->orWhere(function ($q2) use ($nro, $pv) {
                        $q2->where('numerocomprobante', $nro);
                        if ($pv > 0) {
                            $q2->whereHas('puntoventas', fn ($pq) => $pq->where('codigo', $pv));
                        }
                    });
                }
            });
        }

        $venta = $query->orderByDesc('id')->first();
        if (! $venta) {
            return response()->json(['ok' => false, 'mensaje' => 'Factura de venta no encontrada']);
        }

        return response()->json(['ok' => true, 'item' => $this->payloadVenta($venta)]);
    }

    private function assertPuedeConsultarAsiento(): void
    {
        if (
            ! can('crear-asiento', false)
            && ! can('editar-asiento', false)
            && ! can('actualizar-asiento', false)
            && ! can('listar-asiento', false)
        ) {
            abort(403, 'Sin permiso para consultar referencias de asiento');
        }
    }

    private function aplicarFiltroEmpresaOrdencompra($query, int $empresaId): void
    {
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);

            return;
        }
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
    }

    private function aplicarFiltroEmpresaComprobante($query, int $empresaId): void
    {
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);

            return;
        }
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
    }

    private function aplicarFiltroEmpresaVenta($query, int $empresaId): void
    {
        if ($empresaId > 0) {
            $query->whereHas('puntoventas', fn ($q) => $q->where('empresa_id', $empresaId));

            return;
        }
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        if (is_array($asignadas) && count($asignadas) > 0) {
            $query->whereHas('puntoventas', fn ($q) => $q->whereIn('empresa_id', $asignadas));
        }
    }

    /** @return array<string, mixed> */
    private function payloadOrdencompra(Ordencompra $oc): array
    {
        $codigo = (string) $oc->numeroordencompra;
        $desc = trim('OC '.$codigo.' · '.($oc->proveedores->nombre ?? ''));

        return [
            'id' => (int) $oc->id,
            'codigo' => $codigo,
            'descripcion' => $desc,
            'numeroordencompra' => (int) $oc->numeroordencompra,
        ];
    }

    /** @return array<string, mixed> */
    private function payloadComprobanteProveedor(Comprobante_Proveedor $cp): array
    {
        $codigo = $this->codigoComprobanteProveedor($cp);
        $desc = trim($codigo.' · '.($cp->proveedores->nombre ?? ''));

        return [
            'id' => (int) $cp->id,
            'codigo' => $codigo,
            'descripcion' => $desc,
            'ordencompra_id' => (int) ($cp->ordencompra_id ?? 0),
            'numeroordencompra' => (string) ($cp->ordencompras->numeroordencompra ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private function payloadVenta(Venta $venta): array
    {
        $codigo = $this->codigoVenta($venta);
        $cliente = (string) ($venta->clientes->nombre ?? $venta->nombre ?? '');

        return [
            'id' => (int) $venta->id,
            'codigo' => $codigo,
            'descripcion' => trim($codigo.' · '.$cliente),
        ];
    }

    private function codigoComprobanteProveedor(Comprobante_Proveedor $cp): string
    {
        $abrev = (string) ($cp->tipotransaccion_compras?->abreviatura ?? '');
        $suc = str_pad((string) ($cp->sucursal ?? 0), 4, '0', STR_PAD_LEFT);

        return trim($abrev.' '.$cp->letra.'-'.$suc.'-'.$cp->numerocomprobante);
    }

    private function codigoVenta(Venta $venta): string
    {
        $abrev = (string) ($venta->tipotransacciones?->abreviatura ?? '');
        $pv = (string) ($venta->puntoventas?->codigo ?? '');

        return trim($abrev.' '.$pv.'-'.$venta->numerocomprobante);
    }

    private function formatearFecha(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('d/m/Y');
        }
        $raw = trim((string) $fecha);
        if ($raw === '') {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($raw)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $raw;
        }
    }
}
