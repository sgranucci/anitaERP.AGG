<?php

declare(strict_types=1);

namespace App\Services\Ventas\TransferenciaCrown;

use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use App\Services\Ventas\Gastronomia\GastronomiaReplicarVentasAnitaErpService;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownSalas;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownVencaeSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Remedia facturas Crown transferidas sin CAE (gastro) o sin cabecera Anita (ARCA).
 */
final class TransferenciaCrownCompletarCaeYReplicarService
{
    public function __construct(
        private readonly TransferenciaCrownVencaeSupport $vencae,
        private readonly GastronomiaReplicarVentasAnitaErpService $replicarAnita,
        private readonly FacturacionService $facturacion,
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoAnita,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  list<int>  $sucursales  Códigos PV (ej. 106, 134). Vacío = todos.
     */
    public function ejecutar(
        string $desde,
        string $hasta,
        bool $dryRun = true,
        ?int $usuarioId = null,
        int $limite = 0,
        array $sucursales = [],
    ): array {
        $usuarioId = $usuarioId ?: (int) (Auth::id() ?: Usuario::query()->orderBy('id')->value('id') ?: 1);
        if ($usuarioId > 0 && ! Auth::check()) {
            Auth::loginUsingId($usuarioId);
        }
        $sucursales = array_values(array_unique(array_filter(array_map('intval', $sucursales))));

        $resultado = [
            'desde' => $desde,
            'hasta' => $hasta,
            'dry_run' => $dryRun,
            'sucursales' => $sucursales,
            'gastro' => [
                'candidatas' => 0,
                'cae_completados' => 0,
                'cae_ya_ok' => 0,
                'cae_faltante_anita' => 0,
                'errores' => [],
            ],
            'arca' => [
                'candidatas' => 0,
                'vto_completados' => 0,
                'replicadas' => 0,
                'omitidas_ya_en_anita' => 0,
                'errores' => [],
            ],
            'detalle' => [],
        ];

        $this->completarCaeGastro($desde, $hasta, $dryRun, $limite, $sucursales, $resultado);
        $this->replicarArca($desde, $hasta, $dryRun, $limite, $sucursales, $resultado);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    /**
     * @param  list<int>  $sucursales
     * @param  array<string, mixed>  $resultado
     */
    private function completarCaeGastro(string $desde, string $hasta, bool $dryRun, int $limite, array $sucursales, array &$resultado): void
    {
        $query = Venta::query()
            ->with(['puntoventas:id,codigo,nombre', 'tipotransacciones:id,codigo,abreviatura'])
            ->whereBetween('fechajornada', [$desde, $hasta])
            ->where('leyenda', 'like', 'Transferencia gastronomía%')
            ->where(function ($q): void {
                $q->whereNull('cae')->orWhere('cae', '');
            })
            ->when($sucursales !== [], function ($q) use ($sucursales): void {
                $codigos = array_map(
                    static fn (int $s): string => str_pad((string) $s, 5, '0', STR_PAD_LEFT),
                    $sucursales,
                );
                $q->whereHas('puntoventas', static fn ($pq) => $pq->whereIn('codigo', $codigos));
            })
            ->orderBy('id');
        $resultado['gastro']['candidatas'] = (clone $query)->count();
        if ($limite > 0) {
            $query->limit($limite);
        }
        $ventas = $query->get();
        if ($ventas->isEmpty()) {
            return;
        }

        $porSala = [];
        foreach ($ventas as $venta) {
            $sala = $this->salaDesdeLeyenda((string) $venta->leyenda);
            if ($sala === null) {
                $resultado['gastro']['errores'][] = [
                    'venta_id' => (int) $venta->id,
                    'codigo' => (string) $venta->codigo,
                    'mensaje' => 'No se pudo resolver la sala Anita desde la leyenda.',
                ];

                continue;
            }
            $porSala[$sala['codigo']]['sala'] = $sala;
            $porSala[$sala['codigo']]['ventas'][] = $venta;
        }

        $procesadas = 0;
        foreach ($porSala as $bloque) {
            /** @var array{codigo: string, nombre: string, ifx_server: string} $sala */
            $sala = $bloque['sala'];
            /** @var list<Venta> $lista */
            $lista = $bloque['ventas'];
            $proxy = [];
            foreach ($lista as $venta) {
                $proxy[] = (object) [
                    'ven_sucursal' => (int) ($venta->puntoventas->codigo ?? 0),
                    'ven_nro' => (int) $venta->numerocomprobante,
                ];
            }
            try {
                $mapa = $this->vencae->mapaDesdeVentasSala($proxy, $sala['ifx_server']);
            } catch (\Throwable $e) {
                $resultado['gastro']['errores'][] = [
                    'venta_id' => 0,
                    'codigo' => $sala['codigo'],
                    'mensaje' => 'vencae '.$sala['ifx_server'].': '.$e->getMessage(),
                ];

                continue;
            }

            foreach ($lista as $venta) {
                if ($limite > 0 && $procesadas >= $limite) {
                    return;
                }
                $tipo = $this->tipoAnitaDesdeVenta($venta);
                $letra = $this->letraDesdeVenta($venta);
                $suc = (int) ($venta->puntoventas->codigo ?? 0);
                $nro = (int) $venta->numerocomprobante;
                $cae = TransferenciaCrownVencaeSupport::buscar($mapa, $tipo, $letra, $suc, $nro);
                $fila = [
                    'venta_id' => (int) $venta->id,
                    'codigo' => (string) $venta->codigo,
                    'origen' => 'gastro',
                    'sala' => $sala['codigo'],
                    'estado' => 'pendiente',
                    'mensaje' => '',
                ];
                if ($cae === null) {
                    $resultado['gastro']['cae_faltante_anita']++;
                    $fila['estado'] = 'sin_cae_anita';
                    $fila['mensaje'] = 'Sin vencae en '.$sala['ifx_server'];
                    $resultado['detalle'][] = $fila;
                    $procesadas++;

                    continue;
                }

                if ($dryRun) {
                    $resultado['gastro']['cae_completados']++;
                    $fila['estado'] = 'simulado_cae';
                    $fila['mensaje'] = $cae['cae'];
                    $resultado['detalle'][] = $fila;
                    $procesadas++;

                    continue;
                }

                try {
                    $venta->cae = $cae['cae'];
                    if ($cae['fechavencimientocae'] !== '') {
                        $venta->fechavencimientocae = $cae['fechavencimientocae'];
                    }
                    $venta->save();
                    $resultado['gastro']['cae_completados']++;
                    $fila['estado'] = 'cae_ok';
                    $fila['mensaje'] = $cae['cae'];
                } catch (\Throwable $e) {
                    $fila['estado'] = 'error';
                    $fila['mensaje'] = $e->getMessage();
                    $resultado['gastro']['errores'][] = $fila;
                    Log::error('transferencia_crown.completar_cae.fallo', [
                        'venta_id' => $venta->id,
                        'msg' => $e->getMessage(),
                    ]);
                }
                $resultado['detalle'][] = $fila;
                $procesadas++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    /**
     * @param  list<int>  $sucursales
     * @param  array<string, mixed>  $resultado
     */
    private function replicarArca(string $desde, string $hasta, bool $dryRun, int $limite, array $sucursales, array &$resultado): void
    {
        $query = Venta::query()
            ->with(['puntoventas', 'tipotransacciones', 'clientes', 'venta_emisiones', 'venta_impuestos'])
            ->whereBetween('fechajornada', [$desde, $hasta])
            ->where('leyenda', 'like', 'Importación ARCA%')
            ->when($sucursales !== [], function ($q) use ($sucursales): void {
                $codigos = array_map(
                    static fn (int $s): string => str_pad((string) $s, 5, '0', STR_PAD_LEFT),
                    $sucursales,
                );
                $q->whereHas('puntoventas', static fn ($pq) => $pq->whereIn('codigo', $codigos));
            })
            ->orderBy('id');
        $resultado['arca']['candidatas'] = (clone $query)->count();
        if ($limite > 0) {
            $query->limit($limite);
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
                'origen' => 'arca',
                'estado' => 'pendiente',
                'mensaje' => '',
            ];

            if (trim((string) ($venta->cae ?? '')) === '') {
                $fila['estado'] = 'sin_cae_erp';
                $fila['mensaje'] = 'Importación ARCA sin CAE en ERP; no se replica.';
                $resultado['arca']['errores'][] = $fila;
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            if (trim((string) ($venta->fechavencimientocae ?? '')) === '') {
                $vto = Carbon::parse((string) $venta->fecha)->addDays(10)->format('Y-m-d');
                if (! $dryRun) {
                    $venta->fechavencimientocae = $vto;
                    $venta->save();
                }
                $resultado['arca']['vto_completados']++;
                $venta->fechavencimientocae = $vto;
            }

            if (! $dryRun) {
                try {
                    $letra = $this->letraDesdeVenta($venta);
                    $consulta = $this->chequeoAnita->consultarCabeceraAnitaDesdeVenta($venta, $letra);
                    if ($consulta['error_lectura'] === null && $consulta['cabecera'] !== null) {
                        $resultado['arca']['omitidas_ya_en_anita']++;
                        $fila['estado'] = 'ya_en_anita';
                        $resultado['detalle'][] = $fila;
                        $procesadas++;

                        continue;
                    }
                } catch (\Throwable) {
                    // si no se puede chequear, intenta replicar
                }
            }

            if ($dryRun) {
                $resultado['arca']['replicadas']++;
                $fila['estado'] = 'simulado_replica';
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            try {
                $this->replicarAnita->replicarVenta($venta->fresh(), false, true);
                $this->grabarVencaeSiCorresponde($venta->fresh());
                $resultado['arca']['replicadas']++;
                $fila['estado'] = 'replicada';
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if ($this->esDuplicadoAnita($msg)) {
                    $resultado['arca']['omitidas_ya_en_anita']++;
                    $fila['estado'] = 'ya_en_anita';
                    $fila['mensaje'] = $msg;
                } else {
                    $fila['estado'] = 'error';
                    $fila['mensaje'] = $msg;
                    $resultado['arca']['errores'][] = $fila;
                    Log::error('transferencia_crown.replicar_arca.fallo', [
                        'venta_id' => $venta->id,
                        'msg' => $msg,
                    ]);
                }
            }
            $resultado['detalle'][] = $fila;
            $procesadas++;
        }
    }

    private function grabarVencaeSiCorresponde(Venta $venta): void
    {
        $cae = trim((string) ($venta->cae ?? ''));
        $vto = TransferenciaCrownVencaeSupport::fechaAnitaDesdeIso((string) ($venta->fechavencimientocae ?? ''));
        if ($cae === '' || $vto === '') {
            return;
        }
        $tipo = $this->tipoAnitaDesdeVenta($venta);
        $letra = $this->letraDesdeVenta($venta);
        $pv = (int) ($venta->puntoventas->codigo ?? 0);
        $nro = (int) $venta->numerocomprobante;
        $ok = $this->facturacion->grabaVenCae($tipo, $letra, $pv, $nro, $cae, $vto);
        if ($ok === 'Error') {
            throw new \RuntimeException('No se pudo grabar vencae en Anita para '.$venta->codigo);
        }
    }

    /**
     * @return array{codigo: string, nombre: string, ifx_server: string}|null
     */
    private function salaDesdeLeyenda(string $leyenda): ?array
    {
        foreach (TransferenciaCrownSalas::gastronomia() as $sala) {
            if (str_contains($leyenda, 'Transferencia gastronomía '.$sala['nombre'].' ')) {
                return $sala;
            }
        }

        return null;
    }

    private function tipoAnitaDesdeVenta(Venta $venta): string
    {
        $codigo = strtoupper(trim((string) ($venta->codigo ?? '')));
        if (preg_match('/^([A-Z]{2,3})\b/', $codigo, $m)) {
            return $m[1];
        }
        $abrev = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? $venta->tipotransacciones->codigo ?? 'FAC')));

        return $abrev !== '' ? $abrev : 'FAC';
    }

    private function letraDesdeVenta(Venta $venta): string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if (preg_match('/\s+([A-Z])[\s\-]/', $codigo, $m)) {
            return $m[1];
        }

        return 'B';
    }

    private function esDuplicadoAnita(string $mensaje): bool
    {
        $m = mb_strtolower($mensaje);

        return str_contains($m, 'duplicate')
            || str_contains($m, 'duplicad')
            || str_contains($m, 'unique')
            || str_contains($m, 'ya existe');
    }
}
