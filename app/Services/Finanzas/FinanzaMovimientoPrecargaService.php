<?php

declare(strict_types=1);

namespace App\Services\Finanzas;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Contable\Cuentacontable;
use App\Models\Finanzas\FinanzaMovimientoPrecarga;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Services\Caja\IngresoEgresoService;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Finanzas\FinanzaMovimientoPrecargaRubro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class FinanzaMovimientoPrecargaService
{
    public function __construct(
        private readonly EmpresaRepository $empresaRepository,
        private readonly IngresoEgresoService $ingresoEgresoService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function grabar(array $input, ?FinanzaMovimientoPrecarga $existente = null): FinanzaMovimientoPrecarga
    {
        if ($existente !== null && $existente->estaCerrada()) {
            throw new InvalidArgumentException('La precarga está contabilizada. Revertí el ingreso/egreso para modificarla.');
        }

        $data = $this->normalizar($input);
        if ($existente !== null && $existente->movimientoRevertido()) {
            $data['estado'] = FinanzaMovimientoPrecarga::ESTADO_ABIERTO;
            $data['caja_movimiento_id'] = null;
        }

        if ($existente === null) {
            $data['estado'] = FinanzaMovimientoPrecarga::ESTADO_ABIERTO;
            $data['usuario_id'] = Auth::id() ? (int) Auth::id() : null;

            return FinanzaMovimientoPrecarga::query()->create($data);
        }

        $existente->fill($data);
        $existente->save();

        return $existente->fresh();
    }

    public function convertir(FinanzaMovimientoPrecarga $precarga): FinanzaMovimientoPrecarga
    {
        $precarga->load([
            'cuentacaja.cuentacontables',
            'cuentacajaDesde.cuentacontables',
            'cuentacajaHasta.cuentacontables',
            'cajaMovimiento',
        ]);
        if ($precarga->estaCerrada()) {
            throw new InvalidArgumentException('Esta precarga ya tiene un ingreso/egreso vigente.');
        }

        $tipo = $this->tipoTransaccion($precarga->tipo);
        $monto = round(abs((float) $precarga->monto), 2);
        $cot = (float) $precarga->cotizacion;
        if ($cot <= 0) {
            $cot = 1;
        }
        $detalle = mb_substr(trim((string) $precarga->detalle), 0, 255);
        $monedaId = (int) $precarga->moneda_id;

        if ($precarga->tipo === 'transferencia') {
            $desde = $precarga->cuentacajaDesde;
            $hasta = $precarga->cuentacajaHasta;
            if ($desde === null || $hasta === null) {
                throw new InvalidArgumentException('La transferencia necesita cuenta desde y cuenta hasta.');
            }
            $ctaDesde = (int) ($desde->cuentacontable_id ?? 0);
            $ctaHasta = (int) ($hasta->cuentacontable_id ?? 0);
            if ($ctaDesde <= 0 || $ctaHasta <= 0) {
                throw new InvalidArgumentException('Las dos cuentas de caja tienen que tener cuenta contable para contabilizar la transferencia.');
            }
            $payload = $this->payloadBase($precarga, (int) $tipo->id, $detalle);
            $payload['cuentacaja_ids'] = [(int) $desde->id, (int) $hasta->id];
            $payload['moneda_ids'] = [$monedaId, $monedaId];
            $payload['montos'] = [-1 * $monto, $monto];
            $payload['cotizaciones'] = [$cot, $cot];
            $payload['observaciones'] = [$detalle, $detalle];
            $payload['cuentacontable_ids'] = [$ctaDesde, $ctaHasta];
            $payload['monedaasiento_ids'] = [$monedaId, $monedaId];
            $payload['centrocostoasiento_ids'] = [0, 0];
            $payload['debeasientos'] = [0, $monto];
            $payload['haberasientos'] = [$monto, 0];
            $payload['cotizacionasientos'] = [$cot, $cot];
            $payload['observacionasientos'] = [$detalle, $detalle];
        } else {
            $cuenta = $precarga->cuentacaja;
            if ($cuenta === null) {
                throw new InvalidArgumentException('Falta la cuenta de caja.');
            }
            $ctaCaja = (int) ($cuenta->cuentacontable_id ?? 0);
            $ctaContra = (int) ($precarga->cuentacontable_contrapartida_id ?? 0);
            if ($ctaCaja <= 0) {
                throw new InvalidArgumentException('La cuenta de caja no tiene cuenta contable.');
            }
            if ($ctaContra <= 0 || ! Cuentacontable::query()->whereKey($ctaContra)->exists()) {
                throw new InvalidArgumentException('Indicá la cuenta contable de contrapartida para contabilizar el ingreso o egreso.');
            }
            $esEgreso = $precarga->tipo === 'egreso';
            $payload = $this->payloadBase($precarga, (int) $tipo->id, $detalle);
            $payload['cuentacaja_ids'] = [(int) $cuenta->id];
            $payload['moneda_ids'] = [$monedaId];
            $payload['montos'] = [$monto];
            $payload['cotizaciones'] = [$cot];
            $payload['observaciones'] = [$detalle];
            $payload['cuentacontable_ids'] = [$ctaCaja, $ctaContra];
            $payload['monedaasiento_ids'] = [$monedaId, $monedaId];
            $payload['centrocostoasiento_ids'] = [0, 0];
            $payload['debeasientos'] = [$esEgreso ? 0 : $monto, $esEgreso ? $monto : 0];
            $payload['haberasientos'] = [$esEgreso ? $monto : 0, $esEgreso ? 0 : $monto];
            $payload['cotizacionasientos'] = [$cot, $cot];
            $payload['observacionasientos'] = [$detalle, $detalle];
        }

        $request = Request::create('/finanzas/movimiento-precarga/convertir', 'POST', $payload);
        $resultado = $this->ingresoEgresoService->guardaIngresoEgreso($request);
        if (! is_array($resultado) || isset($resultado['errores'])) {
            $mensaje = is_array($resultado) ? (string) ($resultado['errores'] ?? 'No se pudo generar el ingreso/egreso.') : 'No se pudo generar el ingreso/egreso.';
            throw new InvalidArgumentException($mensaje);
        }
        $movimientoId = (int) ($resultado['caja_movimiento_id'] ?? 0);
        if ($movimientoId <= 0) {
            throw new InvalidArgumentException('El ingreso/egreso no devolvió un identificador.');
        }

        $precarga->estado = FinanzaMovimientoPrecarga::ESTADO_CONVERTIDO;
        $precarga->caja_movimiento_id = $movimientoId;
        $precarga->save();

        return $precarga->fresh(['cajaMovimiento']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizar(array $input): array
    {
        $empresaId = (int) ($input['empresa_id'] ?? 0);
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new InvalidArgumentException('La empresa no está asignada al usuario.');
        }

        $tipo = (string) ($input['tipo'] ?? '');
        $rubro = (string) ($input['rubro'] ?? '');
        if (! isset(FinanzaMovimientoPrecargaRubro::TIPOS[$tipo])) {
            throw new InvalidArgumentException('El tipo de operación no es válido.');
        }
        if (! isset(FinanzaMovimientoPrecargaRubro::ETIQUETAS[$rubro])) {
            throw new InvalidArgumentException('El rubro de la posición no es válido.');
        }

        $fecha = trim((string) ($input['fecha'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            throw new InvalidArgumentException('La fecha no es válida.');
        }

        $detalle = trim((string) ($input['detalle'] ?? ''));
        if ($detalle === '') {
            throw new InvalidArgumentException('El detalle es obligatorio.');
        }

        $monto = round((float) ($input['monto'] ?? 0), 2);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto tiene que ser mayor a cero.');
        }

        $cuentaId = null;
        $desdeId = null;
        $hastaId = null;
        $monedaId = 0;

        if ($tipo === 'transferencia') {
            $desde = $this->cuentaOperable((int) ($input['cuentacaja_desde_id'] ?? 0), $empresaId, 'La cuenta desde');
            $hasta = Cuentacaja::query()->find((int) ($input['cuentacaja_hasta_id'] ?? 0));
            if ($hasta === null) {
                throw new InvalidArgumentException('La cuenta hasta no existe.');
            }
            if ((int) ($hasta->empresa_id ?? 0) > 0 && ! $this->empresaRepository->empresaIdPermitida((int) $hasta->empresa_id)) {
                throw new InvalidArgumentException('La cuenta hasta es de una empresa que no tenés asignada.');
            }
            if ((int) $desde->id === (int) $hasta->id) {
                throw new InvalidArgumentException('La transferencia tiene que ir a otra cuenta de caja.');
            }
            if ((int) $desde->moneda_id !== (int) $hasta->moneda_id) {
                throw new InvalidArgumentException('Las dos cuentas tienen que ser de la misma moneda.');
            }
            $desdeId = (int) $desde->id;
            $hastaId = (int) $hasta->id;
            $monedaId = (int) $desde->moneda_id;
        } else {
            $cuenta = $this->cuentaOperable((int) ($input['cuentacaja_id'] ?? 0), $empresaId, 'La cuenta de caja');
            $cuentaId = (int) $cuenta->id;
            $monedaId = (int) $cuenta->moneda_id;
        }

        if ($monedaId <= 0) {
            throw new InvalidArgumentException('La cuenta de caja no tiene moneda.');
        }

        $cotizacion = $this->cotizacion($monedaId, (string) ($input['fecha'] ?? ''), (float) ($input['cotizacion'] ?? 0));
        $contra = (int) ($input['cuentacontable_contrapartida_id'] ?? 0);
        if ($tipo === 'transferencia') {
            $contra = 0;
        } elseif ($contra > 0 && ! Cuentacontable::query()->whereKey($contra)->exists()) {
            throw new InvalidArgumentException('La cuenta contable de contrapartida no existe.');
        }

        return [
            'empresa_id' => $empresaId,
            'fecha' => $fecha,
            'tipo' => $tipo,
            'rubro' => $rubro,
            'detalle' => mb_substr($detalle, 0, 255),
            'cuentacaja_id' => $cuentaId,
            'cuentacaja_desde_id' => $desdeId,
            'cuentacaja_hasta_id' => $hastaId,
            'moneda_id' => $monedaId,
            'monto' => $monto,
            'cotizacion' => $cotizacion,
            'cuentacontable_contrapartida_id' => $contra > 0 ? $contra : null,
        ];
    }

    private function cuentaOperable(int $id, int $empresaId, string $etiqueta): Cuentacaja
    {
        $cuenta = Cuentacaja::query()->find($id);
        if ($cuenta === null || ! $cuenta->perteneceAEmpresa($empresaId)) {
            throw new InvalidArgumentException($etiqueta.' no existe o no corresponde a la empresa.');
        }

        return $cuenta;
    }

    private function cotizacion(int $monedaId, string $fecha, float $ingresada): float
    {
        if ($monedaId <= 1) {
            return 1.0;
        }
        if ($ingresada > 1.0001) {
            return round($ingresada, 6);
        }
        $vigente = CotizacionVigenteSupport::ventaValor($fecha, $monedaId);
        if ($vigente <= 0) {
            throw new InvalidArgumentException('No hay cotización vigente para la moneda de la cuenta.');
        }

        return round($vigente, 6);
    }

    private function tipoTransaccion(string $tipo): Tipotransaccion_Caja
    {
        $abrev = match ($tipo) {
            'egreso' => 'EGR',
            'transferencia' => 'TRA',
            default => 'ING',
        };
        $row = Tipotransaccion_Caja::query()->where('abreviatura', $abrev)->first();
        if ($row === null) {
            throw new InvalidArgumentException('No existe el tipo de caja '.$abrev.'.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadBase(FinanzaMovimientoPrecarga $precarga, int $tipoId, string $detalle): array
    {
        return [
            'empresa_id' => (int) $precarga->empresa_id,
            'tipotransaccion_caja_id' => $tipoId,
            'fecha' => $precarga->fecha?->format('Y-m-d'),
            'detalle' => $detalle,
            'origen' => 'finanza_precarga',
        ];
    }
}
