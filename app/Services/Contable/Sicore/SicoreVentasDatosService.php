<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\Models\Contable\Sicore_Config;
use App\Models\Ventas\Venta;
use App\Support\Contable\Sicore\SicoreCodigoCondicionSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;
use App\Support\Contable\Sicore\SicoreVentaImpuestoSupport;

final class SicoreVentasDatosService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function generar(int $empresaId, string $fechaDesde, string $fechaHasta, Sicore_Config $config): array
    {
        $tipoImpuesto = match ($config->criterio) {
            'ventas_perc_no_categ' => 'no_categ',
            default => 'perc_iva',
        };

        $regimen = (int) ($config->codigo_regimen ?? 493);

        $ventas = Venta::query()
            ->select('venta.*')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->whereBetween('venta.fecha', [$fechaDesde, $fechaHasta])
            ->with([
                'venta_impuestos',
                'clientes.condicionivas',
                'tipotransacciones',
                'puntoventas',
            ])
            ->orderBy('venta.fecha')
            ->orderBy('venta.id')
            ->get();

        $filas = [];
        foreach ($ventas as $venta) {
            $signo = SicoreVentaImpuestoSupport::signoVenta($venta);
            $coef = SicoreVentaImpuestoSupport::coefMoneda($venta);

            foreach ($venta->venta_impuestos as $imp) {
                $match = $tipoImpuesto === 'no_categ'
                    ? SicoreVentaImpuestoSupport::esPercepcionNoCategorizada($imp)
                    : SicoreVentaImpuestoSupport::esPercepcionIva($imp);

                if (! $match) {
                    continue;
                }

                $importe = round((float) $imp->importe * $signo * $coef, 2);
                if (abs($importe) < 0.001) {
                    continue;
                }

                $cliente = $venta->clientes;
                $cuit = $cliente?->numerodocumento ?? $venta->nroinscripcion ?? '';
                $nombre = $cliente?->nombre ?? $venta->nombre ?? '';
                $tipoComp = (string) ($venta->tipotransacciones?->codigo ?? '01');

                $gravado = $this->netoGravadoVenta($venta) * $coef * $signo;

                $filas[] = [
                    'origen' => 'ventas',
                    'sicore_config_id' => (int) $config->id,
                    'cod_regimen' => $regimen,
                    'cod_impuesto' => (int) $config->codigo_impuesto,
                    'cod_operacion' => (int) ($config->codigo_operacion ?? 2),
                    'cod_comp' => SicoreFormatoV8Support::codigoComprobanteDesdeTipo($tipoComp),
                    'fecha_comp' => (string) $venta->fecha,
                    'nro_comp' => (int) ($venta->numerocomprobante ?? 0),
                    'importe_comp' => round(abs((float) $venta->total) * $coef, 2),
                    'base_calculo' => round(abs($gravado), 2),
                    'fecha_retencion' => (string) $venta->fecha,
                    'cod_condicion' => SicoreCodigoCondicionSupport::desdeCondicionIvaCliente(
                        $cliente?->condicionivas?->nombre ?? null,
                    ),
                    'importe' => $importe,
                    'porc_excl' => (float) ($cliente?->coeficienteextra ?? 0),
                    'fecha_boletin' => '',
                    'cod_documento' => 80,
                    'nro_documento' => SicoreFormatoV8Support::normalizarCuit((string) $cuit),
                    'nro_cert' => 0,
                    'razon_social' => substr(trim((string) $nombre), 0, 30),
                    'venta_id' => (int) $venta->id,
                    'referencia' => sprintf(
                        'PV %s — %s %s',
                        $venta->puntoventas?->codigo ?? '',
                        $tipoComp,
                        $venta->numerocomprobante ?? '',
                    ),
                ];
            }
        }

        return $filas;
    }

    private function netoGravadoVenta(Venta $venta): float
    {
        $gravado = 0.0;
        foreach ($venta->venta_impuestos as $imp) {
            $concepto = trim((string) $imp->concepto);
            if (stripos($concepto, 'Gravado') === 0 || stripos($concepto, 'Gravado al') !== false) {
                $gravado += abs((float) $imp->importe);
            }
        }

        if ($gravado <= 0) {
            foreach ($venta->venta_impuestos as $imp) {
                $concepto = trim((string) $imp->concepto);
                if (stripos($concepto, 'Iva ') !== false || stripos($concepto, 'IVA') === 0) {
                    $gravado += abs((float) $imp->baseimponible);
                }
            }
        }

        return $gravado;
    }
}
