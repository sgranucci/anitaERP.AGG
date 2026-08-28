<?php

namespace App\Support\Ventas;

use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de comprobante y puntos de venta por usuario.
 * Fuente de verdad: columnas en `usuario`. Se graban al elegirlos y al emitir.
 */
final class UsuarioPreferenciaFacturacionSupport
{
    public const COL_TIPO = 'tipotransaccion_venta_id';

    public const COL_PV = 'puntoventa_id';

    public const COL_PV_REMITO = 'puntoventaremito_id';

    /** @var array<string, int|null> */
    private static array $idPorCodigoPv = [];

    /**
     * @return array{tipotransaccion_id: int|null, puntoventa_id: int|null, puntoventaremito_id: int|null}
     */
    public static function leer(?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?: (int) (auth()->id() ?: 0);
        $desdeUsuario = [
            'tipotransaccion_id' => null,
            'puntoventa_id' => null,
            'puntoventaremito_id' => null,
        ];
        $login = null;

        if ($usuarioId > 0 && self::tieneColumnas()) {
            $usuario = Usuario::query()->whereKey($usuarioId)->first([
                'id',
                'usuario',
                self::COL_TIPO,
                self::COL_PV,
                self::COL_PV_REMITO,
            ]);
            if ($usuario) {
                $login = (string) ($usuario->usuario ?? '');
                $desdeUsuario['tipotransaccion_id'] = self::idPositivo($usuario->{self::COL_TIPO} ?? null);
                $desdeUsuario['puntoventa_id'] = self::idPositivo($usuario->{self::COL_PV} ?? null);
                $desdeUsuario['puntoventaremito_id'] = self::idPositivo($usuario->{self::COL_PV_REMITO} ?? null);
            }
        }

        $defaults = self::defaultsParaLogin($login);
        $resuelto = [
            'tipotransaccion_id' => $desdeUsuario['tipotransaccion_id'] ?? $defaults['tipotransaccion_id'],
            'puntoventa_id' => $desdeUsuario['puntoventa_id'] ?? $defaults['puntoventa_id'],
            'puntoventaremito_id' => $desdeUsuario['puntoventaremito_id'] ?? $defaults['puntoventaremito_id'],
        ];

        $faltantes = [];
        foreach (['tipotransaccion_id', 'puntoventa_id', 'puntoventaremito_id'] as $campo) {
            if (! $desdeUsuario[$campo] && $resuelto[$campo]) {
                $faltantes[$campo] = $resuelto[$campo];
            }
        }
        if ($usuarioId > 0 && $faltantes !== []) {
            self::guardar($faltantes, $usuarioId);
        }

        $tipoGuardado = $resuelto['tipotransaccion_id'];
        if ($tipoGuardado && TipoComprobantePreviewSupport::esTipoFceId($tipoGuardado)) {
            $facId = TipoComprobantePreviewSupport::idTipoFactura();
            if ($facId) {
                $resuelto['tipotransaccion_id'] = $facId;
                if ($usuarioId > 0) {
                    self::guardar(['tipotransaccion_id' => $facId], $usuarioId);
                }
            }
        }

        return $resuelto;
    }

    /**
     * Completa columnas vacías con el default del login (no pisa lo ya elegido).
     */
    public static function asegurar(?int $usuarioId = null): void
    {
        self::leer($usuarioId);
    }

    /**
     * @param  array{tipotransaccion_id?: mixed, puntoventa_id?: mixed, puntoventaremito_id?: mixed}  $data
     */
    public static function guardar(array $data, ?int $usuarioId = null): void
    {
        $usuarioId = $usuarioId ?: (int) (auth()->id() ?: 0);
        if ($usuarioId <= 0 || ! self::tieneColumnas()) {
            return;
        }

        $tipo = self::idPositivo($data['tipotransaccion_id'] ?? null);
        $pv = self::idPositivo($data['puntoventa_id'] ?? null);
        $pvRemito = self::idPositivo($data['puntoventaremito_id'] ?? null);

        if ($tipo && TipoComprobantePreviewSupport::esTipoFceId($tipo)) {
            $tipo = TipoComprobantePreviewSupport::idTipoFactura();
        }
        if ($tipo && ! Tipotransaccion::query()->whereKey($tipo)->exists()) {
            $tipo = null;
        }
        if ($pv && ! Puntoventa::query()->whereKey($pv)->exists()) {
            $pv = null;
        }
        if ($pvRemito && ! Puntoventa::query()->whereKey($pvRemito)->exists()) {
            $pvRemito = null;
        }

        $update = [];
        if ($tipo) {
            $update[self::COL_TIPO] = $tipo;
        }
        if ($pv) {
            $update[self::COL_PV] = $pv;
        }
        if ($pvRemito) {
            $update[self::COL_PV_REMITO] = $pvRemito;
        }
        if ($update === []) {
            return;
        }

        $usuario = Usuario::query()->whereKey($usuarioId)->first();
        if (! $usuario) {
            return;
        }

        $cambio = false;
        foreach ($update as $col => $valor) {
            if ((int) ($usuario->{$col} ?? 0) !== (int) $valor) {
                $usuario->{$col} = $valor;
                $cambio = true;
            }
        }
        if ($cambio) {
            $usuario->save();
        }
    }

