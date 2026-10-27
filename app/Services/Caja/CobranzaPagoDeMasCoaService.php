<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\Cobranza;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Support\Caja\CobranzaNumeracionTransaccion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pago de más de una cobranza = saldo a favor del cliente, siempre como COA.
 *
 * - Cobranza COA (sin comprobantes): el saldo es el renglón de CC de la propia cobranza.
 * - Cobranza COB con sobrante: COA aparte (cobranza_origen_id = la COB) con su renglón de CC.
 *
 * Un renglón de CC con cobranza COB y sin venta no es deuda ni COA: aplicarlo baja la deuda
 * sin mover la ficha. Por eso el sobrante nunca se graba así.
 */
final class CobranzaPagoDeMasCoaService
{
    private const TOLERANCIA = 0.009;

    /**
     * Deja el pago de más de la cobranza como lo indica el formulario (alta y edición).
     * Conserva las COA ya grabadas si no cambian; si cambian y están aplicadas, corta.
     *
     * @param  array<string, mixed>  $data
     */
    public function sincronizar(array $data, int $cobranzaId): void
    {
        $cobranza = Cobranza::query()->findOrFail($cobranzaId);
        $coaTipoId = $this->tipoCoaId();
        $esCoa = (int) $cobranza->tipotransaccion_caja_id === $coaTipoId;
        $deseado = $this->excedentePorMoneda($data);

        $existentes = [];
        foreach ($this->renglonesPropios($cobranzaId) as $fila) {
            $existentes[] = ['fila' => $fila, 'propio' => true];
        }
        foreach ($this->coaHijas($cobranzaId) as $hija) {
            foreach (Cliente_Cuentacorriente::query()->where('cobranza_id', $hija->id)->get() as $fila) {
                $existentes[] = ['fila' => $fila, 'propio' => false, 'hija' => $hija];
            }
        }

        $cubierto = [];
        foreach ($existentes as $item) {
            $fila = $item['fila'];
            $monedaId = (int) ($fila->moneda_id ?: 1);
            $importe = round(abs((float) $fila->total), 2);
            $coincide = $item['propio'] === $esCoa
                && isset($deseado[$monedaId])
                && ! isset($cubierto[$monedaId])
                && abs($importe - $deseado[$monedaId]) <= self::TOLERANCIA;

            if ($coincide) {
                $cubierto[$monedaId] = true;

                continue;
            }

            $this->borrarRenglon($fila, $item['hija'] ?? null);
        }

        foreach ($deseado as $monedaId => $importe) {
            if (isset($cubierto[$monedaId])) {
                continue;
            }
            if ($esCoa) {
                $this->crearRenglon($cobranza, $data, $monedaId, $importe, (int) $cobranza->id, null);

                continue;
            }
            $coa = $this->crearCoaHija($cobranza, $data, $monedaId, $importe, $coaTipoId);
            $this->crearRenglon($cobranza, $data, $monedaId, $importe, (int) $coa->id, (string) $coa->detalle);
        }
    }

    /**
     * Antes de anular la cobranza: borra sus COA de pago de más. Si alguna está aplicada, corta.
     */
    public function eliminarCoaHijas(int $cobranzaId): void
    {
        foreach ($this->coaHijas($cobranzaId) as $hija) {
            foreach (Cliente_Cuentacorriente::query()->where('cobranza_id', $hija->id)->get() as $fila) {
                $this->borrarRenglon($fila, $hija);
            }
            if (Cobranza::query()->whereKey($hija->id)->exists()) {
                $hija->delete();
            }
        }
    }

