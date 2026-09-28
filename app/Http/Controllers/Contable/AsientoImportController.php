<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Contable\AsientoImportPreviewService;
use App\Services\Contable\AsientoImportService;
use App\Services\Contable\AsientoPegadoTextoService;
use Illuminate\Http\Request;
use Throwable;

class AsientoImportController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private TipoasientoRepositoryInterface $tipoasientoRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private AsientoImportPreviewService $previewService,
        private AsientoImportService $importService,
    ) {
    }

    public function crear()
    {
        can('crear-asiento');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $tipoasiento_query = $this->tipoasientoRepository->all();
        $moneda_query = $this->monedaRepository->all();

        return view('contable.asiento.crearimportacion', compact(
            'empresa_query',
            'tipoasiento_query',
            'moneda_query'
        ));
    }

    public function preview(Request $request)
    {
        can('crear-asiento');

        $request->validate([
            'file' => 'required|file|mimetypes:application/vnd.ms-office,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain',
            'empresa_id' => 'nullable|integer|exists:empresa,id',
            'moneda_id' => 'nullable|integer|exists:moneda,id',
            'col_cuenta' => 'nullable|string|max:80',
            'col_debe' => 'nullable|string|max:80',
            'col_haber' => 'nullable|string|max:80',
            'col_centrocosto' => 'nullable|string|max:80',
            'col_moneda' => 'nullable|string|max:80',
            'col_cotizacion' => 'nullable|string|max:80',
            'col_detalle' => 'nullable|string|max:80',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:100',
        ]);

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Empresa no válida o no asignada al usuario.',
            ], 422);
        }

        $preview = $this->previewService->previsualizar(
            $request->file('file'),
            $empresaId,
            $request->filled('moneda_id') ? (int) $request->input('moneda_id') : null,
            $request->input('col_cuenta'),
            $request->input('col_debe'),
            $request->input('col_haber'),
            $request->input('col_centrocosto'),
            $request->input('col_moneda'),
            $request->input('col_cotizacion'),
            $request->input('col_detalle'),
            $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null,
            $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null
        );

        return response()->json($preview);
    }

    public function importar(Request $request)
    {
        can('crear-asiento');

        foreach (['fila_encabezado', 'observacion', 'col_centrocosto', 'col_moneda', 'col_cotizacion', 'col_detalle'] as $campoOpcional) {
            if (! $request->filled($campoOpcional)) {
                $request->merge([$campoOpcional => null]);
            }
        }

        $request->validate([
            'file' => 'required|file|mimetypes:application/vnd.ms-office,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain',
            'empresa_id' => 'required|integer|exists:empresa,id',
            'tipoasiento_id' => 'required|integer|exists:tipoasiento,id',
            'fecha' => 'required|date',
            'observacion' => 'nullable|string|max:500',
            'moneda_id' => 'required|integer|exists:moneda,id',
            'col_cuenta' => 'nullable|string|max:80',
            'col_debe' => 'nullable|string|max:80',
            'col_haber' => 'nullable|string|max:80',
            'col_centrocosto' => 'nullable|string|max:80',
            'col_moneda' => 'nullable|string|max:80',
            'col_cotizacion' => 'nullable|string|max:80',
            'col_detalle' => 'nullable|string|max:80',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:100',
            'confirmar_pendiente_aprobacion' => 'nullable|boolean',
        ]);

        try {
            set_time_limit(0);

            session(['empresa_id' => (int) $request->input('empresa_id')]);
            session(['tipoasiento_id' => (int) $request->input('tipoasiento_id')]);

            $resumen = $this->importService->importar(
                $request->file('file'),
                (int) $request->input('empresa_id'),
                (int) $request->input('tipoasiento_id'),
                (string) $request->input('fecha'),
                $request->input('observacion'),
                (int) $request->input('moneda_id'),
                $request->input('col_cuenta'),
                $request->input('col_debe'),
                $request->input('col_haber'),
                $request->input('col_centrocosto'),
                $request->input('col_moneda'),
                $request->input('col_cotizacion'),
                $request->input('col_detalle'),
                $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null,
                $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null,
                $request->boolean('confirmar_pendiente_aprobacion')
            );
        } catch (Throwable $e) {
            return redirect()
                ->route('crear_importacion_asiento')
                ->withInput($request->except(['file']))
                ->with('mensaje-error', $e->getMessage());
        }

        $mensaje = 'Asiento importado: N° '.$resumen['numeroasiento']
            .' con '.(int) $resumen['movimientos'].' movimiento(s).';
        if (! empty($resumen['pendiente_aprobacion'])) {
            $mensaje .= ' Quedó pendiente de aprobación.';
        }

        return redirect()
            ->route('crear_importacion_asiento')
            ->with('asiento_import_resultado', $resumen)
            ->with('mensaje', $mensaje);
    }

    public function pegar(Request $request, AsientoPegadoTextoService $pegadoService)
    {
        if (! can('crear-asiento', false) && ! can('editar-asiento', false)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No tiene permiso para cargar asientos.',
            ], 403);
        }

        $request->validate([
            'texto' => 'nullable|string|max:200000',
            'archivo' => 'nullable|file|max:15360',
            'empresa_id' => 'nullable|integer',
        ]);

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Empresa no válida o no asignada al usuario.',
            ], 422);
        }

        if ($request->hasFile('archivo')) {
            $archivo = $request->file('archivo');
            $extension = strtolower((string) $archivo->getClientOriginalExtension());
            if (! in_array($extension, ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'tif', 'tiff', 'bmp'], true)) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'El archivo tiene que ser un PDF o una imagen (jpg, png).',
                ], 422);
            }

            $ruta = $archivo->getRealPath();
            if ($ruta === false || $ruta === '') {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'No se pudo leer el archivo subido.',
                ], 422);
            }

            try {
                return response()->json($pegadoService->decodificarArchivo($ruta, $archivo->getMimeType(), $empresaId));
            } catch (Throwable $e) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => $e->getMessage(),
                ], 422);
            }
        }

        if (! $request->filled('texto')) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Suba el PDF o la imagen, o pegue el texto del asiento.',
            ], 422);
        }

        return response()->json($pegadoService->decodificar((string) $request->input('texto'), $empresaId));
    }
}
