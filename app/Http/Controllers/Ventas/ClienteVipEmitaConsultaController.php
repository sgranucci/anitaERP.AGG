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

    public function index(Request $request, string $modo)
    {
        can('consultar-cliente-vip-emita');

        $modo = $this->modo($modo);
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
                    $resultado = $this->consulta->pagina($modo, $texto, $pagina);
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
            'modo' => $modo,
            'texto' => $texto,
            'consultar' => $consultar,
            'aviso' => $aviso,
            'error' => $error,
            'filas' => $filas,
            'total' => $total,
            'filtrosQuery' => $this->filtrosQuery($texto, $consultar),
            'titulo' => $this->titulo($modo),
            'etiquetaCampo' => $modo === EmitaClienteVipConsulta::MODO_ALIAS ? 'Alias' : 'Nombre y apellido',
            'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([
                (object) ['nombreempresa' => (string) config('app.empresa')],
            ])),
        ]);
    }

    public function exportar(Request $request, string $modo, string $formato)
    {
        can('consultar-cliente-vip-emita');

        $modo = $this->modo($modo);
        $texto = trim((string) $request->input('texto', ''));
        if (! EmitaClienteVipConsulta::textoValido($texto)) {
            return redirect()->route('consultar_cliente_vip_emita', ['modo' => $modo])
                ->with('errores', ['Indique al menos '.EmitaClienteVipConsulta::LONGITUD_MINIMA.' caracteres para exportar.']);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        try {
            $resultado = $this->consulta->exportar($modo, $texto);
        } catch (RuntimeException $e) {
            return redirect()->route('consultar_cliente_vip_emita', ['modo' => $modo, 'texto' => $texto, 'consultar' => 1])
                ->with('errores', [$e->getMessage()]);
        }

        $titulo = $this->titulo($modo);
        $subtitulo = ($modo === EmitaClienteVipConsulta::MODO_ALIAS ? 'Alias' : 'Nombre').': '.$texto;
        if ($resultado['truncado']) {
            $subtitulo .= ' (se exportan los primeros '.EmitaClienteVipConsulta::TOPE_EXPORTACION.' de '.$resultado['total'].')';
        }

        $base = $modo === EmitaClienteVipConsulta::MODO_ALIAS
            ? 'cliente_vip_emita_por_alias'
            : 'cliente_vip_emita_por_nombre';

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

        return redirect()->route('consultar_cliente_vip_emita', $this->filtrosQuery($texto, true) + ['modo' => $modo]);
    }

    private function modo(string $modo): string
    {
        if (! in_array($modo, [EmitaClienteVipConsulta::MODO_NOMBRE, EmitaClienteVipConsulta::MODO_ALIAS], true)) {
            abort(404);
        }

        return $modo;
    }

    private function titulo(string $modo): string
    {
        return $modo === EmitaClienteVipConsulta::MODO_ALIAS
            ? 'Clientes VIP Emita por alias'
            : 'Clientes VIP Emita por nombre';
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