    /**
     * Asigna FAC + PV según el login (producción vs prueba). Pisa valores previos.
     *
     * @return list<array{id:int, usuario:string, perfil:string, tipotransaccion_id:?int, puntoventa_id:?int, puntoventaremito_id:?int}>
     */
    public static function aplicarAsignacionPorPerfil(bool $ejecutar = false): array
    {
        $filas = [];
        if (! self::tieneColumnas()) {
            return $filas;
        }

        foreach (Usuario::query()->orderBy('usuario')->get(['id', 'usuario', self::COL_TIPO, self::COL_PV, self::COL_PV_REMITO]) as $usuario) {
            $login = (string) ($usuario->usuario ?? '');
            $perfil = self::esUsuarioFacturacionProduccion($login) ? 'produccion' : 'prueba';
            $defaults = self::defaultsParaLogin($login);
            $filas[] = [
                'id' => (int) $usuario->id,
                'usuario' => $login,
                'perfil' => $perfil,
                'antes' => [
                    'tipotransaccion_id' => self::idPositivo($usuario->{self::COL_TIPO} ?? null),
                    'puntoventa_id' => self::idPositivo($usuario->{self::COL_PV} ?? null),
                    'puntoventaremito_id' => self::idPositivo($usuario->{self::COL_PV_REMITO} ?? null),
                ],
                'tipotransaccion_id' => $defaults['tipotransaccion_id'],
                'puntoventa_id' => $defaults['puntoventa_id'],
                'puntoventaremito_id' => $defaults['puntoventaremito_id'],
            ];

            if (! $ejecutar) {
                continue;
            }

            self::guardar([
                'tipotransaccion_id' => $defaults['tipotransaccion_id'],
                'puntoventa_id' => $defaults['puntoventa_id'],
                'puntoventaremito_id' => $defaults['puntoventaremito_id'],
            ], (int) $usuario->id);
        }

        return $filas;
    }

    /**
     * @return array{tipotransaccion_id: int|null, puntoventa_id: int|null, puntoventaremito_id: int|null}
     */
    public static function defaultsParaLogin(?string $login): array
    {
        $tipoId = self::tipoFacturaId();
        if (self::esUsuarioFacturacionProduccion($login)) {
            return [
                'tipotransaccion_id' => $tipoId,
                'puntoventa_id' => self::puntoventaIdPorCodigo(
                    (string) config('facturacion.PUNTOVENTA_FACTURACION_PRODUCCION_CODIGO', '00010')
                ) ?? self::defaultPuntoventaFacturacion(),
                'puntoventaremito_id' => self::puntoventaIdPorCodigo(
                    (string) config('facturacion.PUNTOVENTA_REMITO_PRODUCCION_CODIGO', '00001')
                ) ?? self::defaultPuntoventaRemito(),
            ];
        }

        return [
            'tipotransaccion_id' => $tipoId,
            'puntoventa_id' => self::defaultPuntoventaFacturacion(),
            'puntoventaremito_id' => self::defaultPuntoventaRemito(),
        ];
    }

    public static function esUsuarioFacturacionProduccion(?string $login): bool
    {
        $login = strtolower(trim((string) $login));
        if ($login === '') {
            return false;
        }

        $lista = config('facturacion.PREFERENCIA_FACTURACION_USUARIOS_PRODUCCION', ['clarisad', 'cdacurso']);
        foreach ((array) $lista as $nombre) {
            if (strtolower(trim((string) $nombre)) === $login) {
                return true;
            }
        }

        return false;
    }

    private static function tipoFacturaId(): ?int
    {
        return TipoComprobantePreviewSupport::idTipoFactura();
    }

    private static function defaultPuntoventaFacturacion(): ?int
    {
        $codigo = config('facturacion.PUNTOVENTA_FACTURACION_CODIGO');
        if (is_string($codigo) && trim($codigo) !== '') {
            $id = self::puntoventaIdPorCodigo($codigo);
            if ($id) {
                return $id;
            }
        }

        $v = config('facturacion.PUNTOVENTA_FACTURACION');
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }

        return null;
    }

    private static function defaultPuntoventaRemito(): ?int
    {
        $codigo = config('facturacion.PUNTOVENTA_REMITO_CODIGO');
        if (is_string($codigo) && trim($codigo) !== '') {
            $id = self::puntoventaIdPorCodigo($codigo);
            if ($id) {
                return $id;
            }
        }

        $v = config('facturacion.PUNTOVENTA_REMITO');
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }

        return null;
    }

    private static function puntoventaIdPorCodigo(string $codigo): ?int
    {
        $normalizado = Puntoventa::normalizarCodigoArca($codigo);
        if ($normalizado === null) {
            return null;
        }
        if (array_key_exists($normalizado, self::$idPorCodigoPv)) {
            return self::$idPorCodigoPv[$normalizado];
        }

        $id = self::idPositivo(Puntoventa::query()->where('codigo', $normalizado)->value('id'));
        self::$idPorCodigoPv[$normalizado] = $id;

        return $id;
    }

    private static function tieneColumnas(): bool
    {
        return Schema::hasColumn('usuario', self::COL_TIPO)
            && Schema::hasColumn('usuario', self::COL_PV)
            && Schema::hasColumn('usuario', self::COL_PV_REMITO);
    }

    private static function idPositivo(mixed $valor): ?int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }
}
