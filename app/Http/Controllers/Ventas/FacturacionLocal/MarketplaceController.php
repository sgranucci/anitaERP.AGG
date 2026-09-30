<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Exports\Ventas\MarketplaceListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMarketplace;
use App\Models\Ventas\Marketplace;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\FacturacionLocal\MarketplaceListadoFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-marketplace-facturacion-local');

        $filtros = MarketplaceListadoFiltros::resolverDesdeRequest($request);
        $query = Marketplace::query()->withCount('asignaciones')->orderBy('codigo');
        MarketplaceListadoFiltros::aplicar($query, $filtros);
        $datas = $query->paginate(15);

        return view('ventas.facturacion_local.marketplace.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => MarketplaceListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => MarketplaceListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null)
    {
        $this->assertFerli();
        can('listar-marketplace-facturacion-local');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = MarketplaceListadoFiltros::resolverDesdeRequest($request);
        $query = Marketplace::query()->withCount('asignaciones')->orderBy('codigo');
        MarketplaceListadoFiltros::aplicar($query, $filtros);
        $datas = $query->get();
        $formato = strtoupper((string) $formato);

        if ($formato === 'PDF') {
            $html = view('ventas.facturacion_local.marketplace.listado', [
                'datas' => $datas,
                'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas),
                'totalFilas' => $datas->count(),
            ])->render();
            $dir = storage_path('pdf/listados');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $ruta = $dir.'/listado_marketplace.pdf';
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Marketplaces',
            ]);

            return response()->download($ruta, 'marketplaces.pdf')->deleteFileAfterSend(true);
        }

        if ($formato === 'EXCEL') {
            return (new MarketplaceListadoExport)->parametros($filtros)->download('marketplaces.xlsx');
        }
        if ($formato === 'CSV') {
            return (new MarketplaceListadoExport)->parametros($filtros)->download('marketplaces.csv', Excel::CSV);
        }

        return redirect()->route('facturacion_local_marketplaces', MarketplaceListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        $this->assertFerli();
        can('crear-marketplace-facturacion-local');

        return view('ventas.facturacion_local.marketplace.crear', [
            'data' => new Marketplace(['activo' => true]),
        ]);
    }

    public function guardar(ValidacionMarketplace $request)
    {
        $this->assertFerli();
        can('crear-marketplace-facturacion-local');
        $marketplace = Marketplace::query()->create($request->datos());

        return redirect()
            ->route('facturacion_local_marketplaces')
            ->with('mensaje', 'Marketplace '.$marketplace->nombre.' creado.');
    }

    public function editar(Request $request, $id)
    {
        $this->assertFerli();
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('editar-marketplace-facturacion-local', false)
                && ! can('listar-marketplace-facturacion-local', false)
                && ! can('editar-articulos', false)) {
                abort(403);
            }
        } else {
            can('editar-marketplace-facturacion-local');
        }

        $data = Marketplace::query()->findOrFail($id);

        return view('ventas.facturacion_local.marketplace.editar', [
            'data' => $data,
            'soloConsulta' => $soloConsulta,
            'puedeActualizar' => can('actualizar-marketplace-facturacion-local', false),
        ]);
    }

    public function actualizar(ValidacionMarketplace $request, $id)
    {
        $this->assertFerli();
        can('actualizar-marketplace-facturacion-local');
        $marketplace = Marketplace::query()->findOrFail($id);
        $marketplace->update($request->datos());

        if ($request->input('origen') === 'modal_consulta' || $request->query('origen') === 'modal_consulta') {
            return redirect()
                ->route('editar_marketplace', [
                    'id' => $marketplace->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ])
                ->with('mensaje', 'Marketplace actualizado.');
        }

        return redirect()
            ->route('facturacion_local_marketplaces')
            ->with('mensaje', 'Marketplace actualizado.');
    }

    public function eliminar($id)
    {
        $this->assertFerli();
        can('eliminar-marketplace-facturacion-local');
        $marketplace = Marketplace::query()->withCount('asignaciones')->findOrFail($id);
        if ((int) $marketplace->asignaciones_count > 0) {
            $marketplace->activo = false;
            $marketplace->save();

            return redirect()
                ->route('facturacion_local_marketplaces')
                ->with('mensaje', 'El marketplace tiene artículos asignados. Quedó inactivo.');
        }

        $marketplace->delete();

        return redirect()
            ->route('facturacion_local_marketplaces')
            ->with('mensaje', 'Marketplace eliminado.');
    }

    public function consulta(Request $request)
    {
        $this->assertFerli();
        $this->assertPuedeConsultar();

        $texto = trim((string) $request->input('texto', $request->input('busqueda', '')));
        $query = Marketplace::query()->where('activo', true)->orderBy('codigo');
        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('nombre', 'like', '%'.$texto.'%');
                $digitos = preg_replace('/\D+/', '', $texto) ?? '';
                if ($digitos !== '') {
                    $q->orWhere('codigo', (int) $digitos);
                }
            });
        }

        $filas = $query->limit(50)->get(['id', 'codigo', 'nombre']);
        $puedeAbrir = can('editar-marketplace-facturacion-local', false) || can('listar-marketplace-facturacion-local', false);

        return response()->json([
            'filas' => $filas->map(function (Marketplace $row) use ($puedeAbrir) {
                return [
                    'id' => (int) $row->id,
                    'codigo' => (string) $row->codigo,
                    'nombre' => (string) $row->nombre,
                    'url_consultar' => $puedeAbrir ? route('editar_marketplace', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]) : '',
                ];
            })->values()->all(),
        ]);
    }

    public function resolver(Request $request)
    {
        $this->assertFerli();
        $this->assertPuedeConsultar();

        $codigo = (int) preg_replace('/\D+/', '', (string) $request->input('codigo', ''));
        if ($codigo <= 0) {
            return response()->json(['ok' => false, 'error' => 'Indique el código de marketplace.']);
        }

        $row = Marketplace::query()->where('codigo', $codigo)->where('activo', true)->first();
        if (! $row) {
            return response()->json(['ok' => false, 'error' => 'Marketplace '.$codigo.' no encontrado o inactivo.']);
        }

        $puedeAbrir = can('editar-marketplace-facturacion-local', false) || can('listar-marketplace-facturacion-local', false);

        return response()->json([
            'ok' => true,
            'id' => (int) $row->id,
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'url_consultar' => $puedeAbrir ? route('editar_marketplace', [
                'id' => $row->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]) : '',
        ]);
    }

    private function assertPuedeConsultar(): void
    {
        if (can('listar-marketplace-facturacion-local', false)
            || can('editar-marketplace-facturacion-local', false)
            || can('crear-articulos', false)
            || can('editar-articulos', false)
            || can('actualizar-articulos', false)
            || can('editar-configuracion-tiendanube', false)
            || can('actualizar-configuracion-tiendanube', false)) {
            return;
        }

        abort(403);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
