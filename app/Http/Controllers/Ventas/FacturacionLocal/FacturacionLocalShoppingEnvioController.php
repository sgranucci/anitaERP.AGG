<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Models\Ventas\FacturacionLocalShoppingEnvio;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalShoppingCaballitoExportService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalShoppingCaballitoSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FacturacionLocalShoppingEnvioController extends Controller
{
    public function index(Request $request)
    {
        $this->assertFerli();
        can('editar-facturacion-local-parametro');

        $filtros = [
            'fecha_desde' => trim((string) $request->query('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->query('fecha_hasta', '')),
            'estado' => trim((string) $request->query('estado', '')),
        ];

        $query = FacturacionLocalShoppingEnvio::query()->with('localVenta')->orderByDesc('id');
        if ($filtros['fecha_desde'] !== '') {
            $query->where('fecha_venta', '>=', $filtros['fecha_desde']);
        }
        if ($filtros['fecha_hasta'] !== '') {
            $query->where('fecha_venta', '<=', $filtros['fecha_hasta']);
        }
        if (in_array($filtros['estado'], [FacturacionLocalShoppingEnvio::ESTADO_ENVIADO, FacturacionLocalShoppingEnvio::ESTADO_ERROR], true)) {
            $query->where('estado', $filtros['estado']);
        }

        return view('ventas.facturacion_local.shopping_caballito.index', [
            'envios' => $query->paginate(20)->appends($filtros),
            'filtros' => $filtros,
            'faltantes' => FacturacionLocalShoppingCaballitoSupport::faltantesIdentificacion(),
            'emails' => FacturacionLocalShoppingCaballitoSupport::emails(),
            'habilitado' => FacturacionLocalShoppingCaballitoSupport::habilitado(),
            'hora' => FacturacionLocalShoppingCaballitoSupport::hora(),
            'hoy' => Carbon::now((string) config('app.timezone'))->toDateString(),
            'puedeEnviar' => can('actualizar-facturacion-local-parametro', false),
        ]);
    }

    public function enviar(Request $request, FacturacionLocalShoppingCaballitoExportService $servicio)
    {
        $this->assertFerli();
        can('actualizar-facturacion-local-parametro');

        $datos = $request->validate([
            'fecha' => 'required|date',
            'forzar' => 'nullable|in:1',
        ]);

        $resultado = $servicio->ejecutar(
            (string) $datos['fecha'],
            $request->boolean('forzar'),
            false
        );

        $redirect = redirect()->route('facturacion_local_shopping_caballito');
        if ($resultado['estado'] === 'omitido') {
            return $redirect->with('mensaje-aviso', $resultado['mensaje']);
        }
        if (! $resultado['ok']) {
            return $redirect->with('mensaje-error', $resultado['mensaje']);
        }

        return $redirect->with('mensaje', $resultado['mensaje']);
    }

    public function descargar(int $id)
    {
        $this->assertFerli();
        can('editar-facturacion-local-parametro');

        $envio = FacturacionLocalShoppingEnvio::query()->findOrFail($id);
        $ruta = str_replace('\\', '/', (string) $envio->ruta);
        if ($ruta === '' || str_contains($ruta, '..') || ! str_starts_with($ruta, 'facturacion_local_shopping/')) {
            abort(404);
        }
        $absoluta = storage_path('app/'.$ruta);
        if (! is_file($absoluta)) {
            abort(404);
        }

        return response()->download($absoluta, (string) ($envio->nombre_archivo ?: 'TRANCOMP.TXT'), [
            'Content-Type' => 'text/plain; charset=US-ASCII',
        ]);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
