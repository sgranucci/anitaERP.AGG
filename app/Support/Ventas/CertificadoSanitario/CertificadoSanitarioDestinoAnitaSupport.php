<?php

namespace App\Support\Ventas\CertificadoSanitario;

use App\ApiAnita;
use App\Models\Configuracion\Localidad;
use App\Models\Ventas\CertificadoSanitarioDestino;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Destino SENASA = tabla Anita `destino` (dest_destino = zonavta.codigo).
 *
 * p-certsan.c una_zona_reparto():
 *   dest_destino = penm_zonavta;
 *   lee(fddest) → dest_localidad / dest_provincia / dest_patagonico
 *
 * certsan.fc CERTS_genera_certificado_web:
 *   se:lugarDestino = dest_localidad (no la localidad del cliente).
 */
final class CertificadoSanitarioDestinoAnitaSupport
{
    /** @var array<int, array{localidad: string, provincia: string, patagonico: bool, senasa: ?int}|null> */
    private static array $cache = [];

    /**
     * @return array{localidad: string, provincia: string, patagonico: bool, senasa: ?int}|null
     */
    public static function porCodigoZona(?int $codigoZona): ?array
    {
        $codigo = (int) $codigoZona;
        if ($codigo <= 0) {
            return null;
        }
        if (array_key_exists($codigo, self::$cache)) {
            return self::$cache[$codigo];
        }

        $fila = self::buscarEnAnita($codigo);
        $dest = $fila ? self::desdeFilaAnita($fila) : null;
        if ($dest !== null) {
            $dest['senasa'] = self::codigoSenasaLocalidad($dest['localidad'], $dest['provincia']);
        }
        self::$cache[$codigo] = $dest;

        return $dest;
    }

    /**
     * @return array{localidad: string, provincia: string, patagonico: bool, senasa: ?int}|null
     */
    public static function desdeFilaAnita(object $row): ?array
    {
        $localidad = self::normalizarTexto($row->dest_localidad ?? '');
        if ($localidad === '') {
            return null;
        }

        return [
            'localidad' => $localidad,
            'provincia' => self::normalizarTexto($row->dest_provincia ?? ''),
            'patagonico' => strtoupper(substr(trim((string) ($row->dest_patagonico ?? 'N')), 0, 1)) === 'S',
            'senasa' => null,
        ];
    }

    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     * @return Collection<int, PedidoCertificadoLinea>
     */
    public static function enriquecerLineas(Collection $lineas): Collection
    {
        return $lineas->map(function (PedidoCertificadoLinea $l) {
            $dest = self::porCodigoZona($l->codigoZona);
            if ($dest === null) {
                return $l;
            }

            return $l->conDestinoZona(
                $dest['localidad'],
                $dest['provincia'],
                $dest['senasa']
            );
        });
    }

    public static function aplicarADestino(CertificadoSanitarioDestino $destino): bool
    {
        $dest = self::porCodigoZona((int) ($destino->codigo_destino ?? 0));
        if ($dest === null) {
            return false;
        }

        $cambio = false;
        if (self::normalizarComparar($destino->localidad) !== self::normalizarComparar($dest['localidad'])) {
            $destino->localidad = $dest['localidad'];
            $cambio = true;
        }
        if (self::normalizarComparar($destino->provincia) !== self::normalizarComparar($dest['provincia'])) {
            $destino->provincia = $dest['provincia'];
            $cambio = true;
        }
        if ((bool) $destino->patagonico !== $dest['patagonico']) {
            $destino->patagonico = $dest['patagonico'];
            $cambio = true;
        }
        if ($cambio) {
            $destino->save();
        }

        return $cambio;
    }

    public static function normalizarComparar(?string $valor): string
    {
        return mb_strtoupper(self::normalizarTexto((string) $valor));
    }

    public static function normalizarTexto(string $valor): string
    {
        return trim(preg_replace('/\s+/', ' ', $valor) ?? $valor);
    }

    private static function buscarEnAnita(int $codigo): ?object
    {
        try {
            $api = new ApiAnita();
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'destino',
                'campos' => 'dest_destino, dest_localidad, dest_provincia, dest_patagonico',
                'whereArmado' => ' WHERE dest_destino = '.$codigo.' ',
            ]);
            $rawStr = is_string($raw) ? $raw : json_encode($raw);
            $err = ApiAnita::extraerMensajeError($rawStr === false ? null : $rawStr);
            if ($err !== null) {
                Log::warning('certificado_sanitario.destino_anita', [
                    'codigo' => $codigo,
                    'mensaje' => $err,
                ]);

                return null;
            }

            return ApiAnita::primeraFilaLista($rawStr === false ? null : $rawStr);
        } catch (\Throwable $e) {
            Log::warning('certificado_sanitario.destino_anita', [
                'codigo' => $codigo,
                'mensaje' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function codigoSenasaLocalidad(string $localidad, string $provincia): ?int
    {
        $loc = mb_strtoupper(trim($localidad));
        $prov = mb_strtoupper(trim($provincia));
        if ($loc === '') {
            return null;
        }

        $query = Localidad::query()
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [$loc])
            ->whereNotNull('codigosenasa')
            ->where('codigosenasa', '!=', '')
            ->where('codigosenasa', '!=', '0');

        if ($prov !== '') {
            $query->whereHas('provincias', function ($p) use ($prov) {
                $p->whereRaw('UPPER(TRIM(nombre)) = ?', [$prov]);
            });
        }

        $codigo = $query->value('codigosenasa');

        return $codigo !== null && $codigo !== '' ? (int) $codigo : null;
    }
}
