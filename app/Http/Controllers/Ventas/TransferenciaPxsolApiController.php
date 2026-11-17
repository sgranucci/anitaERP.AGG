<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\TransferenciaCrown\ClientePrecargaDesdePadronArcaService;
use App\Services\Ventas\TransferenciaCrown\TransferenciaPxsolApiService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\TransferenciaCrown\PxsolHotelesSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownControlSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownPreviewStore;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownResumenImportSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Transferencia de facturas hotel desde la API PXSOL.
 */
class TransferenciaPxsolApiController extends Controller
{
    private const PERMISO = 'transferir-facturas-pxsol-api-crown';

    public function __construct(
        private readonly TransferenciaPxsolApiService $service,
        private readonly TransferenciaCrownPreviewStore $preview,
        private readonly ClientePrecargaDesdePadronArcaService $precargaClientes,
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

        $hoteles = PxsolHotelesSupport::listar();

        return view('ventas.transferencia_pxsol_api.index', [
            'preview' => $preview,
            'token' => $token,
            'errorPreview' => $errorPreview,
            'hoteles' => $hoteles,
            'hotel' => old('hotel', $preview['hotel'] ?? ($hoteles[0]['codigo'] ?? '')),
            'puntoventa' => old('puntoventa', $preview['puntoventa'] ?? ''),
            'desde' => old('desde', $preview['desde'] ?? now()->toDateString()),
            'hasta' => old('hasta', $preview['hasta'] ?? now()->toDateString()),
            'controlPostImport' => session('control_transferencia'),
            'resumenCarga' => session('resumen_transferencia'),
        ]);
    }

