<?php

namespace App\Services\Ventas\Gastronomia;

use App\Support\Wigos\WigosTrackdataNormalizer;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Consulta datos de tarjeta Wigos vía HTTP (AccountInfoJSON).
 * Equivalente a: wget "http://serverwigosws:7788/WIGOS/AccountInfoJSON?trackdata={id}"
 */
final class WigosAccountInfoService
{
    /**
     * @return array{
     *   account_number:int,
     *   documento:string,
     *   apellido:string,
     *   nombre:string,
     *   email:string,
     *   level:string,
     *   level_code:int,
     *   status_code:int,
     *   status_text:string,
     *   raw:array<string,mixed>
     * }
     */
    public function consultarPorTrackdata(string $trackdata): array
    {
        if (! config('wigos.account_info_habilitado', false)) {
            throw new RuntimeException(
                'Consulta de tarjeta Wigos deshabilitada. Configure WIGOS_ACCOUNT_INFO_HABILITADO y WIGOS_ACCOUNT_INFO_URL.'
            );
        }

        $track = $this->normalizarTrackdata($trackdata);
        $url = $this->construirUrl($track);

        try {
            $response = Http::timeout(max(3, (int) config('wigos.account_info_timeout', 8)))
                ->acceptJson()
                ->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'No se pudo conectar con el servidor de tarjetas Wigos. Verifique la red o avise a sistemas.'
                .($e->getMessage() !== '' ? ' ('.$e->getMessage().')' : ''),
                0,
                $e
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->mensajeErrorHttpWigos($response->status(), $response->json()));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('La respuesta de Wigos no es JSON válido.');
        }

        return $this->parsearRespuesta($json);
    }

    private function normalizarTrackdata(string $trackdata): string
    {
        return WigosTrackdataNormalizer::normalizar($trackdata);
    }

    /**
     * @param  array<string,mixed>|null  $json
     */
    private function mensajeErrorHttpWigos(int $status, ?array $json): string
    {
        $detalleWigos = trim((string) ($json['Message'] ?? $json['message'] ?? $json['statusText'] ?? ''));

        if ($status >= 500) {
            return 'Wigos no pudo consultar la tarjeta (error del servidor, HTTP '.$status.'). '
                .'Suele deberse a datos de lectura incorrectos (caracteres extra al inicio o al final) o al servicio caído.'
                .($detalleWigos !== '' ? ' Detalle: '.$detalleWigos.'.' : '');
        }

        if ($status === 404) {
            return 'No se encontró el servicio de consulta de tarjetas en Wigos (HTTP 404). Revise WIGOS_ACCOUNT_INFO_URL.';
        }

        return 'Wigos rechazó la consulta de tarjeta (HTTP '.$status.').'
            .($detalleWigos !== '' ? ' '.$detalleWigos.'.' : '');
    }

    private function construirUrl(string $trackdata): string
    {
        $plantilla = trim((string) config('wigos.account_info_url', ''));
        if ($plantilla === '') {
            throw new RuntimeException('Falta configurar WIGOS_ACCOUNT_INFO_URL.');
        }

        if (str_contains($plantilla, '%s')) {
            return sprintf($plantilla, rawurlencode($trackdata));
        }

        $sep = str_contains($plantilla, '?') ? '&' : '?';

        return $plantilla.$sep.'trackdata='.rawurlencode($trackdata);
    }

    /**
     * @param  array<string,mixed>  $json
     * @return array{
     *   account_number:int,
     *   documento:string,
     *   apellido:string,
     *   nombre:string,
     *   email:string,
     *   level:string,
     *   level_code:int,
     *   status_code:int,
     *   status_text:string,
     *   raw:array<string,mixed>
     * }
     */
    private function parsearRespuesta(array $json): array
    {
        $result = $json['GetAccountInfoJsonResult'] ?? $json;
        if (! is_array($result)) {
            throw new RuntimeException('Formato de respuesta Wigos no reconocido.');
        }

        $statusCode = (int) ($result['statusCode'] ?? $result['StatusCode'] ?? -1);
        $statusText = trim((string) ($result['statusText'] ?? $result['StatusText'] ?? ''));
        if ($statusCode !== 0) {
            throw new InvalidArgumentException(
                'Wigos rechazó la consulta de tarjeta'
                .($statusText !== '' ? ': '.$statusText : '.')
            );
        }

        $levelCode = (int) ($result['levelCode'] ?? $result['LevelCode'] ?? 0);
        if ($levelCode <= 0) {
            throw new InvalidArgumentException('La tarjeta no tiene categoría de fidelidad (levelCode).');
        }

        $documento = trim((string) ($result['documentNumber'] ?? $result['DocumentNumber'] ?? ''));
        if ($documento === '') {
            throw new InvalidArgumentException('La tarjeta no tiene número de documento.');
        }

        return [
            'account_number' => (int) ($result['accountNumber'] ?? $result['AccountNumber'] ?? 0),
            'documento' => $documento,
            'apellido' => trim((string) ($result['firstSurname'] ?? $result['FirstSurname'] ?? '')),
            'nombre' => trim((string) ($result['name'] ?? $result['Name'] ?? '')),
            'email' => trim((string) ($result['email'] ?? $result['Email'] ?? '')),
            'level' => trim((string) ($result['level'] ?? $result['Level'] ?? '')),
            'level_code' => $levelCode,
            'status_code' => $statusCode,
            'status_text' => $statusText,
            'raw' => $result,
        ];
    }
}
