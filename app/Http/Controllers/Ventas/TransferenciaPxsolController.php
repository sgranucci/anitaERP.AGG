<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\TransferenciaCrown\TransferenciaPxsolService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownArcaTipos;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownControlSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownPreviewStore;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownResumenImportSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Transferencia de facturas ARCA / PXSOL (p-tr-pxsol.c).
 */
class TransferenciaPxsolController extends Controller
{
    private const PERMISO = 'transferir-facturas-arca-crown';

    public function __construct(
        private readonly TransferenciaPxsolService $service,
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

        return view('ventas.transferencia_pxsol.index', [
            'preview' => $preview,
            'token' => $token,
            'errorPreview' => $errorPreview,
            'mapeo' => TransferenciaCrownArcaTipos::resolver((int) auth()->id()),
            'codigosArca' => TransferenciaCrownArcaTipos::CODIGOS,
            'opcionesTipo' => TransferenciaCrownArcaTipos::OPCIONES,
            'controlPostImport' => session('control_transferencia'),
            'resumenCarga' => session('resumen_transferencia'),
        ]);
    }

    public function previsualizar(Request $request): RedirectResponse
    {
        $this->asegurar();
        $request->validate([
            'archivo' => 'required|file|max:8192',
        ]);

        $archivo = $request->file('archivo');
        $extension = strtolower((string) ($archivo?->getClientOriginalExtension() ?? ''));
        if ($archivo === null || ! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            return redirect()->route('transferencia_pxsol_crown')
                ->with('mensaje_error', 'Elegí un Excel o CSV de ARCA.');
        }

        $mapeo = TransferenciaCrownArcaTipos::resolver((int) auth()->id(), (array) $request->input('tipos', []));
        TransferenciaCrownArcaTipos::recordar((int) auth()->id(), $mapeo);

        try {
            $resultado = $this->service->previsualizar($archivo->getRealPath() ?: $archivo->getPathname(), $mapeo);
            $token = $this->preview->guardar((int) auth()->id(), [
                'origen' => 'pxsol',
                'titulo' => 'Facturas ARCA',
                'filas' => $resultado['filas'],
                'resumen' => $resultado['resumen'],
                'advertencias' => $resultado['advertencias'],
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('transferencia_pxsol_crown')
                ->with('mensaje_error', $e->getMessage());
        }

        return redirect()->route('transferencia_pxsol_crown', ['token' => $token]);
    }

    public function confirmar(Request $request): RedirectResponse
    {
        $this->asegurar();
        $token = trim((string) $request->input('token', ''));
        try {
            $guardado = $this->preview->leer((int) auth()->id(), $token);
            $resultado = $this->service->confirmar($guardado['filas'] ?? [], (int) auth()->id());
        } catch (RuntimeException $e) {
            return redirect()->route('transferencia_pxsol_crown')
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
                'titulo' => 'Carga ARCA / PXSOL',
                'sala' => '',
                'desde' => $desde,
                'hasta' => $hasta,
                'empresa_id' => 1,
                'preview' => $guardado,
                'resultado' => $resultado,
            ])
        );

        return redirect()
            ->to(route('transferencia_pxsol_crown').'#resumen-post-import')
            ->with('mensaje', $this->mensajeConfirmacion($resultado))
            ->with('control_transferencia', $control)
            ->with('resumen_transferencia', $resumenCarga);
    }

    /**
     * @param  array{grabados?: int, enganchados?: int, errores?: list<string>}  $resultado
     */
    private function mensajeConfirmacion(array $resultado): string
    {
        $partes = [];
        $grabados = (int) ($resultado['grabados'] ?? 0);
        $enganchados = (int) ($resultado['enganchados'] ?? 0);
        if ($grabados > 0) {
            $partes[] = 'Se generaron '.$grabados.' facturas con su asiento';
        }
        if ($enganchados > 0) {
            $partes[] = 'se enganchó el asiento de '.$enganchados.' facturas que ya estaban';
        }
        $mensaje = $partes === [] ? 'No había facturas para generar.' : implode(' y ', $partes).'.';
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
