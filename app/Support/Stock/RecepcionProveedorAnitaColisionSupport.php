<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Facades\DB;

/**
 * Evita confirmar una recepción COM cuyo número ya existe en Anita con datos ajenos
 * (p. ej. numeración ERP con max() desfasada del legado Anita).
 *
 * Numerador COM único para las 3 empresas: recm_nro no se reutiliza entre sucursales Anita.
 */
final class RecepcionProveedorAnitaColisionSupport
{
    /**
     * Antes de asignar numerorecepcion: ocupado en ERP (global) o en recepmae (cualquier sucursal).
     *
     * @param  int|null  $excluirRecepcionId  Al renumerar un borrador, excluir la fila actual en ERP.
     */
    public static function numeroComOcupadoParaNuevaAsignacion(int $nro, ?int $excluirRecepcionId = null): bool
    {
        if ($nro <= 0) {
            return false;
        }

        $query = DB::table('recepcion_proveedor')->where('numerorecepcion', $nro);
        if ($excluirRecepcionId !== null && $excluirRecepcionId > 0) {
            $query->where('id', '!=', $excluirRecepcionId);
        }
        if ($query->exists()) {
            return true;
        }

        return self::existeComNroEnRecepmae($nro);
    }

    /**
     * Primer COM libre desde $desde (numerador único ERP + Anita, las 3 empresas).
     */
    public static function primerNumeroComDisponible(int $desde, ?int $excluirRecepcionId = null): int
    {
        $nro = max(1, $desde);
        for ($intentos = 0; $intentos < 500; $intentos++) {
            if (! self::numeroComOcupadoParaNuevaAsignacion($nro, $excluirRecepcionId)) {
                return $nro;
            }
            $nro++;
        }

        throw new \RuntimeException(
            'No se encontró número COM libre desde '.$desde.' (numerador único ERP/Anita).'
        );
    }

    /**
     * ¿El numerorecepcion choca con un COM ya grabado en Anita en otra empresa/sucursal?
     */
    public static function colisionaNumeradorGlobalConAnita(Recepcion_Proveedor $recepcion): bool
    {
        $recepcion->loadMissing(['proveedores', 'empresas']);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        if ((int) ($clave['nro'] ?? 0) <= 0) {
            return false;
        }

        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);

