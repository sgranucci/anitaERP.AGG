<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Ventas\Puntoventa;
use App\Services\Caja\Estacionamiento\EstacionamientoChequeoVentasAnitaErpService;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoAnitaRendgastroSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Concilia facturación bruta del día (ERP) con rendg_total_z y rendg_tot_nc en rendgastro (Anita bridge).
 */
final class RendicionEstacionamientoAuditoriaAnitaService
{
    public function __construct(
        private readonly RendicionEstacionamientoAnitaSyncService $anitaSyncService,
        private readonly RendicionEstacionamientoAnitaRendgastroSupport $rendgastroSupport,
        private readonly EstacionamientoChequeoVentasAnitaErpService $chequeoVentasService,
    ) {
    }

    /**
     * @return array{
     *   fecha_jornada: string,
     *   empresa_id: int,
     *   tolerancia: float,
     *   filas: list<array<string, mixed>>,
     *   resumen: array<string, mixed>
     * }
     */
    public function auditarFechaJornada(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia = 0.02,
        ?string $codigoPuntoventaFiltro = null,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_ESTACIONAMIENTO_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $fechaJornada = Carbon::parse($fechaJornada)->toDateString();
        $fechaEntera = (int) Carbon::parse($fechaJornada)->format('Ymd');
        if ($fechaEntera <= 0) {
            throw new \InvalidArgumentException('Fecha de jornada inválida: '.$fechaJornada);
        }

        $puntosVenta = $this->resolverPuntosVenta($empresaId, $fechaJornada, $codigoPuntoventaFiltro);
        $filas = [];
        $conteo = [
            'ok' => 0,
            'diferencia' => 0,
            'sin_anita' => 0,
            'sin_ventas_erp' => 0,
        ];

        foreach ($puntosVenta as $pv) {
            $fila = $this->auditarPuntoventa($pv, $empresaId, $fechaJornada, $fechaEntera, $tolerancia);
            $filas[] = $fila;
            $estado = (string) ($fila['estado'] ?? '');
            if (isset($conteo[$estado])) {
                $conteo[$estado]++;
            }
        }

        return [
            'fecha_jornada' => $fechaJornada,
            'empresa_id' => $empresaId,
            'tolerancia' => $tolerancia,
            'filas' => $filas,
            'resumen' => [
                'puntoventas' => count($filas),
                'conteo' => $conteo,
                'requiere_alerta' => ($conteo['diferencia'] ?? 0) > 0
                    || ($conteo['sin_anita'] ?? 0) > 0,
                'filtro_erp' => 'venta.fechajornada + venta_estacionamiento_emision (facturas sin NC en Z; NC en tot_nc)',
                'filtro_anita' => 'rendgastro rendg_empresa + rendg_fecha + rendg_sucursal; portadora turno N→T→M',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditarPuntoventa(
        Puntoventa $pv,
        int $empresaId,
        string $fechaJornada,
        int $fechaEntera,
        float $tolerancia,
    ): array {
        $sucursal = $this->rendgastroSupport->codigoPuntoventaEntero($pv->codigo);
        $erpZ = EstacionamientoTurnoOperativoTotalesSupport::totalFacturasSinNotasCreditoPorPuntoventa(
            (int) $pv->id,
            $empresaId,
            $fechaJornada,
        );
        $erpNc = EstacionamientoTurnoOperativoTotalesSupport::totalNotasCreditoPorPuntoventa(
            (int) $pv->id,
            $empresaId,
            $fechaJornada,
        );
        $totalesErp = EstacionamientoTurnoOperativoTotalesSupport::totalesDiaPorPuntoventa(
            (int) $pv->id,
            $empresaId,
            $fechaJornada,
        );
        $cantFacturas = (int) ($totalesErp['cantidad_facturas'] ?? 0);
        $cantNc = (int) ($totalesErp['cantidad_notas_credito'] ?? 0);

        if ($sucursal <= 0) {
            return [
                'puntoventa' => $pv->codigo,
                'sucursal' => 0,
                'estado' => 'diferencia',
                'erp_z' => $erpZ,
                'erp_nc' => $erpNc,
                'anita_z' => null,
                'anita_nc' => null,
                'diff_z' => null,
                'diff_nc' => null,
                'cantidad_facturas_erp' => $cantFacturas,
                'cantidad_nc_erp' => $cantNc,
                'mensaje' => 'Código PV sin sucursal numérica',
            ];
        }

        $cabeceras = $this->rendgastroSupport->listarCabecerasPorSucursal($empresaId, $fechaEntera, $sucursal);

        if ($cabeceras === []) {
            $estado = ($erpZ > $tolerancia || $erpNc > $tolerancia) ? 'sin_anita' : 'sin_ventas_erp';

            return [
                'puntoventa' => $pv->codigo,
                'sucursal' => $sucursal,
                'estado' => $estado,
                'erp_z' => $erpZ,
                'erp_nc' => $erpNc,
                'anita_z' => null,
                'anita_nc' => null,
                'diff_z' => null,
                'diff_nc' => null,
                'cantidad_facturas_erp' => $cantFacturas,
                'cantidad_nc_erp' => $cantNc,
                'cabeceras' => 0,
                'mensaje' => $estado === 'sin_anita'
                    ? 'Hay facturación ERP sin rendgastro en Anita'
                    : 'Sin ventas ERP ni rendgastro',
            ];
        }

        $portadora = $this->rendgastroSupport->elegirPortadora($cabeceras);
        $portadoraNro = (int) ($portadora->rendg_nro_oper ?? 0);
        $anitaZ = round((float) ($portadora->rendg_total_z ?? 0), 2);
        $anitaNc = round((float) ($portadora->rendg_tot_nc ?? 0), 2);
        $diffZ = round($erpZ - $anitaZ, 2);
        $diffNc = round($erpNc - $anitaNc, 2);

        $detalle = $this->rendgastroSupport->detalleCabecerasOrdenado($cabeceras, $portadoraNro);
        $cabecerasHuerfanas = $this->detectarCabecerasConTotalesFueraPortadora($detalle, $tolerancia);
        $portadoraTurno = '—';
        foreach ($detalle as $d) {
            if (! empty($d['portadora'])) {
                $portadoraTurno = (string) ($d['turno'] ?? '—');
                break;
            }
        }

        $okZ = abs($diffZ) <= $tolerancia;
        $okNc = abs($diffNc) <= $tolerancia;
        $estado = ($okZ && $okNc && $cabecerasHuerfanas === []) ? 'ok' : 'diferencia';

        if ($erpZ <= $tolerancia && $erpNc <= $tolerancia && ($anitaZ > $tolerancia || $anitaNc > $tolerancia)) {
            $estado = 'diferencia';
        }

        return [
            'puntoventa' => $pv->codigo,
            'sucursal' => $sucursal,
            'estado' => $estado,
            'erp_z' => $erpZ,
            'erp_nc' => $erpNc,
            'anita_z' => $anitaZ,
            'anita_nc' => $anitaNc,
            'diff_z' => $diffZ,
            'diff_nc' => $diffNc,
            'cantidad_facturas_erp' => $cantFacturas,
            'cantidad_nc_erp' => $cantNc,
            'cabeceras' => count($detalle),
            'portadora_nro_oper' => $portadoraNro,
            'portadora_turno' => $portadoraTurno,
            'cabeceras_huerfanas' => $cabecerasHuerfanas,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  list<array{nro_oper:int, z:float, tot_nc:float, portadora:bool}>  $detalle
     * @return list<string>
     */
    private function detectarCabecerasConTotalesFueraPortadora(array $detalle, float $tolerancia): array
    {
        $alertas = [];
        foreach ($detalle as $d) {
            if (! empty($d['portadora'])) {
                continue;
            }
            if (abs((float) ($d['z'] ?? 0)) > $tolerancia || abs((float) ($d['tot_nc'] ?? 0)) > $tolerancia) {
                $alertas[] = 'nro_oper '.$d['nro_oper'].' turno '.$d['turno'].' tiene Z/NC distinto de cero fuera de portadora';
            }
        }

        return $alertas;
    }

    /**
     * @return Collection<int, Puntoventa>
     */
    private function resolverPuntosVenta(int $empresaId, string $fechaJornada, ?string $codigoFiltro): Collection
    {
        $porId = [];

        foreach ($this->chequeoVentasService->listarCombinacionesPvJornada(
            $fechaJornada,
            $fechaJornada,
            $empresaId,
            $codigoFiltro,
        ) as $combo) {
            $pv = Puntoventa::query()->find((int) $combo['puntoventa_id']);
            if ($pv !== null) {
                $porId[(int) $pv->id] = $pv;
            }
        }

        $rendiciones = RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_TURNO)
            ->where('empresa_id', $empresaId)
            ->whereHas('turnoOperativo', fn ($q) => $q->whereHas(
                'jornada',
                fn ($j) => $j->whereDate('fecha_jornada', $fechaJornada),
            ))
            ->with('puntoventaCae')
            ->get();

        foreach ($rendiciones as $rendicion) {
            $pv = $rendicion->puntoventaCae;
            if ($pv === null) {
                continue;
            }
            if ($codigoFiltro !== null && trim($codigoFiltro) !== ''
                && trim((string) $pv->codigo) !== trim($codigoFiltro)) {
                continue;
            }
            $porId[(int) $pv->id] = $pv;
        }

        $fechaEntera = (int) Carbon::parse($fechaJornada)->format('Ymd');
        foreach ($this->rendgastroSupport->listarCabecerasEmpresaFecha($empresaId, $fechaEntera) as $fila) {
            $sucursal = (int) ($fila->rendg_sucursal ?? 0);
            if ($sucursal <= 0) {
                continue;
            }
            $pv = $this->puntoventaPorSucursal($empresaId, $sucursal, $codigoFiltro);
            if ($pv !== null) {
                $porId[(int) $pv->id] = $pv;
            }
        }

        return collect($porId)->sortBy('codigo')->values();
    }

    private function puntoventaPorSucursal(int $empresaId, int $sucursal, ?string $codigoFiltro): ?Puntoventa
    {
        $candidatos = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', '!=', 'M')
            ->get();

        foreach ($candidatos as $pv) {
            if ($this->rendgastroSupport->codigoPuntoventaEntero($pv->codigo) !== $sucursal) {
                continue;
            }
            if ($codigoFiltro !== null && trim($codigoFiltro) !== ''
                && trim((string) $pv->codigo) !== trim($codigoFiltro)) {
                continue;
            }

            return $pv;
        }

        return null;
    }

    public function resolverJornada(int $empresaId, string $fechaJornada): ?JornadaEstacionamiento
    {
        return JornadaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first();
    }
}
