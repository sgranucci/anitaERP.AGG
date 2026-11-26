<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\AhorroImportacionPlantillaExport;
use App\Http\Controllers\Controller;
use App\Models\Compras\TipoAhorro;
use App\Services\Compras\AhorroImportacionService;
use App\Support\Compras\AhorroLineaSupport;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Carga histórica de ahorros desde Excel (requisiciones aprobadas / OC ya generadas).
 * Paso 1: subir y previsualizar (no escribe). Paso 2: confirmar y aplicar solo las filas válidas.
 */
class AhorroImportacionController extends Controller
{
    private const DIR = 'ahorro_importacion';

    public function __construct(private AhorroImportacionService $service)
    {
        $this->middleware('auth');
    }

    public function index()
    {
        can('importar-ahorro-compras');

        return view('compras.ahorro_importacion.index', $this->datosAyuda());
    }

    public function plantilla()
    {
        can('importar-ahorro-compras');

        return Excel::download(new AhorroImportacionPlantillaExport(), 'plantilla_ahorros.xlsx');
    }

    public function previsualizar(Request $request)
    {
        can('importar-ahorro-compras');

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ], [
            'archivo.required' => 'Elija el archivo Excel a importar.',
            'archivo.mimes' => 'El archivo debe ser Excel (.xlsx, .xls) o CSV.',
        ]);

        $archivo = $request->file('archivo');
        $token = Str::random(32);
        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'xlsx');
        $nombre = $this->nombreArchivo($token, $extension);
        Storage::disk('local')->putFileAs(self::DIR, $archivo, $nombre);

        try {
            $evaluadas = $this->service->evaluar($this->service->leerArchivo(Storage::disk('local')->path(self::DIR.'/'.$nombre)));
        } catch (DomainException $e) {
            Storage::disk('local')->delete(self::DIR.'/'.$nombre);

            return redirect()->route('importar_ahorro_compras')->withErrors(['archivo' => $e->getMessage()]);
        }

        return view('compras.ahorro_importacion.previsualizacion', [
            'token' => $token,
            'nombreOriginal' => $archivo->getClientOriginalName(),
            'filas' => $evaluadas,
            'cantOk' => collect($evaluadas)->where('estado', 'ok')->count(),
            'cantError' => collect($evaluadas)->where('estado', 'error')->count(),
            'resultado' => null,
        ]);
    }

    public function confirmar(Request $request)
    {
        can('importar-ahorro-compras');

        $token = (string) $request->input('token', '');
        $ruta = $this->rutaPorToken($token);
        if ($ruta === null) {
            return redirect()->route('importar_ahorro_compras')
                ->withErrors(['archivo' => 'La previsualización venció. Suba el archivo de nuevo.']);
        }

        try {
            // Se evalúa de nuevo: entre la vista previa y la confirmación los datos pueden haber cambiado.
            $evaluadas = $this->service->evaluar($this->service->leerArchivo(Storage::disk('local')->path($ruta)));
        } catch (DomainException $e) {
            return redirect()->route('importar_ahorro_compras')->withErrors(['archivo' => $e->getMessage()]);
        }

        $resultado = $this->service->aplicar($evaluadas);
        Storage::disk('local')->delete($ruta);

        return view('compras.ahorro_importacion.previsualizacion', [
            'token' => null,
            'nombreOriginal' => '',
            'filas' => $evaluadas,
            'cantOk' => $resultado['aplicadas'],
            'cantError' => $resultado['con_error'],
            'resultado' => $resultado,
        ]);
    }

    /** @return array<string, mixed> */
    private function datosAyuda(): array
    {
        return [
            'tipos' => TipoAhorro::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->pluck('nombre')->all(),
            'origenes' => array_values(AhorroLineaSupport::origenes()),
            'maxFilas' => AhorroImportacionService::MAX_FILAS,
        ];
    }

    private function nombreArchivo(string $token, string $extension): string
    {
        return (int) auth()->id().'_'.$token.'.'.preg_replace('/[^a-z0-9]/', '', $extension);
    }

    private function rutaPorToken(string $token): ?string
    {
        if (! preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            return null;
        }
        $prefijo = self::DIR.'/'.(int) auth()->id().'_'.$token.'.';
        foreach (Storage::disk('local')->files(self::DIR) as $f) {
            if (str_starts_with($f, $prefijo)) {
                return $f;
            }
        }

        return null;
    }
}