    public function previsualizar(Request $request): RedirectResponse
    {
        $this->asegurar();
        $request->validate([
            'hotel' => 'required|string|max:20',
            'puntoventa' => 'nullable|string|max:20',
            'desde' => 'required|date_format:Y-m-d',
            'hasta' => 'required|date_format:Y-m-d',
        ]);

        try {
            $resultado = $this->service->previsualizar(
                (string) $request->input('hotel'),
                (string) $request->input('desde'),
                (string) $request->input('hasta'),
                (string) $request->input('puntoventa', ''),
                (int) auth()->id(),
            );
            $token = $this->preview->guardar((int) auth()->id(), [
                'origen' => 'pxsol_api',
                'titulo' => 'Facturas PXSOL (API)',
                'filas' => $resultado['filas'],
                'resumen' => $resultado['resumen'],
                'advertencias' => $resultado['advertencias'],
                'hotel' => $resultado['hotel'],
                'hotel_nombre' => $resultado['hotel_nombre'],
                'puntoventa' => $resultado['puntoventa'],
                'desde' => $resultado['desde'],
                'hasta' => $resultado['hasta'],
                'mostrar_pago' => true,
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('transferencia_pxsol_api_crown')
                ->withInput()
                ->with('mensaje_error', $e->getMessage());
        }

        return redirect()->route('transferencia_pxsol_api_crown', ['token' => $token]);
    }

    public function precargarClientes(Request $request): RedirectResponse
    {
        $this->asegurar();
        $token = trim((string) $request->input('token', ''));
        try {
            $guardado = $this->preview->leer((int) auth()->id(), $token);
        } catch (RuntimeException $e) {
            return redirect()->route('transferencia_pxsol_api_crown')
                ->with('mensaje_error', $e->getMessage());
        }

        $filas = is_array($guardado['filas'] ?? null) ? $guardado['filas'] : [];
        $cuits = $this->precargaClientes->cuitsFaltantesDesdeFilas($filas);
        if ($cuits === []) {
            return redirect()->route('transferencia_pxsol_api_crown', ['token' => $token])
                ->with('mensaje_error', 'No hay errores de cliente inexistente en este preview.');
        }

        $nombresPorCuit = [];
        foreach ($filas as $fila) {
            $cuit = preg_replace('/\D+/', '', (string) ($fila['cuit'] ?? '')) ?? '';
            if ($cuit === '' || isset($nombresPorCuit[$cuit])) {
                continue;
            }
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            if ($nombre !== '') {
                $nombresPorCuit[$cuit] = $nombre;
            }
        }

        $resultado = $this->precargaClientes->precargar($cuits, $nombresPorCuit);
        $mensaje = $this->mensajePrecarga($resultado);

        // Reconsultar API con los mismos filtros para que el preview tome los clientes nuevos.
        try {
            $nuevo = $this->service->previsualizar(
                (string) ($guardado['hotel'] ?? ''),
                (string) ($guardado['desde'] ?? ''),
                (string) ($guardado['hasta'] ?? ''),
                (string) ($guardado['puntoventa'] ?? ''),
                (int) auth()->id(),
            );
            $tokenNuevo = $this->preview->guardar((int) auth()->id(), [
                'origen' => 'pxsol_api',
                'titulo' => 'Facturas PXSOL (API)',
                'filas' => $nuevo['filas'],
                'resumen' => $nuevo['resumen'],
                'advertencias' => array_values(array_merge(
                    $nuevo['advertencias'] ?? [],
                    $resultado['advertencias'] ?? [],
                )),
                'hotel' => $nuevo['hotel'],
                'hotel_nombre' => $nuevo['hotel_nombre'],
                'puntoventa' => $nuevo['puntoventa'],
                'desde' => $nuevo['desde'],
                'hasta' => $nuevo['hasta'],
                'mostrar_pago' => true,
                'precarga_clientes' => $resultado,
            ]);
            $this->preview->olvidar($token);
        } catch (\Throwable $e) {
            return redirect()->route('transferencia_pxsol_api_crown', ['token' => $token])
                ->with('mensaje', $mensaje)
                ->with('mensaje_error', 'Clientes procesados, pero falló la reconsulta del preview: '.$e->getMessage())
                ->with('precarga_clientes', $resultado);
        }

        return redirect()->route('transferencia_pxsol_api_crown', ['token' => $tokenNuevo])
            ->with('mensaje', $mensaje)
            ->with('precarga_clientes', $resultado);
    }

    public function confirmar(Request $request): RedirectResponse
    {
        $this->asegurar();
        $token = trim((string) $request->input('token', ''));
        try {
            $guardado = $this->preview->leer((int) auth()->id(), $token);
            $resultado = $this->service->confirmar($guardado['filas'] ?? [], (int) auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->route('transferencia_pxsol_api_crown')
                ->with('mensaje_error', $e->getMessage());
        }

        $this->preview->olvidar($token);
        $control = TransferenciaCrownControlSupport::analizarLuegoDeImportar(
            'arca',
            '',
            '',
            1,
            $guardado['filas'] ?? [],
        );
        [$desde, $hasta] = TransferenciaCrownControlSupport::rangoFechasDesdeFilas($guardado['filas'] ?? []);
        $resumenCarga = TransferenciaCrownResumenImportSupport::conUrlsMayor(
            TransferenciaCrownResumenImportSupport::armar([
                'origen' => 'arca',
                'titulo' => 'Carga PXSOL API',
                'sala' => (string) ($guardado['hotel_nombre'] ?? $guardado['hotel'] ?? ''),
                'desde' => $desde,
                'hasta' => $hasta,
                'empresa_id' => 1,
                'preview' => $guardado,
                'resultado' => $resultado,
            ])
        );

        return redirect()
            ->to(route('transferencia_pxsol_api_crown').'#resumen-post-import')
            ->with('mensaje', $this->mensajeConfirmacion($resultado))
            ->with('control_transferencia', $control)
            ->with('resumen_transferencia', $resumenCarga);
    }

    /**
     * @param  array{
     *     creados?: list<array{cuit: string, codigo: string, id: int, nombre: string}>,
     *     existentes?: list<array{cuit: string, codigo: string, id: int, nombre: string}>,
     *     errores?: list<array{cuit: string, mensaje: string}>
     * }  $resultado
     */
    private function mensajePrecarga(array $resultado): string
    {
        $partes = [];
        $creados = count($resultado['creados'] ?? []);
        $existentes = count($resultado['existentes'] ?? []);
        $errores = count($resultado['errores'] ?? []);
        if ($creados > 0) {
            $detalle = [];
            foreach (array_slice($resultado['creados'] ?? [], 0, 8) as $item) {
                $detalle[] = ($item['codigo'] ?? '').' '.$this->formatearCuitCorto((string) ($item['cuit'] ?? ''));
            }
            $partes[] = 'Clientes creados: '.$creados.($detalle !== [] ? ' ('.implode(', ', $detalle).')' : '');
        }
        if ($existentes > 0) {
            $partes[] = 'Ya existían: '.$existentes;
        }
        if ($errores > 0) {
            $msgs = [];
            foreach (array_slice($resultado['errores'] ?? [], 0, 3) as $err) {
                $msgs[] = (string) ($err['mensaje'] ?? '');
            }
            $partes[] = 'Con error: '.$errores.($msgs !== [] ? ' — '.implode(' | ', $msgs) : '');
        }
        if ($partes === []) {
            return 'No se precargó ningún cliente.';
        }

        return implode('. ', $partes).'.';
    }

    private function formatearCuitCorto(string $cuit): string
    {
        $cuit = preg_replace('/\D+/', '', $cuit) ?? '';
        if (strlen($cuit) !== 11) {
            return $cuit;
        }

        return substr($cuit, 0, 2).'-'.substr($cuit, 2, 8).'-'.substr($cuit, 10, 1);
    }

    /**
     * @param  array{grabados?: int, enganchados?: int, errores?: list<string>}  $resultado
     */
    private function mensajeConfirmacion(array $resultado): string
    {
        $grabados = (int) ($resultado['grabados'] ?? 0);
        $mensaje = $grabados > 0
            ? 'Se generaron '.$grabados.' facturas con su asiento.'
            : 'No había facturas nuevas para generar (las existentes no se pisaron).';
        $errores = $resultado['errores'] ?? [];
        if ($errores !== []) {
            $mensaje .= ' Con error: '.count($errores).'. '.implode(' ', array_slice($errores, 0, 5));
        }

        return $mensaje;
    }

    private function asegurar(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
        can(self::PERMISO);
    }
}
