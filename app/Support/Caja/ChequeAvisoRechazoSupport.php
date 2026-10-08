<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Models\Ventas\Venta_Emision;

/**
 * Arma el aviso de cheque rechazado (el formulario que imprimía Anita).
 * Los gastos salen de la nota de débito, si ya se emitió. El importe del
 * cheque va aparte: no entra en «Total rechazo+IVA».
 */
final class ChequeAvisoRechazoSupport
{
    public static function estaRechazado(Cheque $cheque): bool
    {
        return (string) ($cheque->estado ?? '') === 'R'
            || trim((string) ($cheque->fecha_rechazo ?? '')) !== '';
    }

    /**
     * @return array<string, string>
     */
    public static function armar(Cheque $cheque): array
    {
        $cheque->loadMissing([
            'empresas.localidad',
            'clientes.localidades',
            'clientes.condicionivas',
            'bancos',
            'monedas',
            'cobranzas',
            'ventaNd.venta_emisiones.impuestos',
            'ventaNd.venta_impuestos',
        ]);

        $cliente = $cheque->clientes;
        $codigo = trim((string) ($cliente->codigo ?? ''));
        if ($codigo !== '' && ctype_digit($codigo)) {
            $codigo = str_pad($codigo, 6, '0', STR_PAD_LEFT);
        }

        $cpCliente = trim((string) ($cliente->codigopostal ?? ''));
        $localidad = trim((string) ($cliente->localidades->nombre ?? ''));
        if ($localidad !== '' && $cpCliente !== '') {
            $localidad .= ' ('.$cpCliente.')';
        }

        $cpCheque = trim((string) ($cheque->codigopostalbanco ?? ''));
        if ($cpCheque === '') {
            $cpCheque = $cpCliente;
        }

        $gastos = self::gastosDesdeNotaDebito($cheque);
        $interno = (int) ($cheque->nro_interno_anita ?? 0);
        if ($interno <= 0) {
            $interno = (int) $cheque->id;
        }

        $empresa = $cheque->empresas;
        $empresaNombre = trim((string) ($empresa->nombre ?? ''));
        $empresaLocalidad = trim((string) ($empresa->localidad->nombre ?? ''));
        $empresaDomicilio = trim((string) ($empresa->domicilio ?? ''));
        $empresaCuit = trim((string) ($empresa->nroinscripcion ?? ''));

        $cotizacion = (float) ($cheque->cotizacion ?? 0);
        if ($cotizacion <= 0) {
            $cotizacion = 1;
        }

        $codigoNd = trim((string) ($cheque->ventaNd->codigo ?? ''));

        return [
            'empresa_nombre' => $empresaNombre,
            'empresa_domicilio' => $empresaDomicilio,
            'empresa_localidad' => $empresaLocalidad,
            'empresa_cuit' => $empresaCuit,
            'titulo' => 'Aviso de cheque rechazado',
            'interno' => (string) $interno,
            'fecha_rechazo' => self::fechaLarga($cheque->fecha_rechazo),
            'codigo_nd' => $codigoNd,
            'emisor_codigo' => $codigo,
            'emisor_nombre' => trim((string) ($cliente->nombre ?? $cheque->entregado ?? '')),
            'direccion' => trim((string) ($cliente->domicilio ?? '')),
            'localidad' => $localidad,
            'cuit' => trim((string) ($cliente->numerodocumento ?? $cheque->numerodocumento ?? '')),
            'condicion_iva' => trim((string) ($cliente->condicionivas->nombre ?? '')),
            'motivo' => trim((string) ($cheque->motivo_rechazo ?? '')),
            'gastos_bancarios' => self::importe($gastos['bancarios']),
            'gastos_admin' => self::importe($gastos['admin']),
            'interes' => self::importe($gastos['interes']),
            'total' => self::importe($gastos['total']),
            'banco' => trim((string) ($cheque->bancos->nombre ?? $cheque->entregado ?? '')),
            'fecha_cheque' => self::fechaLarga($cheque->fechapago),
            'nro_talon' => trim((string) ($cheque->numerocheque ?? '')),
            'cuenta_libradora' => trim((string) ($cheque->cuentalibradora ?? '')),
            'sucursal' => trim((string) ($cheque->sucursalpago ?? '')),
            'codigo_postal' => $cpCheque,
            'importe' => self::importe((float) ($cheque->monto ?? 0)),
            'moneda' => trim((string) ($cheque->monedas->abreviatura ?? '$')),
            'cotizacion' => number_format($cotizacion, 4, ',', '.'),
            'nro_recibo' => trim((string) ($cheque->cobranzas->numerotransaccion ?? '')),
            'archivo' => 'aviso-rechazo-'.$interno.'.pdf',
        ];
    }

    /**
     * @return array{bancarios: float, admin: float, interes: float, total: float}
     */
    private static function gastosDesdeNotaDebito(Cheque $cheque): array
    {
        $vacio = ['bancarios' => 0.0, 'admin' => 0.0, 'interes' => 0.0, 'total' => 0.0];
        $venta = $cheque->ventaNd;
        if ($venta === null) {
            return $vacio;
        }

        $conceptoCheque = 0;
        try {
            $conceptoCheque = ChequeNdConfigSupport::conceptoIdParaChequeRechazado();
        } catch (\Throwable) {
            $conceptoCheque = 0;
        }
        $conceptoGastos = (int) (ChequeNdConfigSupport::conceptoIdParaGastosBancarios() ?? 0);

        $bancarios = 0.0;
        $admin = 0.0;
        $interes = 0.0;

        foreach ($venta->venta_emisiones as $linea) {
            if (! $linea instanceof Venta_Emision) {
                continue;
            }
            $detalle = mb_strtolower(trim((string) ($linea->detalle ?? '')));
            $conceptoId = (int) ($linea->concepto_venta_id ?? 0);
            $neto = round((float) $linea->precio * (float) $linea->cantidad, 2);
            if ($neto == 0.0) {
                continue;
            }
            $tasa = (float) ($linea->impuestos->valor ?? 0);
            if (strtoupper((string) ($linea->incluyeimpuesto ?? 'N')) === 'S' && $tasa > 0) {
                $neto = round($neto / (1 + ($tasa / 100)), 2);
            }

            if ($conceptoCheque > 0 && $conceptoId === $conceptoCheque) {
                continue;
            }
            if (str_starts_with($detalle, 'cheque rechazado')) {
                continue;
            }
            if (($conceptoGastos > 0 && $conceptoId === $conceptoGastos) || str_contains($detalle, 'banc')) {
                $bancarios += $neto;
            } elseif (str_contains($detalle, 'interes') || str_contains($detalle, 'interés')) {
                $interes += $neto;
            } elseif (str_contains($detalle, 'administr')) {
                $admin += $neto;
            } else {
                $admin += $neto;
            }
        }

        $iva = 0.0;
        foreach ($venta->venta_impuestos as $impuesto) {
            $concepto = mb_strtolower(trim((string) ($impuesto->concepto ?? '')));
            if (str_contains($concepto, 'iva')) {
                $iva += (float) $impuesto->importe;
            }
        }

        $total = round($bancarios + $admin + $interes + $iva, 2);

        return [
            'bancarios' => round($bancarios, 2),
            'admin' => round($admin, 2),
            'interes' => round($interes, 2),
            'total' => $total,
        ];
    }

    private static function importe(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    private static function fechaLarga(mixed $fecha): string
    {
        $f = trim((string) ($fecha ?? ''));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $f, $m)) {
            return $m[3].'/'.$m[2].'/'.$m[1];
        }

        return '';
    }
}
