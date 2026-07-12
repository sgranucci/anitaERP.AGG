<?php

namespace App\Support\Arca;

use App\Support\Compras\ProveedorFacturasApocrifasSupport;
use App\Support\Ventas\ClienteFacturasApocrifasSupport;
use App\Services\Arca\WsapocConsultaService;
use Throwable;

/**
 * Una sola consulta GetAllByPublicacion → procesa proveedores y clientes del ERP.
 */
final class WsapocAuditoriaNovedadesSupport
{
    public function __construct(
        private WsapocConsultaService $wsapocService,
        private ProveedorFacturasApocrifasSupport $proveedorSupport,
        private ClienteFacturasApocrifasSupport $clienteSupport,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function procesarNovedadesPorRango(string $desde, string $hasta, bool $suspenderSiApocrifo = true): array
    {
        if (! filter_var(config('arca_wsapoc.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'ok' => true,
                'skipped' => true,
                'desde' => $desde,
                'hasta' => $hasta,
                'publicaciones_ws' => 0,
                'cuits_novedad' => 0,
                'proveedores_coincidentes' => 0,
                'clientes_coincidentes' => 0,
                'apocrifos' => 0,
                'apocrifos_proveedores' => 0,
                'apocrifos_clientes' => 0,
                'suspendidos' => 0,
                'suspendidos_proveedores' => 0,
                'suspendidos_clientes' => 0,
                'errores' => 0,
                'cuits_sin_proveedor' => [],
                'cuits_sin_cliente' => [],
                'proveedores_suspendidos' => [],
                'clientes_suspendidos' => [],
            ];
        }

        try {
            $ws = $this->wsapocService->getAllByPublicacion($desde, $hasta);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'desde' => $desde,
                'hasta' => $hasta,
                'mensaje' => 'Error al consultar novedades WSAPOC: '.$e->getMessage(),
                'publicaciones_ws' => 0,
                'cuits_novedad' => 0,
                'proveedores_coincidentes' => 0,
                'clientes_coincidentes' => 0,
                'apocrifos' => 0,
                'suspendidos' => 0,
                'errores' => 1,
                'cuits_sin_proveedor' => [],
                'cuits_sin_cliente' => [],
                'proveedores_suspendidos' => [],
                'clientes_suspendidos' => [],
                'error' => $e->getMessage(),
            ];
        }

        if ($ws['error_servicio'] ?? false) {
            return [
                'ok' => false,
                'desde' => $desde,
                'hasta' => $hasta,
                'mensaje' => 'ARCA WSAPOC respondió error '.($ws['codigo'] ?? '?').': '.($ws['descripcion'] ?? ''),
                'publicaciones_ws' => count($ws['publicaciones'] ?? []),
                'cuits_novedad' => 0,
                'proveedores_coincidentes' => 0,
                'clientes_coincidentes' => 0,
                'apocrifos' => 0,
                'suspendidos' => 0,
                'errores' => 1,
                'cuits_sin_proveedor' => [],
                'cuits_sin_cliente' => [],
                'proveedores_suspendidos' => [],
                'clientes_suspendidos' => [],
                'ws' => $ws,
            ];
        }

        $porCuit = $this->agruparPublicacionesPorCuit($ws['publicaciones'] ?? []);

        $resProveedor = $this->proveedorSupport->procesarCuitsDesdeNovedades($porCuit, $suspenderSiApocrifo);
        $resCliente = $this->clienteSupport->procesarCuitsDesdeNovedades($porCuit, $suspenderSiApocrifo);

        $apocrifosProveedores = (int) ($resProveedor['apocrifos'] ?? 0);
        $apocrifosClientes = (int) ($resCliente['apocrifos'] ?? 0);
        $suspendidosProveedores = (int) ($resProveedor['suspendidos'] ?? 0);
        $suspendidosClientes = (int) ($resCliente['suspendidos'] ?? 0);

        return [
            'ok' => true,
            'desde' => $desde,
            'hasta' => $hasta,
            'publicaciones_ws' => count($ws['publicaciones'] ?? []),
            'cuits_novedad' => count($porCuit),
            'proveedores_coincidentes' => (int) ($resProveedor['proveedores_coincidentes'] ?? 0),
            'clientes_coincidentes' => (int) ($resCliente['clientes_coincidentes'] ?? 0),
            'apocrifos' => $apocrifosProveedores + $apocrifosClientes,
            'apocrifos_proveedores' => $apocrifosProveedores,
            'apocrifos_clientes' => $apocrifosClientes,
            'suspendidos' => $suspendidosProveedores + $suspendidosClientes,
            'suspendidos_proveedores' => $suspendidosProveedores,
            'suspendidos_clientes' => $suspendidosClientes,
            'errores' => (int) ($resProveedor['errores'] ?? 0) + (int) ($resCliente['errores'] ?? 0),
            'cuits_sin_proveedor' => $resProveedor['cuits_sin_proveedor'] ?? [],
            'cuits_sin_cliente' => $resCliente['cuits_sin_cliente'] ?? [],
            'proveedores_suspendidos' => $resProveedor['proveedores_suspendidos'] ?? [],
            'clientes_suspendidos' => $resCliente['clientes_suspendidos'] ?? [],
            'ws' => $ws,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $publicaciones
     * @return array<string, list<array<string, mixed>>>
     */
    private function agruparPublicacionesPorCuit(array $publicaciones): array
    {
        $porCuit = [];
        foreach ($publicaciones as $pub) {
            $cuit = preg_replace('/\D+/', '', (string) ($pub['cuit'] ?? '')) ?? '';
            if (strlen($cuit) !== 11) {
                continue;
            }
            if (! isset($porCuit[$cuit])) {
                $porCuit[$cuit] = [];
            }
            $porCuit[$cuit][] = $pub;
        }

        return $porCuit;
    }
}
