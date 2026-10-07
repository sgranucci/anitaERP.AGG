<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\PercepcionSufridaListadoExport;
use App\Exports\Contable\PercepcionSufridaReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\PercepcionSufrida\PercepcionSufridaProcesoService;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaCorteSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaListadoFiltros;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class PercepcionSufridaController extends Controller
{
    public function __construct(
        private readonly PercepcionSufridaProcesoService $proceso,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function sifere(Request $request)
    {
        return $this->pantalla($request, PercepcionSufridaCorteSupport::TIPO_IIBB);
    }

    public function percepcionIva(Request $request)
    {
        return $this->pantalla($request, PercepcionSufridaCorteSupport::TIPO_IVA);
    }

    public function exportarSifere(Request $request)
    {
        return $this->descargarArchivo($request, PercepcionSufridaCorteSupport::TIPO_IIBB);
    }

    public function exportarPercepcionIva(Request $request)
    {
        return $this->descargarArchivo($request, PercepcionSufridaCorteSupport::TIPO_IVA);
    }

    public function listarSifere(Request $request, ?string $formato = null)
    {
        return $this->exportarListado($request, PercepcionSufridaCorteSupport::TIPO_IIBB, $formato);
    }

    public function listarPercepcionIva(Request $request, ?string $formato = null)
    {
        return $this->exportarListado($request, PercepcionSufridaCorteSupport::TIPO_IVA, $formato);
    }

    public function exportarReporteSifere(Request $request)
    {
        can('exportar-sifere');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $jurisdiccion = (int) $request->query('jurisdiccion');
        if (! in_array($jurisdiccion, [901, 902], true)) {
            return redirect()->route('sifere');
        }

        $filtros = PercepcionSufridaListadoFiltros::resolverDesdeRequest($request);
        if (! PercepcionSufridaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('sifere');
        }

        $resultado = $this->proceso->generar(PercepcionSufridaCorteSupport::TIPO_IIBB, $filtros);
        $filas = array_values(array_filter(
            $resultado['cruzados'] ?? [],
            static fn (array $linea): bool => (int) ($linea['jurisdiccion'] ?? 0) === $jurisdiccion,
        ));
        $empresa = $this->empresaRepository->allFiltrado()
            ->firstWhere('id', (int) ($filtros['empresa_id'] ?? 0));
        $nombreEmpresa = (string) ($empresa->nombre ?? '');
        $periodo = PercepcionSufridaListadoFiltros::formatearPeriodoTexto($filtros);
        $titulo = $jurisdiccion === 901
            ? 'SiFeRe 901 — CABA'
            : 'SiFeRe 902 — Buenos Aires';
        $subtitulo = trim($nombreEmpresa.($periodo !== '' ? ' — '.$periodo : ''));

        return (new PercepcionSufridaReporteExport(
            [(object) ['nombreempresa' => $nombreEmpresa]],
            $filas,
            $titulo,
            $subtitulo,
            $jurisdiccion,
        ))->download('sifere_'.$jurisdiccion.'.xlsx');
    }

    private function pantalla(Request $request, string $tipo)
    {
        can($this->permisoListar($tipo));

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->aplicarPreferencias(
            $request,
            PercepcionSufridaListadoFiltros::resolverDesdeRequest($request),
            $empresaQuery,
            $tipo,
        );

        $consultado = false;
        $resultado = null;
        $error = '';

        if ($request->boolean('consultar') && PercepcionSufridaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');
            ReportePreferenciasUsuario::persistir($this->clavePreferencias($tipo), [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);
            try {
                $resultado = $this->proceso->generar($tipo, $filtros);
                $consultado = true;
            } catch (\Throwable $e) {
                report($e);
                $error = 'No se pudo armar el cruce. '.$e->getMessage();
            }
        }

        $filtrosQuery = PercepcionSufridaListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }

        return view('contable.percepcion_sufrida.index', $this->presentacion($tipo, [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'error' => $error,
            'periodo_texto' => PercepcionSufridaListadoFiltros::formatearPeriodoTexto($filtros),
        ]));
    }

    private function descargarArchivo(Request $request, string $tipo)
    {
        can($this->permisoExportar($tipo));

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PercepcionSufridaListadoFiltros::resolverDesdeRequest($request);
        if (! PercepcionSufridaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route($this->rutaIndex($tipo));
        }

        $resultado = $this->proceso->generar($tipo, $filtros);
        $jurisdiccion = (int) $request->query('jurisdiccion');
        if ($tipo === PercepcionSufridaCorteSupport::TIPO_IIBB && in_array($jurisdiccion, [901, 902], true)) {
            $nombre = 'psif-'.$jurisdiccion.'.txt';
            $contenido = (string) ($resultado['archivo_'.$jurisdiccion] ?? '');
        } else {
            $nombre = (string) ($resultado['nombre_archivo'] ?? 'archivo.txt');
            $contenido = (string) ($resultado['archivo'] ?? '');
        }
        $contentType = $tipo === PercepcionSufridaCorteSupport::TIPO_IVA
            ? 'text/csv; charset=UTF-8'
            : 'text/plain; charset=UTF-8';

        return Response::make($contenido, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    private function exportarListado(Request $request, string $tipo, ?string $formato)
    {
        can($this->permisoExportar($tipo));

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PercepcionSufridaListadoFiltros::resolverDesdeRequest($request);
        if (! PercepcionSufridaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route($this->rutaIndex($tipo));
        }

        $resultado = $this->proceso->generar($tipo, $filtros);
        $empresa = $this->empresaRepository->allFiltrado()
            ->firstWhere('id', (int) ($filtros['empresa_id'] ?? 0));
        $nombreEmpresa = (string) ($empresa->nombre ?? '');
        $filasParaLogo = [(object) ['nombreempresa' => $nombreEmpresa]];
        $meta = $this->meta($tipo);
        $periodo = PercepcionSufridaListadoFiltros::formatearPeriodoTexto($filtros);
        $titulo = $meta['titulo_listado'];
        $subtitulo = trim($nombreEmpresa.($periodo !== '' ? ' — '.$periodo : ''));
        $diferencias = $resultado['diferencias'] ?? [];
        $totales = $resultado['totales'] ?? [];
        $esIibb = $tipo === PercepcionSufridaCorteSupport::TIPO_IIBB;

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $html = view('contable.percepcion_sufrida.listado', [
                    'filasParaLogo' => $filasParaLogo,
                    'diferencias' => $diferencias,
                    'totales' => $totales,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'esIibb' => $esIibb,
                ])->render();
                $ruta = storage_path('pdf/listados/listado_percepcion_sufrida_'.date('Ymd_His').'.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => $titulo,
                ]);

                return response()->download($ruta);

            case 'EXCEL':
                return (new PercepcionSufridaListadoExport(
                    $filasParaLogo,
                    $diferencias,
                    $totales,
                    $titulo,
                    $subtitulo,
                    $esIibb,
                ))->download($meta['archivo_listado'].'.xlsx');

            case 'CSV':
                return (new PercepcionSufridaListadoExport(
                    $filasParaLogo,
                    $diferencias,
                    $totales,
                    $titulo,
                    $subtitulo,
                    $esIibb,
                ))->download($meta['archivo_listado'].'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route($this->rutaIndex($tipo), array_merge(
            PercepcionSufridaListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1],
        ));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferencias(Request $request, array $filtros, $empresaQuery, string $tipo): array
    {
        if (! $request->boolean('consultar') && (int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $empresaPref = ReportePreferenciasUsuario::leerEmpresaId($this->clavePreferencias($tipo));
            if ($empresaPref && $empresaQuery->contains('id', $empresaPref)) {
                $filtros['empresa_id'] = $empresaPref;
            }
        }

        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function presentacion(string $tipo, array $datos): array
    {
        return array_merge($datos, $this->meta($tipo), [
            'fecha_limite' => PercepcionSufridaCorteSupport::fechaLimiteDma(),
            'cuenta_texto' => $this->cuentaTexto(PercepcionSufridaCorteSupport::cuentaDeTipo($tipo)),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function meta(string $tipo): array
    {
        if ($tipo === PercepcionSufridaCorteSupport::TIPO_IVA) {
            return [
                'titulo' => 'Percepciones de IVA sufridas',
                'titulo_listado' => 'Diferencias — percepciones de IVA sufridas',
                'ayuda' => 'Cruza el mayor de la cuenta de percepción de IVA sufrida contra los conceptos de percepción del comprobante. El archivo es el CSV de IVA Simple (perciva.csv).',
                'archivo_boton' => 'Descargar perciva.csv',
                'archivo_listado' => 'diferencias_percepciones_iva',
                'ruta_index' => 'percepciones_iva',
                'ruta_exportar' => 'exportar_percepciones_iva',
                'ruta_listar' => 'listar_percepciones_iva',
                'permiso_exportar' => 'exportar-percepcion-iva',
                'es_iibb' => '0',
            ];
        }

        return [
            'titulo' => 'SiFeRe — percepciones de IIBB sufridas',
            'titulo_listado' => 'Diferencias — percepciones de IIBB sufridas',
            'ayuda' => 'Cruza el mayor de la cuenta de percepción de ingresos brutos sufrida contra los reportes 901 (CABA) y 902 (Buenos Aires). Los archivos de importación salen separados por jurisdicción.',
            'archivo_boton' => 'Descargar psif.txt',
            'archivo_listado' => 'diferencias_sifere',
            'ruta_index' => 'sifere',
            'ruta_exportar' => 'exportar_sifere',
            'ruta_listar' => 'listar_sifere',
            'permiso_exportar' => 'exportar-sifere',
            'es_iibb' => '1',
        ];
    }

    private function permisoListar(string $tipo): string
    {
        return $tipo === PercepcionSufridaCorteSupport::TIPO_IVA
            ? 'listar-percepcion-iva'
            : 'listar-sifere';
    }

    private function permisoExportar(string $tipo): string
    {
        return $tipo === PercepcionSufridaCorteSupport::TIPO_IVA
            ? 'exportar-percepcion-iva'
            : 'exportar-sifere';
    }

    private function rutaIndex(string $tipo): string
    {
        return $tipo === PercepcionSufridaCorteSupport::TIPO_IVA ? 'percepciones_iva' : 'sifere';
    }

    private function clavePreferencias(string $tipo): string
    {
        return $tipo === PercepcionSufridaCorteSupport::TIPO_IVA ? 'percepciones_iva' : 'sifere';
    }

    private function cuentaTexto(int $codigo): string
    {
        $texto = (string) $codigo;
        if (strlen($texto) <= 3) {
            return $texto;
        }

        return substr($texto, 0, -3).'-'.substr($texto, -3);
    }
}
