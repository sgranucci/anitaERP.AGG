<?php

namespace App\Services\Ventas\CotElectronico;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\CotRemitoEnvio;
use App\Models\Ventas\Venta;
use App\Support\Ventas\IvaVentas\IvaVentasDesgloseSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CotRemitoConsultaService
{
    /**
     * @param  list<array{transporte_id:int,codigo:string,nombre:string,patente:?string,cuit_chofer:?string}>  $repartos
     * @return list<array<string, mixed>>
     */
    public function listarRemitosDelDia(Carbon $fecha, array $repartos): array
    {
        $transporteIds = collect($repartos)
            ->pluck('transporte_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($transporteIds === []) {
            return [];
        }

        $repartosPorId = collect($repartos)->keyBy('transporte_id');
        $tipoRemitoId = (int) config('facturacion.TIPO_REMITO_ID', 0);
        $fechaSql = $fecha->toDateString();

        $numerosRemitoStandalone = Venta::query()
            ->whereDate('fecha', $fechaSql)
            ->whereIn('transporte_id', $transporteIds)
            ->when($tipoRemitoId > 0, fn ($q) => $q->where('tipotransaccion_id', $tipoRemitoId))
            ->pluck('numerocomprobante')
            ->map(fn ($n) => (int) $n)
            ->all();

        $facturas = Venta::query()
            ->whereDate('fecha', $fechaSql)
            ->whereIn('transporte_id', $transporteIds)
            ->where('numeroremito', '>', 0)
            ->when($tipoRemitoId > 0, fn ($q) => $q->where('tipotransaccion_id', '!=', $tipoRemitoId))
            ->with([
                'clientes.localidades',
                'clientes.provincias',
                'clientes.condicionivas',
                'transportes',
                'puntoventaremito',
                'puntoventas',
                'venta_emisiones.articulos.unidadesdemedidas',
                'venta_impuestos',
            ])
            ->orderBy('numeroremito')
            ->get();

        $remitosStandalone = Venta::query()
            ->whereDate('fecha', $fechaSql)
            ->whereIn('transporte_id', $transporteIds)
            ->when($tipoRemitoId > 0, fn ($q) => $q->where('tipotransaccion_id', $tipoRemitoId))
            ->with([
                'clientes.localidades',
                'clientes.provincias',
                'clientes.condicionivas',
                'transportes',
                'puntoventas',
                'venta_emisiones.articulos.unidadesdemedidas',
                'venta_impuestos',
            ])
            ->orderBy('numerocomprobante')
            ->get();

        $filas = [];
        $claves = [];
        $pedidosOriginales = [];

        foreach ($remitosStandalone as $venta) {
            $numeroRemito = (int) $venta->numerocomprobante;
            $clave = $this->claveRemito('REM', 'R', $this->sucursalRemito($venta), $numeroRemito);
            if (isset($claves[$clave])) {
                continue;
            }

            $pedidoId = (int) ($venta->pedido_id ?? 0);
            if ($pedidoId > 0 && isset($pedidosOriginales[$pedidoId])) {
                continue;
            }

            $claves[$clave] = true;
            if ($pedidoId > 0) {
                $pedidosOriginales[$pedidoId] = true;
            }

            $filas[] = $this->mapearFila($venta, $repartosPorId, $fecha, false, $numeroRemito);
        }

        foreach ($facturas as $venta) {
            $numeroRemito = (int) $venta->numeroremito;
            if (in_array($numeroRemito, $numerosRemitoStandalone, true)) {
                continue;
            }

            $clave = $this->claveRemito('REM', 'R', $this->sucursalRemito($venta), $numeroRemito);
            if (isset($claves[$clave])) {
                continue;
            }

            $pedidoId = (int) ($venta->pedido_id ?? 0);
            if ($pedidoId > 0 && isset($pedidosOriginales[$pedidoId])) {
                continue;
            }

            $claves[$clave] = true;
            if ($pedidoId > 0) {
                $pedidosOriginales[$pedidoId] = true;
            }

            $filas[] = $this->mapearFila($venta, $repartosPorId, $fecha, true, $numeroRemito);
        }

        usort($filas, fn ($a, $b) => ($a['numero_remito'] <=> $b['numero_remito']));

        return $filas;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $repartosPorId
     * @return array<string, mixed>
     */
    private function mapearFila(Venta $venta, Collection $repartosPorId, Carbon $fecha, bool $desdeFactura, int $numeroRemito): array
    {
        $fechaFactura = Carbon::parse($venta->fecha)->startOfDay();
        $transporteId = (int) ($venta->transporte_id ?? 0);
        $reparto = $repartosPorId->get($transporteId, []);
        $sucursal = $this->sucursalRemito($venta);
        $envioPrevio = $this->buscarEnvioExitosoPrevio('REM', 'R', $sucursal, $numeroRemito, $fechaFactura);

        $cliente = $venta->clientes;
        $kilos = $this->calcularKilos($venta);
        $importe = $this->calcularImporte($venta);

        return [
            'clave' => $this->claveRemito('REM', 'R', $sucursal, $numeroRemito),
            'venta_id' => (int) $venta->id,
            'cliente_id' => (int) ($cliente->id ?? 0) ?: null,
            'tipo' => 'REM',
            'letra' => 'R',
            'sucursal' => $sucursal,
            'numero_remito' => $numeroRemito,
            'fecha_remito' => $fechaFactura->format('Y-m-d'),
            'fecha_factura' => $fechaFactura->format('d/m/Y'),
            'desde_factura' => $desdeFactura,
            'factura_codigo' => $desdeFactura ? (string) ($venta->codigo ?? '') : '',
            'cliente_codigo' => (string) ($cliente->codigo ?? ''),
            'cliente_nombre' => trim((string) ($venta->nombre ?: ($cliente->nombre ?? ''))),
            'transporte_id' => $transporteId,
            'transporte_codigo' => (string) ($reparto['codigo'] ?? $venta->transportes->codigo ?? ''),
            'transporte_nombre' => (string) ($reparto['nombre'] ?? $venta->transportes->nombre ?? ''),
            'patente' => (string) ($reparto['patente'] ?? $venta->transportes->patentevehiculo ?? ''),
            'cuit_chofer' => (string) ($reparto['cuit_chofer'] ?? ''),
            'kilos' => round($kilos, 2),
            'importe' => round($importe, 2),
            'ya_enviado' => $envioPrevio !== null,
            'cot_previo' => $envioPrevio?->cot,
            'nro_unico_previo' => $envioPrevio?->nro_unico,
            'error_previo' => $envioPrevio?->error,
            'seleccionado' => $envioPrevio === null,
        ];
    }

    private function sucursalRemito(Venta $venta): int
    {
        if ($venta->puntoventaremito) {
            return (int) $venta->puntoventaremito->codigo;
        }

        if ($venta->puntoventas) {
            return (int) $venta->puntoventas->codigo;
        }

        return (int) config('facturacion.PUNTOVENTA_REMITO', 1);
    }

    private function claveRemito(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return implode('|', [$tipo, $letra, $sucursal, $numero]);
    }

    private function buscarEnvioExitosoPrevio(
        string $tipo,
        string $letra,
        int $sucursal,
        int $numeroRemito,
        Carbon $fechaFactura,
    ): ?CotRemitoEnvio {
        return CotRemitoEnvio::query()
            ->where('tipo', $tipo)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numero_remito', $numeroRemito)
            ->whereDate('fecha_remito', $fechaFactura->toDateString())
            ->where(function ($q) {
                $q->where('procesado', 'SI')
                    ->orWhere(function ($sq) {
                        $sq->whereNotNull('cot')->where('cot', '!=', '');
                    });
            })
            ->orderByDesc('id')
            ->first();
    }

    private function calcularKilos(Venta $venta): float
    {
        $total = 0.0;

        foreach ($venta->venta_emisiones as $item) {
            $articulo = $item->articulos;
            if (! $articulo) {
                continue;
            }

            if ($this->esLineaExcluida($articulo->sku ?? '')) {
                continue;
            }

            $um = strtoupper((string) optional($articulo->unidadesdemedidas)->abreviatura);
            $cantidad = (float) $item->cantidad;

            if (str_starts_with($um, 'UN') && (float) ($articulo->coeficienteconversion ?? 0) > 0) {
                $total += $cantidad * (float) $articulo->coeficienteconversion;
            } else {
                $total += $cantidad;
            }
        }

        return $total;
    }

    private function calcularImporte(Venta $venta): float
    {
        $desglose = IvaVentasDesgloseSupport::columnasDesdeVenta($venta);
        $neto = (float) ($desglose['neto_gravado'] ?? 0);
        $exento = (float) ($desglose['exento'] ?? 0);
        $noGravado = (float) ($desglose['no_gravado'] ?? 0);

        $total = $neto + $exento + $noGravado;

        return $total > 0 ? $total : abs((float) ($venta->total ?? 0));
    }

    private function esLineaExcluida(string $sku): bool
    {
        $sku = trim($sku);

        return $sku === '' || $sku === 'texto' || $sku === '0000000000903';
    }

    public function resolverEmpresaEmisora(): ?Empresa
    {
        return Empresa::query()->orderBy('id')->first();
    }
}
