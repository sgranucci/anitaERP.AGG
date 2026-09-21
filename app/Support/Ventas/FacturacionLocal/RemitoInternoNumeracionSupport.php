<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\ApiAnita;
use App\Models\Configuracion\SistemaNumerador;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\RemitoInterno;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Numeración remito interno Ferli: serie Anita LOCAL tipo RIN / letra B / sucursal del PV del local.
 * Si existe sistema_numerador ERP, lo alinea al último de Anita y reserva el siguiente.
 */
final class RemitoInternoNumeracionSupport
{
    public const TIPO_ANITA = 'RIN';

    public const LETRA_ANITA = 'B';

    public const CODIGO_NUMERADOR_PREFIX = 'ventas.rin.';

    /**
     * Reserva el siguiente número para el local (ERP + Anita LOCAL).
     * Debe llamarse dentro de una transacción DB.
     */
    public static function reservarSiguiente(LocalVenta $local, bool $actualizarAnita = true): int
    {
        $sucursal = self::sucursalDesdeLocal($local);
        if ($sucursal === '') {
            throw new InvalidArgumentException(
                'El local no tiene punto de venta para numerar RIN (configure PV default).'
            );
        }

        $empresaId = (int) ($local->empresa_id ?: 0);
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('El local no tiene empresa asignada.');
        }

        $servidor = $local->anitaServidor();
        $ifx = $local->anitaIfxServer();

        $anita = self::leerUltimoAnitaLocal($sucursal, $servidor, $ifx);
        $row = self::asegurarFilaNumerador($sucursal, $empresaId, $anita['clave'], $anita['ultimo']);

        /** @var SistemaNumerador $locked */
        $locked = SistemaNumerador::query()
            ->whereKey($row->id)
            ->lockForUpdate()
            ->firstOrFail();

        // Cargar en ERP el último correcto si Anita (u otra fuente) está más adelante.
        $pisoAnita = max(0, (int) $anita['ultimo']);
        if ($pisoAnita > (int) $locked->ultimo_numero) {
            $locked->ultimo_numero = $pisoAnita;
            if ($anita['clave'] !== '' && (string) $locked->anita_clave !== $anita['clave']) {
                $locked->anita_clave = $anita['clave'];
                $locked->anita_sistema = 'ventas';
                $locked->anita_fuente = 'numerador';
            }
            $locked->save();
        }

        $pisoErpTabla = (int) RemitoInterno::query()
            ->where('local_venta_id', (int) $local->id)
            ->lockForUpdate()
            ->max('numero');

        $siguiente = max((int) $locked->ultimo_numero, $pisoErpTabla, $pisoAnita) + 1;
        $locked->ultimo_numero = $siguiente;
        $locked->save();

        if ($actualizarAnita && $anita['clave'] !== '') {
            self::actualizarAnitaLocal($anita['clave'], $siguiente, $servidor, $ifx);
        }

        Log::info('remito_interno.numeracion.reservado', [
            'local_venta_id' => (int) $local->id,
            'sucursal' => $sucursal,
            'clave_anita' => $anita['clave'],
            'piso_anita' => $pisoAnita,
            'piso_erp_tabla' => $pisoErpTabla,
            'asignado' => $siguiente,
            'servidor' => $servidor,
        ]);

