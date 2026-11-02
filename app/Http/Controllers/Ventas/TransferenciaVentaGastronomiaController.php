<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\TransferenciaCrown\TransferenciaVentaGastronomiaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownControlSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownPreviewStore;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownResumenImportSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownSalas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Transferencia de ventas de gastronomía por sala (p-tr-ven.c).
 */
class TransferenciaVentaGastronomiaController extends Controller
{
    private const PERMISO = 'transferir-ventas-gastronomia-crown';

    public function __construct(
        private readonly TransferenciaVentaGastronomiaService $service,
        private readonly TransferenciaCrownPreviewStore $preview,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $this->asegurar();
        $token = trim((string) $request->query('token', ''));
        $preview = null;
        $errorPreview = null;
        if ($token !== '') {
            try {
                $preview = $this->preview->leer((int) auth()->id(), $token);
            } catch (RuntimeException $e) {
                $errorPreview = $e->getMessage();
            }
        }

        return view('ventas.transferencia_gastronomia.index', [
            'salas' => TransferenciaCrownSalas::gastronomia(),
            'preview' => $preview,
            'token' => $token,
            'errorPreview' => $errorPreview,
            'sala' => (string) $request->query('sala', ''),
            'desde' => (string) $request->query('desde', date('Y-m-01')),
            'hasta' => (string) $request->query('hasta', date('Y-m-d')),
            'controlPostImport' => session('control_transferencia'),
            'resumenCarga' => session('resumen_transferencia'),
        ]);
    }

    public function previsualizar(Request $request): RedirectResponse
    {
        $this->asegurar();
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '600');
        $salaCodigo = strtoupper(trim((string) $request->input('sala', '')));
        $desde = (string) $request->input('desde', '');
        $hasta = (string) $request->input('hasta', '');
        $sala = TransferenciaCrownSalas::gastronomiaPorCodigo($salaCodigo);
        if ($sala === null || $desde === '' || $hasta === '' || $hasta < $desde) {
            return redirect()->route('transferencia_gastronomia_crown')
                ->with('mensaje_error', 'Elegí la sala y un rango de fechas válido.');
        }

        try {
            $resultado = $this->service->previsualizar($sala, $desde, $hasta);
            $token = $this->preview->guardar((int) auth()->id(), [
                'origen' => 'gastronomia',
                'titulo' => 'Ventas de '.$sala['nombre'],
                'sala' => $sala['codigo'],
                'sala_nombre' => $sala['nombre'],
                'desde' => $desde,
                'hasta' => $hasta,
                'filas' => $resultado['filas'],
                'resumen' => $resultado['resumen'],
                'advertencias' => $resultado['advertencias'],
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('transferencia_gastronomia_crown', [
                'sala' => $salaCodigo,
                'desde' => $desde,
                'hasta' => $hasta,
            ])->with('mensaje_error', $e->getMessage());
        }

        return redirect()->route('transferencia_gastronomia_crown', [
            'token' => $token,
            'sala' => $salaCodigo,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }

    public function confirmar(Request $request): RedirectResponse
    {
        $this->asegurar();
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '900');
        $token = trim((string) $request->input('token', ''));
        try {
            $guardado = $this->preview->leer((int) auth()->id(), $token);
            $resultado = $this->service->confirmar(
                $guardado['filas'] ?? [],
                (int) auth()->id(),
                (string) ($guardado['sala_nombre'] ?? ''),
            );
        } catch (RuntimeException $e) {
            return redirect()->route('transferencia_gastronomia_crown')
                ->with('mensaje_error', $e->getMessage());
        }

        $this->preview->olvidar($token);
        $partes = [];
        if ((int) $resultado['grabados'] > 0) {
            $partes[] = 'Se generaron '.$resultado['grabados'].' facturas con su asiento';
        }
        if ((int) ($resultado['enganchados'] ?? 0) > 0) {
            $partes[] = 'se enganchó el asiento de '.$resultado['enganchados'].' facturas que ya estaban';
        }
        $mensaje = $partes === [] ? 'No había facturas para generar.' : implode(' y ', $partes).'.';
        if ($resultado['errores'] !== []) {
            $mensaje .= ' Con error: '.count($resultado['errores']).'. '.implode(' ', array_slice($resultado['errores'], 0, 5));
        }

        $control = TransferenciaCrownControlSupport::analizarLuegoDeImportar(
            'gastro',
            (string) ($guardado['desde'] ?? ''),
            (string) ($guardado['hasta'] ?? ''),
            1,
            $guardado['filas'] ?? [],
        );
        $resumenCarga = TransferenciaCrownResumenImportSupport::conUrlsMayor(
            TransferenciaCrownResumenImportSupport::armar([
                'origen' => 'gastro',
                'titulo' => 'Carga de '.((string) ($guardado['sala_nombre'] ?? 'gastronomía')),
                'sala' => (string) ($guardado['sala_nombre'] ?? ''),
                'desde' => (string) ($guardado['desde'] ?? ''),
                'hasta' => (string) ($guardado['hasta'] ?? ''),
                'empresa_id' => 1,
                'preview' => $guardado,
                'resultado' => $resultado,
            ])
        );

        return redirect()
            ->to(route('transferencia_gastronomia_crown').'#resumen-post-import')
            ->with('mensaje', $mensaje)
            ->with('control_transferencia', $control)
            ->with('resumen_transferencia', $resumenCarga);
    }

    private function asegurar(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
        can(self::PERMISO);
    }
}
