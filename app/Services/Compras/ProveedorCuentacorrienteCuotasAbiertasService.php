<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportOrigenSupport;
use App\Support\Compras\AnitaImport\ProveedorCuentacorrienteAnitaImportCcGuardSupport;
use Illuminate\Support\Facades\DB;

/**
 * El import de deuda dejaba un solo movimiento de cuenta corriente por factura
 * y le ponía el importe de la última cuota. Las cuotas abiertas del comprobante
 * ya estaban cargadas y coinciden con Anita; esta reparación les crea su fila.
 */
class ProveedorCuentacorrienteCuotasAbiertasService
{
    private const TOL = 0.05;

    /**
     * @return list<array{comprobante_id:int, codigo:string, numero:string, creadas:int, reasignada:bool, omitido:?string}>
     */
    public function reparar(?int $proveedorId = null, bool $aplicar = false): array
    {
        $informe = [];
        foreach ($this->comprobantesColapsados($proveedorId) as $cp) {
            $informe[] = $aplicar
                ? $this->repararComprobante($cp)
                : $this->preview($cp);
        }

        return $informe;
    }

    /**
     * @return list<Comprobante_Proveedor>
     */
    private function comprobantesColapsados(?int $proveedorId): array
    {
        $q = Comprobante_Proveedor::query()->with(['proveedores', 'comprobante_proveedor_cuotas']);
        if ($proveedorId) {
            $q->where('proveedor_id', $proveedorId);
        }

        $out = [];
        foreach ($q->orderBy('id')->get() as $cp) {
            if (ComprobanteProveedorAnitaImportOrigenSupport::esNativo(
                is_string($cp->origen_entrada) ? $cp->origen_entrada : null
            )) {
                continue;
            }
            $abiertas = $this->abiertas($cp);
            if (count($abiertas) <= 1) {
                continue;
            }
            $ccs = $this->ccsDeuda($cp);
            if (count($ccs) !== 1) {
                continue;
            }
            $sinFila = 0;
            foreach ($abiertas as $cuota) {
                if ((int) $cuota->proveedor_cuentacorriente_id <= 0) {
                    $sinFila++;
                }
            }
            if ($sinFila < 1) {
                continue;
            }
            $out[] = $cp;
        }

        return $out;
    }

    /**
     * @return array{comprobante_id:int, codigo:string, numero:string, creadas:int, reasignada:bool, omitido:?string}
     */
    private function preview(Comprobante_Proveedor $cp): array
    {
        $abiertas = $this->abiertas($cp);
        $cc = $this->ccsDeuda($cp)[0];
        $duena = $this->cuotaDuena($abiertas, $cc);
        $creadas = 0;
        foreach ($abiertas as $cuota) {
            if ($duena && (int) $duena->id === (int) $cuota->id) {
                continue;
            }
            if ((int) $cuota->proveedor_cuentacorriente_id > 0
                && (int) $cuota->proveedor_cuentacorriente_id !== (int) $cc->id) {
                continue;
            }
            $creadas++;
        }

        return $this->fila($cp, $creadas, $duena !== null && (int) ($cc->comprobante_proveedor_cuota_id ?? 0) !== (int) $duena->id, $duena ? null : 'sin cuota cuyo saldo coincide con el movimiento');
    }