        return $siguiente;
    }

    /**
     * Solo lectura: último número Anita LOCAL (sin incrementar).
     *
     * @return array{ultimo: int, clave: string, sucursal: string}
     */
    public static function consultarUltimoAnita(LocalVenta $local): array
    {
        $sucursal = self::sucursalDesdeLocal($local);
        if ($sucursal === '') {
            return ['ultimo' => 0, 'clave' => '', 'sucursal' => ''];
        }

        $anita = self::leerUltimoAnitaLocal(
            $sucursal,
            $local->anitaServidor(),
            $local->anitaIfxServer()
        );

        return [
            'ultimo' => (int) $anita['ultimo'],
            'clave' => (string) $anita['clave'],
            'sucursal' => $sucursal,
        ];
    }

    public static function sucursalDesdeLocal(LocalVenta $local): string
    {
        $pv = $local->puntoventaDefault();
        if ($pv === null) {
            return '';
        }
        $codigo = trim((string) ($pv->codigo ?? ''));
        if ($codigo === '') {
            return '';
        }
        if (ctype_digit($codigo)) {
            $sinCeros = ltrim($codigo, '0');

            return $sinCeros === '' ? '0' : $sinCeros;
        }

        return $codigo;
    }

    public static function codigoNumerador(string $sucursal): string
    {
        return self::CODIGO_NUMERADOR_PREFIX.$sucursal;
    }

    /**
     * @return array{ultimo: int, clave: string}
     */
    private static function leerUltimoAnitaLocal(string $sucursal, string $servidor, string $ifx): array
    {
        $api = new ApiAnita;
        $whereSuc = self::sqlSucursal('compe_sucursal', $sucursal);
        $payload = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'compemis',
            'campos' => 'compe_numero,compe_tipo,compe_letra,compe_sucursal',
            'whereArmado' => " WHERE compe_tipo='".self::TIPO_ANITA."' AND compe_letra='".self::LETRA_ANITA."' AND ".$whereSuc,
            'servidor' => $servidor,
            'ifx_server' => $ifx,
        ];

        $raw = $api->apiCall($payload);
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException('No se pudo leer compemis RIN en Anita Local: '.$err);
        }

        $filaCompe = ApiAnita::primeraFilaLista((string) $raw);
        if ($filaCompe === null || ! isset($filaCompe->compe_numero)) {
            throw new RuntimeException(
                'No existe compemis RIN/'.self::LETRA_ANITA.'/sucursal '.$sucursal.' en el bridge Local.'
            );
        }

        $clave = trim((string) $filaCompe->compe_numero);
        if ($clave === '') {
            throw new RuntimeException('compemis RIN sin compe_numero (sucursal '.$sucursal.').');
        }

        $rawNum = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'numerador',
            'campos' => 'num_clave,num_ult_numero',
            'whereArmado' => " WHERE num_clave='".str_replace("'", "''", $clave)."'",
            'servidor' => $servidor,
            'ifx_server' => $ifx,
        ]);
        $errNum = ApiAnita::extraerMensajeError($rawNum);
        if ($errNum !== null) {
            throw new RuntimeException('No se pudo leer numerador Anita Local (clave '.$clave.'): '.$errNum);
        }

        $filaNum = ApiAnita::primeraFilaLista((string) $rawNum);
        if ($filaNum === null || ! isset($filaNum->num_ult_numero)) {
            throw new RuntimeException('Numerador Anita Local inexistente (num_clave='.$clave.').');
        }

        return [
            'ultimo' => max(0, (int) $filaNum->num_ult_numero),
            'clave' => $clave,
        ];
    }

    private static function actualizarAnitaLocal(
        string $clave,
        int $numero,
        string $servidor,
        string $ifx
    ): void {
        if ($numero <= 0 || $clave === '') {
            return;
        }

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'update',
            'sistema' => 'ventas',
            'tabla' => 'numerador',
            'valores' => 'num_ult_numero = '.(int) $numero,
            'whereArmado' => " WHERE num_clave = '".str_replace("'", "''", $clave)."'",
            'servidor' => $servidor,
            'ifx_server' => $ifx,
        ]);
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::error('remito_interno.numeracion.anita_update_fail', [
                'clave' => $clave,
                'numero' => $numero,
                'error' => $err,
            ]);
            throw new RuntimeException(
                'No se pudo actualizar numerador RIN Anita Local (clave '.$clave.'): '.$err
            );
        }
    }

    private static function asegurarFilaNumerador(
        string $sucursal,
        int $empresaId,
        string $claveAnita,
        int $pisoAnita
    ): SistemaNumerador {
        $codigo = self::codigoNumerador($sucursal);
        $existente = SistemaNumerador::query()
            ->where('codigo', $codigo)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($existente !== null) {
            $dirty = false;
            if ($claveAnita !== '' && (string) $existente->anita_clave !== $claveAnita) {
                $existente->anita_sistema = 'ventas';
                $existente->anita_fuente = 'numerador';
                $existente->anita_clave = $claveAnita;
                $dirty = true;
            }
            if ($pisoAnita > (int) $existente->ultimo_numero) {
                $existente->ultimo_numero = $pisoAnita;
                $dirty = true;
            }
            if ($dirty) {
                $existente->save();
            }

            return $existente;
        }

        return SistemaNumerador::query()->create([
            'codigo' => $codigo,
            'nombre' => 'Remito interno RIN sucursal '.$sucursal,
            'empresa_id' => $empresaId,
            'modulo' => 'ventas',
            'ultimo_numero' => max(0, $pisoAnita),
            'anita_sistema' => $claveAnita !== '' ? 'ventas' : null,
            'anita_fuente' => $claveAnita !== '' ? 'numerador' : null,
            'anita_clave' => $claveAnita !== '' ? $claveAnita : null,
            'activo' => true,
            'observacion' => 'Serie RIN/B del bridge Local (Facturación Local).',
        ]);
    }

    private static function sqlSucursal(string $campo, string $sucursal): string
    {
        $sucursal = trim($sucursal);
        $variantes = [$sucursal];
        if ($sucursal !== '' && ctype_digit($sucursal)) {
            $sinCeros = ltrim($sucursal, '0');
            if ($sinCeros === '') {
                $sinCeros = '0';
            }
            $variantes[] = $sinCeros;
            $variantes[] = str_pad($sinCeros, 5, '0', STR_PAD_LEFT);
        }
        $variantes = array_values(array_unique(array_filter(
            $variantes,
            static fn ($v) => $v !== ''
        )));

        if (count($variantes) === 1) {
            return $campo." = '".str_replace("'", "''", $variantes[0])."'";
        }

        $in = implode(',', array_map(
            static fn ($v) => "'".str_replace("'", "''", $v)."'",
            $variantes
        ));

        return $campo.' IN ('.$in.')';
    }
}
