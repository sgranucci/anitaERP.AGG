<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\Depmae;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Stock\LoteRepositoryInterface;
use App\Services\Stock\MovimientoStockService;
use App\Support\Stock\MovimientoStockAltaMasivaSupport;
use App\Support\Stock\MovimientoStockPreferenciasUsuario;
use App\Support\Stock\UsuarioTipotransaccionStockAutorizado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovimientoStockAltaMasivaController extends Controller
{
    public function __construct(
        private MovimientoStockService $movimientoStockService,
        private EmpresaRepositoryInterface $empresaRepository,
        private LoteRepositoryInterface $loteRepository,
    ) {
    }

    public function crear(): View
    {
        can('crear-movimientos-de-stock');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) old('empresa_id', $empresaQuery->first()->id ?? 0);
        // No sugerir el último depósito usado: en el alta masiva el operador lo elige cada vez.
        $depositoId = (int) old('deposito_id', 0);
        $deposito = $depositoId > 0 ? Depmae::query()->find($depositoId) : null;
        $tipoId = (int) old('tipotransaccion_stock_id', MovimientoStockPreferenciasUsuario::resolverTipoTransaccionDefaultId() ?? 0);
        $tipo = $tipoId > 0 ? Tipotransaccion_Stock::query()->find($tipoId) : null;
        if ($tipo && ! $this->tipoSirveParaAltaMasiva($tipo)) {
            $tipo = null;
            $tipoId = 0;
        }

        return view('stock.movimientostock.alta_masiva', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'depositoId' => $depositoId,
            'depositoCodigo' => old('deposito_codigo', $deposito->codigo ?? ''),
            'depositoNombre' => old('deposito_descripcion', $deposito->nombre ?? ''),
            'depositoCanal' => $deposito->canal ?? '',
            'tipoId' => $tipo ? (int) $tipo->id : 0,
            'tipoAbreviatura' => old('tipotransaccion_abreviatura', $tipo->abreviatura ?? ''),
            'tipoNombre' => old('tipotransaccion_nombre', $tipo->nombre ?? ''),
            'tipoPidePrecio' => (bool) ($tipo->pide_precio ?? false),
            'lote_query' => $this->loteRepository->all(),
            'esFerli' => MovimientoStockAltaMasivaSupport::esParaLocales(),
            'canalEtiqueta' => MovimientoStockAltaMasivaSupport::etiquetaCanal(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        can('crear-movimientos-de-stock');

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:8192',
        ]);

        $depositoId = (int) $request->input('deposito_id', 0);
        $empresaId = (int) $request->input('empresa_id', 0);
        $tipo = $this->tipoDesdeRequest($request);

        try {
            $preview = MovimientoStockAltaMasivaSupport::previsualizar(
                $request->file('archivo'),
                $depositoId,
                $empresaId,
                $tipo
            );
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No se pudo leer el Excel: '.$e->getMessage(),
            ], 422);
        }

        $lineas = [];
        foreach ($preview['lineas'] as $linea) {
            $lineas[] = [
                'fila' => $linea['fila'],
                'hoja' => $linea['hoja'] ?? '',
                'modulos' => $linea['modulos'] ?? null,
                'sku_excel' => $linea['sku_excel'],
                'sku' => $linea['sku'],
                'articulo' => $linea['articulo'],
                'combinacion' => $linea['combinacion'],
                'color_excel' => $linea['color_excel'],
                'color_maestro' => $linea['color_maestro'],
                'talles_txt' => $linea['talles_txt'],
                'pares' => $linea['pares'],
                'errores' => $linea['errores'],
                'avisos' => $linea['avisos'],
            ];
        }

        return response()->json([
            'ok' => true,
            'puede_grabar' => MovimientoStockAltaMasivaSupport::puedeGrabar($preview),
            'formato' => $preview['formato'] ?? 'curva',
            'formato_etiqueta' => $preview['formato_etiqueta'] ?? 'Curva de talles',
            'canal' => $preview['canal'],
            'canal_etiqueta' => $preview['canal_etiqueta'],
            'cabecera_errores' => $preview['cabecera_errores'],
            'cabecera_avisos' => $preview['cabecera_avisos'],
            'resumen' => $preview['resumen'],
            'pide_precio' => (bool) ($tipo->pide_precio ?? false),
            'lineas' => $lineas,
        ]);
    }

    public function grabar(Request $request)
    {
        can('crear-movimientos-de-stock');

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:8192',
            'fecha' => 'required|date',
            'empresa_id' => 'required|integer|exists:empresa,id',
            'deposito_id' => 'required|integer|exists:depmae,id',
            'tipotransaccion_stock_id' => 'required|integer|exists:tipotransaccion_stock,id',
            'leyenda' => 'nullable|string|max:200',
            'lote' => 'nullable|string|max:40',
            'loteimportacion_id' => 'nullable|integer|exists:lote,id',
            'precio' => 'nullable|numeric|min:0',
        ]);

        $empresaId = (int) $request->input('empresa_id');
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return redirect()->back()->withInput()->with('mensaje-error', 'La empresa no está asignada a su usuario.');
        }

        $tipo = $this->tipoDesdeRequest($request);
        $depositoId = (int) $request->input('deposito_id');

        try {
            $preview = MovimientoStockAltaMasivaSupport::previsualizar(
                $request->file('archivo'),
                $depositoId,
                $empresaId,
                $tipo
            );
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('mensaje-error', 'No se pudo leer el Excel: '.$e->getMessage());
        }

        if ((bool) ($tipo->pide_precio ?? false) && (float) $request->input('precio', 0) <= 0) {
            $preview['cabecera_errores'][] = 'Este tipo de transacción exige un precio mayor a cero.';
        }

        if (! MovimientoStockAltaMasivaSupport::puedeGrabar($preview)) {
            $msg = $preview['cabecera_errores'][0]
                ?? 'Hay líneas con error. Corrija el Excel o los datos del comprobante y vuelva a previsualizar.';

            return redirect()->back()->withInput()->with('mensaje-error', $msg);
        }

        $loteImportacionId = (int) $request->input('loteimportacion_id', 0);
        $payload = MovimientoStockAltaMasivaSupport::payloadParaGrabar($preview, [
            'tipotransaccion_stock_id' => (int) $tipo->id,
            'fecha' => (string) $request->input('fecha'),
            'empresa_id' => $empresaId,
            'deposito_id' => $depositoId,
            'lote' => (string) $request->input('lote', ''),
            'loteimportacion_id' => $loteImportacionId > 0 ? $loteImportacionId : null,
            'leyenda' => (string) $request->input('leyenda', ''),
            'precio' => (float) $request->input('precio', 0),
        ]);

        try {
            $data = $this->movimientoStockService->guardaMovimientoStock($payload, 'create');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('mensaje-error', $e->getMessage());
        }

        if (! is_array($data) || (int) ($data['id'] ?? 0) <= 0) {
            $texto = is_string($data) && $data !== '' ? $data : 'No se pudo grabar el movimiento.';

            return redirect()->back()->withInput()->with('mensaje-error', $texto);
        }

        MovimientoStockPreferenciasUsuario::persistirDesdeDatos($payload);

        $pares = (float) ($preview['resumen']['pares'] ?? 0);
        $mensaje = 'Movimiento '.$data['codigo'].' grabado con '
            .(int) $preview['resumen']['ok'].' líneas y '
            .rtrim(rtrim(number_format($pares, 2, '.', ''), '0'), '.')
            .' pares (catálogo '.($preview['canal_etiqueta'] ?? MovimientoStockAltaMasivaSupport::etiquetaCanal()).').';

        if (can('editar-movimientos-de-stock', false)) {
            return redirect()
                ->route('editar_movimientostock', ['id' => (int) $data['id']])
                ->with('mensaje', $mensaje);
        }

        return redirect()->route('movimientostock')->with('mensaje', $mensaje);
    }

    private function tipoDesdeRequest(Request $request): ?Tipotransaccion_Stock
    {
        $tipoId = (int) $request->input('tipotransaccion_stock_id', 0);
        if ($tipoId <= 0) {
            return null;
        }

        return Tipotransaccion_Stock::query()->find($tipoId);
    }

    private function tipoSirveParaAltaMasiva(Tipotransaccion_Stock $tipo): bool
    {
        $operacion = (string) ($tipo->operacion ?? '');
        if (! in_array($operacion, ['E', 'S'], true)) {
            return false;
        }
        if ((bool) $tipo->maneja_contabilidad || (bool) $tipo->alta_npu || (bool) $tipo->baja_npu) {
            return false;
        }

        return UsuarioTipotransaccionStockAutorizado::tipotransaccionAutorizada((int) $tipo->id);
    }
}