    /**
     * @return array{comprobante_id:int, codigo:string, numero:string, creadas:int, reasignada:bool, omitido:?string}
     */
    private function repararComprobante(Comprobante_Proveedor $cp): array
    {
        return DB::transaction(function () use ($cp) {
            $cp->load('comprobante_proveedor_cuotas');
            $abiertas = $this->abiertas($cp);
            $cc = $this->ccsDeuda($cp)[0];
            $duena = $this->cuotaDuena($abiertas, $cc);
            if ($duena === null) {
                return $this->fila($cp, 0, false, 'sin cuota cuyo saldo coincide con el movimiento');
            }

            foreach ($abiertas as $cuota) {
                if ((int) $cuota->id === (int) $duena->id) {
                    continue;
                }
                if ((int) $cuota->proveedor_cuentacorriente_id === (int) $cc->id) {
                    $cuota->proveedor_cuentacorriente_id = null;
                    $cuota->save();
                }
            }

            $signo = ((float) $cc->total) >= 0 ? 1 : -1;
            $conPagoErp = ProveedorCuentacorrienteAnitaImportCcGuardSupport::tieneAplicacionOperativa((int) $cc->id);
            $cc->comprobante_proveedor_cuota_id = $duena->id;
            if (! $conPagoErp) {
                $cc->fechavencimiento = $duena->fechavencimiento;
                $cc->moneda_id = $duena->moneda_id;
                $cc->cotizacion = $duena->cotizacion;
                $cc->total = round($this->restante($duena) * $signo, 4);
            }
            $cc->save();
            $duena->proveedor_cuentacorriente_id = $cc->id;
            $duena->save();

            $creadas = 0;
            foreach ($abiertas as $cuota) {
                if ((int) $cuota->id === (int) $duena->id) {
                    continue;
                }
                if ((int) $cuota->proveedor_cuentacorriente_id > 0) {
                    continue;
                }
                $nueva = Proveedor_Cuentacorriente::query()->create([
                    'fecha' => $cp->fechacomprobante,
                    'fechavencimiento' => $cuota->fechavencimiento,
                    'proveedor_id' => $cp->proveedor_id,
                    'total' => round($this->restante($cuota) * $signo, 4),
                    'moneda_id' => $cuota->moneda_id,
                    'cotizacion' => $cuota->cotizacion ?: 1,
                    'empresa_id' => $cp->empresa_id,
                    'comprobante_proveedor_id' => $cp->id,
                    'comprobante_proveedor_cuota_id' => $cuota->id,
                ]);
                $cuota->proveedor_cuentacorriente_id = $nueva->id;
                $cuota->save();
                $creadas++;
            }

            return $this->fila($cp, $creadas, true, null);
        });
    }

    /**
     * @param  list<Comprobante_Proveedor_Cuota>  $abiertas
     */
    private function cuotaDuena(array $abiertas, Proveedor_Cuentacorriente $cc): ?Comprobante_Proveedor_Cuota
    {
        $abs = abs((float) $cc->total);
        $candidatas = [];
        foreach ($abiertas as $cuota) {
            if (abs($this->restante($cuota) - $abs) <= self::TOL) {
                $candidatas[] = $cuota;
            }
        }
        if ($candidatas === []) {
            return null;
        }
        // El movimiento ya pagado conserva el vencimiento de la cuota que se cobró.
        // Si varias abiertas tienen el mismo saldo, esa es la dueña; si no coincide
        // ninguna, la primera abierta, no la última.
        $vtoCc = substr((string) ($cc->fechavencimiento ?? ''), 0, 10);
        usort($candidatas, static function ($a, $b) use ($vtoCc) {
            $va = substr((string) ($a->fechavencimiento ?? ''), 0, 10);
            $vb = substr((string) ($b->fechavencimiento ?? ''), 0, 10);
            $ma = $vtoCc !== '' && $va === $vtoCc;
            $mb = $vtoCc !== '' && $vb === $vtoCc;
            if ($ma !== $mb) {
                return $ma ? -1 : 1;
            }

            return (int) $a->numero_cuota <=> (int) $b->numero_cuota;
        });

        return $candidatas[0];
    }

    /**
     * @return list<Comprobante_Proveedor_Cuota>
     */
    private function abiertas(Comprobante_Proveedor $cp): array
    {
        $out = [];
        foreach ($cp->comprobante_proveedor_cuotas as $cuota) {
            if ($this->restante($cuota) > self::TOL) {
                $out[] = $cuota;
            }
        }

        return $out;
    }

    /**
     * @return list<Proveedor_Cuentacorriente>
     */
    private function ccsDeuda(Comprobante_Proveedor $cp): array
    {
        return ProveedorCuentacorrienteAnitaImportCcGuardSupport::soloDeudaDocumento(
            Proveedor_Cuentacorriente::query()->where('comprobante_proveedor_id', $cp->id)
        )->orderBy('id')->get()->all();
    }

    private function restante(Comprobante_Proveedor_Cuota $cuota): float
    {
        return round(abs((float) $cuota->monto) - abs((float) $cuota->total_pagado), 4);
    }

    /**
     * @return array{comprobante_id:int, codigo:string, numero:string, creadas:int, reasignada:bool, omitido:?string}
     */
    private function fila(Comprobante_Proveedor $cp, int $creadas, bool $reasignada, ?string $omitido): array
    {
        $cp->loadMissing('proveedores');

        return [
            'comprobante_id' => (int) $cp->id,
            'codigo' => (string) ($cp->proveedores->codigo ?? ''),
            'numero' => trim($cp->letra).' '.$cp->sucursal.'-'.$cp->numerocomprobante,
            'creadas' => $creadas,
            'reasignada' => $reasignada,
            'omitido' => $omitido,
        ];
    }
}
