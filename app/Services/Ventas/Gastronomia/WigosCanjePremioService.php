<?php

namespace App\Services\Ventas\Gastronomia;

use App\Support\Wigos\WigosConfigResolver;
use App\Support\Wigos\WigosSqlServerProcess;
use InvalidArgumentException;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Consulta tickets de canje de premios en Wigos (spVoucherGiftData).
 * Basado en track_wigos.php.
 */
final class WigosCanjePremioService
{
    /**
     * @return list<object{
     *   GIFT_ID:string,
     *   GIFT_NAME:string,
     *   SPENT_POINTS:mixed,
     *   QUANTITY:mixed,
     *   REQUESTED:mixed,
     *   ACCOUNT:mixed,
     *   CUSTOMER:string,
     *   DOCUMENT_NUMBER:string,
     *   STATUS:string
     * }>
     */
    public function consultarPorCodigoBarras(string $numerocupon, int $empresaId = 0): array
    {
        if (! config('wigos.habilitado', false)) {
            throw new RuntimeException(
                'Integración Wigos deshabilitada. Configure WIGOS_HABILITADO=true y credenciales SQL Server.'
            );
        }

        $codigo = trim($numerocupon);
        if ($codigo === '') {
            throw new InvalidArgumentException('Debe indicar el número de cupón.');
        }

        $primario = WigosConfigResolver::currWigos($empresaId);
        $secundario = $primario === 'A' ? 'B' : 'A';

        $errores = [];

        try {
            $filas = $this->ejecutarSp($primario, $codigo, $empresaId);
        } catch (RuntimeException $e) {
            $errores[$primario] = $e->getMessage();
            // Solo A↔B ante conexión/espejo (como flash); errores de SP/datos no reintentan.
            if (! WigosSqlServerProcess::esErrorConexionOEspejo($e)) {
                throw $e;
            }
            $filas = null;
        }

        if ($filas === null && $this->conexionConfigurada($secundario, $empresaId)) {
            try {
                $filas = $this->ejecutarSp($secundario, $codigo, $empresaId);
            } catch (RuntimeException $e) {
                $errores[$secundario] = $e->getMessage();
                $filas = null;
            }
        }

        if ($filas === null) {
            throw new RuntimeException(
                'No se pudo conectar a Wigos. '.implode(' | ', $errores)
            );
        }

        if ($filas === []) {
            throw new InvalidArgumentException(
                'No se encontró el ticket de canje '.$codigo.' en Wigos o no está pendiente.'
            );
        }

        $pendientes = array_values(array_filter(
            $filas,
            fn ($fila) => strtoupper(trim((string) ($fila->STATUS ?? ''))) === 'PENDING'
        ));

        if ($pendientes === []) {
            $estado = strtoupper(trim((string) ($filas[0]->STATUS ?? '')));
            throw new InvalidArgumentException(
                'El ticket no está disponible para canje en Wigos'
                .($estado !== '' ? ' (estado: '.$estado.')' : '.')
            );
        }

        return $pendientes;
    }

    /**
     * @return list<object>
     */
    private function ejecutarSp(string $alias, string $codigo, int $empresaId = 0): array
    {
        if (! $this->conexionConfigurada($alias, $empresaId)) {
            throw new RuntimeException('Wigos '.$alias.': conexión no configurada (host vacío).');
        }

        try {
            return WigosSqlServerProcess::ejecutarSpVoucherGiftData($alias, $codigo, $empresaId);
        } catch (RuntimeException $e) {
            throw new RuntimeException(
                $this->normalizarMensajeError($alias, $e),
                0,
                $e
            );
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Wigos '.$alias.': '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    private function normalizarMensajeError(string $alias, RuntimeException $e): string
    {
        $msg = $e->getMessage();
        if (str_starts_with($msg, 'Wigos '.$alias.':')) {
            $msg = trim(substr($msg, strlen('Wigos '.$alias.':')));
        }

        $previous = $e->getPrevious();
        if ($previous instanceof PDOException) {
            $msg = $previous->getMessage();
        }

        if (stripos($msg, 'Login timeout') !== false) {
            return 'Wigos '.$alias.': no responde (login timeout) — verificar red/firewall hacia '
                .'el SQL Server, puerto y servicio activo.';
        }

        if (stripos($msg, '0x2746') !== false) {
            return 'Wigos '.$alias.': TLS/OpenSSL incompatible con SQL Server 2012 — verificar '
                .'config/openssl/wigos-mssql.cnf y subproceso Wigos.';
        }

        if (stripos($msg, 'TLS') !== false || stripos($msg, 'SSL') !== false || stripos($msg, 'encrypt') !== false) {
            return 'Wigos '.$alias.': TLS/encriptación rechazada — revisar WIGOS_ENCRYPT '
                .'(usar "no" si el server no tiene cert) y WIGOS_TRUST_SERVER_CERTIFICATE.';
        }

        return 'Wigos '.$alias.': '.$msg;
    }

    private function conexionConfigurada(string $alias, int $empresaId = 0): bool
    {
        return WigosConfigResolver::conexionConfigurada($alias, $empresaId);
    }
}
