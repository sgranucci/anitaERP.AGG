<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Support\Caja\AnitaSync\RendicionGastronomiaCabeceraAnitaMapper;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Repara rendg_total_z y rendg_tot_nc en Anita por fecha de jornada, empresa y PV CAE.
 *
 * Portadora del Z/NC del día: secuencia de turno N → T → M (no depende del orden de carga en caja).
 * Si hay varias cabeceras del mismo turno, desempate por hora y nro_oper.
 */
final class RendicionGastronomiaRepararJornadaAnitaService
{
    /** @var list<string> */
    private const SECUENCIA_TURNO_PORTADORA = ['N', 'T', 'M'];

    /** @var array<int, string> */
    private array $letraTurnoPorTurnoOperativo = [];

    public function __construct(
        private readonly RendicionGastronomiaAnitaSyncService $anitaSyncService,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reparar(
        JornadaGastronomia $jornada,
        ?string $codigoPuntoventaFiltro = null,
        bool $dryRun = false,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_GASTRONOMIA_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d')
            ?? $jornada->cierre_en?->format('Y-m-d');

        if ($empresaId <= 0 || $fechaJornada === null || $fechaJornada === '') {
            throw new \InvalidArgumentException('La jornada no tiene empresa o fecha de jornada válida.');
        }

        $fechaEntera = (int) Carbon::parse($fechaJornada)->format('Ymd');
        $tipoOper = substr((string) config('rendicion_gastronomia_anita.tipo_oper', 'F'), 0, 1);
        $puntosVenta = $this->puntosVentaEnJornada($jornada, $codigoPuntoventaFiltro);
        $resultados = [];
        $this->letraTurnoPorTurnoOperativo = [];

        foreach ($puntosVenta as $pv) {
            $sucursal = $this->codigoPuntoventaEntero($pv->codigo);
            if ($sucursal <= 0) {
                continue;
            }

            $cabeceras = $this->listarCabecerasAnita($empresaId, $fechaEntera, $sucursal, $tipoOper);
            if ($cabeceras === []) {
                $resultados[] = [
                    'puntoventa' => $pv->codigo,
                    'sucursal' => $sucursal,
                    'estado' => 'sin_registros_anita',
                    'total_z' => null,
                    'tot_nc' => null,
                    'portadora_nro_oper' => null,
                    'cabeceras' => 0,
                ];

                continue;
            }

            $totalZ = GastronomiaTurnoOperativoTotalesSupport::totalFacturasSinNotasCreditoPorPuntoventa(
                (int) $pv->id,
                $empresaId,
                $fechaJornada,
            );
            $totNc = GastronomiaTurnoOperativoTotalesSupport::totalNotasCreditoPorPuntoventa(
                (int) $pv->id,
                $empresaId,
                $fechaJornada,
            );

            $portadora = $this->elegirPortadoraPorSecuenciaTurno($cabeceras);
            $portadoraNro = (int) ($portadora->rendg_nro_oper ?? 0);
            $portadoraLetra = $this->letraTurnoDesdeCabecera($portadora);

            $detalle = [];
            foreach ($this->ordenarCabecerasParaDetalle($cabeceras, $portadoraNro) as $fila) {
                $nroOper = (int) ($fila->rendg_nro_oper ?? 0);
                if ($nroOper <= 0) {
                    continue;
                }

                $esPortadora = $nroOper === $portadoraNro;
                $z = $esPortadora ? $totalZ : 0.0;
                $nc = $esPortadora ? $totNc : 0.0;

                if (! $dryRun) {
                    $this->anitaSyncService->actualizarTotalZYNcPorNroOper($nroOper, $z, $nc);
                }

                $detalle[] = [
                    'nro_oper' => $nroOper,
                    'turno' => $this->letraTurnoDesdeCabecera($fila),
                    'hora' => (string) ($fila->rendg_hora ?? ''),
                    'turno_erp' => (string) ($fila->rendg_nro_rend_vta ?? ''),
                    'z' => $z,
                    'tot_nc' => $nc,
                    'portadora' => $esPortadora,
                ];
            }

            $resultados[] = [
                'puntoventa' => $pv->codigo,
                'sucursal' => $sucursal,
                'estado' => $dryRun ? 'simulado' : 'actualizado',
                'total_z' => $totalZ,
                'tot_nc' => $totNc,
                'portadora_nro_oper' => $portadoraNro,
                'portadora_turno' => $portadoraLetra,
                'portadora_hora' => (string) ($portadora->rendg_hora ?? ''),
                'cabeceras' => count($detalle),
                'detalle' => $detalle,
            ];
        }

        return $resultados;
    }

    /**
     * @return Collection<int, Puntoventa>
     */
    private function puntosVentaEnJornada(JornadaGastronomia $jornada, ?string $codigoFiltro): Collection
    {
        $rendiciones = RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_TURNO)
            ->where('empresa_id', (int) $jornada->empresa_id)
            ->whereHas('turnoOperativo', fn ($q) => $q->where('jornada_gastronomia_id', (int) $jornada->id))
            ->with('puntoventaCae')
            ->get();

        $porId = [];
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

        return collect($porId)->sortBy('codigo')->values();
    }

