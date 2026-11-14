<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Jobs\Compras\PagoproveedorEnvioMasivoJob;
use App\Services\Compras\PagoproveedorEnvioMasivoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PagoproveedorEnvioMasivoController extends Controller
{
    public function __construct(
        private PagoproveedorEnvioMasivoService $service,
    ) {}

    public function index(Request $request): View
    {
        can('enviar-op-masivo-proveedor');

        return view('compras.pagoproveedor.envio_masivo', $this->service->consultar($request));
    }

    public function enviar(Request $request): RedirectResponse
    {
        can('enviar-op-masivo-proveedor');

        $request->validate([
            'empresa_id' => 'required|integer',
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
            'medio' => 'nullable|string|max:20',
            'mensaje' => 'nullable|string|max:4000',
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer',
        ], [
            'ids.required' => 'Seleccione al menos una orden de pago.',
            'ids.min' => 'Seleccione al menos una orden de pago.',
        ]);

        $usuarioId = (int) Auth::id();
        $query = array_filter([
            'consultar' => 1,
            'empresa_id' => (int) $request->input('empresa_id'),
            'fecha_desde' => (string) $request->input('fecha_desde'),
            'fecha_hasta' => (string) $request->input('fecha_hasta'),
            'medio' => (string) $request->input('medio', 'ambas'),
        ], static fn ($v) => $v !== null && $v !== '');

        $redirect = redirect()->route('pagoproveedor_envio_masivo', $query);

        if ($usuarioId <= 0) {
            return $redirect->with('mensaje_error', 'Sesión inválida para encolar el envío.');
        }

        $consulta = $this->service->consultar($request);
        $filtros = $this->service->filtrosDesdeRequest($request, $consulta['empresa_query']);
        $error = $this->service->validarFiltros($filtros);
        if ($error !== null) {
            return $redirect->with('mensaje_error', $error);
        }

        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        $ids = array_values(array_filter($ids, static fn (int $id) => $id > 0));
        if ($ids === []) {
            return $redirect->with('mensaje_error', 'Seleccione al menos una orden de pago.');
        }

        $email = trim((string) (Auth::user()->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $redirect->with(
                'mensaje_error',
                'Tu usuario no tiene un email válido; no se puede avisar el resultado del envío masivo de OP.'
            );
        }

        $candado = PagoproveedorEnvioMasivoJob::claveCandado($usuarioId);
        $uniqueFor = max(600, (int) config('compras.pagoproveedor_envio_masivo.unique_for', 7200));
        if (! Cache::add($candado, 1, now()->addSeconds($uniqueFor))) {
            return $redirect->with(
                'mensaje_aviso',
                'Ya hay un envío masivo de OP en segundo plano. Esperá el mail de resumen antes de volver a encolar.'
            );
        }

        $mensaje = $request->input('mensaje');
        $mensaje = is_string($mensaje) ? trim($mensaje) : '';
        $mensaje = $mensaje !== '' ? $mensaje : null;

        try {
            PagoproveedorEnvioMasivoJob::dispatch($usuarioId, $filtros, $ids, $mensaje);
        } catch (\Throwable $e) {
            Cache::forget($candado);
            report($e);

            return $redirect->with('mensaje_error', 'No se pudo encolar el envío: '.$e->getMessage());
        }

        return $redirect->with(
            'mensaje',
            'El envío de '.count($ids).' orden(es) de pago se encoló en segundo plano '
            .'(un solo proceso con todas las seleccionadas, no un job por OP). '
            .'Al terminar o frenarse recibirás un mail en '.$email.' con el resumen. '
            .'Podés seguir trabajando; no hace falta dejar esta pantalla abierta.'
        );
    }
}
