<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\Cuentacaja;
use Illuminate\Support\Facades\Log;

/**
 * Numeración de cheques propios vía Anita tesorería (tctes).
 *
 * En Anita, cada cuenta de tesorería tiene tipos de comprobante (tctes):
 * - tctes_numero = 000 → medio sin chequera (caja / transferencia)
 * - tctes_numero = N   → "Ref. a números" del numerador (ej. BMD / 324)
 *
 * El próximo cheque es ultimo+1 de ventas.numerador (o che_ban si falla).
 */
final class ChequePropioAnitaNumeracionSupport
{
    /**
     * @param  bool|null  $diferidoOverride  Si viene de la chequera (D/N), pisa la fecha.
     * @param  int|null  $chequeraId  Si hay chequera, el próximo número sale del talonario ERP (como OP).
     * @return array<string, mixed>
     */
    public static function payloadEmision(
        Cuentacaja $cuenta,
        string $fechaPago = '',
        string $fechaEmision = '',
        ?bool $diferidoOverride = null,
        ?int $chequeraId = null
    ): array {
        $fechaPago = self::ymd($fechaPago);
        $fechaEmision = self::ymd($fechaEmision) ?: $fechaPago;
        $diferido = $diferidoOverride ?? self::esFechaDiferida($fechaEmision, $fechaPago);

        $tctes = [];
        $elegido = null;
        $proximo = null;
        $aviso = null;
        $fuenteNumero = null;
        $chequeraUsadaId = null;

        $chequeras = self::chequerasDeCuenta((int) $cuenta->id, $diferido);
        $chequeraUsadaId = self::resolverChequeraIdParaNumero($chequeraId, $chequeras);

        if ($chequeraUsadaId !== null) {
            try {
                $proximo = (int) ChequeConsultaChequeraSupport::siguienteNumero($chequeraUsadaId);
                $fuenteNumero = 'chequera';
            } catch (\Throwable $e) {
                $aviso = $e->getMessage();
            }
        }

        if (self::estaHabilitada()) {
            try {
                $tctes = self::listarTctesCheque((string) $cuenta->codigo);
                $elegido = self::elegirTctes($tctes, $diferido);
                if ($elegido !== null) {
                    if ($proximo === null) {
                        $proximo = self::leerProximoNumero((int) $elegido['numero']);
                        $fuenteNumero = 'anita';
                        $aviso = null;
                    }
                } elseif ($proximo === null) {
                    $aviso = 'La cuenta no tiene tipo de comprobante Anita con numerador de cheques (tctes).';
                }
            } catch (\Throwable $e) {
                Log::warning('caja.cheque.anita_numerador', [
                    'cuentacaja_id' => $cuenta->id,
                    'error' => $e->getMessage(),
                ]);
                if ($proximo === null) {
                    $aviso = 'No se pudo leer el numerador Anita: '.$e->getMessage();
                }
            }
        }

        return [
            'id' => (int) $cuenta->id,
            'codigo' => (string) $cuenta->codigo,
            'nombre' => (string) $cuenta->nombre,
            'moneda_id' => (int) ($cuenta->moneda_id ?: 1),
            'banco_id' => (int) ($cuenta->banco_id ?: 0),
            'tctes_clave' => $elegido['clave'] ?? '',
            'tctes_desc' => $elegido['desc'] ?? '',
            'tctes_numero' => $elegido['numero'] ?? 0,
            'proximo_numero' => $proximo,
            'fuente_numero' => $fuenteNumero,
            'chequera_id' => $chequeraUsadaId,
            'diferido' => $diferido,
            'aviso' => $aviso,
            'chequeras' => $chequeras,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $chequeras
     */
    public static function resolverChequeraIdParaNumero(?int $chequeraId, array $chequeras): ?int
    {
        $chequeraId = (int) ($chequeraId ?? 0);
        if ($chequeraId > 0) {
            return $chequeraId;
        }
        if ($chequeras === []) {
            return null;
        }
        foreach ($chequeras as $ch) {
            if (! empty($ch['preferida']) && (int) ($ch['id'] ?? 0) > 0) {
                return (int) $ch['id'];
            }
        }
        $primero = (int) ($chequeras[0]['id'] ?? 0);

        return $primero > 0 ? $primero : null;
    }

    public static function estaHabilitada(): bool
    {
        return filter_var(
            config('caja.cheque_propio_anita_numeracion_habilitada', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function imputacionDesdeCodigo(string $codigoCuenta): string
    {
        $digits = preg_replace('/\D+/', '', trim($codigoCuenta)) ?? '';
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            $digits = '0';
        }

        return str_pad($digits, 8, '0', STR_PAD_LEFT);
    }

    public static function esTctesCheque(string $tctesNumero): bool
    {
        $n = str_pad(trim($tctesNumero), 3, '0', STR_PAD_LEFT);

        return $n !== '000' && ctype_digit($n) && (int) $n > 0;
    }

    public static function esDescripcionDiferido(string $desc, string $clave = ''): bool
    {
        $blob = strtoupper($desc.' '.$clave);

        return str_contains($blob, 'DIFER')
            || str_contains($blob, 'CH.D')
            || str_contains($blob, 'CHD')
            || str_contains($blob, ' POSDAT');
    }

    public static function esFechaDiferida(string $fechaEmision, string $fechaPago): bool
    {
        $emi = self::ymd($fechaEmision);
        $pago = self::ymd($fechaPago);
        if ($emi === '' || $pago === '') {
            return false;
        }

        return $pago > $emi;
    }

    /**
     * @param  list<array{clave:string,desc:string,numero:int,diferido:bool}>  $filas
     * @return array{clave:string,desc:string,numero:int,diferido:bool}|null
     */
    public static function elegirTctes(array $filas, bool $diferido): ?array
    {
        if ($filas === []) {
            return null;
        }
        foreach ($filas as $fila) {
            if (($fila['diferido'] ?? false) === $diferido) {
                return $fila;
            }
        }

        return $filas[0];
    }

    /**
     * @return list<array{clave:string,desc:string,numero:int,diferido:bool}>
     */
    public static function listarTctesCheque(string $codigoCuenta): array
    {
        $imputacion = self::imputacionDesdeCodigo($codigoCuenta);
        $raw = (new ApiAnita)->apiCallEscritura([
            'acc' => 'list',
            'sistema' => (string) config('caja.ingresoegreso_anita_tesmov_sistema', 'che_ban'),
            'tabla' => 'tctes',
            'campos' => 'tctes_clave,tctes_imputacion,tctes_numero,tctes_desc',
            'whereArmado' => ' WHERE tctes_imputacion = '.self::escSql($imputacion),
        ], 'caja cheque tctes '.$imputacion);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer tctes Anita: '.$err);
        }

        $out = [];
        foreach (ApiAnita::decodificarListaFilas((string) $raw) as $fila) {
            $numero = trim((string) ($fila->tctes_numero ?? ''));
            if (! self::esTctesCheque($numero)) {
                continue;
            }
            $clave = strtoupper(trim((string) ($fila->tctes_clave ?? '')));
            $desc = trim((string) ($fila->tctes_desc ?? ''));
            $out[] = [
                'clave' => $clave,
                'desc' => $desc,
                'numero' => (int) $numero,
                'diferido' => self::esDescripcionDiferido($desc, $clave),
            ];
        }

        return $out;
    }

    public static function leerProximoNumero(int $claveNumerador): int
    {
        $ultimo = self::leerUltimoNumero($claveNumerador);

        return $ultimo + 1;
    }

    public static function leerUltimoNumero(int $claveNumerador): int
    {
        if ($claveNumerador <= 0) {
            throw new \InvalidArgumentException('Clave de numerador de cheque inválida.');
        }

        $ultimoError = 'Numerador Anita inexistente (num_clave='.$claveNumerador.').';
        foreach (self::sistemasNumerador() as $sistema) {
            $api = new ApiAnita;
            $raw = $api->apiCallEscritura([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => 'numerador',
                'campos' => 'num_ult_numero',
                'whereArmado' => ' WHERE num_clave = '.self::escSql((string) $claveNumerador),
            ], 'caja cheque numerador lectura '.$sistema.' '.$claveNumerador);

            $err = ApiAnita::extraerMensajeError($raw);
            if ($err !== null) {
                $ultimoError = 'No se pudo leer numerador Anita ('.$sistema.'/'.$claveNumerador.'): '.$err;
                continue;
            }

            $fila = ApiAnita::primeraFilaLista((string) $raw);
            if ($fila === null || ! isset($fila->num_ult_numero)) {
                continue;
            }

            return max(0, (int) $fila->num_ult_numero);
        }

        throw new \RuntimeException($ultimoError);
    }

    public static function actualizarSiMayor(int $claveNumerador, int $numeroUsado): void
    {
        if ($claveNumerador <= 0 || $numeroUsado <= 0 || ! self::estaHabilitada()) {
            return;
        }

        try {
            $ultimo = self::leerUltimoNumero($claveNumerador);
        } catch (\Throwable $e) {
            Log::warning('caja.cheque.anita_numerador_lectura_update', [
                'clave' => $claveNumerador,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($numeroUsado <= $ultimo) {
            return;
        }

        $ultimoError = null;
        foreach (self::sistemasNumerador() as $sistema) {
            $raw = (new ApiAnita)->apiCallEscritura([
                'acc' => 'update',
                'sistema' => $sistema,
                'tabla' => 'numerador',
                'valores' => 'num_ult_numero = '.(int) $numeroUsado,
                'whereArmado' => ' WHERE num_clave = '.self::escSql((string) $claveNumerador),
            ], 'caja cheque numerador update '.$sistema.' '.$claveNumerador);

            $err = ApiAnita::extraerMensajeError($raw);
            if ($err === null) {
                return;
            }
            $ultimoError = $err;
        }

        Log::error('caja.cheque.anita_numerador_update_fail', [
            'clave' => $claveNumerador,
            'numero' => $numeroUsado,
            'error' => $ultimoError,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function actualizarDesdeFilasEmitidas(array $data): void
    {
        $claves = $data['tctes_numero_emitidos'] ?? [];
        $numeros = $data['numerocheque_emitidos'] ?? [];
        $montos = $data['montocheque_emitidos'] ?? [];
        $cuentacajaIds = $data['cuentacaja_emitido_ids'] ?? [];
        $fechasPago = $data['fechapago_emitidos'] ?? [];
        $fechaEmision = (string) ($data['fecha'] ?? '');
        $maxPorClave = [];

        foreach ($numeros as $i => $numero) {
            if ((float) ($montos[$i] ?? 0) <= 0) {
                continue;
            }
            $clave = (int) ($claves[$i] ?? 0);
            if ($clave <= 0) {
                $ctaId = (int) ($cuentacajaIds[$i] ?? 0);
                $cuenta = $ctaId > 0 ? Cuentacaja::query()->find($ctaId) : null;
                if ($cuenta) {
                    $payload = self::payloadEmision(
                        $cuenta,
                        (string) ($fechasPago[$i] ?? ''),
                        $fechaEmision
                    );
                    $clave = (int) ($payload['tctes_numero'] ?? 0);
                }
            }
            $nro = (int) preg_replace('/\D/', '', (string) $numero);
            if ($clave <= 0 || $nro <= 0) {
                continue;
            }
            $maxPorClave[$clave] = max($maxPorClave[$clave] ?? 0, $nro);
        }

        foreach ($maxPorClave as $clave => $nro) {
            self::actualizarSiMayor((int) $clave, $nro);
        }
    }

    /**
     * @return list<string>
     */
    public static function sistemasNumerador(): array
    {
        $primario = (string) config('caja.cheque_propio_anita_sistema_numerador', 'ventas');
        $fallback = (string) config('caja.cheque_propio_anita_sistema_numerador_fallback', 'che_ban');
        $out = [];
        foreach ([$primario, $fallback] as $sistema) {
            $sistema = trim($sistema);
            if ($sistema !== '' && ! in_array($sistema, $out, true)) {
                $out[] = $sistema;
            }
        }

        return $out !== [] ? $out : ['ventas'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function chequerasDeCuenta(int $cuentacajaId, bool $preferirDiferido = false): array
    {
        return ChequeConsultaChequeraSupport::consultar([
            'cuentacaja_id' => $cuentacajaId,
            'preferir_diferido' => $preferirDiferido,
            'incluir_terminadas' => false,
        ]);
    }

    private static function ymd(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return '';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $fecha, $m)) {
            return $m[1];
        }

        return '';
    }

    private static function escSql(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
