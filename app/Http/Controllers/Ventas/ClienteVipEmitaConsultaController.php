<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ClienteVipEmitaConsultaExport;
use App\Http\Controllers\Controller;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\Emita\EmitaClienteVipConsulta;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Excel;
use RuntimeException;

class ClienteVipEmitaConsultaController extends Controller
{
    public function __construct(
        private readonly EmitaClienteVipConsulta $consulta,
    ) {
        $this->middleware('auth');
    }

    public function redirigirModo(Request $request, string $modo)
    {
        if (! in_array($modo, ['nombre', 'alias'], true)) {
            abort(404);
        }

        return redirect()->route('consultar_cliente_vip_emita', $request->query());
    }

    public function redirigirExportacion(Request $request, string $formato)
    {
        return redirect()->route('lista_cliente_vip_emita', ['formato' => $formato] + $request->query());
    }

    public function index(Request $request)
    {
        can('consultar-cliente-vip-emita');

        $texto = trim((string) $request->input('texto', ''));
        $consultar = (string) $request->input('consultar', '') === '1';
        $aviso = null;
        $error = null;
        $filas = null;
        $total = 0;

        if ($consultar) {
            if (! EmitaClienteVipConsulta::textoValido($texto)) {
                $aviso = 'Indique al menos '.EmitaClienteVipConsulta::LONGITUD_MINIMA.' caracteres.';
            } else {
                try {
                    $pagina = max(1, (int) $request->input('page', 1));
                    $resultado = $this->consulta->pagina($texto, $pagina);
                    $total = $resultado['total'];
                    $filas = new LengthAwarePaginator(
                        $resultado['filas'],
                        $total,
                        EmitaClienteVipConsulta::POR_PAGINA,
                        $pagina,
                        ['path' => $request->url()]
                    );
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return view('ventas.gastronomia.canjes.cliente_vip_emita.index', [
            'texto' => $texto,
            'consultar' => $consultar,
            'aviso' => $aviso,
            'error' => $error,
            'filas' => $filas,
            'total' => $total,
            'filtrosQuery' => $this->filtrosQuery($texto, $consultar),
            'titulo' => 'Clientes VIP Emita',
            'etiquetaCampo' => 'Nombre o alias',
            'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([
                (object) ['nombreempresa' => (string) config('app.empresa')],
            ])),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('consultar-cliente-vip-emita');

        $texto = trim((string) $request->input('texto', ''));
        if (! EmitaClienteVipConsulta::textoValido($texto)) {
            return redirect()->route('consultar_cliente_vip_emita')
                ->with('errores', ['Indique al menos '.EmitaClienteVipConsulta::LONGITUD_MINIMA.' caracteres para exportar.']);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        try {
            $resultado = $this->consulta->exportar($texto);
        } catch (RuntimeException $e) {
            return redirect()->route('consultar_cliente_vip_emita', ['texto' => $texto, 'consultar' => 1])
                ->with('errores', [$e->getMessage()]);
        }

        $titulo = 'Clientes VIP Emita';
        $subtitulo = 'Nombre o alias: '.$texto;
        if ($resultado['truncado']) {
            $subtitulo .= ' (se exportan los primeros '.EmitaClienteVipConsulta::TOPE_EXPORTACION.' de '.$resultado['total'].')';
        }

        $base = 'cliente_vip_emita';

        switch (strtoupper($formato)) {
            case 'PDF':
                $html = view('ventas.gastronomia.canjes.cliente_vip_emita.listado', [
                    'filas' => $resultado['filas'],
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([
                        (object) ['nombreempresa' => (string) config('app.empresa')],
                    ])),
                ])->render();
                $ruta = storage_path('pdf/listados/'.$base.'.pdf');
                if (! is_dir(dirname($ruta))) {
                    mkdir(dirname($ruta), 0775, true);
                }
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => $titulo,
                ]);

                return response()->download($ruta, $base.'.pdf');

            case 'EXCEL':
                return (new ClienteVipEmitaConsultaExport($resultado['filas'], $titulo, $subtitulo, $resultado['total']))
                    ->download($base.'.xlsx');

            case 'CSV':
                return (new ClienteVipEmitaConsultaExport($resultado['filas'], $titulo, $subtitulo, $resultado['total']))
                    ->download($base.'.csv', Excel::CSV);
        }

        return redirect()->route('consultar_cliente_vip_emita', $this->filtrosQuery($texto, true));
    }

    /**
     * @return array<string, string>
     */
    private function filtrosQuery(string $texto, bool $consultar): array
    {
        $query = [];
        if ($texto !== '') {
            $query['texto'] = $texto;
        }
        if ($consultar) {
            $query['consultar'] = '1';
        }

        return $query;
    }
}
