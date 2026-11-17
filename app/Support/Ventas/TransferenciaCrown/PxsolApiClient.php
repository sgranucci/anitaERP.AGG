<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use RuntimeException;

/**
 * Lectura de vouchers fiscales PXSOL (API v2 canónica).
 * No usa voucher/list/v2: ese endpoint no trae payment_type.
 */
final class PxsolApiClient
{
    private const PER_PAGE = 20;

    /**
     * @return list<array<string, mixed>>
     */
    public function listarVouchers(
        string $token,
        int $hotelId,
        string $desdeYmd,
        string $hastaYmd,
        ?int $fiscalPointOfSale = null,
    ): array {
        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException('Falta el token de PXSOL para este hotel.');
        }
        if ($hotelId <= 0) {
            throw new RuntimeException('Falta el hotel_id de PXSOL.');
        }

        $urlBase = rtrim((string) config('ventas_transferencia_crown.pxsol_list_url'), '/');
        $timeout = max(10, (int) config('ventas_transferencia_crown.pxsol_timeout', 60));
        $maxFilas = (int) config('ventas_transferencia_crown.max_filas', 50000);
        $out = [];
        $page = 1;
        $lastPage = 1;

        do {
            $query = [
                'hotel_id' => $hotelId,
                'date_from' => $desdeYmd,
                'date_to' => $hastaYmd,
                'per_page' => self::PER_PAGE,
                'page' => $page,
            ];
            $payload = $this->getJson($urlBase.'?'.http_build_query($query), $token, $timeout);
            $data = $payload['data'] ?? null;
            if (! is_array($data)) {
                throw new RuntimeException('PXSOL no devolvió la lista de vouchers.');
            }
            foreach ($data as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $attrs = is_array($item['attributes'] ?? null) ? $item['attributes'] : $item;
                if (! isset($attrs['id']) && isset($item['id'])) {
                    $attrs['id'] = $item['id'];
                }
                $pvFiscal = (int) ($attrs['fiscal_point_of_sale'] ?? 0);
                if ($fiscalPointOfSale !== null && $fiscalPointOfSale > 0 && $pvFiscal !== $fiscalPointOfSale) {
                    continue;
                }
                $out[] = $attrs;
            }
            $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
            $lastPage = max(1, (int) ($meta['last_page'] ?? 1));
            if ($maxFilas > 0 && count($out) > $maxFilas) {
                throw new RuntimeException('PXSOL trajo más de '.$maxFilas.' comprobantes. Achicá el rango de fechas.');
            }
            $page++;
        } while ($page <= $lastPage);

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url, string $token, int $timeout): array
    {
        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('No se pudo iniciar la consulta a PXSOL.');
        }
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$token,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $http = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($errno !== 0) {
            throw new RuntimeException('PXSOL no respondió: '.$error);
        }
        if ($http < 200 || $http >= 300) {
            throw new RuntimeException('PXSOL respondió HTTP '.$http.'.');
        }
        if (! is_string($response) || trim($response) === '') {
            throw new RuntimeException('PXSOL no devolvió datos.');
        }
        $decoded = json_decode($response, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('PXSOL devolvió un JSON inválido.');
        }

        return $decoded;
    }
}