    /**
     * @return list<object>
     */
    private function listarCabecerasAnita(
        int $empresaId,
        int $fechaEntera,
        int $sucursalCae,
        string $tipoOper,
    ): array {
        $where = " WHERE rendg_empresa = '".$empresaId."'"
            ." AND rendg_tipo_oper = '".RendicionGastronomiaCabeceraAnitaMapper::texto($tipoOper, 1)."'"
            ." AND rendg_fecha = '".$fechaEntera."'"
            ." AND rendg_sucursal = '".$sucursalCae."' ";

        $api = new ApiAnita;

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('rendicion_gastronomia_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_gastronomia_anita.tabla_cabecera', 'rendgastro'),
            'campos' => 'rendg_nro_oper, rendg_sucursal, rendg_nro_rend_vta, rendg_turno, rendg_total_z, rendg_tot_nc, rendg_hora, rendg_fecha_alfa',
            'whereArmado' => $where,
        ]));
    }

    /**
     * Elige la cabecera que recibe Z/NC: N si existe, si no T, si no M.
     *
     * @param  list<object>  $cabeceras
     */
    private function elegirPortadoraPorSecuenciaTurno(array $cabeceras): object
    {
        /** @var array<string, list<object>> $porLetra */
        $porLetra = [];
        foreach ($cabeceras as $fila) {
            $letra = $this->letraTurnoDesdeCabecera($fila);
            $porLetra[$letra][] = $fila;
        }

        foreach (self::SECUENCIA_TURNO_PORTADORA as $letra) {
            if (! empty($porLetra[$letra])) {
                return $this->elegirUnaEntreMismoTurno($porLetra[$letra]);
            }
        }

        return $this->elegirUnaEntreMismoTurno($cabeceras);
    }

    /**
     * Varias filas del mismo turno (reintentos): la de mayor hora, luego mayor nro_oper.
     *
     * @param  list<object>  $grupo
     */
    private function elegirUnaEntreMismoTurno(array $grupo): object
    {
        $copia = $grupo;
        usort($copia, fn (object $a, object $b): int => $this->compararPorHoraYNroOper($a, $b));

        return $copia[0];
    }

    /**
     * @param  list<object>  $cabeceras
     * @return list<object>
     */
    private function ordenarCabecerasParaDetalle(array $cabeceras, int $portadoraNro): array
    {
        $copia = $cabeceras;
        usort($copia, function (object $a, object $b) use ($portadoraNro): int {
            $aEsPortadora = (int) ($a->rendg_nro_oper ?? 0) === $portadoraNro;
            $bEsPortadora = (int) ($b->rendg_nro_oper ?? 0) === $portadoraNro;
            if ($aEsPortadora !== $bEsPortadora) {
                return $bEsPortadora <=> $aEsPortadora;
            }

            $prioA = $this->prioridadLetraTurno($this->letraTurnoDesdeCabecera($a));
            $prioB = $this->prioridadLetraTurno($this->letraTurnoDesdeCabecera($b));
            if ($prioA !== $prioB) {
                return $prioA <=> $prioB;
            }

            return $this->compararPorHoraYNroOper($a, $b);
        });

        return $copia;
    }

    private function prioridadLetraTurno(string $letra): int
    {
        $idx = array_search($letra, self::SECUENCIA_TURNO_PORTADORA, true);

        return $idx === false ? 99 : $idx;
    }

    private function compararPorHoraYNroOper(object $a, object $b): int
    {
        $segA = $this->segundosDesdeHora((string) ($a->rendg_hora ?? ''));
        $segB = $this->segundosDesdeHora((string) ($b->rendg_hora ?? ''));
        if ($segA !== $segB) {
            return $segB <=> $segA;
        }

        return (int) ($b->rendg_nro_oper ?? 0) <=> (int) ($a->rendg_nro_oper ?? 0);
    }

    private function letraTurnoDesdeCabecera(object $fila): string
    {
        $letra = trim((string) ($fila->rendg_turno ?? ''));
        if ($letra !== '' && $letra !== ' ') {
            return mb_strtoupper(mb_substr($letra, 0, 1));
        }

        $turnoOperativoId = (int) ($fila->rendg_nro_rend_vta ?? 0);
        if ($turnoOperativoId <= 0) {
            return '?';
        }

        if (! array_key_exists($turnoOperativoId, $this->letraTurnoPorTurnoOperativo)) {
            $turno = TurnoOperativoGastronomia::query()
                ->with('turno')
                ->find($turnoOperativoId);
            $nombre = trim((string) ($turno?->turno?->nombre ?? ''));
            $this->letraTurnoPorTurnoOperativo[$turnoOperativoId] = $nombre === ''
                ? '?'
                : mb_strtoupper(mb_substr($nombre, 0, 1));
        }

        return $this->letraTurnoPorTurnoOperativo[$turnoOperativoId];
    }

    private function segundosDesdeHora(string $hora): int
    {
        $hora = trim($hora);
        if ($hora === '') {
            return 0;
        }

        $partes = array_map('intval', explode(':', $hora));

        return ($partes[0] * 3600) + (($partes[1] ?? 0) * 60) + ($partes[2] ?? 0);
    }

    private function codigoPuntoventaEntero(?string $codigo): int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 0;
        }

        return (int) preg_replace('/\D+/', '', $codigo);
    }
}