    /**
     * Renglones grabados antes del 22/10/2026: sobrante con cobranza COB y sin venta.
     *
     * @return \Illuminate\Support\Collection<int, Cliente_Cuentacorriente>
     */
    public function sobrantesLegacy()
    {
        $coaTipoId = $this->tipoCoaId();

        return Cliente_Cuentacorriente::query()
            ->with('cobranzas')
            ->where(function ($q) {
                $q->whereNull('venta_id')->orWhere('venta_id', 0);
            })
            ->where('cobranza_id', '>', 0)
            ->whereHas('cobranzas', function ($q) use ($coaTipoId) {
                $q->where('tipotransaccion_caja_id', '<>', $coaTipoId);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Pasa un sobrante legacy a COA: crea la cobranza COA y le reasigna el renglón de CC.
     * Las aplicaciones apuntan al renglón por id, así que quedan intactas.
     */
    public function convertirSobranteLegacy(Cliente_Cuentacorriente $fila): Cobranza
    {
        return DB::transaction(function () use ($fila) {
            $fila = Cliente_Cuentacorriente::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
            $origen = Cobranza::query()->findOrFail((int) $fila->cobranza_id);
            $coaTipoId = $this->tipoCoaId();
            if ((int) $origen->tipotransaccion_caja_id === $coaTipoId) {
                throw new RuntimeException('El renglón '.$fila->id.' ya pertenece a una COA.');
            }

            $fecha = substr((string) $fila->fecha, 0, 10);
            $coa = $this->crearCoaHija(
                $origen,
                [
                    'fecha' => $fecha,
                    'caja_id' => $origen->caja_id,
                    'cotizacion_cobranza' => (float) ($fila->cotizacion ?: 1),
                ],
                (int) ($fila->moneda_id ?: 1),
                round(abs((float) $fila->total), 2),
                $coaTipoId,
                (int) $origen->usuario_id,
            );

            $fila->cobranza_id = $coa->id;
            $fila->leyenda = $coa->detalle;
            $fila->save();

            return $coa;
        });
    }

    /**
     * totalcobranzas = aplicado − medios; negativo = pago de más.
     * El formulario repite el saldo en cada solapa: un importe por moneda.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, float>
     */
    private function excedentePorMoneda(array $data): array
    {
        $totales = $data['totalcobranzas'] ?? [];
        if (! is_array($totales)) {
            $totales = ($totales !== null && $totales !== '') ? [$totales] : [];
        }
        $monedas = $data['moneda_cobranza_ids'] ?? [];
        if (! is_array($monedas)) {
            $monedas = ($monedas !== null && $monedas !== '') ? [$monedas] : [];
        }

        $salida = [];
        foreach ($totales as $i => $saldo) {
            $saldo = (float) $saldo;
            if ($saldo > -self::TOLERANCIA) {
                continue;
            }
            $monedaId = (int) ($monedas[$i] ?? ($data['moneda_id'] ?? 1));
            if ($monedaId <= 0) {
                $monedaId = 1;
            }
            if (array_key_exists($monedaId, $salida)) {
                continue;
            }
            $importe = round(abs($saldo), 2);
            if ($importe >= 0.01) {
                $salida[$monedaId] = $importe;
            }
        }

        return $salida;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Cliente_Cuentacorriente>
     */
    private function renglonesPropios(int $cobranzaId)
    {
        return Cliente_Cuentacorriente::query()
            ->where('cobranza_id', $cobranzaId)
            ->where(function ($q) {
                $q->whereNull('venta_id')->orWhere('venta_id', 0);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Cobranza>
     */
    private function coaHijas(int $cobranzaId)
    {
        return Cobranza::query()
            ->where('cobranza_origen_id', $cobranzaId)
            ->orderBy('id')
            ->get();
    }

    private function borrarRenglon(Cliente_Cuentacorriente $fila, ?Cobranza $hija): void
    {
        $aplicada = Cliente_Cuentacorriente_Aplicacion::query()
            ->where(function ($q) use ($fila) {
                $q->where('cliente_cuentacorriente_id', $fila->id)
                    ->orWhere('cliente_cuentacorriente_aplicado_id', $fila->id);
            })
            ->exists();
        if ($aplicada) {
            $nombre = $hija !== null
                ? trim((string) $hija->detalle)
                : 'el saldo a favor de la cobranza';
            throw new RuntimeException(
                'El pago de más ya está aplicado a una factura ('.$nombre.'). '
                .'Desaplicalo en la cuenta corriente del cliente antes de cambiar o anular la cobranza.'
            );
        }

        $fila->delete();

        if ($hija !== null
            && ! Cliente_Cuentacorriente::query()->where('cobranza_id', $hija->id)->exists()
            && Cobranza::query()->whereKey($hija->id)->exists()) {
            $hija->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function crearCoaHija(
        Cobranza $origen,
        array $data,
        int $monedaId,
        float $importe,
        int $coaTipoId,
        ?int $usuarioId = null,
    ): Cobranza {
        $empresaId = (int) $origen->empresa_id;
        $numero = CobranzaNumeracionTransaccion::siguienteNumeroSecuencial($empresaId, $coaTipoId);
        $detalle = 'COA Nro. '.$numero.' — pago de más de cobranza';
        $padre = trim((string) ($origen->numerotransaccion ?? ''));
        if ($padre !== '') {
            $detalle .= ' Nro. '.$padre;
        }
        $detalle .= ' (id '.$origen->id.')';

        return Cobranza::query()->create([
            'empresa_id' => $empresaId,
            'tipotransaccion_caja_id' => $coaTipoId,
            'numerotransaccion' => $numero,
            'fecha' => $data['fecha'] ?? $origen->fecha,
            'caja_id' => $data['caja_id'] ?? $origen->caja_id,
            'cliente_id' => (int) $origen->cliente_id,
            'detalle' => $detalle,
            'estado' => 'CONFIRMADA',
            'monto' => $importe,
            'cotizacion' => $data['cotizacion_cobranza'] ?? 1,
            'moneda_id' => $monedaId,
            'usuario_id' => $usuarioId ?? Auth::id(),
            'cobranza_origen_id' => (int) $origen->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function crearRenglon(
        Cobranza $cobranza,
        array $data,
        int $monedaId,
        float $importe,
        int $cobranzaIdRenglon,
        ?string $leyenda,
    ): void {
        $fecha = $data['fecha'] ?? $cobranza->fecha;
        Cliente_Cuentacorriente::query()->create([
            'fecha' => $fecha,
            'fechavencimiento' => $fecha,
            'cliente_id' => (int) $cobranza->cliente_id,
            'total' => -1 * $importe,
            'moneda_id' => $monedaId,
            'cotizacion' => $data['cotizacion_cobranza'] ?? 1,
            'cobranza_id' => $cobranzaIdRenglon,
            'empresa_id' => (int) $cobranza->empresa_id,
            'leyenda' => $leyenda,
        ]);
    }

    private function tipoCoaId(): int
    {
        $id = (int) (Tipotransaccion_Caja::query()
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['COA'])
            ->value('id') ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('No hay tipo de transacción de caja COA. El pago de más no puede quedar como saldo a favor.');
        }

        return $id;
    }
}
