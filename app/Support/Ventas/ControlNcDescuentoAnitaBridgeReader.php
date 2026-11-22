<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportBridgeReader;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use Illuminate\Support\Facades\Log;

/**
 * Lectura Anita para p-controlnc: NCP del día + % dto vía climov → pago/auxpag → FAC/ND.
 */
final class ControlNcDescuentoAnitaBridgeReader
{
    private const AUXPAG_CAMPOS = 'axp_pro,axp_fecha,axp_rec,axp_tipo,axp_nro,axp_tipo_ap,'
        .'axp_monto_ap,axp_letra_comp,axp_sucursal,axp_letra_cob,axp_sucursal_cob';

    private const CLIMAE_CAMPOS = 'clim_cliente,clim_nombre,clim_lista_precio,clim_vendedor';

    public function __construct(
        private readonly ClienteCuentacorrienteAnitaImportBridgeReader $ccReader,
        private readonly ApiAnita $api = new ApiAnita,
    ) {
    }

    /**
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   ok: bool,
     *   error: ?string
     * }
     */
    public function listarNcpDelDia(string $fechaIso): array
    {
        $ymd = ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($fechaIso);
        if ($ymd <= 0) {
            return ['filas' => [], 'ok' => false, 'error' => 'Fecha inválida: '.$fechaIso];
        }

        try {
            $ventas = $this->ccReader->listarVentaPorRangoFecha($ymd, $ymd, 'ven_fecha');
        } catch (\Throwable $e) {
            Log::warning('ControlNcDescuentoAnitaBridgeReader venta', ['error' => $e->getMessage()]);

            return ['filas' => [], 'ok' => false, 'error' => $e->getMessage()];
        }

        $ncp = [];
        $claves = [];
        foreach ($ventas as $fila) {
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['ven_tipo'] ?? ''));
            if ($tipo !== ControlNcDescuentoMailSupport::TIPO_NCP) {
                continue;
            }
            $direccion = strtoupper(trim((string) ($fila['ven_direccion_cli'] ?? '')));
            if (str_starts_with($direccion, 'MOVIMIENTO ANULADO')) {
                continue;
            }
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                $tipo,
                (string) ($fila['ven_letra'] ?? ''),
                (int) ($fila['ven_sucursal'] ?? 0),
                (int) ($fila['ven_nro'] ?? 0),
            );
            $ncp[$clave] = $fila;
            $claves[] = $clave;
        }

        if ($claves === []) {
            return ['filas' => [], 'ok' => true, 'error' => null];
        }

        $climovNc = $this->indexarClimovPorClave($claves);
        $refsPago = [];
        foreach ($climovNc as $clave => $cliv) {
            $refTipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($cliv['cliv_ref_tipo'] ?? ''));
            $refNro = (int) ($cliv['cliv_ref_nro'] ?? 0);
            if ($refTipo === '' || $refNro <= 0) {
                continue;
            }
            $refsPago[] = [
                'clave_nc' => $clave,
                'tipo' => $refTipo,
                'letra' => ClienteCuentacorrienteAnitaImportClaveSupport::letra((string) ($cliv['cliv_ref_letra'] ?? '')),
                'sucursal' => (int) ($cliv['cliv_ref_sucursal'] ?? 0),
                'nro' => $refNro,
            ];
        }

        $auxPorPago = $this->listarAuxpagPorPagos($refsPago);
        $clavesFac = [];
        $facPorNc = [];
        foreach ($refsPago as $ref) {
            $clavePago = $this->clavePago($ref['tipo'], $ref['letra'], $ref['sucursal'], $ref['nro']);
            $lineas = $auxPorPago[$clavePago] ?? [];
            foreach ($lineas as $axp) {
                $tipoAp = ClienteCuentacorrienteAnitaImportClaveSupport::tipo(
                    (string) ($axp['axp_tipo_ap'] ?? $axp['axp_tipo_apli'] ?? '')
                );
                if ($tipoAp === '' || (! str_starts_with($tipoAp, 'FAC') && ! str_starts_with($tipoAp, 'ND'))) {
                    continue;
                }
                $claveFac = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                    $tipoAp,
                    (string) ($axp['axp_letra_comp'] ?? ''),
                    (int) ($axp['axp_sucursal'] ?? $axp['axp_sucursal_comp'] ?? 0),
                    (int) ($axp['axp_nro'] ?? 0),
                );
                $clavesFac[] = $claveFac;
                $facPorNc[$ref['clave_nc']][$claveFac] = true;
            }
        }

        $climovFac = $this->indexarClimovPorClave(array_values(array_unique($clavesFac)));
        $clientesCodigos = [];
        foreach ($climovNc as $cliv) {
            $cod = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
                (string) ($cliv['cliv_cliente'] ?? '')
            );
            if ($cod !== '') {
                $clientesCodigos[] = $cod;
            }
        }
        foreach ($ncp as $fila) {
            $cod = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
                (string) ($fila['ven_cliente'] ?? '')
            );
            if ($cod !== '') {
                $clientesCodigos[] = $cod;
            }
        }
        $climae = $this->indexarClimae(array_values(array_unique($clientesCodigos)));

        $out = [];
        foreach ($ncp as $clave => $ven) {
            $cliv = $climovNc[$clave] ?? null;
            if ($cliv === null) {
                continue;
            }
            $montoNc = abs((float) ($cliv['cliv_monto'] ?? $ven['ven_monto'] ?? 0));
            $sumaFac = 0.0;
            foreach (array_keys($facPorNc[$clave] ?? []) as $claveFac) {
                $filaFac = $climovFac[$claveFac] ?? null;
                if ($filaFac === null) {
                    continue;
                }
                $sumaFac += abs((float) ($filaFac['cliv_monto'] ?? 0));
            }
            $porc = ($sumaFac > 0.0001) ? round(($montoNc / $sumaFac) * 100.0, 4) : 0.0;
            if ($porc <= ControlNcDescuentoMailSupport::UMBRAL_LISTA_ESPECIAL) {
                continue;
            }

            $clienteCod = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
                (string) ($cliv['cliv_cliente'] ?? $ven['ven_cliente'] ?? '')
            );
            $cli = $climae[$clienteCod] ?? null;
            $lista = (int) ($cli['clim_lista_precio'] ?? 0);
            $nombre = trim((string) ($cli['clim_nombre'] ?? $ven['ven_nombre_cliente'] ?? ''));
            if ($nombre === '') {
                $nombre = 'Inexistente';
            }

            $out[] = [
                'clave' => $clave,
                'tipo' => ControlNcDescuentoMailSupport::TIPO_NCP,
                'letra' => ClienteCuentacorrienteAnitaImportClaveSupport::letra((string) ($ven['ven_letra'] ?? '')),
                'sucursal' => (int) ($ven['ven_sucursal'] ?? 0),
                'numero' => (int) ($ven['ven_nro'] ?? 0),
                'cliente_codigo' => ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp($clienteCod),
                'cliente_nombre' => $nombre,
                'monto' => round($montoNc, 2),
                'monto_fac' => round($sumaFac, 2),
                'porcentaje' => $porc,
                'lista_precio' => $lista,
                'tipo_recibo' => ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($cliv['cliv_ref_tipo'] ?? '')),
                'nro_recibo' => (int) ($cliv['cliv_ref_nro'] ?? 0),
                'vendedor_codigo' => (int) ($ven['ven_vendedor'] ?? $cli['clim_vendedor'] ?? 0),
                'fuente' => 'anita',
            ];
        }

        return ['filas' => $out, 'ok' => true, 'error' => null];
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, array<string, mixed>>
     */
    private function indexarClimovPorClave(array $claves): array
    {
        $claves = array_values(array_unique(array_filter($claves)));
        if ($claves === []) {
            return [];
        }

        // Primera fila conserva refs; monto suma todas las cuotas (como el while ISNEXT del C).
        $sumas = [];
        foreach ($this->ccReader->listarClimovPorDocumentos($claves) as $fila) {
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeClimov($fila);
            if ($clave === '') {
                continue;
            }
            $monto = abs((float) ($fila['cliv_monto'] ?? 0));
            if (! isset($sumas[$clave])) {
                $sumas[$clave] = $fila;
                $sumas[$clave]['cliv_monto'] = $monto;
            } else {
                $sumas[$clave]['cliv_monto'] = (float) $sumas[$clave]['cliv_monto'] + $monto;
            }
        }

        return $sumas;
    }

    /**
     * @param  list<array{clave_nc:string,tipo:string,letra:string,sucursal:int,nro:int}>  $refs
     * @return array<string, list<array<string, mixed>>>
     */
    private function listarAuxpagPorPagos(array $refs): array
    {
        $unicos = [];
        foreach ($refs as $ref) {
            $k = $this->clavePago($ref['tipo'], $ref['letra'], $ref['sucursal'], $ref['nro']);
            $unicos[$k] = $ref;
        }
        if ($unicos === []) {
            return [];
        }

        $index = [];
        foreach (array_chunk(array_values($unicos), 30) as $chunk) {
            $ors = [];
            foreach ($chunk as $ref) {
                $ors[] = "(axp_tipo = '".$this->esc($ref['tipo'])."' AND axp_rec = ".(int) $ref['nro'].')';
            }
            if ($ors === []) {
                continue;
            }
            $where = ' WHERE ('.implode(' OR ', $ors).')';
            foreach ($this->listar('che_ban', 'auxpag', self::AUXPAG_CAMPOS, $where, 'axp_rec, axp_nro') as $fila) {
                $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['axp_tipo'] ?? ''));
                $nro = (int) ($fila['axp_rec'] ?? 0);
                $letra = ClienteCuentacorrienteAnitaImportClaveSupport::letra(
                    (string) ($fila['axp_letra_cob'] ?? '')
                );
                $suc = (int) ($fila['axp_sucursal_cob'] ?? 0);
                // Match flexible: si cob letra/suc vienen vacíos, aceptar por tipo+rec.
                foreach ($chunk as $ref) {
                    if ($ref['tipo'] !== $tipo || $ref['nro'] !== $nro) {
                        continue;
                    }
                    $letraOk = $letra === ' ' || $letra === $ref['letra'] || $ref['letra'] === ' ';
                    $sucOk = $suc === 0 || $suc === $ref['sucursal'] || $ref['sucursal'] === 0;
                    if (! $letraOk || ! $sucOk) {
                        continue;
                    }
                    $clavePago = $this->clavePago($ref['tipo'], $ref['letra'], $ref['sucursal'], $ref['nro']);
                    $index[$clavePago][] = $fila;
                }
            }
        }

        return $index;
    }

    /**
     * @param  list<string>  $codigosCliente  padded 6
     * @return array<string, array<string, mixed>>
     */
    private function indexarClimae(array $codigosCliente): array
    {
        $codigosCliente = array_values(array_unique(array_filter($codigosCliente)));
        if ($codigosCliente === []) {
            return [];
        }

        $index = [];
        foreach (array_chunk($codigosCliente, 40) as $chunk) {
            $in = implode(',', array_map(fn ($c) => "'".$this->esc($c)."'", $chunk));
            $where = ' WHERE clim_cliente IN ('.$in.')';
            foreach ($this->listar('ventas', 'climae', self::CLIMAE_CAMPOS, $where, 'clim_cliente') as $fila) {
                $cod = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita(
                    (string) ($fila['clim_cliente'] ?? '')
                );
                if ($cod !== '') {
                    $index[$cod] = $fila;
                }
            }
        }

        return $index;
    }

    private function clavePago(string $tipo, string $letra, int $sucursal, int $nro): string
    {
        return implode('|', [
            ClienteCuentacorrienteAnitaImportClaveSupport::tipo($tipo),
            ClienteCuentacorrienteAnitaImportClaveSupport::letra($letra),
            (string) $sucursal,
            (string) $nro,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listar(
        string $sistema,
        string $tabla,
        string $campos,
        string $whereArmado,
        string $orderBy,
    ): array {
        try {
            $raw = $this->api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $whereArmado,
                'orderBy' => $orderBy,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ControlNcDescuentoAnitaBridgeReader', [
                'tabla' => $tabla,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $msg = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($msg !== null) {
            Log::warning('ControlNcDescuentoAnitaBridgeReader', ['tabla' => $tabla, 'error' => $msg]);

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : '');
        $out = [];
        foreach ($filas as $fila) {
            if (is_array($fila)) {
                $out[] = $fila;
            } elseif (is_object($fila)) {
                $out[] = (array) $fila;
            }
        }

        return $out;
    }

    private function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
