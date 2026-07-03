<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Ventas\Puntoventa;
use App\Services\Caja\Estacionamiento\EstacionamientoChequeoVentasAnitaErpService;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoAnitaRendgastroSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionCaeaCompartidoRendgSupport;
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
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroGastroSupport,
        private readonly GastronomiaConciliacionCaeaCompartidoRendgSupport $caeaCompartidoRendgSupport,
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

        if ($this->rendgastroSupport->esSucursalMaquinaVending($sucursal)) {
            return [
                'puntoventa' => $pv->codigo,
                'sucursal' => $sucursal,
                'estado' => 'vending_omitido',
                'erp_z' => $erpZ,
                'erp_nc' => $erpNc,
                'anita_z' => null,
                'anita_nc' => null,
                'diff_z' => null,
                'diff_nc' => null,
                'cantidad_facturas_erp' => $cantFacturas,
                'cantidad_nc_erp' => $cantNc,
                'mensaje' => 'Máquina vending (sucursal ≥ '
                    .RendicionEstacionamientoAnitaRendgastroSupport::SUCURSAL_VENDING_MINIMA
                    .'); no audita rendgastro desde estacionamiento',
            ];
        }

        $cabecerasTodas = $this->rendgastroSupport->listarCabecerasPorSucursal($empresaId, $fechaEntera, $sucursal);
        $contextoRendiciones = $this->contextoRendicionesPuntoventa($empresaId, $fechaJornada, (int) $pv->id);
        $cabeceras = $this->rendgastroSupport->filtrarCabecerasSoloEstacionamiento(
            $cabecerasTodas,
            $empresaId,
            $contextoRendiciones['nro_oper'],
            $contextoRendiciones['turno_oper_ids'],
        );

        if ($cabeceras === []) {
            $cabeceras = $this->resolverCabecerasPorHostPuntoventaCaea(
                $empresaId,
                $fechaEntera,
                (int) $pv->id,
                $contextoRendiciones,
            );
        }

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
        $zPortadora = round((float) ($portadora->rendg_total_z ?? 0), 2);
        $ncPortadora = round((float) ($portadora->rendg_tot_nc ?? 0), 2);
        $sumX = 0.0;
        foreach ($cabeceras as $cab) {
            $sumX += round((float) ($cab->rendg_total_x ?? 0), 2);
        }
        $sumX = round($sumX, 2);
        // Bruto ERP: Z almacenado = Σ total_x; si Z viejo venía neto (Σ total_x − NC), reconstituir.
        $zBrutoPortadora = abs($zPortadora - $sumX) <= $tolerancia
            ? $zPortadora
            : round($zPortadora + $ncPortadora, 2);
        $caeaNeto = $this->rendgastroGastroSupport->totalCaeaNetoCabeceras($cabeceras);
        $anitaZ = $this->resolverAnitaZPorPuntoventa(
            (int) $pv->id,
            $erpZ,
            $zBrutoPortadora,
            $caeaNeto,
            $tolerancia,
        );
        $anitaNc = 0.0;
        $esPuntoventaCaea = ConfiguracionPuntoventaEstacionamiento::query()
            ->where('puntoventa_caea_id', (int) $pv->id)
            ->exists();
        foreach ($cabeceras as $cab) {
            if ($esPuntoventaCaea) {
                $anitaNc += round((float) ($cab->rendg_tot_nc_caea ?? 0), 2);
            } else {
                $anitaNc += round((float) ($cab->rendg_tot_nc ?? 0), 2);
                $anitaNc += round((float) ($cab->rendg_tot_nc_caea ?? 0), 2);
            }
        }
        $anitaNc = round($anitaNc, 2);
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
     * PV CAEA compartido (00020): rendgastro usa rendg_sucursal del CAE; el CAEA va en rendg_tot_fc_caea por host.
     *
     * @param  array{nro_oper: list<int>, turno_oper_ids: list<int>}  $contextoRendiciones
     * @return list<object>
     */
    private function resolverCabecerasPorHostPuntoventaCaea(
        int $empresaId,
        int $fechaEntera,
        int $puntoventaId,
        array $contextoRendiciones,
    ): array {
        $hosts = $this->caeaCompartidoRendgSupport->hostsEstacionamientoConPuntoventaCaea($empresaId, $puntoventaId);
        if ($hosts === []) {
            return [];
        }

        $cabecerasDia = $this->rendgastroGastroSupport->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
        $cabeceras = [];

        foreach ($hosts as $host) {
            $porHost = $this->rendgastroGastroSupport->filtrarCabecerasPorHost($cabecerasDia, $host);
            $cabeceras = array_merge(
                $cabeceras,
                $this->rendgastroSupport->filtrarCabecerasSoloEstacionamiento(
                    $porHost,
                    $empresaId,
                    $contextoRendiciones['nro_oper'],
                    $contextoRendiciones['turno_oper_ids'],
                ),
            );
        }

        return array_values($cabeceras);
    }

    /**
     * Concilia Z Anita con ERP. rendg_total_z portadora = bruto (NC en turno portador) o bruto − NC (NC en otro turno).
     * Para comparar con facturas ERP del PV CAE se usa el bruto Z + rendg_tot_nc (− CAEA embebido si aplica).
     * PV CAEA compartido: neto rendg_tot_fc_caea de la PC originadora.
     */
    private function resolverAnitaZPorPuntoventa(
        int $puntoventaId,
        float $erpZ,
        float $zPortadora,
        float $caeaNeto,
        float $tolerancia,
    ): float {
        $esCaea = ConfiguracionPuntoventaEstacionamiento::query()
            ->where('puntoventa_caea_id', $puntoventaId)
            ->exists();

        if ($esCaea) {
            return round($caeaNeto, 2);
        }

        if ($caeaNeto > $tolerancia && $zPortadora > $caeaNeto + $tolerancia
            && abs($zPortadora - $erpZ - $caeaNeto) <= $tolerancia) {
            return round($zPortadora - $caeaNeto, 2);
        }

        return $zPortadora;
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
        foreach ($this->rendgastroGastroSupport->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera) as $fila) {
            if ($this->rendgastroGastroSupport->esCabeceraPostCierreWaitry($fila)) {
                continue;
            }
            $sucursal = (int) ($fila->rendg_sucursal ?? 0);
            if ($sucursal <= 0 || $this->rendgastroSupport->esSucursalMaquinaVending($sucursal)) {
                continue;
            }
            if (! $this->rendgastroGastroSupport->esSucursalDeEstacionamiento($empresaId, $sucursal)) {
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

    /**
     * @return array{nro_oper: list<int>, turno_oper_ids: list<int>}
     */
    private function contextoRendicionesPuntoventa(int $empresaId, string $fechaJornada, int $puntoventaId): array
    {
        $rendiciones = RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_TURNO)
            ->where('empresa_id', $empresaId)
            ->where('puntoventa_cae_id', $puntoventaId)
            ->whereHas('turnoOperativo', fn ($q) => $q->whereHas(
                'jornada',
                fn ($j) => $j->whereDate('fecha_jornada', $fechaJornada),
            ))
            ->get();

        $nroOper = [];
        foreach ($rendiciones as $rendicion) {
            $nro = (int) ($rendicion->nro_oper_anita ?? 0);
            if ($nro > 0) {
                $nroOper[] = $nro;
            }
        }

        $turnoOperIds = $rendiciones
            ->pluck('turno_operativo_estacionamiento_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return [
            'nro_oper' => array_values(array_unique($nroOper)),
            'turno_oper_ids' => $turnoOperIds,
        ];
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
