<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ArticuloVendidoExport;
use App\Exports\Ventas\ArticuloVendidoMultiExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Linea;
use App\Models\Stock\Mventa;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Services\Ventas\RepArticuloVendidoFerliService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepArticuloVendidoController extends Controller
{
    protected $reporteService;

    protected $clienteQuery;

    public function __construct(
        RepArticuloVendidoFerliService $reporteService,
        ClienteQueryInterface $clientequery
    ) {
        $this->middleware('auth');
        $this->reporteService = $reporteService;
        $this->clienteQuery = $clientequery;
    }

    public function index()
    {
        $tipoOrigen_enum = [
            'IMPORTADO' => 'Artículos importados',
            'NACIONAL' => 'Artículos nacionales',
            'AMBOS' => 'Importados y nacionales (dos hojas)',
        ];
        $cliente_query = $this->clienteQuery->allQueryCargaPedido(['id', 'nombre', 'codigo']);
        $cliente_query->prepend((object) ['id' => '0', 'nombre' => 'Primero']);
        $cliente_query->push((object) ['id' => '99999999', 'nombre' => 'Ultimo']);
        $articulo_query = Articulo::select('id', 'sku', 'descripcion', 'mventa_id')
            ->orderBy('descripcion', 'ASC')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('combinacion')
                    ->whereRaw('combinacion.articulo_id=articulo.id');
            })
            ->get();
        $articulo_query->prepend((object) ['id' => '0', 'descripcion' => 'Primero']);
        $articulo_query->push((object) ['id' => '99999999', 'descripcion' => 'Ultimo']);
        $linea_query = Linea::orderBy('nombre', 'ASC')->get();
        $linea_query->prepend((object) ['id' => '0', 'nombre' => 'Primero']);
        $linea_query->push((object) ['id' => '99999999', 'nombre' => 'Ultimo']);
        $mventa_query = Mventa::all();
        $mventa_query->prepend((object) ['id' => '0', 'nombre' => 'Todas las marcas']);

        return view('ventas.reparticulovendido.crear', compact(
            'tipoOrigen_enum',
            'cliente_query',
            'articulo_query',
            'linea_query',
            'mventa_query'
        ));
    }

    public function crearReporteArticuloVendido(Request $request)
    {
        switch ($request->extension) {
            case 'Genera Reporte en Excel':
                $extension = 'xlsx';
                break;
            case 'Genera Reporte en PDF':
                $extension = 'pdf';
                break;
            case 'Genera Reporte en CSV':
                $extension = 'csv';
                break;
            default:
                $extension = 'xlsx';
                break;
        }

        $nombreMventa = 'Todas las marcas';
        if ($request->mventa_id > 0) {
            $mventa = Mventa::find($request->mventa_id);
            if ($mventa) {
                $nombreMventa = $mventa->nombre;
            }
        }

        $params = [
            'desdefecha' => $request->desdefecha,
            'hastafecha' => $request->hastafecha,
            'desdearticulo_id' => $request->desdearticulo_id,
            'hastaarticulo_id' => $request->hastaarticulo_id,
            'desdecliente_id' => $request->desdecliente_id,
            'hastacliente_id' => $request->hastacliente_id,
            'desdelinea_id' => $request->desdelinea_id,
            'hastalinea_id' => $request->hastalinea_id,
            'mventa_id' => $request->mventa_id,
            'nombremventa' => $nombreMventa,
        ];

        if ($request->tipoOrigen == 'AMBOS') {
            return (new ArticuloVendidoMultiExport($this->reporteService, $params))
                ->download('articulos_vendidos.'.$extension);
        }

        return (new ArticuloVendidoExport($this->reporteService))
            ->parametros(
                $request->tipoOrigen,
                $request->desdefecha,
                $request->hastafecha,
                $request->desdearticulo_id,
                $request->hastaarticulo_id,
                $request->desdecliente_id,
                $request->hastacliente_id,
                $request->desdelinea_id,
                $request->hastalinea_id,
                $request->mventa_id,
                $nombreMventa
            )
            ->download('articulos_vendidos.'.$extension);
    }
}
