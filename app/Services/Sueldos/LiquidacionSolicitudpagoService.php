<?php

namespace App\Services\Sueldos;

use App\Models\Solicitudpago\Concepto_Solicitudpago;
use App\Models\Solicitudpago\Concepto_Solicitudpago_Cuenta;
use App\Models\Solicitudpago\Formapagosol;
use App\Models\Solicitudpago\Solicitudpago;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Sueldos\SueldosAsientoCalidadCierreSupport;
use App\Support\Sueldos\SueldosAsientoSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Genera la solicitud de pago del neto (fase 3).
 * El asiento TES lo hace el Ingreso/Egreso al pagar; acá solo se arma la SP.
 */
class LiquidacionSolicitudpagoService
{
    public function __construct(
        private SolicitudpagoRepositoryInterface $solicitudpagoRepository,
    ) {}

    public function generar(Liquidacion_Sueldos $liq): int
    {
        $this->assertPuedeGenerar($liq);

        $preview = SueldosAsientoCalidadCierreSupport::evaluar($liq);
        $monto = round((float) ($preview['haber_a_pagar'] ?? $liq->total_neto ?? 0), 2);
        if ($monto < 0.01) {
            throw new RuntimeException('La corrida no tiene neto para solicitar el pago.');
        }

        $aPagarId = (int) (CuentaAutomaticaResolver::resolverId(
            (int) $liq->empresa_id,
            CuentaAutomaticaClaves::SUELDOS_A_PAGAR
        ) ?? 0);
        if ($aPagarId <= 0) {
            throw new RuntimeException('Falta la cuenta automática sueldos.a_pagar.');
        }

        $concepto = $this->resolverConceptoHaberes();
        $formaId = $this->resolverFormaPagoId();
        $haberBancoId = $this->resolverHaberBancoId((int) $liq->empresa_id, $concepto);

        $empresaIds = [];
        $cuentaIds = [];
        $ccIds = [];
        $dhs = [];
        $montos = [];

        foreach ($preview['lineas'] ?? [] as $linea) {
            if ((int) ($linea['cuentacontable_id'] ?? 0) !== $aPagarId) {
                continue;
            }
            $haber = round((float) ($linea['haber'] ?? 0), 2);
            if ($haber < 0.01) {
                continue;
            }
            $empresaIds[] = (int) $liq->empresa_id;
            $cuentaIds[] = $aPagarId;
            $ccIds[] = (int) ($linea['centrocosto_id'] ?? 0) ?: null;
            $dhs[] = 'D';
            $montos[] = $haber;
        }

        if ($empresaIds === []) {
            $empresaIds[] = (int) $liq->empresa_id;
            $cuentaIds[] = $aPagarId;
            $ccIds[] = null;
            $dhs[] = 'D';
            $montos[] = $monto;
        }

        if ($haberBancoId > 0) {
            $empresaIds[] = (int) $liq->empresa_id;
            $cuentaIds[] = $haberBancoId;
            $ccIds[] = null;
            $dhs[] = 'H';
            $montos[] = $monto;
        }

        $periodoLabel = $liq->periodo_mes
            ? sprintf('%02d/%04d', (int) $liq->periodo_mes, (int) $liq->periodo_anio)
            : (string) ($liq->periodo ?? '');
        $fechaVto = $liq->fecha_pago?->format('Y-m-d') ?? now()->toDateString();

        $payload = [
            'empresa_id' => (int) $liq->empresa_id,
            'fecha' => now()->toDateString(),
            'tratamiento' => SolicitudpagoTratamientos::NORMAL,
            'proveedor_id' => null,
            'concepto_solicitudpago_id' => $concepto?->id,
            'formapagosol_id' => $formaId,
            'moneda_id' => SueldosAsientoSupport::MONEDA_LOCAL_ID,
            'beneficiario' => $this->beneficiario($liq),
            'fecha_entrega' => $fechaVto,
            'fecha_vencimiento' => $fechaVto,
            'monto' => $monto,
            'estado' => SolicitudpagoEstados::AUTORIZADA,
            'sector_solicitudpago_id' => $concepto?->sector_solicitudpago_id,
            'detalle' => trim('Haberes corrida '.$liq->numero.($periodoLabel !== '' ? ' '.$periodoLabel : '')),
            'liquidacion_sueldos_id' => (int) $liq->id,
            'empresa_ids' => $empresaIds,
            'cuentacontable_ids' => $cuentaIds,
            'centrocosto_ids' => $ccIds,
            'debe_haberes' => $dhs,
            'montos_cuenta' => $montos,
        ];

        return (int) DB::transaction(function () use ($liq, $payload) {
            $sp = $this->solicitudpagoRepository->create($payload);
            $spId = (int) ($sp->id ?? 0);
            if ($spId <= 0) {
                throw new RuntimeException('No se pudo generar la solicitud de pago.');
            }

            $liq->update(['solicitudpago_id' => $spId]);

            return $spId;
        });
    }

