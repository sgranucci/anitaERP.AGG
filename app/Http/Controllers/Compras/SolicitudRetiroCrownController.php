<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Support\Caja\InterbankingArchivoPagoFormatoSupport;
use App\Support\Compras\CbuSupport;
use App\Support\Compras\SolicitudRetiroCrownPreferenciaSupport;
use App\Support\Compras\SolicitudRetiroCrownSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Carga de solicitudes de retiro a Interbanking y Banco Francés (p-cargasolret).
 */
class SolicitudRetiroCrownController extends Controller
{
    private const PERMISO = 'generar-solicitud-retiro-crown';

    private const SESSION = 'solicitud_retiro_crown';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->asegurar();
        if ($request->boolean('limpiar')) {
            $request->session()->forget(self::SESSION);

            return redirect()->route('solicitud_retiro_crown');
        }

        $preferencia = SolicitudRetiroCrownPreferenciaSupport::leer();

        return view('compras.solicitud_retiro.index', [
            'preferencia' => $preferencia,
            'resultado' => $request->session()->get(self::SESSION),
        ]);
    }

    public function generar(Request $request): RedirectResponse
    {
        $this->asegurar();

        $request->validate([
            'cbu_origen' => ['required', 'string', 'max:32'],
            'fecha' => ['required', 'date'],
            'secuencia' => ['required', 'integer', 'min:1', 'max:99999999'],
            'archivo' => ['required', 'file', 'max:20480'],
        ], [
            'cbu_origen.required' => 'Ingrese el CBU de la cuenta origen.',
            'fecha.required' => 'Indique la fecha del archivo.',
            'secuencia.required' => 'Indique la secuencia.',
            'archivo.required' => 'Seleccione el archivo CSV.',
            'archivo.max' => 'El archivo supera los 20 MB.',
        ]);

        $cbu = CbuSupport::validarConMensaje((string) $request->input('cbu_origen'));
        if (! $cbu['ok']) {
            return back()->withInput()->with('mensaje_error', 'CBU origen: '.$cbu['mensaje']);
        }

        $archivo = $request->file('archivo');
        $extension = strtolower((string) $archivo?->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt'], true)) {
            return back()->withInput()->with('mensaje_error', 'El archivo tiene que ser CSV o TXT.');
        }

        $contenido = $archivo?->get();
        if (! is_string($contenido) || trim($contenido) === '') {
            return back()->withInput()->with('mensaje_error', 'El archivo está vacío.');
        }

        $fechaYmd = InterbankingArchivoPagoFormatoSupport::ymdDesdeFecha((string) $request->input('fecha'));
        $secuencia = (int) $request->input('secuencia');
        $resultado = SolicitudRetiroCrownSupport::procesar($contenido, $cbu['cbu'], $fechaYmd, $secuencia);

        $creadas = (int) $resultado['cantidad_interbanking'] + (int) $resultado['cantidad_frances'];
        if ($creadas <= 0) {
            return back()->withInput()->with(
                'mensaje_error',
                'No hay solicitudes con monto y CBU de 22 dígitos. Revise el CSV.'
            );
        }

        SolicitudRetiroCrownPreferenciaSupport::registrar($secuencia, $cbu['cbu']);

        $request->session()->put(self::SESSION, [
            'filas' => $resultado['filas'],
            'omitidas' => $resultado['omitidas'],
            'interbanking' => $resultado['interbanking'],
            'frances' => $resultado['frances'],
            'cantidad_datos' => $resultado['cantidad_datos'],
            'cantidad_interbanking' => $resultado['cantidad_interbanking'],
            'cantidad_frances' => $resultado['cantidad_frances'],
            'cantidad_no_autorizadas' => $resultado['cantidad_no_autorizadas'],
            'total_interbanking' => $resultado['total_interbanking'],
            'total_frances' => $resultado['total_frances'],
            'secuencia' => $secuencia,
            'fecha' => (string) $request->input('fecha'),
            'cbu_origen' => $cbu['cbu'],
        ]);

        return redirect()
            ->route('solicitud_retiro_crown')
            ->with('mensaje', 'Listo: '.$creadas.' solicitud(es). Interbanking '
                .$resultado['cantidad_interbanking'].', Francés '.$resultado['cantidad_frances'].'.');
    }

    public function descargar(Request $request, string $tipo): Response|BinaryFileResponse|RedirectResponse
    {
        $this->asegurar();

        $guardado = $request->session()->get(self::SESSION);
        if (! is_array($guardado)) {
            return redirect()->route('solicitud_retiro_crown')
                ->with('mensaje_error', 'No hay un archivo generado en esta sesión. Volvé a procesar el CSV.');
        }

        $secuencia = sprintf('%08d', (int) ($guardado['secuencia'] ?? 0));
        $fecha = str_replace('-', '', (string) ($guardado['fecha'] ?? date('Y-m-d')));

        if ($tipo === 'interbanking') {
            $contenido = (string) ($guardado['interbanking'] ?? '');
            if ($contenido === '') {
                return redirect()->route('solicitud_retiro_crown')
                    ->with('mensaje_error', 'Esta generación no tiene movimientos para Interbanking.');
            }

            return response($contenido, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="solretiro_'.$fecha.'_'.$secuencia.'.txt"',
            ]);
        }

        if ($tipo === 'frances') {
            $contenido = (string) ($guardado['frances'] ?? '');
            if ($contenido === '') {
                return redirect()->route('solicitud_retiro_crown')
                    ->with('mensaje_error', 'Esta generación no tiene movimientos para el Francés.');
            }

            return response($contenido, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="frances_'.$fecha.'_'.$secuencia.'.csv"',
            ]);
        }

        if ($tipo !== 'zip') {
            abort(404);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'solret');
        if ($zipPath === false) {
            return redirect()->route('solicitud_retiro_crown')
                ->with('mensaje_error', 'No se pudo armar el zip.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);

            return redirect()->route('solicitud_retiro_crown')
                ->with('mensaje_error', 'No se pudo armar el zip.');
        }

        $ib = (string) ($guardado['interbanking'] ?? '');
        $fr = (string) ($guardado['frances'] ?? '');
        if ($ib !== '') {
            $zip->addFromString('solretiro.txt', $ib);
        }
        if ($fr !== '') {
            $zip->addFromString('frances.csv', $fr);
        }
        $zip->close();

        if ($ib === '' && $fr === '') {
            @unlink($zipPath);

            return redirect()->route('solicitud_retiro_crown')
                ->with('mensaje_error', 'No hay archivos para descargar.');
        }

        return response()->download($zipPath, 'solicitudes_retiro_'.$fecha.'_'.$secuencia.'.zip')
            ->deleteFileAfterSend(true);
    }

    private function asegurar(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
        can(self::PERMISO);
    }
}
