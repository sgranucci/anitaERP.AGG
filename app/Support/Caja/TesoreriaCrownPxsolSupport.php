<?php

namespace App\Support\Caja;

use App\Models\Caja\CajeroCrownExterno;
use App\Models\Caja\TesoreriaCrownComprobante;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Lectura PXSol de la rendición (procesa_lectura_pxsol en a-transfefe.c).
 * Preferencia: API nativa gateway-prod.pxsol.com (trae_comprob.php).
 * Fallback: comando externo TESORERIA_CROWN_PXSOL_COMANDO.
 */
final class TesoreriaCrownPxsolSupport
{
    /**
     * @return list<string>
     */
    public static function codigos(TesoreriaCrownComprobante $comprobante): array
    {
        $cajeroId = (int) ($comprobante->cajeroOperativo()?->id ?? 0);
        if ($cajeroId <= 0) {
            return [];
        }

        return CajeroCrownExterno::query()
            ->where('cajero_crown_id', $cajeroId)
            ->where('sistema', CajeroCrownSistemaExternoSupport::PXSOL)
            ->orderBy('id')
            ->pluck('codigo_externo')
            ->map(fn ($codigo) => trim((string) $codigo))
            ->filter(fn (string $codigo) => $codigo !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{facturado: float, credito: float, iva_facturas: float, iva_nc: float, desde: string, hasta: string}
     */
    public static function leer(TesoreriaCrownComprobante $comprobante): array
    {
        if ($comprobante->tipo !== TesoreriaCrownComprobanteSupport::RENDICION
            || ! TesoreriaCrownRendicionCampos::esCajaHotel($comprobante->tipoTareaOperativa())) {
            throw new RuntimeException('PXSol se consulta en la rendición de hotel.');
        }
        $codigos = self::codigos($comprobante);
        if ($codigos === []) {
            throw new RuntimeException('Este cajero no tiene código PXSol.');
        }
        [$desde, $hasta] = self::ventana($comprobante);
        $total = [
            'facturado' => 0.0,
            'credito' => 0.0,
            'iva_facturas' => 0.0,
            'iva_nc' => 0.0,
        ];
        foreach ($codigos as $codigo) {
            $fila = self::consultarCodigo($codigo, $desde, $hasta);
            foreach ($total as $clave => $importe) {
                $total[$clave] = round($importe + $fila[$clave], 2);
            }
        }
        $total['desde'] = $desde;
        $total['hasta'] = $hasta;

        return $total;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function ventana(TesoreriaCrownComprobante $comprobante): array
    {
        $habilitacion = TesoreriaCrownComprobante::query()
            ->where('asignacion_id', $comprobante->asignacion_id)
            ->where('tipo', TesoreriaCrownComprobanteSupport::HABILITACION)
            ->orderBy('id')
            ->first();
        if ($habilitacion === null || $habilitacion->created_at === null) {
            throw new RuntimeException('Esta caja no tiene habilitación. PXSol arranca en esa hora.');
        }
        $dia = $comprobante->jornada->fecha->format('Y-m-d');
        $desde = $habilitacion->created_at->toDateString() === $dia
            ? $habilitacion->created_at->format('Y-m-d H:i:s')
            : $dia.' '.$habilitacion->created_at->format('H:i:s');
        $hasta = now()->toDateString() === $dia
            ? now()->format('Y-m-d H:i:s')
            : $dia.' 23:59:59';
        if ($hasta < $desde) {
            $hasta = $desde;
        }

        return [$desde, $hasta];
    }

    /**
     * @return array{facturado: float, credito: float, iva_facturas: float, iva_nc: float}
     */
    public static function parsear(string $salida): array
    {
        $linea = trim((string) strtok($salida, "\n"));
        $partes = explode('=', $linea);
        if (count($partes) < 4 || ! is_numeric($partes[0]) || ! is_numeric($partes[1]) || ! is_numeric($partes[2]) || ! is_numeric($partes[3])) {
            throw new RuntimeException('PXSol no devolvió los cuatro importes.');
        }

        return [
            'facturado' => round((float) $partes[0], 2),
            'credito' => round((float) $partes[1], 2),
            'iva_facturas' => round((float) $partes[2], 2),
            'iva_nc' => round((float) $partes[3], 2),
        ];
    }

    /**
     * @return array{facturado: float, credito: float, iva_facturas: float, iva_nc: float}
     */
    public static function consultarCodigo(string $codigo, string $desde, string $hasta): array
    {
        if (self::apiConfigurada()) {
            return self::consultarApi($codigo, $desde, $hasta);
        }
        $comando = trim((string) config('tesoreria_crown.pxsol_comando'));
        if ($comando === '') {
            throw new RuntimeException('PXSol no está configurado (falta token de API o comando).');
        }

        return self::consultarComando($comando, $codigo, $desde, $hasta);
    }

    public static function apiConfigurada(): bool
    {
        return trim((string) config('tesoreria_crown.pxsol_token')) !== ''
            && trim((string) config('tesoreria_crown.pxsol_url')) !== '';
    }

    /**
     * API nativa (mismo contrato que /home/sergio/tmp/trae_comprob.php).
     *
     * @return array{facturado: float, credito: float, iva_facturas: float, iva_nc: float}
     */
    public static function consultarApi(string $codigoCajero, string $desde, string $hasta): array
    {
        $url = trim((string) config('tesoreria_crown.pxsol_url'));
        $token = trim((string) config('tesoreria_crown.pxsol_token'));
        $timeout = max(5, (int) config('tesoreria_crown.pxsol_timeout'));
        $body = json_encode([
            'created_at_start_date' => $desde,
            'created_at_end_date' => $hasta,
        ], JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new RuntimeException('No se pudo armar el pedido a PXSol.');
        }

        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('No se pudo iniciar la consulta a PXSol.');
        }
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $http = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($errno !== 0) {
            throw new RuntimeException('PXSol no respondió: '.$error);
        }
        if ($http < 200 || $http >= 300) {
            throw new RuntimeException('PXSol respondió HTTP '.$http.'.');
        }
        if (! is_string($response) || trim($response) === '') {
            throw new RuntimeException('PXSol no devolvió datos.');
        }

        return self::sumarVouchers($response, $codigoCajero);
    }

    /**
     * @return array{facturado: float, credito: float, iva_facturas: float, iva_nc: float}
     */
    public static function sumarVouchers(string $json, string $codigoCajero): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('PXSol devolvió un JSON inválido.');
        }
        $data = $decoded['data'] ?? null;
        if (! is_array($data)) {
            throw new RuntimeException('PXSol no trajo la lista de vouchers.');
        }

        $facturado = 0.0;
        $credito = 0.0;
        $ivaFacturas = 0.0;
        $ivaNc = 0.0;
        $cajeroBuscado = trim($codigoCajero);

        foreach ($data as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $userId = trim((string) data_get($fila, 'created_by.user_id', ''));
            if ($userId === '' || $userId !== $cajeroBuscado) {
                continue;
            }
            $tipo = (string) ($fila['voucher_type'] ?? '');
            $total = (float) ($fila['total'] ?? 0);
            $iva = (float) ($fila['iva'] ?? 0);
            if (str_starts_with($tipo, 'Factura')) {
                $facturado += $total;
                $ivaFacturas += $iva;
            } elseif (str_starts_with($tipo, 'Nota de cr')) {
                $credito += $total;
                $ivaNc += $iva;
            }
        }

        return [
            'facturado' => round($facturado, 2),
            'credito' => round($credito, 2),
            'iva_facturas' => round($ivaFacturas, 2),
            'iva_nc' => round($ivaNc, 2),
        ];
    }

    /**
     * @return array{facturado: float, credito: float, iva_facturas: float, iva_nc: float}
     */
    private static function consultarComando(string $comando, string $codigo, string $desde, string $hasta): array
    {
        $partes = preg_split('/\s+/', trim($comando)) ?: [];
        $partes = array_values(array_filter($partes, fn ($p) => $p !== ''));
        if ($partes === []) {
            throw new RuntimeException('PXSol no está configurado.');
        }
        $argv = array_merge($partes, [$codigo, $desde, $hasta]);
        $proceso = new Process($argv);
        $proceso->setTimeout(max(5, (int) config('tesoreria_crown.pxsol_timeout')));
        try {
            $proceso->run();
        } catch (ProcessTimedOutException) {
            throw new RuntimeException('PXSol no respondió a tiempo.');
        }
        if (! $proceso->isSuccessful()) {
            $detalle = trim($proceso->getErrorOutput() ?: $proceso->getOutput());
            throw new RuntimeException($detalle !== '' ? $detalle : 'PXSol no respondió.');
        }

        return self::parsear($proceso->getOutput());
    }
}
