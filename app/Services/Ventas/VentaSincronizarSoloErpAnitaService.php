<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use App\Support\Ventas\Gastronomia\GastronomiaVentasSoloErpSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas presentes en ERP sin cabecera en Anita: sincronizar (nunca borrar).
 *
 * Sustituye el saneamiento destructivo “solo ERP → delete”. Las jornadas POS
 * intencionalmente solo-ERP (GASTRONOMIA_VENTAS_SOLO_ERP_DESDE) se omiten salvo
 * que se pida incluirlas.
 */
final class VentaSincronizarSoloErpAnitaService
{
    public function __construct(
        private FacturacionService $facturacionService,
        private GastronomiaChequeoVentasAnitaErpService $chequeoAnita,
    ) {
    }

    /**
     * @param  list<int>  $puntoventaIds
     * @param  list<int>  $ventaIds
     * @return array{
     *   candidatas:int,
     *   ya_en_anita:int,
     *   omitidas_intencionales:int,
     *   replicadas:int,
     *   errores:list<array<string,mixed>>,
     *   detalle:list<array<string,mixed>>
     * }
     */
    public function sincronizar(
        string $fechaDesde,
        string $fechaHasta,
        bool $dryRun = true,
        int $empresaId = 0,
        array $puntoventaIds = [],
        array $ventaIds = [],
        int $limite = 0,
        bool $incluirGastroIntencional = false,
    ): array {
        $resultado = [
            'candidatas' => 0,
            'ya_en_anita' => 0,
            'omitidas_intencionales' => 0,
            'replicadas' => 0,
            'errores' => [],
            'detalle' => [],
        ];

        $query = Venta::query()
            ->with(['puntoventas:id,codigo,empresa_id,modofacturacion', 'condicionivas:id,letra', 'tipotransacciones:id,codigo,signo'])
            ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->orderBy('puntoventa_id')
            ->orderBy('numerocomprobante');

        if ($ventaIds !== []) {
            $query->whereIn('id', $ventaIds);
        }
        if ($puntoventaIds !== []) {
            $query->whereIn('puntoventa_id', $puntoventaIds);
        }
        if ($empresaId > 0) {
            $query->whereHas('puntoventas', static fn ($q) => $q->where('empresa_id', $empresaId));
        }

        $ventas = $query->get();
        $procesadas = 0;

        foreach ($ventas as $venta) {
            if ($limite > 0 && $procesadas >= $limite) {
                break;
            }

            $fila = [
                'venta_id' => (int) $venta->id,
                'codigo' => (string) $venta->codigo,
                'fecha' => (string) $venta->fecha,
                'pv' => (string) ($venta->puntoventas->codigo ?? ''),
                'total' => round((float) $venta->total, 2),
                'estado' => 'pendiente',
                'mensaje' => '',
            ];

            $letra = $this->resolverLetra($venta);
            try {
                $consulta = $this->chequeoAnita->consultarCabeceraAnitaDesdeVenta($venta, $letra);
            } catch (\Throwable $e) {
                $fila['estado'] = 'error_lectura_anita';
                $fila['mensaje'] = $e->getMessage();
                $resultado['errores'][] = $fila;
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            if (($consulta['error_lectura'] ?? null) !== null) {
                $fila['estado'] = 'error_lectura_anita';
                $fila['mensaje'] = (string) $consulta['error_lectura'];
                $resultado['errores'][] = $fila;
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            if (($consulta['cabecera'] ?? null) !== null) {
                $resultado['ya_en_anita']++;
                $fila['estado'] = 'ya_en_anita';
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            $resultado['candidatas']++;

            $empresaPv = (int) ($venta->puntoventas->empresa_id ?? 0);
            $fechaJornada = (string) ($venta->fechajornada ?: $venta->fecha);
            if (! $incluirGastroIntencional
                && $this->esPuntoventaPos($venta)
                && GastronomiaVentasSoloErpSupport::esJornada($empresaPv, $fechaJornada)
            ) {
                $resultado['omitidas_intencionales']++;
                $fila['estado'] = 'omitida_solo_erp_intencional';
                $fila['mensaje'] = 'POS en ventana solo-ERP (no se replica a Anita).';
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            if ($dryRun) {
                $resultado['replicadas']++;
                $fila['estado'] = 'simulado_replica';
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            try {
                $rep = $this->facturacionService->replicarVentaErpEnAnita((int) $venta->id);
                if (! empty($rep['ok'])) {
                    if (! empty($rep['ya_en_anita'])) {
                        $resultado['ya_en_anita']++;
                        $fila['estado'] = 'ya_en_anita';
                    } else {
                        $resultado['replicadas']++;
                        $fila['estado'] = 'replicada';
                    }
                } else {
                    $fila['estado'] = 'error';
                    $fila['mensaje'] = (string) ($rep['error'] ?? 'Error desconocido');
                    $resultado['errores'][] = $fila;
                    Log::error('venta.sincronizar_solo_erp_anita.fallo', [
                        'venta_id' => $venta->id,
                        'codigo' => $venta->codigo,
                        'msg' => $fila['mensaje'],
                    ]);
                }
            } catch (\Throwable $e) {
                $fila['estado'] = 'error';
                $fila['mensaje'] = $e->getMessage();
                $resultado['errores'][] = $fila;
                Log::error('venta.sincronizar_solo_erp_anita.excepcion', [
                    'venta_id' => $venta->id,
                    'msg' => $e->getMessage(),
                ]);
            }

            $resultado['detalle'][] = $fila;
            $procesadas++;
        }

        return $resultado;
    }

    private function resolverLetra(Venta $venta): string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if (preg_match('/\s+([A-Z])(?:[\s\-]|$)/', $codigo, $m)) {
            return $m[1];
        }
        $letra = trim((string) ($venta->condicionivas->letra ?? ''));

        return $letra !== '' ? $letra : 'A';
    }

    private function esPuntoventaPos(Venta $venta): bool
    {
        $pvId = (int) $venta->puntoventa_id;
        if ($pvId <= 0) {
            return false;
        }

        if (Schema::hasTable('configuracion_puntoventa_gastronomia')) {
            $q = DB::table('configuracion_puntoventa_gastronomia')
                ->where('puntoventa_cae_id', $pvId);
            if (Schema::hasColumn('configuracion_puntoventa_gastronomia', 'puntoventa_caea_id')) {
                $q->orWhere('puntoventa_caea_id', $pvId);
            }
            if ($q->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('configuracion_puntoventa_estacionamiento')) {
            $q = DB::table('configuracion_puntoventa_estacionamiento')
                ->where('puntoventa_cae_id', $pvId);
            if (Schema::hasColumn('configuracion_puntoventa_estacionamiento', 'puntoventa_caea_id')) {
                $q->orWhere('puntoventa_caea_id', $pvId);
            }
            if ($q->exists()) {
                return true;
            }
        }

        return false;
    }
}