    public function marcarPagada(Solicitudpago $sp): void
    {
        $liqId = (int) ($sp->liquidacion_sueldos_id ?? 0);
        if ($liqId <= 0) {
            return;
        }

        Liquidacion_Sueldos::query()
            ->whereKey($liqId)
            ->where('estado', 'contabilizada')
            ->update(['estado' => 'pagada']);
    }

    public function revertirPago(Solicitudpago $sp): void
    {
        $liqId = (int) ($sp->liquidacion_sueldos_id ?? 0);
        if ($liqId <= 0) {
            return;
        }

        Liquidacion_Sueldos::query()
            ->whereKey($liqId)
            ->where('estado', 'pagada')
            ->update(['estado' => 'contabilizada']);
    }

    public function assertSinSolicitudActiva(Liquidacion_Sueldos $liq): void
    {
        $spId = (int) ($liq->solicitudpago_id ?? 0);
        if ($spId <= 0) {
            return;
        }
        $sp = Solicitudpago::query()->find($spId);
        if ($sp === null) {
            return;
        }
        $estado = (string) $sp->estado;
        if (in_array($estado, [SolicitudpagoEstados::PAGADA, SolicitudpagoEstados::AUTORIZADA], true)) {
            throw new RuntimeException(
                'La corrida tiene la solicitud de pago '.$sp->codigo.' en estado '.$estado
                .'. Anulá o revertí el pago antes de descontabilizar.'
            );
        }
    }

    private function assertPuedeGenerar(Liquidacion_Sueldos $liq): void
    {
        if ((string) $liq->estado !== 'contabilizada' && empty($liq->contabilizado)) {
            throw new RuntimeException('Solo se genera la solicitud de pago de una corrida contabilizada.');
        }
        if ((int) ($liq->solicitudpago_id ?? 0) > 0) {
            throw new RuntimeException('La corrida ya tiene una solicitud de pago.');
        }
        if (Solicitudpago::query()->where('liquidacion_sueldos_id', $liq->id)->exists()) {
            throw new RuntimeException('Ya existe una solicitud de pago para esta corrida.');
        }
        if (! empty($liq->simulacion)) {
            throw new RuntimeException('Una simulación no genera solicitud de pago.');
        }
    }

    private function resolverConceptoHaberes(): ?Concepto_Solicitudpago
    {
        return Concepto_Solicitudpago::query()
            ->where('nombre', SueldosAsientoSupport::CONCEPTO_SP_NOMBRE)
            ->where('estado', 'ACTIVO')
            ->orderBy('id')
            ->first();
    }

    private function resolverFormaPagoId(): ?int
    {
        $id = (int) (Formapagosol::query()
            ->where('nombre', SueldosAsientoSupport::FORMA_PAGO_SP_NOMBRE)
            ->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }

    private function resolverHaberBancoId(int $empresaId, ?Concepto_Solicitudpago $concepto): int
    {
        if ($concepto !== null) {
            $id = (int) (Concepto_Solicitudpago_Cuenta::query()
                ->where('concepto_solicitudpago_id', $concepto->id)
                ->where('empresa_id', $empresaId)
                ->where('debe_haber', 'H')
                ->value('cuentacontable_id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::SUELDOS_BANCO_PAGO
        ) ?? 0);
    }

    private function beneficiario(Liquidacion_Sueldos $liq): string
    {
        $texto = 'Haberes corrida '.$liq->numero;

        return mb_substr($texto, 0, 80);
    }
}
