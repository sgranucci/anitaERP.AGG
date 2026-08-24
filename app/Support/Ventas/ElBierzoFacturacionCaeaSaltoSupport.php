<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Salto CAE → CAEA en facturación administrativa de El Bierzo
 * (mostrador, pedido, remito). No aplica en AGG ni otros clientes.
 *
 * Replica el reintento de gastronomía: si ARCA CAE falla por transporte
 * (o el failover ya está activo), emite en el PV CAEA pareja (00010 → 00005).
 */
final class ElBierzoFacturacionCaeaSaltoSupport
{
    public const FLAG_INTERNO = '__el_bierzo_caea_salto_interno';

    public static function habilitado(): bool
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return false;
        }

        return filter_var(config('facturacion.SALTO_CAEA_ADMINISTRATIVA', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  callable(array<string, mixed>): mixed  $emitir
     * @param  callable(int): ?array{cae_id:int, caea_id:int, webservice:?string, ya_caea:bool}|null  $resolverIds
     */
    public static function ejecutarConReintento(array $data, callable $emitir, ?callable $resolverIds = null): mixed
    {
        if (! self::habilitado()) {
            return $emitir($data);
        }

        $originalId = (int) ($data['puntoventa_id'] ?? 0);
        $ids = ($resolverIds ?? [self::class, 'resolverIdsDesdeBd'])($originalId);
        if ($ids === null || (int) $ids['caea_id'] <= 0) {
            return $emitir($data);
        }

        $webservice = self::normalizarWebservice($ids['webservice'] ?? null);
        $forzarCaea = $ids['ya_caea']
            || ArcaWsfeEmisionResiliencia::forzarModoCaea($webservice);
        $idUso = $forzarCaea ? (int) $ids['caea_id'] : (int) $ids['cae_id'];
        $usaCaea = $forzarCaea || $idUso === (int) $ids['caea_id'];

        $data['puntoventa_id'] = $idUso;
        $resultado = $emitir($data);

        $mensajeError = self::mensajeError($resultado);
        if ($mensajeError !== null && ArcaWsfeEmisionResiliencia::esErrorTransporte($mensajeError)) {
            ArcaWsfeEmisionResiliencia::notificarFallaTransporteEmision(
                $mensajeError,
                $webservice,
                ['probe' => 'facturacion.administrativa.el_bierzo'],
            );
        }

        if (
            $mensajeError === null
            || (int) $ids['caea_id'] === (int) $ids['cae_id']
            || ! ArcaWsfeEmisionResiliencia::debeReintentarTransaccionConCaea(
                $mensajeError,
                $usaCaea,
                $webservice,
            )
        ) {
            return $resultado;
        }

        Log::warning('facturacion.el_bierzo.reintento_caea', [
            'puntoventa_cae_id' => $ids['cae_id'],
            'puntoventa_caea_id' => $ids['caea_id'],
            'msg' => $mensajeError,
        ]);

        $data['puntoventa_id'] = (int) $ids['caea_id'];
        $resultadoCaea = $emitir($data);

        return self::anexarAvisoCaea($resultadoCaea, (int) $ids['caea_id']);
    }

    /**
     * @return array{cae_id:int, caea_id:int, webservice:?string, ya_caea:bool}|null
     */
    public static function resolverIdsDesdeBd(int $puntoventaId): ?array
    {
        if ($puntoventaId <= 0) {
            return null;
        }

        $pv = Puntoventa::query()->find($puntoventaId);
        if ($pv === null) {
            return null;
        }

        $modo = (string) ($pv->modofacturacion ?? '');
        $ws = self::normalizarWebservice($pv->webservice ?? null);

        if ($modo === 'A') {
            return [
                'cae_id' => $puntoventaId,
                'caea_id' => $puntoventaId,
                'webservice' => $ws,
                'ya_caea' => true,
            ];
        }

        if (! in_array($modo, ['C', 'E'], true)) {
            return null;
        }

        $codigoCaea = self::codigoCaeaParaCodigoCae((string) $pv->codigo);
        if ($codigoCaea === null) {
            return null;
        }

        $caea = Puntoventa::query()
            ->where('empresa_id', $pv->empresa_id)
            ->where('modofacturacion', 'A')
            ->where('codigo', $codigoCaea)
            ->first();

        if ($caea === null) {
            return null;
        }

        return [
            'cae_id' => $puntoventaId,
            'caea_id' => (int) $caea->id,
            'webservice' => $ws,
            'ya_caea' => false,
        ];
    }

    public static function codigoCaeaParaCodigoCae(string $codigoCae): ?string
    {
        $normalizado = Puntoventa::normalizarCodigoArca($codigoCae) ?? trim($codigoCae);
        $mapa = config('facturacion.SALTO_CAEA_MAPEO_CODIGOS', []);
        if (! is_array($mapa)) {
            return null;
        }

        foreach ($mapa as $desde => $hasta) {
            $desdeN = Puntoventa::normalizarCodigoArca((string) $desde) ?? (string) $desde;
            if ($desdeN === $normalizado) {
                return Puntoventa::normalizarCodigoArca((string) $hasta) ?? (string) $hasta;
            }
        }

        return null;
    }

    public static function mensajeError(mixed $resultado): ?string
    {
        if (is_string($resultado)) {
            $t = trim($resultado);

            return $t !== '' ? $t : null;
        }

        if (! is_array($resultado)) {
            return null;
        }

        $err = $resultado['error'] ?? null;
        if (! is_string($err)) {
            return null;
        }
        $t = trim($err);

        return $t !== '' ? $t : null;
    }

    public static function normalizarWebservice(?string $webservice): ?string
    {
        $w = strtolower(trim((string) $webservice));
        if ($w === '') {
            return null;
        }
        if (in_array($w, ['wsmtxca', 'mtxca', 'mtxsca'], true)) {
            return 'wsmtxca';
        }

        return $w;
    }

    /**
     * @param  mixed  $resultado
     * @return mixed
     */
    public static function anexarAvisoCaea(mixed $resultado, int $puntoventaCaeaId)
    {
        if (! is_array($resultado) || self::mensajeError($resultado) !== null) {
            return $resultado;
        }

        $resultado['aviso_caea'] = 'Comprobante emitido con CAEA (PV id '.$puntoventaCaeaId.') por contingencia ARCA.';
        $resultado['puntoventa_caea_id'] = $puntoventaCaeaId;

        return $resultado;
    }
}
