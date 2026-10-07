<?php

declare(strict_types=1);

namespace App\Services\Contable\PercepcionSufrida;

use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Contable\MayorConcepto\MayorConceptoMonedaConverter;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaArchivoSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaCorteSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaCruceSupport;
use App\Support\Contable\PercepcionSufrida\PercepcionSufridaLineaSupport;

final class PercepcionSufridaProcesoService
{
    public function __construct(
        private readonly PercepcionSufridaMayorAnitaReader $mayorAnita,
        private readonly PercepcionSufridaErpReader $erp,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(string $tipo, array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        $cuenta = PercepcionSufridaCorteSupport::cuentaDeTipo($tipo);
        $tramos = PercepcionSufridaCorteSupport::partirRango($desde, $hasta);
        $esIibb = $tipo === PercepcionSufridaCorteSupport::TIPO_IIBB;

        $mayor = [];
        $reporte = [];

        if ($tramos['anita_desde'] !== '') {
            $mayorAnita = $this->mayorAnita->leer($empresaId, $tramos['anita_desde'], $tramos['anita_hasta'], $cuenta);
            $conceptos = $this->erp->reporteConceptos($empresaId, $tramos['anita_desde'], $tramos['anita_hasta'], $tipo);
            $numeros = array_values(array_unique(array_filter(array_map(
                static fn (array $linea): int => (int) ($linea['nro'] ?? 0),
                $mayorAnita,
            ))));
            if ($numeros !== []) {
                $conceptos = $this->unirConceptos(
                    $conceptos,
                    $this->erp->reporteConceptos($empresaId, '', '', $tipo, $numeros),
                );
            }
            $mayorAnita = $this->completarDesdeConceptos($mayorAnita, $conceptos, $esIibb);
            $mayor = array_merge($mayor, $mayorAnita);
            $reporte = array_merge(
                $reporte,
                $esIibb ? $conceptos : $this->reporteDesdeMayor($mayorAnita, false),
            );
        }

        if ($tramos['erp_desde'] !== '') {
            $mayorErp = $this->erp->mayor($empresaId, $tramos['erp_desde'], $tramos['erp_hasta'], $cuenta);
            $conceptosErp = $this->erp->reporteConceptos($empresaId, $tramos['erp_desde'], $tramos['erp_hasta'], $tipo);
            $mayorErp = $this->completarDesdeConceptos($mayorErp, $conceptosErp, $esIibb);
            $mayor = array_merge($mayor, $mayorErp);
            $reporte = array_merge($reporte, $conceptosErp);
        }

        $mayor = $this->aplicarPesos($mayor);
        $reporte = $this->aplicarPesos($reporte);

        $cruce = PercepcionSufridaCruceSupport::cruzar($mayor, $reporte, $esIibb);
        $archivo = $esIibb
            ? PercepcionSufridaArchivoSupport::sifere($cruce['cruzados'])
            : PercepcionSufridaArchivoSupport::percepcionIva($cruce['cruzados']);

        $totales = $cruce['totales'];
        $saldo = $this->erp->saldoPeriodoPesos($empresaId, $desde, $hasta, $cuenta);
        $totales['saldo_periodo'] = $saldo;
        $totales['desvio_saldo'] = round((float) $totales['mayor'] - $saldo, 2);

        return [
            'tipo' => $tipo,
            'cuenta' => $cuenta,
            'fecha_limite' => PercepcionSufridaCorteSupport::fechaLimiteIso(),
            'tramos' => $tramos,
            'totales' => $totales,
            'diferencias' => $cruce['diferencias'],
            'cruzados' => $cruce['cruzados'],
            'archivo' => $archivo,
            'archivo_901' => $esIibb ? PercepcionSufridaArchivoSupport::sifere($cruce['cruzados'], 901) : '',
            'archivo_902' => $esIibb ? PercepcionSufridaArchivoSupport::sifere($cruce['cruzados'], 902) : '',
            'nombre_archivo' => $esIibb ? 'psif.txt' : 'perciva.csv',
        ];
    }

    /**
     * El mayor y los conceptos quedan en pesos. El importe nativo se conserva para cruzar.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function aplicarPesos(array $lineas): array
    {
        $converter = app(MayorConceptoMonedaConverter::class);
        foreach ($lineas as $idx => $linea) {
            $monedaId = (int) ($linea['moneda_id'] ?? 0);
            if ($monedaId <= 0) {
                $monedaId = $converter->monedaIdDesdeCodigoAnita((string) ($linea['cod_mon'] ?? ''));
            }
            if ($monedaId <= 0) {
                $monedaId = CotizacionVigenteSupport::MONEDA_LOCAL_ID;
            }
            $cotizacion = (float) ($linea['cotizacion'] ?? 0);
            if ($monedaId !== CotizacionVigenteSupport::MONEDA_LOCAL_ID
                && $cotizacion <= CotizacionVigenteSupport::COTIZACION_MINIMA_EXTRANJERA) {
                $cotizacion = CotizacionVigenteSupport::ventaValor((string) ($linea['fecha'] ?? ''), $monedaId);
            }
            $nativo = round((float) ($linea['importe'] ?? 0), 2);
            $pesos = $monedaId === CotizacionVigenteSupport::MONEDA_LOCAL_ID
                ? $nativo
                : round($nativo * $cotizacion, 2);
            $linea['moneda_id'] = $monedaId;
            $linea['cotizacion'] = $cotizacion;
            $linea['importe_nativo'] = $nativo;
            $linea['importe_pesos'] = $pesos;
            $lineas[$idx] = $linea;
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  list<array<string, mixed>>  $extra
     * @return list<array<string, mixed>>
     */
    private function unirConceptos(array $base, array $extra): array
    {
        $porClave = [];
        foreach (array_merge($base, $extra) as $concepto) {
            $clave = (string) ($concepto['clave_importe'] ?? '');
            if ($clave === '') {
                continue;
            }
            $porClave[$clave] = $concepto;
        }

        return array_values($porClave);
    }

    /**
     * Copia CUIT, emisor y jurisdicción del comprobante de compra cuando el mayor
     * no los trae. Si el comprobante de Anita no coincide (EGR contra el ICO del banco),
     * busca por fecha e importe. Si ese importe es único en el día, une aunque el texto
     * del mayor no sea el del concepto.
     *
     * @param  list<array<string, mixed>>  $mayor
     * @param  list<array<string, mixed>>  $conceptos
     * @return list<array<string, mixed>>
     */
    private function completarDesdeConceptos(array $mayor, array $conceptos, bool $esIibb): array
    {
        $porImporte = [];
        $porFechaImporte = [];
        $porFechaMonto = [];
        foreach ($conceptos as $concepto) {
            $porImporte[(string) ($concepto['clave_importe'] ?? '')][] = $concepto;
            $fechaImporte = (string) ($concepto['fecha'] ?? '').'|'.number_format((float) ($concepto['importe'] ?? 0), 2, '.', '');
            $porFechaMonto[$fechaImporte][] = $concepto;
            $porFechaImporte[$fechaImporte.'|'.PercepcionSufridaLineaSupport::normalizar((string) ($concepto['descripcion'] ?? ''))][] = $concepto;
        }

        foreach ($mayor as $idx => $linea) {
            $concepto = $this->conceptoDeLinea($linea, $porImporte, $porFechaImporte, $porFechaMonto);
            if ($concepto === null) {
                continue;
            }
            if ($linea['cuit'] === '' && $concepto['cuit'] !== '') {
                $linea['cuit'] = $concepto['cuit'];
            }
            if ($linea['emisor'] === '' && $concepto['emisor'] !== '') {
                $linea['emisor'] = $concepto['emisor'];
            }
            if ($linea['emisor_nombre'] === '' && $concepto['emisor_nombre'] !== '') {
                $linea['emisor_nombre'] = $concepto['emisor_nombre'];
            }
            if ((int) $linea['jurisdiccion'] === 0 && (int) $concepto['jurisdiccion'] > 0) {
                $linea['jurisdiccion'] = (int) $concepto['jurisdiccion'];
            }
            if ($concepto['descripcion'] !== '') {
                $linea['descripcion'] = $concepto['descripcion'];
            }
            $linea['califica_reporte'] = $linea['cuit'] !== ''
                && ($esIibb
                    ? in_array((int) $linea['jurisdiccion'], [901, 902], true)
                    : true);
            $mismoComprobante = $linea['clave_comprobante'] === $concepto['clave_comprobante'];
            $cuitsCompatibles = $linea['cuit'] === '' || $concepto['cuit'] === '' || $linea['cuit'] === $concepto['cuit'];
            if (! $mismoComprobante && $cuitsCompatibles && $concepto['comprobante'] !== '') {
                $linea['tipo'] = $concepto['tipo'];
                $linea['letra'] = $concepto['letra'];
                $linea['sucursal'] = $concepto['sucursal'];
                $linea['nro'] = $concepto['nro'];
                $linea['comprobante'] = $concepto['comprobante'];
                $linea['clave_comprobante'] = $concepto['clave_comprobante'];
                $linea['clave_importe'] = $concepto['clave_importe'];
                $linea['descripcion'] = $concepto['descripcion'] !== '' ? $concepto['descripcion'] : $linea['descripcion'];
            }
            $mayor[$idx] = $linea;
        }

        return $mayor;
    }

    /**
     * @param  array<string, mixed>  $linea
     * @param  array<string, list<array<string, mixed>>>  $porImporte
     * @param  array<string, list<array<string, mixed>>>  $porFechaImporte
     * @param  array<string, list<array<string, mixed>>>  $porFechaMonto
     * @return array<string, mixed>|null
     */
    private function conceptoDeLinea(array $linea, array $porImporte, array $porFechaImporte, array $porFechaMonto): ?array
    {
        $exactos = $porImporte[(string) ($linea['clave_importe'] ?? '')] ?? [];
        if (count($exactos) === 1) {
            return $exactos[0];
        }

        $fechaImporte = (string) ($linea['fecha'] ?? '').'|'.number_format((float) ($linea['importe'] ?? 0), 2, '.', '');
        $porTexto = $porFechaImporte[$fechaImporte.'|'.PercepcionSufridaLineaSupport::normalizar((string) ($linea['descripcion'] ?? ''))] ?? [];
        if (count($porTexto) === 1) {
            return $porTexto[0];
        }

        $porMonto = $porFechaMonto[$fechaImporte] ?? [];
        if (count($porMonto) === 1) {
            return $porMonto[0];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $mayor
     * @return list<array<string, mixed>>
     */
    private function reporteDesdeMayor(array $mayor, bool $esIibb): array
    {
        $out = [];
        foreach ($mayor as $linea) {
            if (! empty($linea['califica_reporte'])) {
                $out[] = $linea;
                continue;
            }
            if ($linea['cuit'] === '') {
                continue;
            }
            if ($esIibb && in_array((int) $linea['jurisdiccion'], [901, 902], true)) {
                $out[] = $linea;
                continue;
            }
            if (! $esIibb && PercepcionSufridaLineaSupport::esTextoPercepcionIva((string) $linea['descripcion'])) {
                $out[] = $linea;
            }
        }

        return $out;
    }
}
