<?php

namespace App\Support\Ventas\Waitry;

/**
 * Identificador del monitor Waitry: contador numérico diario (external_delivery_id del push)
 * o código alfanumérico legacy (display_id, E-…).
 */
final class WaitryDisplayIdSupport
{
    /** Tope para distinguir contador diario del orderId global Waitry (~17M). */
    private const CONTADOR_MONITOR_MAX = 999_999;

    /**
     * Código alfanumérico legacy (display_id, external_reference_id, E-…).
     */
    public static function esCodigoMonitorAlfanumerico(string $valor): bool
    {
        $valor = trim($valor);

        return $valor !== '' && ! ctype_digit($valor);
    }

    /**
     * @deprecated Preferir esCodigoMonitorAlfanumerico o esIdentificadorMonitorValido.
     */
    public static function esCodigoMonitor(string $valor): bool
    {
        return self::esCodigoMonitorAlfanumerico($valor);
    }

    /**
     * Contador numérico del monitor (external_delivery_id del pushExternalOrder).
     */
    public static function esContadorMonitorNumerico(mixed $valor): bool
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        if (is_int($valor)) {
            return $valor > 0 && $valor <= self::CONTADOR_MONITOR_MAX;
        }

        $texto = trim((string) $valor);
        if ($texto === '' || ! ctype_digit($texto)) {
            return false;
        }

        $n = (int) $texto;

        return $n > 0 && $n <= self::CONTADOR_MONITOR_MAX;
    }

    /**
     * Identificador persistible en waitry_display_id (numérico de monitor o alfanumérico).
     */
    public static function esIdentificadorMonitorValido(string $valor): bool
    {
        $valor = trim($valor);
        if ($valor === '') {
            return false;
        }

        if (ctype_digit($valor)) {
            return self::esContadorMonitorNumerico($valor);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function extraerDesdeOrden(array $orden): string
    {
        foreach (['sequence', 'external_delivery_id', 'externalDeliveryId'] as $campo) {
            if (! array_key_exists($campo, $orden)) {
                continue;
            }

            $numerico = self::normalizarContadorMonitor($orden[$campo]);
            if ($numerico !== '') {
                return $numerico;
            }
        }

        foreach (['external_delivery_id', 'externalDeliveryId'] as $campo) {
            if (! array_key_exists($campo, $orden)) {
                continue;
            }

            $alfa = trim((string) $orden[$campo]);
            if (self::esCodigoMonitorAlfanumerico($alfa)) {
                return $alfa;
            }
        }

        foreach (['display_id', 'displayId', 'external_reference_id'] as $campo) {
            $valor = trim((string) ($orden[$campo] ?? ''));
            if (self::esCodigoMonitorAlfanumerico($valor)) {
                return $valor;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data  Respuesta completa de pushExternalOrder.
     */
    public static function extraerDesdeRespuestaPush(array $data): string
    {
        $response = $data['response'] ?? null;
        if (is_array($response)) {
            $desdeResponse = self::extraerDesdeOrden($response);
            if ($desdeResponse !== '') {
                return $desdeResponse;
            }
        }

        return self::extraerDesdeOrden($data);
    }

    public static function normalizarContadorMonitor(mixed $valor): string
    {
        if (! self::esContadorMonitorNumerico($valor)) {
            return '';
        }

        return (string) (int) (is_int($valor) ? $valor : trim((string) $valor));
    }

    /**
     * Busca orderId Waitry por número de secuencia del monitor (campo sequence en getordersdetails).
     *
     * @param  list<array<string, mixed>>  $ordenes
     */
    public static function orderIdDesdeOrdenesPorSecuencia(string $secuencia, array $ordenes): ?int
    {
        $secuencia = self::normalizarContadorMonitor($secuencia);
        if ($secuencia === '') {
            return null;
        }

        foreach ($ordenes as $orden) {
            if (! is_array($orden)) {
                continue;
            }

            $seqOrden = self::normalizarContadorMonitor($orden['sequence'] ?? null);
            if ($seqOrden !== $secuencia) {
                continue;
            }

            $orderId = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);

            return $orderId > 0 ? $orderId : null;
        }

        return null;
    }
}
