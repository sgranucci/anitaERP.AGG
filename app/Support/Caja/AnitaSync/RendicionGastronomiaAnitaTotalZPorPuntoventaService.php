<?php

namespace App\Support\Caja\AnitaSync;

use App\Models\Ventas\Puntoventa;
use App\Services\Caja\RendicionGastronomiaAnitaSyncService;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Recalcula rendg_total_z / rendg_tot_nc en Anita por PV (rendg_sucursal) y fecha de jornada.
 *
 * Misma regla que reparación / presentación jornada en caja: Z bruto del día (ERP, sin NC)
 * solo en la cabecera portadora (turno N → T → M; desempate hora / nro_oper).
 * Usado al agregar la rendición adicional del proceso Waitry (PV CAEA del batch CF).
 */
final class RendicionGastronomiaAnitaTotalZPorPuntoventaService
{
    private const LOG_EVENTO = 'rendicion_gastronomia.anita_total_z_pv';

    public function __construct(
        private readonly RendicionGastronomiaAnitaSyncService $anitaSyncService,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
    ) {
    }

    /**
     * @return array{
     *   puntoventa: string,
     *   sucursal: int,
     *   total_z: float,
     *   tot_nc: float,
     *   portadora_nro_oper: int|null,
     *   cabeceras: int,
     *   detalle: list<array<string, mixed>>
     * }
     */
    public function aplicar(
        int $empresaId,
        string $fechaJornada,
        int $puntoventaId,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            return [
                'puntoventa' => '',
                'sucursal' => 0,
                'total_z' => 0.0,
                'tot_nc' => 0.0,
                'portadora_nro_oper' => null,
                'cabeceras' => 0,
                'detalle' => [],
                'estado' => 'sync_deshabilitado',
            ];
        }

        if ($empresaId <= 0 || $puntoventaId <= 0 || trim($fechaJornada) === '') {
            throw new \InvalidArgumentException('Empresa, PV o fecha de jornada inválidos para recalcular Z.');
        }

        $pv = Puntoventa::query()->find($puntoventaId);
        if ($pv === null) {
            throw new \InvalidArgumentException('Punto de venta #'.$puntoventaId.' inexistente.');
        }

        $fechaJornada = Carbon::parse($fechaJornada)->toDateString();
        $fechaEntera = (int) Carbon::parse($fechaJornada)->format('Ymd');
        $sucursal = $this->rendgastroSupport->codigoPuntoventaEntero($pv->codigo);

        if ($sucursal <= 0) {
            throw new \InvalidArgumentException('PV '.$pv->codigo.' sin código de sucursal numérico.');
        }

        $totalZ = GastronomiaTurnoOperativoTotalesSupport::totalFacturasSinNotasCreditoPorPuntoventa(
            $puntoventaId,
            $empresaId,
            $fechaJornada,
        );
        $totNc = GastronomiaTurnoOperativoTotalesSupport::totalNotasCreditoPorPuntoventa(
            $puntoventaId,
            $empresaId,
            $fechaJornada,
        );

        $cabeceras = $this->rendgastroSupport->listarCabecerasPorSucursal($empresaId, $fechaEntera, $sucursal);
        if ($cabeceras === []) {
            return [
                'puntoventa' => (string) $pv->codigo,
                'sucursal' => $sucursal,
                'total_z' => $totalZ,
                'tot_nc' => $totNc,
                'portadora_nro_oper' => null,
                'cabeceras' => 0,
                'detalle' => [],
                'estado' => 'sin_registros_anita',
            ];
        }

        $portadora = $this->rendgastroSupport->elegirPortadora($cabeceras);
        $portadoraNro = (int) ($portadora->rendg_nro_oper ?? 0);
        $detalle = [];

        foreach ($this->rendgastroSupport->detalleCabecerasOrdenado($cabeceras, $portadoraNro) as $d) {
            $nroOper = (int) $d['nro_oper'];
            $esPortadora = ! empty($d['portadora']);
            $z = $esPortadora ? $totalZ : 0.0;
            $nc = $esPortadora ? $totNc : 0.0;

            try {
                $this->anitaSyncService->actualizarTotalZYNcPorNroOper($nroOper, $z, $nc);
            } catch (\Throwable $e) {
                Log::warning(self::LOG_EVENTO.'.fallo', [
                    'empresa_id' => $empresaId,
                    'puntoventa_id' => $puntoventaId,
                    'nro_oper' => $nroOper,
                    'total_z' => $z,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $detalle[] = array_merge($d, [
                'z' => $z,
                'tot_nc' => $nc,
            ]);
        }

        return [
            'puntoventa' => (string) $pv->codigo,
            'sucursal' => $sucursal,
            'total_z' => $totalZ,
            'tot_nc' => $totNc,
            'portadora_nro_oper' => $portadoraNro > 0 ? $portadoraNro : null,
            'cabeceras' => count($detalle),
            'detalle' => $detalle,
            'estado' => 'actualizado',
        ];
    }
}
