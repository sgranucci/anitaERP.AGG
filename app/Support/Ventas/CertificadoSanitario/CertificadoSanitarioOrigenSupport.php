<?php

namespace App\Support\Ventas\CertificadoSanitario;

use App\ApiAnita;
use App\Models\Ventas\CertificadoSanitarioArticulo;
use Illuminate\Support\Facades\Log;

/**
 * Certificado de origen SENASA (amparo de terceros).
 *
 * Port de certsan.fc + carga_certart.fc: si el producto pertenece a otro
 * establecimiento (prefijo ≠ SENASA_ESTABLECIMIENTO), el XML debe llevar
 * <se:certificadoDeOrigen> (ej. 3353-A-09459). En Anita vive en certart.certa_cert_terc.
 */
final class CertificadoSanitarioOrigenSupport
{
    /** @var array<string, string> sku normalizado => amparo */
    private static array $cacheSku = [];

    public static function establecimientoPropio(): string
    {
        return trim((string) config('senasa.establecimiento', ''));
    }

    public static function esProductoTercero(?string $prefijo): bool
    {
        $prefijo = trim((string) $prefijo);
        if ($prefijo === '' || $prefijo === '0') {
            return false;
        }

        return $prefijo !== self::establecimientoPropio();
    }

    /**
     * Anita: si el amparo es tipo "A-09459" antepone nro_establ → "3353-A-09459".
     */
    public static function normalizar(string $amparo, ?string $prefijo = null): string
    {
        $amparo = trim($amparo);
        if ($amparo === '' || $amparo === '0') {
            return '';
        }

        $prefijo = trim((string) $prefijo);
        if (
            $prefijo !== ''
            && isset($amparo[1])
            && $amparo[1] === '-'
            && ! str_starts_with($amparo, $prefijo.'-')
        ) {
            return $prefijo.'-'.$amparo;
        }

        return $amparo;
    }

    public static function skuAnita(string $sku): string
    {
        $sku = trim($sku);
        $sinCeros = ltrim($sku, '0');
        if ($sinCeros === '') {
            $sinCeros = '0';
        }

        return str_pad($sinCeros, 13, '0', STR_PAD_LEFT);
    }

    public static function skuClave(string $sku): string
    {
        $sin = ltrim(trim($sku), '0');

        return $sin === '' ? '0' : $sin;
    }

    /**
     * Resuelve amparo para un SKU de tercero. Cachea por request.
     */
    public static function resolverParaSku(string $sku, string $prefijo): string
    {
        $clave = self::skuClave($sku);
        if ($clave !== '' && array_key_exists($clave, self::$cacheSku)) {
            return self::$cacheSku[$clave];
        }

        $valor = self::buscarEnAnita($sku, $prefijo);
        if ($valor === '') {
            $valor = self::buscarEnErp($sku, $prefijo);
        }

        $valor = self::normalizar($valor, $prefijo);
        if ($clave !== '') {
            self::$cacheSku[$clave] = $valor;
        }

        return $valor;
    }

    /**
     * Completa certificadoOrigen en líneas de terceros (mutación vía reconstrucción).
     *
     * @param  \Illuminate\Support\Collection<int, PedidoCertificadoLinea>  $lineas
     * @return \Illuminate\Support\Collection<int, PedidoCertificadoLinea>
     */
    public static function enriquecerLineas($lineas)
    {
        return $lineas->map(function (PedidoCertificadoLinea $l) {
            if (! self::esProductoTercero($l->prefijoSenasa)) {
                return $l;
            }
            if (trim($l->certificadoOrigen) !== '') {
                return $l;
            }

            $origen = self::resolverParaSku($l->sku, $l->prefijoSenasa);

            return $l->conCertificadoOrigen($origen);
        });
    }

