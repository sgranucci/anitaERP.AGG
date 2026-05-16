<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\GastronomiaFacturasDiaExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\VentaGastronomiaEmision;
use App\Models\Ventas\Venta;
use App\Support\Stock\GastronomiaIdentificadorPc;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class GastronomiaFacturasDiaController extends Controller
{
    public function index(Request $request)
    {
        can('listar-facturas-gastronomia-dia');

        $pc = GastronomiaIdentificadorPc::resolver($request);
        $fecha = $request->get('fecha', Carbon::today()->format('Y-m-d'));
        $busqueda = trim((string) $request->get('busqueda', ''));

        $registros = $this->registrosFacturasDiaQuery($request)->get();

        return view('stock.gastronomia.facturas_dia.index', [
            'registros' => $registros,
            'fecha' => $fecha,
            'busqueda' => $busqueda,
            'identificador_pc' => $pc,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-facturas-gastronomia-dia');

        $registros = $this->registrosFacturasDiaQuery($request)
            ->get()
            ->map(function (VentaGastronomiaEmision $r) {
                $emp = $r->venta?->puntoventas?->empresas;
                $r->setAttribute('nombreempresa', $emp->nombre ?? '');

                return $r;
            });

        $fecha = $request->get('fecha', Carbon::today()->format('Y-m-d'));
        $identificador_pc = GastronomiaIdentificadorPc::resolver($request);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $view = \View::make('stock.gastronomia.facturas_dia.listado', compact('registros', 'fecha', 'identificador_pc'))
                    ->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_gastronomia_facturas_dia';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new GastronomiaFacturasDiaExport($registros))
                    ->download('gastronomia_facturas_dia.xlsx');

            case 'CSV':
                return (new GastronomiaFacturasDiaExport($registros))
                    ->download('gastronomia_facturas_dia.csv', Excel::CSV);
        }

        abort(404);
    }

    public function ver(int $ventaId)
    {
        can('ver-factura-gastronomia');

        $meta = VentaGastronomiaEmision::query()
            ->where('venta_id', $ventaId)
            ->with(['cuenta', 'configuracionPuntoventa'])
            ->firstOrFail();

        $venta = Venta::query()
            ->with([
                'clientes',
                'venta_emisiones.articulos',
                'venta_impuestos',
                'asientos.asiento_movimientos.cuentacontables',
                'caja_movimientos.cobranzas',
                'puntoventas',
                'monedas',
            ])
            ->findOrFail($ventaId);

        $cobranzas = collect();
        foreach ($venta->caja_movimientos as $mov) {
            if ($mov->cobranza_id && $mov->cobranzas) {
                $cobranzas->push($mov->cobranzas);
            }
        }

        return view('stock.gastronomia.facturas_dia.ver', [
            'meta' => $meta,
            'venta' => $venta,
            'cobranzas' => $cobranzas->unique('id')->values(),
        ]);
    }

    private function registrosFacturasDiaQuery(Request $request): Builder
    {
        $pc = GastronomiaIdentificadorPc::resolver($request);
        $fecha = $request->get('fecha', Carbon::today()->format('Y-m-d'));
        $busqueda = trim((string) $request->get('busqueda', ''));

        $q = VentaGastronomiaEmision::query()
            ->with(['venta.clientes', 'venta.puntoventas.empresas', 'cuenta'])
            ->where('identificador_pc', $pc)
            ->whereHas('venta', fn ($qq) => $qq->whereDate('fecha', $fecha));

        if ($busqueda !== '') {
            $like = '%'.addcslashes($busqueda, '%_\\').'%';
            $digit = preg_replace('/\s+/', '', $busqueda);
            $q->where(function ($w) use ($like, $digit) {
                $w->whereHas('venta', function ($vq) use ($like) {
                    $vq->where('codigo', 'like', $like)
                        ->orWhereHas('clientes', fn ($c) => $c->where('nombre', 'like', $like))
                        ->orWhereHas('puntoventas', fn ($p) => $p->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like));
                });
                if ($digit !== '' && ctype_digit($digit)) {
                    $id = (int) $digit;
                    $w->orWhere('venta_id', $id)->orWhere('cuenta_gastronomia_id', $id);
                }
            });
        }

        return $q->orderByDesc('venta_id');
    }
}
