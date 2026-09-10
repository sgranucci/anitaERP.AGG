<?php

namespace App\Support\Compras\Retencion;

use App\ApiAnita;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Retencionganancia;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Lectura en vivo de retmov Anita para el acumulado RG 830 (período híbrido).
 * Solo suma OPs que no están ya en pagoproveedor_retencion (clave completa).
 */
final class RetencionGananciasAcumuladoAnitaRespaldoSupport
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {
    }

    public static function estaHabilitada(): bool
    {
        return (bool) config('pagoproveedor.acumulado_ganancias_anita_respaldo', true);
    }

    /**
     * @param  array<string, true>  $clavesOcupadas
     * @param  array<int, true>  $certificadosOcupados
     * @return list<array{
     *     pagoproveedor_id: int,
     *     fecha: string|null,
     *     neto: float,
     *     retenido: float,
     *     nro: string|null,
     *     origen: string,
     *     clave: string
     * }>
     */
    public function listarFaltantes(
        int $proveedorId,
        string $desdeIso,
        string $hastaIso,
        ?int $empresaId,
        ?int $retenciongananciaId,
        array $clavesOcupadas,
        array $certificadosOcupados = [],
    ): array {
        if (! self::estaHabilitada() || $proveedorId <= 0) {
            return [];
        }

        $codigoProv = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita(
            (string) (Proveedor::query()->whereKey($proveedorId)->value('codigo') ?? '')
        );
        if ($codigoProv === '') {
            return [];
        }

        $desde = ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($desdeIso);
        $hasta = ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($hastaIso);
        if ($desde <= 0 || $hasta <= 0) {
            return [];
        }

        $codigoRegimen = '';
        if ($retenciongananciaId && $retenciongananciaId > 0) {
            $codigoRegimen = trim((string) (Retencionganancia::query()
                ->whereKey($retenciongananciaId)
                ->value('codigo') ?? ''));
        }

        $empresaAnita = null;
        if ($empresaId && $empresaId > 0) {
            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
            if ($empresaAnita <= 0) {
                $empresaAnita = $empresaId;
            }
        }

        try {
            $filas = $this->listarRetmov($codigoProv, $desde, $hasta, $empresaAnita);
        } catch (\Throwable $e) {
            Log::warning('pagoproveedor.acumulado_ganancias.anita_respaldo', [
                'proveedor_id' => $proveedorId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $out = [];
        $vistos = $clavesOcupadas;
        foreach ($filas as $fila) {
            $parsed = RetencionGananciasAcumuladoAnitaClaveSupport::parsearFilaRetmov((array) $fila);
            if ($parsed === null) {
                continue;
            }
            if ($parsed['fecha'] < $desdeIso || $parsed['fecha'] > $hastaIso) {
                continue;
            }
            if ($codigoRegimen !== ''
                && ! RetencionGananciasAcumuladoAnitaClaveSupport::codigoRetCoincide(
                    $parsed['codigo_ret'],
                    $codigoRegimen
                )
            ) {
                continue;
            }
            if (isset($vistos[$parsed['clave']])) {
                continue;
            }
            $cert = $parsed['nro_certificado'];
            if ($cert > 0 && isset($certificadosOcupados[$cert])) {
                continue;
            }

            $vistos[$parsed['clave']] = true;
            $out[] = [
                'pagoproveedor_id' => 0,
                'fecha' => $parsed['fecha'],
                'neto' => $parsed['neto'],
                'retenido' => $parsed['retenido'],
                'nro' => RetencionGananciasAcumuladoAnitaClaveSupport::etiqueta(
                    $parsed['tipo'],
                    $parsed['letra'],
                    $parsed['sucursal'],
                    $parsed['nro']
                ),
                'origen' => 'anita',
                'clave' => $parsed['clave'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarRetmov(
        string $proveedorCodigo6,
        int $desde,
        int $hasta,
        ?int $empresaAnita,
    ): array {
        $provPad = self::escSql($proveedorCodigo6);
        $provAlt = self::escSql(ltrim($proveedorCodigo6, '0') ?: '0');
        $where = ' WHERE retv_fecha BETWEEN '.$desde.' AND '.$hasta
            .' AND retv_retencion <> 0'
            .' AND (retv_tipo="OPP" OR retv_tipo LIKE "OPP%" OR retv_tipo="AOP" OR retv_tipo LIKE "AOP%")'
            .' AND (retv_proveedor='.$provPad.' OR retv_proveedor='.$provAlt.')';
        if ($empresaAnita !== null && $empresaAnita > 0) {
            $where .= ' AND retv_empresa='.(int) $empresaAnita;
        }

        $raw = (string) $this->api->apiCall([
            'acc' => 'list',
            'sistema' => (string) config('pagoproveedor.anita_sistema_retenciones', 'compras'),
            'tabla' => 'retmov',
            'campos' => 'retv_proveedor,retv_tipo,retv_letra,retv_sucursal,retv_nro,retv_fecha,'
                .'retv_codigo_ret,retv_gravado,retv_pago_actual,retv_retencion,retv_nro_retencion,retv_empresa',
            'whereArmado' => $where,
            'orderBy' => 'retv_fecha, retv_letra, retv_sucursal, retv_nro',
        ]);
        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            Log::warning('pagoproveedor.acumulado_ganancias.anita_respaldo.retmov', ['error' => $msg]);

            return [];
        }

        $out = [];
        foreach (ApiAnita::decodificarListaFilas($raw) as $f) {
            $out[] = (array) $f;
        }

        return $out;
    }

    private static function escSql(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
