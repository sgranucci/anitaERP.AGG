<?php

namespace App\Support\Caja\AnitaSync;

use App\ApiAnita;
use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;

/**
 * Lectura y reglas de portadora Z/NC en Informix rendgastro (bridge Anita).
 */
final class RendicionEstacionamientoAnitaRendgastroSupport
{
    /** @var list<string> */
    public const SECUENCIA_TURNO_PORTADORA = ['N', 'T', 'M'];

    /** @var array<int, string> */
    private array $letraTurnoPorTurnoOperativo = [];

    /** @var array<int, list<string>> */
    private array $hostsEstacionamientoPorEmpresaCache = [];

    /** Sucursal rendgastro de máquina vending (Maquina N); no modificar desde estacionamiento. */
    public const SUCURSAL_VENDING_MINIMA = 1001;

    public function esSucursalMaquinaVending(int $sucursal): bool
    {
        return $sucursal >= self::SUCURSAL_VENDING_MINIMA;
    }

    public static function letraTurnoDesdeNombre(?string $nombreTurno): string
    {
        $nombre = trim((string) $nombreTurno);
        if ($nombre === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($nombre, 0, 1));
    }

    /**
     * @return list<object>
     */
    public function listarCabecerasPorSucursal(
        int $empresaId,
        int $fechaEntera,
        int $sucursalCae,
        ?string $tipoOper = null,
    ): array {
        $tipoOper = $tipoOper ?? (string) config('rendicion_estacionamiento_anita.tipo_oper', 'F');
        $where = " WHERE rendg_empresa = '".$empresaId."'"
            ." AND rendg_tipo_oper = '".RendicionEstacionamientoCabeceraAnitaMapper::texto($tipoOper, 1)."'"
            ." AND rendg_fecha = '".$fechaEntera."'"
            ." AND rendg_sucursal = '".$sucursalCae."' ";

        $api = new ApiAnita;

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('rendicion_estacionamiento_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_estacionamiento_anita.tabla_cabecera', 'rendgastro'),
            'campos' => 'rendg_nro_oper, rendg_sucursal, rendg_nro_rend_vta, rendg_turno, rendg_total_x, rendg_total_z, rendg_tot_nc, rendg_hora, rendg_fecha_alfa, rendg_host, rendg_estado',
            'whereArmado' => $where,
        ]));
    }

    /**
     * @return list<object>
     */
    public function listarCabecerasEmpresaFecha(
        int $empresaId,
        int $fechaEntera,
        ?string $tipoOper = null,
    ): array {
        $tipoOper = $tipoOper ?? (string) config('rendicion_estacionamiento_anita.tipo_oper', 'F');
        $where = " WHERE rendg_empresa = '".$empresaId."'"
            ." AND rendg_tipo_oper = '".RendicionEstacionamientoCabeceraAnitaMapper::texto($tipoOper, 1)."'"
            ." AND rendg_fecha = '".$fechaEntera."' ";

        $api = new ApiAnita;

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('rendicion_estacionamiento_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_estacionamiento_anita.tabla_cabecera', 'rendgastro'),
            'campos' => 'rendg_nro_oper, rendg_sucursal, rendg_nro_rend_vta, rendg_turno, rendg_total_z, rendg_tot_nc, rendg_hora',
            'whereArmado' => $where,
        ]));
    }

    /**
     * Elige la cabecera que debe portar Z/NC del día: N → T → M.
     *
     * @param  list<object>  $cabeceras
     */
    public function elegirPortadora(array $cabeceras): object
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
     * @param  list<object>  $cabeceras
     * @return list<array{nro_oper:int, turno:string, hora:string, turno_erp:string, z:float, tot_nc:float, portadora:bool}>
     */
    public function detalleCabecerasOrdenado(array $cabeceras, int $portadoraNroOper): array
    {
        $copia = $cabeceras;
        usort($copia, function (object $a, object $b) use ($portadoraNroOper): int {
            $aEsPortadora = (int) ($a->rendg_nro_oper ?? 0) === $portadoraNroOper;
            $bEsPortadora = (int) ($b->rendg_nro_oper ?? 0) === $portadoraNroOper;
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

        $detalle = [];
        foreach ($copia as $fila) {
            $nroOper = (int) ($fila->rendg_nro_oper ?? 0);
            if ($nroOper <= 0) {
                continue;
            }
            $detalle[] = [
                'nro_oper' => $nroOper,
                'turno' => $this->letraTurnoDesdeCabecera($fila),
                'hora' => (string) ($fila->rendg_hora ?? ''),
                'turno_erp' => (string) ($fila->rendg_nro_rend_vta ?? ''),
                'z' => round((float) ($fila->rendg_total_z ?? 0), 2),
                'tot_nc' => round((float) ($fila->rendg_tot_nc ?? 0), 2),
                'portadora' => $nroOper === $portadoraNroOper,
            ];
        }

        return $detalle;
    }

    public function codigoPuntoventaEntero(?string $codigo): int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 0;
        }

        return (int) preg_replace('/\D+/', '', $codigo);
    }

    public function listarCabeceraPorNroOper(int $nroOper): ?object
    {
        if ($nroOper <= 0) {
            return null;
        }

        $where = " WHERE rendg_nro_oper = '".$nroOper."' ";
        $api = new ApiAnita;
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('rendicion_estacionamiento_anita.sistema', 'caja'),
            'tabla' => (string) config('rendicion_estacionamiento_anita.tabla_cabecera', 'rendgastro'),
            'campos' => 'rendg_nro_oper, rendg_sucursal, rendg_host, rendg_estado',
            'whereArmado' => $where,
        ]));

        return $filas[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function hostsEstacionamientoEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        if (isset($this->hostsEstacionamientoPorEmpresaCache[$empresaId])) {
            return $this->hostsEstacionamientoPorEmpresaCache[$empresaId];
        }

        $hosts = ConfiguracionPuntoventaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->pluck('identificador_pc')
            ->map(static fn ($pc): string => trim((string) $pc))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->hostsEstacionamientoPorEmpresaCache[$empresaId] = $hosts;
    }

    public function esCabeceraPostCierreWaitry(object $fila): bool
    {
        $host = mb_strtoupper(trim((string) ($fila->rendg_host ?? '')));

        return $host === 'CIERRE-WAITRY'
            || str_contains($host, 'CIERRE-JORNADA-WAITRY')
            || str_contains($host, 'WAITRY');
    }

    /**
     * Cabecera rendgastro de rendición estacionamiento (no salón / Waitry en PV compartido).
     *
     * @param  list<int>  $nroOperErp
     * @param  list<int>  $turnoOperativoIds
     */
    public function esCabeceraRendicionEstacionamiento(
        object $fila,
        int $empresaId,
        array $nroOperErp = [],
        array $turnoOperativoIds = [],
    ): bool {
        if ($this->esSucursalMaquinaVending((int) ($fila->rendg_sucursal ?? 0))) {
            return false;
        }

        if ($this->esCabeceraPostCierreWaitry($fila)) {
            return false;
        }

        $nroOper = (int) ($fila->rendg_nro_oper ?? 0);
        if ($nroOper > 0 && in_array($nroOper, $nroOperErp, true)) {
            return true;
        }

        $turnoOperId = (int) ($fila->rendg_nro_rend_vta ?? 0);
        if ($turnoOperId > 0 && in_array($turnoOperId, $turnoOperativoIds, true)) {
            return true;
        }

        $host = trim((string) ($fila->rendg_host ?? ''));
        if ($host !== '' && in_array($host, $this->hostsEstacionamientoEmpresa($empresaId), true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<object>  $cabeceras
     * @param  list<int>  $nroOperErp
     * @param  list<int>  $turnoOperativoIds
     * @return list<object>
     */
    public function filtrarCabecerasSoloEstacionamiento(
        array $cabeceras,
        int $empresaId,
        array $nroOperErp = [],
        array $turnoOperativoIds = [],
    ): array {
        return array_values(array_filter(
            $cabeceras,
            fn (object $fila): bool => $this->esCabeceraRendicionEstacionamiento(
                $fila,
                $empresaId,
                $nroOperErp,
                $turnoOperativoIds,
            ),
        ));
    }

    /**
     * @param  list<object>  $grupo
     */
    private function elegirUnaEntreMismoTurno(array $grupo): object
    {
        $copia = $grupo;
        usort($copia, fn (object $a, object $b): int => $this->compararPorHoraYNroOper($a, $b));

        return $copia[0];
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
            $turno = TurnoOperativoEstacionamiento::query()
                ->with('turno')
                ->find($turnoOperativoId);
            $nombre = trim((string) ($turno?->turno?->nombre ?? ''));
            $this->letraTurnoPorTurnoOperativo[$turnoOperativoId] = self::letraTurnoDesdeNombre($nombre);
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
}
