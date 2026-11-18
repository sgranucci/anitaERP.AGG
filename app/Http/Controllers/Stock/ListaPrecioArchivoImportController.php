<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Listaprecio;
use App\Services\Stock\ListaPrecioArchivoImportPreviewService;
use App\Services\Stock\ListaPrecioArchivoImportService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\ListaPrecioArchivoParserSupport;
use Illuminate\Http\Request;

class ListaPrecioArchivoImportController extends Controller
{
    public function __construct(
        private ListaPrecioArchivoImportPreviewService $previewService,
        private ListaPrecioArchivoImportService $importService,
    ) {}

    public function crear()
    {
        $this->assertInterforming();
        can('crear-precios');

        $moneda = Moneda::query()->select('id', 'codigo', 'nombre')->find(1);
        $listaprecioId = (int) old('listaprecio_id', 0);
        $lista = $listaprecioId > 0
            ? Listaprecio::query()->select('id', 'codigo', 'nombre')->find($listaprecioId)
            : null;

        return view('stock.precio.importar_lista_archivo', [
            'monedaId' => (int) old('moneda_id', $moneda->id ?? 1),
            'monedaCodigo' => (string) old('moneda_codigo', $moneda->codigo ?? ''),
            'monedaNombre' => (string) old('moneda_nombre', $moneda->nombre ?? 'PESOS'),
            'listaprecioId' => $listaprecioId > 0 ? $listaprecioId : '',
            'listaprecioCodigo' => (string) old('listaprecio_codigo', $lista->codigo ?? ''),
            'listaprecioNombre' => (string) old('listaprecio_nombre', $lista->nombre ?? ''),
            'fechavigencia' => (string) old('fechavigencia', date('Y-m-d')),
        ]);
    }

    public function preview(Request $request)
    {
        $this->assertInterforming();
        can('crear-precios');

        $request->validate([
            'file' => 'required|file',
            'listaprecio_id' => 'nullable|integer|exists:listaprecio,id',
        ]);

        $this->assertExtensionArchivo($request);

        $preview = $this->previewService->previsualizar(
            $request->file('file'),
            $request->filled('listaprecio_id') ? (int) $request->input('listaprecio_id') : null
        );

        return response()->json($preview);
    }

    public function importar(Request $request)
    {
        $this->assertInterforming();
        can('crear-precios');

        $request->validate([
            'file' => 'required|file',
            'listaprecio_id' => 'required|integer|exists:listaprecio,id',
            'moneda_id' => 'required|integer|exists:moneda,id',
            'fechavigencia' => 'required|string',
        ]);

        $this->assertExtensionArchivo($request);

        try {
            set_time_limit(0);
            $resultado = $this->importService->importar(
                $request->file('file'),
                (int) $request->input('listaprecio_id'),
                (int) $request->input('moneda_id'),
                (string) $request->input('fechavigencia')
            );

            return redirect()
                ->route('precio_importar_lista_archivo')
                ->withInput($request->except('file'))
                ->with('precio_archivo_import_resultado', $resultado);
        } catch (\Throwable $e) {
            return redirect()
                ->route('precio_importar_lista_archivo')
                ->withInput($request->except('file'))
                ->with('mensaje-error', $e->getMessage());
        }
    }

    private function assertInterforming(): void
    {
        abort_unless(EntornoEmpresaSupport::esInterforming(), 404);
    }

    private function assertExtensionArchivo(Request $request): void
    {
        $archivo = $request->file('file');
        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        if (! in_array($ext, ListaPrecioArchivoParserSupport::extensionesPermitidas(), true)) {
            abort(422, 'El archivo debe ser PDF o DOCX.');
        }
    }
}
