<?php

namespace App\Services\Ventas\GastronomiaCrown;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuenta;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Services\Caja\RendicionGastronomiaCajaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cierre X de Crown: lo que cada mozo (cajero en la lógica Anita de p-cierrecajero.c) tiene
 * que rendir en tesorería antes del Z fiscal. El total X sale de las cuentas cerradas del POS
 * Crown de la jornada de la sala, agrupadas por mozo.
 */
class GastronomiaCrownCierreXService
{
    public function __construct(
        private readonly RendicionGastronomiaCajaService $rendicionService,
    ) {
    }

    /**
     * Jornadas (abiertas y cerradas) de la empresa + sala para elegir el cierre X.
     *
     * @return Collection<int, JornadaGastronomia>
     */
    public function jornadasParaElegir(int $empresaId, ?int $salaId, int $limite = 30): Collection
    {
        if ($empresaId <= 0) {
            return collect();
        }

        return JornadaGastronomia::query()
            ->with(['sala:id,codigo,nombre', 'usuarioApertura:id,nombre'])
            ->where('empresa_id', $empresaId)
            ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
            ->orderByDesc('fecha_jornada')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    /**
     * Total a rendir por mozo. Si viene jornada se usa su ventana (apertura → cierre o ahora);
     * si no, el día calendario de `$fecha` sobre `cerrada_at`.
     *
     * @return list<array{
     *   mozo_gastronomia_id:int,
     *   mozo_codigo:string,
     *   mozo_nombre:string,
     *   cuentas:int,
     *   total_x:float,
     *   total_facturado:float,
     *   ya_rendido:bool,
     *   rendicion_id:int
     * }>
     */
    public function totalesPorMozo(
        int $empresaId,
        ?int $salaId,
        ?JornadaGastronomia $jornada = null,
        ?string $fecha = null,
    ): array {
        if ($empresaId <= 0) {
            return [];
        }

        $q = GastroCrownCuenta::query()
            ->leftJoin('venta as v', 'v.id', '=', 'gastro_crown_cuenta.venta_id')
            ->where('gastro_crown_cuenta.empresa_id', $empresaId)
            ->where('gastro_crown_cuenta.estado', GastroCrownCuenta::ESTADO_CERRADA)
            ->whereNotNull('gastro_crown_cuenta.mozo_gastronomia_id')
            ->when($salaId !== null && $salaId > 0, fn ($qq) => $qq->where('gastro_crown_cuenta.sala_id', $salaId))
            ->groupBy('gastro_crown_cuenta.mozo_gastronomia_id')
            ->select([
                'gastro_crown_cuenta.mozo_gastronomia_id',
                DB::raw('COUNT(*) as cuentas'),
                DB::raw('COALESCE(SUM(gastro_crown_cuenta.importe_cobrado), 0) as total_x'),
                DB::raw('COALESCE(SUM(v.total), 0) as total_facturado'),
            ]);

        $this->aplicarVentana($q, $jornada, $fecha);

        $filas = $q->get();
        if ($filas->isEmpty()) {
            return [];
        }

        $mozos = MozoGastronomia::query()
            ->whereIn('id', $filas->pluck('mozo_gastronomia_id')->map(fn ($v) => (int) $v)->all())
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $rendiciones = $jornada !== null
            ? RendicionGastronomiaCaja::query()
                ->where('tipo', RendicionGastronomiaCaja::TIPO_CIERRE_X)
                ->where('jornada_gastronomia_id', (int) $jornada->id)
                ->pluck('id', 'mozo_gastronomia_id')
            : collect();

        $out = [];
        foreach ($filas as $fila) {
            $mozoId = (int) $fila->mozo_gastronomia_id;
            $mozo = $mozos->get($mozoId);
            $rendicionId = (int) ($rendiciones[$mozoId] ?? 0);

            $out[] = [
                'mozo_gastronomia_id' => $mozoId,
                'mozo_codigo' => (string) ($mozo->codigo ?? ''),
                'mozo_nombre' => (string) ($mozo->nombre ?? ('Mozo #'.$mozoId)),
                'cuentas' => (int) $fila->cuentas,
                'total_x' => round((float) $fila->total_x, 2),
                'total_facturado' => round((float) $fila->total_facturado, 2),
                'ya_rendido' => $rendicionId > 0,
                'rendicion_id' => $rendicionId,
            ];
        }

        usort($out, fn (array $a, array $b) => strcmp($a['mozo_nombre'], $b['mozo_nombre']));

        return $out;
    }

    /**
     * Crea la rendición de tesorería del mozo y la deja lista para que Caja la continúe.
     */
    public function crearRendicion(JornadaGastronomia $jornada, int $mozoId, ?int $salaId = null): RendicionGastronomiaCaja
    {
        $empresaId = (int) $jornada->empresa_id;
        $salaId = $salaId !== null && $salaId > 0
            ? $salaId
            : ($jornada->sala_id !== null ? (int) $jornada->sala_id : null);

        if ($mozoId <= 0) {
            throw new InvalidArgumentException('Debe indicar el mozo a rendir.');
        }

        $mozo = MozoGastronomia::query()->findOrFail($mozoId);
        if ((int) $mozo->empresa_id !== $empresaId) {
            throw new InvalidArgumentException('El mozo pertenece a otra empresa.');
        }

        if ($this->rendicionService->cierreXYaRendido((int) $jornada->id, $mozoId)) {
            throw new InvalidArgumentException(
                'El mozo '.$mozo->nombre.' ya tiene una rendición de cierre X en esta jornada.'
            );
        }

        $totales = collect($this->totalesPorMozo($empresaId, $salaId, $jornada))
            ->firstWhere('mozo_gastronomia_id', $mozoId);

        if ($totales === null) {
            throw new InvalidArgumentException(
                'El mozo '.$mozo->nombre.' no tiene cuentas cerradas en esta jornada.'
            );
        }

        $totalX = round((float) $totales['total_x'], 2);
        $referencias = $this->referenciasCaja($empresaId, $salaId);

        $cabecera = [
            'tipo' => RendicionGastronomiaCaja::TIPO_CIERRE_X,
            'codigo' => $this->proponerCodigo($empresaId, (int) $jornada->id, $mozoId),
            'empresa_id' => $empresaId,
            'sala_id' => $salaId,
            'mozo_gastronomia_id' => $mozoId,
            'jornada_gastronomia_id' => (int) $jornada->id,
            'puntoventa_cae_id' => $referencias['puntoventa_cae_id'],
            'puntoventa_caea_id' => $referencias['puntoventa_caea_id'],
            'caja_id' => $referencias['caja_id'],
            'creousuario_id' => (int) Auth::id(),
            'fecharendicion' => now()->format('Y-m-d H:i:s'),
            'iniciodelfondo' => 0.0,
            'totalfactura' => round((float) $totales['total_facturado'], 2),
            'totalcobrado' => $totalX,
            'total_x' => $totalX,
            'totalinvitacion' => 0.0,
            'totalnotacredito' => 0.0,
            'totalredondeo' => 0.0,
            'totalredondeoinvitacion' => 0.0,
            'sobrantefaltante' => 0.0,
            'observacion' => 'Cierre X — mozo '.$mozo->nombre
                .' — jornada #'.$jornada->id
                .' del '.($jornada->fecha_jornada?->format('d/m/Y') ?? ''),
        ];

        return $this->rendicionService->guardar($cabecera, []);
    }

    /**
     * Punto de venta y caja con los que Caja continúa la rendición (columnas NOT NULL).
     *
     * @return array{puntoventa_cae_id:int, puntoventa_caea_id:int, caja_id:int}
     */
    private function referenciasCaja(int $empresaId, ?int $salaId): array
    {
        $cfg = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->when($salaId !== null && $salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
            ->orderBy('id')
            ->first();

        if ($cfg === null && $salaId !== null) {
            $cfg = ConfiguracionPuntoventaGastronomia::query()
                ->where('empresa_id', $empresaId)
                ->orderBy('id')
                ->first();
        }

        if ($cfg === null) {
            throw new InvalidArgumentException(
                'No hay configuración de punto de venta de gastronomía para la empresa/sala. '
                .'Configúrela en Configuración → Configuración por módulo → Ventas.'
            );
        }

        $caeId = (int) ($cfg->puntoventa_cae_id ?? 0);
        $caeaId = (int) ($cfg->puntoventa_caea_id ?? 0);
        $cajaId = (int) ($cfg->caja_id ?? 0);

        if ($caeId <= 0) {
            throw new InvalidArgumentException('La configuración de punto de venta no tiene punto de venta CAE.');
        }

        if ($cajaId <= 0) {
            $cajaId = (int) (config('cliente.CAJA_DEFAULT_ID') ?? 0);
        }

        if ($cajaId <= 0) {
            throw new InvalidArgumentException(
                'La configuración de punto de venta no tiene caja de recepción. '
                .'Asigne la caja en la configuración del punto de venta.'
            );
        }

        return [
            'puntoventa_cae_id' => $caeId,
            'puntoventa_caea_id' => $caeaId > 0 ? $caeaId : $caeId,
            'caja_id' => $cajaId,
        ];
    }

    private function proponerCodigo(int $empresaId, int $jornadaId, int $mozoId): string
    {
        return 'RGX-'.$empresaId.'-'.$jornadaId.'-M'.$mozoId;
    }

    /**
     * Ventana temporal del cierre X: la jornada manda; si no hay jornada se usa el día.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<GastroCrownCuenta>  $q
     */
    private function aplicarVentana($q, ?JornadaGastronomia $jornada, ?string $fecha): void
    {
        if ($jornada !== null) {
            $desde = $jornada->apertura_en ?? $jornada->fecha_jornada?->startOfDay();
            $hasta = $jornada->cierre_en ?? now();

            if ($desde !== null) {
                $q->where('gastro_crown_cuenta.cerrada_at', '>=', $desde);
            }
            $q->where('gastro_crown_cuenta.cerrada_at', '<=', $hasta);

            return;
        }

        $q->whereDate('gastro_crown_cuenta.cerrada_at', $fecha ?: now()->format('Y-m-d'));
    }
}
