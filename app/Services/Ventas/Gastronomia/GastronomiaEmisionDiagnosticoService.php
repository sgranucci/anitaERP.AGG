<?php

namespace App\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Configuracion\Condicioniva;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use InvalidArgumentException;

/**
 * Diagnóstico de latencia en emisión gastronomía (Anita vs ERP local).
 */
final class GastronomiaEmisionDiagnosticoService
{
    /**
     * @return array<string, mixed>
     */
    public function medirNumeracion(ConfiguracionPuntoventaGastronomia $cfg): array
    {
        $cfg->loadMissing(['tipotransaccion', 'puntoventaCaea', 'puntoventaCae']);

        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? 0);
        if ($tipoFacturaId <= 0) {
            $tipoFacturaId = (int) config('gastronomia.tipotransaccion_factura_id', 0);
        }
        if ($tipoFacturaId <= 0) {
            throw new InvalidArgumentException('Configure tipotransaccion_id en el PV gastronomía.');
        }

        $tt = $cfg->tipotransaccion ?? Tipotransaccion::query()->find($tipoFacturaId);
        if (! $tt) {
            throw new InvalidArgumentException('Tipo de transacción factura inexistente.');
        }

        $pvResolucion = ArcaWsfeEmisionResiliencia::resolverPuntoventaEmision(
            (int) ($cfg->puntoventa_cae_id ?? 0),
            (int) ($cfg->puntoventa_caea_id ?? 0),
            false,
        );
        $puntoventaId = (int) ($pvResolucion['puntoventa_id'] ?? 0);
        if ($puntoventaId <= 0) {
            throw new InvalidArgumentException('Configure punto de venta CAE/CAEA en el PV gastronomía.');
        }

        $puntoventa = Puntoventa::query()->find($puntoventaId);
        if (! $puntoventa) {
            throw new InvalidArgumentException('Punto de venta #'.$puntoventaId.' inexistente.');
        }

        $codigoTipo = (string) $tt->codigo;
        $tipoAnita = $codigoTipo >= '200'
            ? substr((string) $tt->abreviatura, 0, 1).'CE'
            : (string) $tt->abreviatura;

        $letra = 'B';
        $cfCondicionId = (int) config('gastronomia.consumidor_final_condicioniva_id', 3);
        $condicion = Condicioniva::query()->find($cfCondicionId);
        if ($condicion && trim((string) ($condicion->letra ?? '')) !== '') {
            $letra = (string) $condicion->letra;
        }

        $anitaTComp = $this->medirConsultaAnita([
            'acc' => 'list',
            'tabla' => 't_comp',
            'campos' => 'tcomp_tipo_comp',
            'whereArmado' => " WHERE tcomp_clave = '".$tipoAnita."'",
        ]);

        $tipoComp = '01';
        $filaTipo = ApiAnita::primeraFilaLista($anitaTComp['raw'] ?? '[]');
        if ($filaTipo !== null && isset($filaTipo->tcomp_tipo_comp)) {
            $tipoComp = (string) $filaTipo->tcomp_tipo_comp;
        }

        $anitaMaxVen = $this->medirConsultaAnita([
            'acc' => 'list',
            'tabla' => 'venta, t_comp',
            'campos' => 'max(ven_nro) as ultimonumero',
            'whereArmado' => " WHERE ven_tipo = tcomp_clave AND
                tcomp_tipo_comp = '".$tipoComp."' AND
                ven_letra = '".$letra."' AND
                ven_sucursal = '".$puntoventa->codigo."'",
        ]);

        $filaUltimo = ApiAnita::primeraFilaLista($anitaMaxVen['raw'] ?? '[]');
        $ultimoAnita = ($filaUltimo !== null && isset($filaUltimo->ultimonumero))
            ? (int) $filaUltimo->ultimonumero
            : 0;

        $t0Erp = microtime(true);
        $ultimoErp = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
            $puntoventaId,
            (int) ($tt->codigo ?? 0),
            $letra,
        );
        $erpMaxMs = round((microtime(true) - $t0Erp) * 1000, 2);

        $anitaPing = $this->medirConsultaAnita([
            'acc' => 'list',
            'tabla' => 't_comp',
            'campos' => 'tcomp_clave',
            'whereArmado' => ' WHERE 1=0',
        ]);

        $anitaNumerador = $this->medirConsultaAnita([
            'acc' => 'list',
            'tabla' => 'numerador',
            'campos' => 'num_valor',
            'whereArmado' => " WHERE num_tipo = '".$tipoAnita."' AND num_letra = '".$letra
                ."' AND num_sucursal = '".$puntoventa->codigo."'",
        ]);

        return [
            'puntoventa_id' => $puntoventaId,
            'puntoventa_codigo' => $puntoventa->codigo,
            'modofacturacion' => $puntoventa->modofacturacion,
            'usa_caea' => (bool) ($pvResolucion['usa_caea'] ?? false),
            'tipo_anita' => $tipoAnita,
            'tipo_comp_anita' => $tipoComp,
            'letra' => $letra,
            'ultimo_numero_anita' => $ultimoAnita,
            'ultimo_numero_erp' => $ultimoErp,
            'siguiente_anita' => $ultimoAnita + 1,
            'siguiente_erp' => $ultimoErp + 1,
            'diferencia_anita_vs_erp' => $ultimoAnita - $ultimoErp,
            'latencias_ms' => [
                'anita_t_comp' => $anitaTComp['ms'],
                'anita_max_ven_nro' => $anitaMaxVen['ms'],
                'anita_max_ven_nro_total' => round($anitaTComp['ms'] + $anitaMaxVen['ms'], 2),
                'erp_max_numerocomprobante' => $erpMaxMs,
                'anita_ping_vacio' => $anitaPing['ms'],
                'anita_numerador_tabla' => $anitaNumerador['ms'],
            ],
            'bridge' => [
                'host' => config('anita.ip'),
                'tipo' => config('anita_bridge_type', config('gastronomia.anita_bridge_type', 'HTTP')),
            ],
            'nota' => 'CAEA numera en ERP (max venta.numerocomprobante + lock por PV).',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ms:float,raw:string,error:?string}
     */
    private function medirConsultaAnita(array $payload): array
    {
        $api = new ApiAnita();
        $t0 = microtime(true);
        try {
            $raw = (string) $api->apiCall($payload);
            $err = ApiAnita::extraerMensajeError($raw);

            return [
                'ms' => round((microtime(true) - $t0) * 1000, 2),
                'raw' => $raw,
                'error' => $err,
            ];
        } catch (\Throwable $e) {
            return [
                'ms' => round((microtime(true) - $t0) * 1000, 2),
                'raw' => '',
                'error' => $e->getMessage(),
            ];
        }
    }
}