        return self::existeComNroEnRecepmae((int) $clave['nro'])
            && self::leerRecepmaeProveedorClave($codigoProveedor, $clave) === null
            && self::leerRecepmaeErpPorClave($codigoProveedor, $clave) === null;
    }

    /**
     * ¿Existe recepmae COM/X con este recm_nro (cualquier sucursal, proveedor o terminal)?
     */
    public static function existeComNroEnRecepmae(int $nro): bool
    {
        if ($nro <= 0) {
            return false;
        }

        $cfg = config('recepcion_proveedor.anita');
        $tipo = addslashes((string) $cfg['recepcion_tipo']);
        $letra = addslashes(RecepcionProveedorAnitaClaveSupport::letraCom());

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_compras'],
            'tabla' => (string) $cfg['tablas']['recepcion_cabecera'],
            'campos' => 'recm_nro',
            'whereArmado' => " WHERE recm_tipo = '{$tipo}' AND recm_letra = '{$letra}'"
                .' AND recm_nro = '.(int) $nro,
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    /**
     * ¿Existe recepmae COM/X en esta sucursal Anita (misma clave numérica que ERP empresa)?
     */
    public static function existeComNroEnRecepmaeSucursal(int $sucursal, int $nro): bool
    {
        if ($nro <= 0 || $sucursal <= 0) {
            return false;
        }

        $cfg = config('recepcion_proveedor.anita');
        $tipo = addslashes((string) $cfg['recepcion_tipo']);
        $letra = addslashes(RecepcionProveedorAnitaClaveSupport::letraCom());

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_compras'],
            'tabla' => (string) $cfg['tablas']['recepcion_cabecera'],
            'campos' => 'recm_nro',
            'whereArmado' => " WHERE recm_tipo = '{$tipo}' AND recm_letra = '{$letra}'"
                .' AND recm_sucursal = '.(int) $sucursal
                .' AND recm_nro = '.(int) $nro,
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    /**
     * Huérfano ERP: recepmae recm_terminal=ERP sin recepción ERP viva con ese documentoid.
     * Solo este caso se considera para limpieza/renumeración ERP (no REF ni terminal vacío).
     *
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    public static function existeRecepmaeErpHuerfano(string $codigoProveedor, array $clave): bool
    {
        $cabecera = self::leerRecepmaeErpPorClave($codigoProveedor, $clave);
        if ($cabecera === null) {
            return false;
        }

        $documentoId = (int) ($cabecera->recm_documentoid ?? 0);
        if ($documentoId <= 0) {
            return true;
        }

        return ! DB::table('recepcion_proveedor')->where('id', $documentoId)->exists();
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function numeroOcupadoEnAnita(array $clave, string $codigoProveedor): bool
    {
        if ((int) $clave['nro'] <= 0) {
            return false;
        }

        return self::existeRecepmaeErp($codigoProveedor, $clave)
            || self::existeRecepmovProveedor($codigoProveedor, $clave)
            || (RecepcionProveedorStkmovAnitaSupport::habilitado() && self::existeStkmovErp($clave));
    }

    /**
     * Cabecera COM en Anita para proveedor+clave (cualquier recm_terminal).
     *
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    public static function leerRecepmaeProveedorClave(string $codigoProveedor, array $clave): ?object
    {
        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_compras'],
            'tabla' => (string) $cfg['tablas']['recepcion_cabecera'],
            'campos' => 'recm_proveedor, recm_fecha, recm_tipo_fac, recm_letra_fac, recm_sucursal_fac, recm_nro_fac, recm_estado, recm_documentoid, recm_terminal',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmae($codigoProveedor, $clave),
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw);
    }

    private static function existeRecepmaeErp(string $codigoProveedor, array $clave): bool
    {
        return self::leerRecepmaeErpPorClave($codigoProveedor, $clave) !== null;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    public static function tieneRecepmovOStkmov(string $codigoProveedor, array $clave): bool
    {
        if (self::existeRecepmovProveedor($codigoProveedor, $clave)) {
            return true;
        }

        return RecepcionProveedorStkmovAnitaSupport::habilitado()
            && self::existeStkmovErp($clave);
    }

    private static function existeRecepmovProveedor(string $codigoProveedor, array $clave): bool
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('recepcion_proveedor.anita.sistema_compras'),
            'tabla' => (string) config('recepcion_proveedor.anita.tablas.recepcion_linea'),
            'campos' => 'recv_nro',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmovProveedorCabecera($codigoProveedor, $clave),
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    private static function existeStkmovErp(array $clave): bool
    {
        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_ventas'],
            'tabla' => (string) $cfg['tablas']['stock_movimiento'],
            'campos' => 'stkv_nro',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::stkmovCabeceraSoloErp($clave),
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    /**
     * @deprecated Solo huérfanos ERP en recepmae; no usar para asignar número nuevo.
     */
    public static function numeroOcupadoEnAnitaLegacy(array $clave): bool
    {
        if ((int) $clave['nro'] <= 0) {
            return false;
        }

        return self::existeComNroEnRecepmae((int) $clave['nro']);
    }

    /**
     * Antes de confirmar/sincronizar: no pisar COM Anita (REF/vacío) ni otro proveedor.
     */
    public static function assertConfirmacionSegura(Recepcion_Proveedor $recepcion): void
    {
        $recepcion->loadMissing(['proveedores', 'ordencompras', 'empresas']);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);

        if (self::existeComNroEnRecepmae((int) $clave['nro'])
            && ! self::leerRecepmaeProveedorClave($codigoProveedor, $clave)
            && ! self::leerRecepmaeErpPorClave($codigoProveedor, $clave)
        ) {
            throw new \RuntimeException(
                'El número COM '.(int) $clave['nro']
                .' ya existe en Anita (otra empresa o sucursal).'
                .' Asigne otro numerorecepcion antes de confirmar.'
            );
        }

        $cabeceraClave = self::leerRecepmaeProveedorClave($codigoProveedor, $clave);
        if ($cabeceraClave !== null && self::esMismaRecepcionEnAnita($recepcion, $cabeceraClave)) {
            return;
        }

        if ($cabeceraClave !== null) {
            $terminal = trim((string) ($cabeceraClave->recm_terminal ?? ''));
            $docId = (int) ($cabeceraClave->recm_documentoid ?? 0);

            if (RecepcionProveedorAnitaWhereSupport::esTerminalProtegidoAnita($terminal) && $docId === 0) {
                throw new \RuntimeException(
                    'El número COM '.(int) $clave['nro'].' (sucursal '.(int) $clave['sucursal'].')'
                    .' ya está registrado en Anita desktop (recm_terminal='.$terminal.', documentoid=0).'
                    .' No se puede confirmar desde ERP sin renumerar.'
                );
            }

            if ($terminal === RecepcionProveedorAnitaWhereSupport::TERMINAL_ERP && self::esRecepmaeErpHuerfano($cabeceraClave)) {
                // Cabecera ERP vacía (sin recepmov/stkmov) del mismo proveedor: reclamar en confirmación.
                $proveedorCabeceraHuerfano = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6(
                    trim((string) ($cabeceraClave->recm_proveedor ?? ''))
                );
                $proveedorAnitaHuerfano = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor);
                if (
                    $proveedorCabeceraHuerfano === $proveedorAnitaHuerfano
                    && ! self::tieneRecepmovOStkmov($codigoProveedor, $clave)
                ) {
                    return;
                }

                throw new \RuntimeException(self::mensajeColision(
                    $clave,
                    'tiene cabecera ERP huérfana en Anita (recm_terminal=ERP). Limpie o renumeré antes de confirmar.'
                ));
            }

            $proveedorCabecera = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6(
                trim((string) ($cabeceraClave->recm_proveedor ?? ''))
            );
            $proveedorAnita = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor);

            if ($proveedorCabecera !== $proveedorAnita) {
                throw new \RuntimeException(self::mensajeColision(
                    $clave,
                    'ya está registrada en Anita para el proveedor '.$proveedorCabecera.'.'
                ));
            }

            throw new \RuntimeException(self::mensajeColision(
                $clave,
                'ya tiene cabecera en Anita (terminal '.$terminal.').'
            ));
        }

        if (self::existeRecepmovProveedor($codigoProveedor, $clave) || self::existeStkmov($clave)) {
            if (RecepcionProveedorAnitaCorrespondenciaSupport::correspondeConRecepcionErp($recepcion)) {
                return;
            }

            throw new \RuntimeException(self::mensajeColision(
                $clave,
                'ya tiene movimientos en Anita sin cabecera coherente de esta recepción.'
            ));
        }
    }

    public static function esMismaRecepcionEnAnita(Recepcion_Proveedor $recepcion, object $cabecera): bool
    {
        if ((int) ($cabecera->recm_documentoid ?? 0) === (int) $recepcion->id) {
            return true;
        }

        return RecepcionProveedorAnitaCorrespondenciaSupport::correspondeConRecepcionErp($recepcion, $cabecera);
    }

    private static function esRecepmaeErpHuerfano(object $cabecera): bool
    {
        $documentoId = (int) ($cabecera->recm_documentoid ?? 0);
        if ($documentoId <= 0) {
            return true;
        }

        return ! DB::table('recepcion_proveedor')->where('id', $documentoId)->exists();
    }

    /**
     * @deprecated use assertConfirmacionSegura
     */
    public static function assertConfirmacionSeguraLegacy(Recepcion_Proveedor $recepcion): void
    {
        $recepcion->loadMissing(['proveedores', 'ordencompras', 'empresas']);

        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $codigoProveedor = RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
        if (! self::numeroOcupadoEnAnita($clave, $codigoProveedor)) {
            return;
        }

        $cabeceraClave = self::leerRecepmaeErpPorClave($codigoProveedor, $clave);
        if ($cabeceraClave !== null && self::esReintentoMismaRecepcion($recepcion, $cabeceraClave)) {
            return;
        }

        if ($cabeceraClave !== null) {
            $proveedorAnita = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor);
            $proveedorCabecera = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6(
                trim((string) ($cabeceraClave->recm_proveedor ?? ''))
            );

            if ($proveedorCabecera !== $proveedorAnita) {
                throw new \RuntimeException(self::mensajeColision(
                    $clave,
                    'ya está registrada en Anita para el proveedor '.$proveedorCabecera.'.'
                ));
            }
        }

        throw new \RuntimeException(self::mensajeColision(
            $clave,
            'ya tiene datos en Anita'.($cabeceraClave === null ? ' (movimientos sin cabecera de esta recepción)' : '').'.'
        ));
    }

    /**
     * Máximo recm_nro COM en Anita para la sucursal (empresa).
     */
    public static function maxNumeroRecepmaeSucursal(int $sucursal): int
    {
        if ($sucursal <= 0) {
            return 0;
        }

        $cfg = config('recepcion_proveedor.anita');
        $tipo = addslashes((string) $cfg['recepcion_tipo']);
        $letra = addslashes(RecepcionProveedorAnitaClaveSupport::letraCom());

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_compras'],
            'tabla' => (string) $cfg['tablas']['recepcion_cabecera'],
            'campos' => 'max(recm_nro) as max_nro',
            'whereArmado' => " WHERE recm_tipo = '{$tipo}' AND recm_letra = '{$letra}'"
                .' AND recm_sucursal = '.(int) $sucursal,
        ]);

        $fila = ApiAnita::primeraFilaLista($raw);

        return max(0, (int) ($fila->max_nro ?? 0));
    }

    /**
     * Máximo recm_nro COM en Anita (todas las sucursales). Secuencia COM única ERP + Anita.
     */
    public static function maxNumeroRecepmaeGlobal(): int
    {
        $cfg = config('recepcion_proveedor.anita');
        $tipo = addslashes((string) $cfg['recepcion_tipo']);
        $letra = addslashes(RecepcionProveedorAnitaClaveSupport::letraCom());

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_compras'],
            'tabla' => (string) $cfg['tablas']['recepcion_cabecera'],
            'campos' => 'max(recm_nro) as max_nro',
            'whereArmado' => " WHERE recm_tipo = '{$tipo}' AND recm_letra = '{$letra}'",
        ]);

        $fila = ApiAnita::primeraFilaLista($raw);

        return max(0, (int) ($fila->max_nro ?? 0));
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function existeRecepmaePorClave(array $clave): bool
    {
        return self::leerRecepmaePorClave($clave) !== null;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function existeRecepmov(array $clave): bool
    {
        return RecepcionProveedorAnitaImportSupport::listarRecepmov(
            (string) $clave['tipo'],
            (string) $clave['letra'],
            (int) $clave['sucursal'],
            (int) $clave['nro']
        ) !== [];
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function existeStkmov(array $clave): bool
    {
        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_ventas'],
            'tabla' => (string) $cfg['tablas']['stock_movimiento'],
            'campos' => 'stkv_nro',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::stkmovCabecera($clave),
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw) !== null;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function leerRecepmaeErpPorClave(string $codigoProveedor, array $clave): ?object
    {
        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_compras'],
            'tabla' => (string) $cfg['tablas']['recepcion_cabecera'],
            'campos' => 'recm_proveedor, recm_tipo_fac, recm_letra_fac, recm_sucursal_fac, recm_nro_fac, recm_estado, recm_documentoid',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmaeSoloErp($codigoProveedor, $clave),
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function leerRecepmaePorClave(array $clave): ?object
    {
        $cfg = config('recepcion_proveedor.anita');
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => (string) $cfg['sistema_compras'],
            'tabla' => (string) $cfg['tablas']['recepcion_cabecera'],
            'campos' => 'recm_proveedor, recm_tipo_fac, recm_letra_fac, recm_sucursal_fac, recm_nro_fac, recm_estado, recm_documentoid',
            'whereArmado' => RecepcionProveedorAnitaWhereSupport::recepmaePorClave($clave),
            'limit' => 'FIRST 1',
        ]);

        return ApiAnita::primeraFilaLista($raw);
    }

    private static function esReintentoMismaRecepcion(Recepcion_Proveedor $recepcion, object $cabecera): bool
    {
        return self::esMismaRecepcionEnAnita($recepcion, $cabecera);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     */
    private static function mensajeColision(array $clave, string $detalle): string
    {
        return 'El número de recepción COM '.(int) $clave['nro']
            .' (sucursal '.(int) $clave['sucursal'].') '.$detalle
            .' No se puede confirmar para no sobrescribir movimientos existentes.'
            .' Revise la numeración o importe la recepción desde Anita.';
    }
}