    /**
     * SKUs de tercero que en Anita/ERP ya usaron amparo y esta línea no lo trae.
     * No incluye jamones 9066: certa_cert_terc vacío; el elaborador va en codigoProducto.
     *
     * @param  \Illuminate\Support\Collection<int, PedidoCertificadoLinea>  $lineas
     * @return list<string>
     */
    public static function skusTerceroConAmparoFaltante($lineas): array
    {
        $faltan = [];
        foreach ($lineas as $l) {
            if (! self::esProductoTercero($l->prefijoSenasa)) {
                continue;
            }
            if (trim($l->certificadoOrigen) !== '') {
                continue;
            }
            $historico = self::resolverParaSku($l->sku, $l->prefijoSenasa);
            if ($historico === '') {
                continue;
            }
            $faltan[] = $l->sku.' ('.$l->prefijoSenasa.'-'.$l->registroSenasa.')';
        }

        return array_values(array_unique($faltan));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PedidoCertificadoLinea>  $lineas
     * @return list<string> SKUs de tercero sin amparo
     */
    public static function skusTerceroSinOrigen($lineas): array
    {
        return self::skusTerceroConAmparoFaltante($lineas);
    }

    private static function buscarEnErp(string $sku, string $prefijo): string
    {
        $clave = self::skuClave($sku);
        $row = CertificadoSanitarioArticulo::query()
            ->select('certificado_sanitario_articulo.cert_tercero')
            ->join(
                'certificado_sanitario',
                'certificado_sanitario.id',
                '=',
                'certificado_sanitario_articulo.certificado_sanitario_id'
            )
            ->whereNotNull('certificado_sanitario_articulo.cert_tercero')
            ->where('certificado_sanitario_articulo.cert_tercero', '!=', '')
            ->where(function ($q) use ($sku, $clave) {
                $q->where('certificado_sanitario_articulo.sku', $sku);
                if ($clave !== $sku) {
                    $q->orWhere('certificado_sanitario_articulo.sku', $clave);
                }
            })
            ->orderByDesc('certificado_sanitario.fecha')
            ->orderByDesc('certificado_sanitario.id')
            ->value('cert_tercero');

        $valor = self::normalizar((string) ($row ?? ''), $prefijo);
        if ($valor !== '' && ($prefijo === '' || str_starts_with($valor, $prefijo.'-') || ! str_contains($valor, '-'))) {
            return $valor;
        }

        return $valor;
    }

    private static function buscarEnAnita(string $sku, string $prefijo): string
    {
        try {
            $dias = max(7, (int) config('senasa.origen_dias_busqueda', 45));
            $desde = (int) now()->subDays($dias)->format('Ymd');
            $api = new ApiAnita();
            $sistema = (string) config('senasa.numeracion_anita.sistema_ventas', 'ventas');

            $certsRaw = $api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => 'certsan',
                'campos' => 'certs_certificado,certs_fecha,certs_serie',
                'whereArmado' => ' WHERE certs_fecha >= '.$desde
                    .' AND certs_fecha <= '.(int) now()->format('Ymd').' ',
                'limit' => 'FIRST 300',
            ]);
            $certs = ApiAnita::decodificarListaFilas($certsRaw);
            if ($certs === []) {
                return '';
            }

            /** @var array<int, array{fecha: int, serie: string}> $metaPorId */
            $metaPorId = [];
            $hoyAnita = (int) now()->format('Ymd');
            foreach ($certs as $row) {
                $id = (int) ($row->certs_certificado ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $fecha = (int) ($row->certs_fecha ?? 0);
                // Anita usa 99999999 / ceros como placeholder; no son fechas reales.
                if ($fecha < $desde || $fecha > $hoyAnita) {
                    continue;
                }
                $serie = trim((string) ($row->certs_serie ?? ''));
                if (! isset($metaPorId[$id]) || $fecha > $metaPorId[$id]['fecha']) {
                    $metaPorId[$id] = ['fecha' => $fecha, 'serie' => $serie];
                }
            }
            if ($metaPorId === []) {
                return '';
            }

            uasort($metaPorId, static function (array $a, array $b): int {
                $porFecha = $b['fecha'] <=> $a['fecha'];
                if ($porFecha !== 0) {
                    return $porFecha;
                }
                // Ante misma fecha, preferir serie E (vigente Bierzo) sobre A/B/C/D históricas.
                $rank = static fn (string $s): int => $s === 'E' ? 2 : ($s === '' ? 0 : 1);

                return $rank($b['serie']) <=> $rank($a['serie']);
            });
            $ids = array_slice(array_keys($metaPorId), 0, 120);
            if ($ids === []) {
                return '';
            }

            $skuAnita = self::skuAnita($sku);
            $inList = implode(',', $ids);
            $where = " WHERE certa_articulo = '".str_replace("'", "''", $skuAnita)."'"
                .' AND certa_certificado IN ('.$inList.')'
                ." AND certa_cert_terc[1] <> ' ' ";

            $artsRaw = $api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => 'certart',
                'campos' => 'certa_certificado,certa_articulo,certa_cert_terc',
                'whereArmado' => $where,
                'limit' => 'FIRST 120',
            ]);
            $filas = ApiAnita::decodificarListaFilas($artsRaw);
            if ($filas === []) {
                return '';
            }

            $candidatos = [];
            foreach ($filas as $fila) {
                $amparo = self::normalizar((string) ($fila->certa_cert_terc ?? ''), $prefijo);
                if ($amparo === '') {
                    continue;
                }
                if ($prefijo !== '' && str_contains($amparo, '-') && ! str_starts_with($amparo, $prefijo.'-')) {
                    continue;
                }
                $nro = (int) ($fila->certa_certificado ?? 0);
                $fecha = $metaPorId[$nro]['fecha'] ?? 0;
                $candidatos[] = [
                    'fecha' => $fecha,
                    'nro' => $nro,
                    'amparo' => $amparo,
                ];
            }
            if ($candidatos === []) {
                return '';
            }

            usort($candidatos, static function (array $a, array $b): int {
                return ($b['fecha'] <=> $a['fecha']) ?: ($b['nro'] <=> $a['nro']);
            });

            return $candidatos[0]['amparo'];
        } catch (\Throwable $e) {
            Log::warning('CertificadoSanitarioOrigenSupport: no se pudo leer amparo Anita', [
                'sku' => $sku,
                'prefijo' => $prefijo,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
