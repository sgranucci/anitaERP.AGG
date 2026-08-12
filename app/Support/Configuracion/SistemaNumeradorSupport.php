<?php

namespace App\Support\Configuracion;

use App\ApiAnita;
use App\Models\Configuracion\SistemaNumerador;
use App\Support\Caja\IngresoEgresoAnitaNumeracionSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Numeradores de documento globales del ERP (tabla sistema_numerador).
 * Punto único para reservar/actualizar; opcionalmente sincroniza Anita.
 */
final class SistemaNumeradorSupport
{
    public static function codigoCaja(string $abreviatura): string
    {
        return 'caja.'.strtoupper(trim($abreviatura));
    }

    public static function aplicaTipoCaja(int $tipotransaccionCajaId): bool
    {
        $abrev = IngresoEgresoAnitaNumeracionSupport::abreviaturaTipo($tipotransaccionCajaId);

        return $abrev !== '' && isset(IngresoEgresoAnitaNumeracionSupport::mapaSemillas()[$abrev]);
    }

    /**
     * Reserva el siguiente número para un tipo de caja con semilla dedicada.
     * Debe llamarse dentro del lock de CobranzaNumeracionTransaccion::conExclusividad.
     */
    public static function reservarSiguienteCaja(int $empresaId, int $tipotransaccionCajaId, int $pisoErp = 0): string
    {
        $abrev = IngresoEgresoAnitaNumeracionSupport::abreviaturaTipo($tipotransaccionCajaId);
        if ($abrev === '') {
            throw new \RuntimeException('Tipo de transacción de caja sin abreviatura para numerador.');
        }

        $codigo = self::codigoCaja($abrev);
        $row = self::asegurarFilaCaja($codigo, $abrev, $empresaId, $tipotransaccionCajaId);

        return (string) self::reservarSiguiente($row->id, $pisoErp);
    }

    /**
     * Avanza ultimo_numero = max(ERP, Anita si aplica, piso) + 1.
     */
    public static function reservarSiguiente(int $sistemaNumeradorId, int $pisoExterno = 0): int
    {
        return (int) DB::transaction(function () use ($sistemaNumeradorId, $pisoExterno): int {
            /** @var SistemaNumerador $row */
            $row = SistemaNumerador::query()
                ->whereKey($sistemaNumeradorId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $row->activo) {
                throw new \RuntimeException(
                    'El numerador '.$row->codigo.' (empresa '.$row->empresa_id.') está inactivo.'
                );
            }

            $pisoAnita = 0;
            $syncAnita = self::debeSincronizarAnita($row);
            if ($syncAnita) {
                $pisoAnita = self::leerUltimoAnita($row);
            }

            $siguiente = max((int) $row->ultimo_numero, $pisoAnita, max(0, $pisoExterno)) + 1;
            $row->ultimo_numero = $siguiente;
            $row->save();

            if ($syncAnita) {
                self::actualizarAnita($row, $siguiente);
            }

            Log::info('sistema_numerador.reservado', [
                'id' => $row->id,
                'codigo' => $row->codigo,
                'empresa_id' => $row->empresa_id,
                'piso_anita' => $pisoAnita,
                'piso_externo' => $pisoExterno,
                'asignado' => $siguiente,
                'sync_anita' => $syncAnita,
            ]);

            return $siguiente;
        });
    }

    public static function sincronizarDesdeAnita(int $sistemaNumeradorId): int
    {
        /** @var SistemaNumerador $row */
        $row = SistemaNumerador::query()->findOrFail($sistemaNumeradorId);
        if (! self::tienePuenteAnita($row)) {
            throw new \RuntimeException(
                'El numerador no tiene puente Anita (sistema/fuente/clave).'
            );
        }

        $ultimo = self::leerUltimoAnita($row);
        $row->ultimo_numero = $ultimo;
        $row->save();

        return $ultimo;
    }

    public static function debeSincronizarAnita(SistemaNumerador $row): bool
    {
        return IngresoEgresoAnitaNumeracionSupport::estaHabilitada()
            && self::tienePuenteAnita($row);
    }

    public static function tienePuenteAnita(SistemaNumerador $row): bool
    {
        return trim((string) $row->anita_sistema) !== ''
            && trim((string) $row->anita_fuente) !== ''
            && trim((string) $row->anita_clave) !== '';
    }

    public static function leerUltimoAnita(SistemaNumerador $row): int
    {
        $sistema = trim((string) $row->anita_sistema);
        $fuente = strtolower(trim((string) $row->anita_fuente));
        $clave = trim((string) $row->anita_clave);

        if ($fuente !== 'numerador') {
            throw new \RuntimeException(
                'Fuente Anita no soportada en numerador ERP: '.$fuente.' (solo numerador).'
            );
        }

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => ' WHERE num_clave = '.self::escSqlLiteral($clave),
        ], 'sistema_numerador lectura '.$row->codigo);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException(
                'No se pudo leer numerador Anita ('.$sistema.'/'.$clave.'): '.$err
            );
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null || ! isset($fila->num_ult_numero)) {
            throw new \RuntimeException(
                'Numerador Anita inexistente (sistema='.$sistema.', num_clave='.$clave.').'
            );
        }

        return max(0, (int) $fila->num_ult_numero);
    }

    public static function actualizarAnita(SistemaNumerador $row, int $numero): void
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('Número de numerador inválido.');
        }

        $sistema = trim((string) $row->anita_sistema);
        $clave = trim((string) $row->anita_clave);

        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => $sistema,
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => ' WHERE num_clave = '.self::escSqlLiteral($clave),
        ], 'sistema_numerador update '.$row->codigo);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::error('sistema_numerador.anita_update_fail', [
                'id' => $row->id,
                'codigo' => $row->codigo,
                'clave' => $clave,
                'numero' => $numero,
                'error' => $err,
            ]);
            throw new \RuntimeException(
                'No se pudo actualizar numerador Anita ('.$sistema.'/'.$clave.'): '.$err
            );
        }
    }

    private static function asegurarFilaCaja(
        string $codigo,
        string $abrev,
        int $empresaId,
        int $tipotransaccionCajaId,
    ): SistemaNumerador {
        $existente = SistemaNumerador::query()
            ->where('codigo', $codigo)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $clave = null;
        try {
            $clave = (string) IngresoEgresoAnitaNumeracionSupport::claveNumerador($empresaId, $tipotransaccionCajaId);
        } catch (\Throwable $e) {
            // Sin semilla Anita: igual se crea fila ERP en 0.
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);

        return SistemaNumerador::query()->create([
            'codigo' => $codigo,
            'nombre' => 'Caja '.$abrev.' (auto)',
            'empresa_id' => $empresaId,
            'modulo' => 'caja',
            'ultimo_numero' => 0,
            'anita_sistema' => $clave !== null ? IngresoEgresoAnitaNumeracionSupport::sistemaNumerador() : null,
            'anita_fuente' => $clave !== null ? 'numerador' : null,
            'anita_clave' => $clave,
            'activo' => true,
            'observacion' => $empresaAnita > 0
                ? 'Creado automáticamente al numerar (empresa Anita '.$empresaAnita.').'
                : 'Creado automáticamente al numerar.',
        ]);
    }

    private static function escSqlLiteral(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
