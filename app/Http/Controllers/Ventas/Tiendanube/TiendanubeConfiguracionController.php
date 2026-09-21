<?php

namespace App\Http\Controllers\Ventas\Tiendanube;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\Tiendanube\TiendanubeConfiguracionSupport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TiendanubeConfiguracionController extends Controller
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function editar()
    {
        can('editar-configuracion-tiendanube');

        $config = TiendanubeConfiguracionSupport::asegurarCabecera();
        $config->load(['puntoventa', 'deposito']);

        $lista = null;
        if ((int) $config->listaprecio_id > 0) {
            $lista = Listaprecio::query()->find((int) $config->listaprecio_id);
        }

        $gateways = TiendanubeConfiguracionSupport::gateways();
        if ($gateways->isEmpty()) {
            // Semilla visual desde config si aún no hay filas (migración sin seed).
            foreach ((array) config('tiendanube.gateway_cuentacaja', []) as $key => $codigo) {
                $cuenta = Cuentacaja::query()->where('codigo', (string) $codigo)->first()
                    ?? (is_numeric($codigo) ? Cuentacaja::query()->find((int) $codigo) : null);
                if (! $cuenta) {
                    continue;
                }
                $gateways->push((object) [
                    'gateway_key' => (string) $key,
                    'cuentacaja_id' => (int) $cuenta->id,
                    'cuentacaja' => $cuenta,
                ]);
            }
        }

        $pares = TiendanubeConfiguracionSupport::paresPuntoventaDeposito();
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.tiendanube_configuracion.editar', [
            'config' => $config,
            'lista' => $lista,
            'gateways' => $gateways,
            'pares' => $pares,
            'empresa_query' => $empresa_query,
        ]);
    }

    public function actualizar(Request $request)
    {
        can('actualizar-configuracion-tiendanube');

        $data = $request->validate([
            'empresa_id' => 'nullable|integer|exists:empresa,id',
            'puntoventa_id' => 'nullable|integer|exists:puntoventa,id',
            'deposito_id' => 'nullable|integer|exists:depmae,id',
            'listaprecio_id' => 'nullable|integer|exists:listaprecio,id',
            'articulo_envio_sku' => 'nullable|string|max:40',
            'articulo_descuento_sku' => 'nullable|string|max:40',
            'usocuentacaja_nombre' => 'nullable|string|max:80',
            'gateway_key' => 'nullable|array',
            'gateway_key.*' => 'nullable|string|max:80',
            'gateway_cuentacaja_id' => 'nullable|array',
            'gateway_cuentacaja_id.*' => 'nullable|integer',
            'par_puntoventa_id' => 'nullable|array',
            'par_puntoventa_id.*' => 'nullable|integer',
            'par_deposito_id' => 'nullable|array',
            'par_deposito_id.*' => 'nullable|integer',
            'par_default' => 'nullable|integer|min:0',
        ]);

        $gateways = [];
        $keys = $data['gateway_key'] ?? [];
        $cuentas = $data['gateway_cuentacaja_id'] ?? [];
        foreach ($keys as $i => $key) {
            $key = strtolower(trim((string) $key));
            $cuentaId = (int) ($cuentas[$i] ?? 0);
            if ($key === '' && $cuentaId <= 0) {
                continue;
            }
            if ($key === '' || $cuentaId <= 0) {
                throw ValidationException::withMessages([
                    'gateway_key' => 'Cada fila de gateway debe tener clave y cuenta de caja.',
                ]);
            }
            if (! Cuentacaja::query()->whereKey($cuentaId)->exists()) {
                throw ValidationException::withMessages([
                    'gateway_cuentacaja_id' => "Cuenta de caja inválida en gateway «{$key}».",
                ]);
            }
            $gateways[] = ['gateway_key' => $key, 'cuentacaja_id' => $cuentaId];
        }

        $pares = [];
        $pvs = $data['par_puntoventa_id'] ?? [];
        $deps = $data['par_deposito_id'] ?? [];
        $defaultIdx = (int) ($data['par_default'] ?? -1);
        foreach ($pvs as $i => $pvId) {
            $pvId = (int) $pvId;
            $depId = (int) ($deps[$i] ?? 0);
            if ($pvId <= 0 && $depId <= 0) {
                continue;
            }
            if ($pvId <= 0 || $depId <= 0) {
                throw ValidationException::withMessages([
                    'par_puntoventa_id' => 'Cada par debe tener punto de venta y depósito.',
                ]);
            }
            if (! Puntoventa::query()->whereKey($pvId)->exists()) {
                throw ValidationException::withMessages([
                    'par_puntoventa_id' => 'Punto de venta inválido en un par.',
                ]);
            }
            if (! Depmae::query()->whereKey($depId)->exists()) {
                throw ValidationException::withMessages([
                    'par_deposito_id' => 'Depósito inválido en un par.',
                ]);
            }
            $pares[] = [
                'puntoventa_id' => $pvId,
                'deposito_id' => $depId,
                'es_default' => $i === $defaultIdx,
            ];
        }

        if ($pares === []) {
            throw ValidationException::withMessages([
                'par_puntoventa_id' => 'Debe cargar al menos un par punto de venta / depósito.',
            ]);
        }

        TiendanubeConfiguracionSupport::guardar([
            'empresa_id' => $data['empresa_id'] ?? null,
            'puntoventa_id' => $data['puntoventa_id'] ?? null,
            'deposito_id' => $data['deposito_id'] ?? null,
            'listaprecio_id' => $data['listaprecio_id'] ?? null,
            'articulo_envio_sku' => $data['articulo_envio_sku'] ?? null,
            'articulo_descuento_sku' => $data['articulo_descuento_sku'] ?? null,
            'usocuentacaja_nombre' => $data['usocuentacaja_nombre'] ?? 'TIENDA NUBE',
        ], $gateways, $pares);

        return redirect()
            ->route('editar_configuracion_tiendanube')
            ->with('mensaje', 'Configuración Tiendanube actualizada.');
    }
}
