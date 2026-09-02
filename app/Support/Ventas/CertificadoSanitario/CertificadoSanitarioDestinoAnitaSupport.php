<?php

namespace App\Support\Ventas\CertificadoSanitario;

use App\ApiAnita;
use App\Models\Configuracion\Localidad;
use App\Models\Ventas\CertificadoSanitarioDestino;
use App\Models\Ventas\Destino;
use App\Models\Ventas\Zonavta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Destino SENASA = maestro `destino` (Anita dest_destino = zonavta.codigo).
 *
 * p-certsan.c / certsan.fc El Bierzo:
 *   se:lugarDestino = dest_localidad (+ dest_provincia)
 *   se:localidad = loc_cod_senasa de la localidad del cliente
 *   Si el cliente no tiene código: fallback ERP = dest_cod_localidad cargado.
 *   Si aún no hay ninguno: buscar loc_cod_senasa por nombre de destino.
 */
final class CertificadoSanitarioDestinoAnitaSupport
{
    /** @var array<int, array{localidad: string, provincia: string, patagonico: bool, senasa: ?int}|null> */
    private static array $cache = [];

    /**
     * dest_destino = zonv_codigo de Anita, nunca zonavta.id del ERP.
     */
    public static function codigoAnitaZona(?int $codigoZona, ?int $zonavtaId): int
    {
        if ($zonavtaId !== null && $zonavtaId > 0) {
            $anita = Zonavta::codigoAnitaDesdeId($zonavtaId);
            if ($anita > 0) {
                return $anita;
            }
        }

        return max(0, (int) ($codigoZona ?? 0));
    }

    /**
     * @return array{localidad: string, provincia: string, patagonico: bool, senasa: ?int}|null
     */
    public static function porCodigoZona(?int $codigoZona, ?int $zonavtaId = null): ?array
    {
        $codigo = self::codigoAnitaZona($codigoZona, $zonavtaId);
        if ($codigo <= 0) {
            return null;
        }
        if (array_key_exists($codigo, self::$cache)) {
            return self::$cache[$codigo];
        }

        $erp = Destino::porCodigo($codigo);
        if ($erp !== null) {
            $dest = $erp->aArraySenasa();
            self::$cache[$codigo] = $dest;

            return $dest;
        }

        $fila = self::buscarEnAnita($codigo);
        $dest = $fila ? self::desdeFilaAnita($fila) : null;
        if ($fila && Destino::tablaLista()) {
            Destino::upsertDesdeFilaAnita($fila);
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

        $senasa = (int) ($row->dest_cod_localidad ?? 0);

        return [
            'localidad' => $localidad,
            'provincia' => self::normalizarTexto($row->dest_provincia ?? ''),
            'patagonico' => strtoupper(substr(trim((string) ($row->dest_patagonico ?? 'N')), 0, 1)) === 'S',
            'senasa' => $senasa > 0 ? $senasa : null,
        ];
    }

    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     * @return Collection<int, PedidoCertificadoLinea>
     */
    public static function enriquecerLineas(Collection $lineas): Collection
    {
        return $lineas->map(function (PedidoCertificadoLinea $l) {
            $dest = self::porCodigoZona($l->codigoZona, $l->zonavtaId);
            if ($dest === null) {
                return $l;
            }

            $senasaCliente = (int) ($l->localidadSenasaCodigo ?? 0);
            $senasaDestino = (int) ($dest['senasa'] ?? 0);

            $nombreLoc = trim((string) ($dest['localidad'] ?? ''));
            $nombreProv = trim((string) ($dest['provincia'] ?? ''));

            return $l->conDestinoZona(
                $nombreLoc !== '' ? $nombreLoc : $l->localidadNombre,
                $nombreProv !== '' ? $nombreProv : $l->provinciaNombre,
                self::senasaLocalidadXml($senasaCliente > 0 ? $senasaCliente : null, $senasaDestino > 0 ? $senasaDestino : null)
            );
        });
    }

    public static function aplicarADestino(CertificadoSanitarioDestino $destino): bool
    {
        $dest = self::porCodigoZona(
            (int) ($destino->codigo_destino ?? 0),
            $destino->zonavta_id ? (int) $destino->zonavta_id : null
        );
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
                'campos' => 'dest_destino, dest_localidad, dest_provincia, dest_pais, dest_patagonico, dest_cod_localidad',
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

    /**
     * se:localidad (certsan.fc Bierzo): manda el código SENASA de la localidad del cliente.
     * Fallback ERP: dest_cod_localidad del maestro destino si está cargado.
     */
    public static function senasaLocalidadXml(?int $senasaCliente, ?int $senasaDestino): ?int
    {
        $cliente = (int) ($senasaCliente ?? 0);
        if ($cliente > 0) {
            return $cliente;
        }
        $destino = (int) ($senasaDestino ?? 0);

        return $destino > 0 ? $destino : null;
    }

    /**
     * loc_cod_senasa del maestro ERP por nombre (último recurso para se:localidad).
     * dest_provincia viene abreviado (BS AS, BS-AS); provincia.nombre es "Buenos Aires".
     */
    public static function codigoSenasaLocalidad(string $localidad, string $provincia): ?int
    {
        $loc = mb_strtoupper(trim($localidad));
        $prov = mb_strtoupper(trim($provincia));
        if ($loc === '') {
            return null;
        }

        $base = Localidad::query()
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [$loc])
            ->whereNotNull('codigosenasa')
            ->where('codigosenasa', '!=', '')
            ->where('codigosenasa', '!=', '0');

        $nombresProv = self::equivalentesProvincia($prov);
        if ($nombresProv !== []) {
            $conProv = (clone $base)->whereHas('provincias', function ($p) use ($nombresProv, $prov) {
                $p->where(function ($q) use ($nombresProv, $prov) {
                    foreach ($nombresProv as $nombre) {
                        $q->orWhereRaw('UPPER(TRIM(nombre)) = ?', [$nombre]);
                    }
                    $compacto = self::compactarProvincia($prov);
                    if ($compacto !== '') {
                        $q->orWhereRaw(
                            'UPPER(REPLACE(REPLACE(REPLACE(TRIM(abreviatura), ".", ""), "-", ""), " ", "")) = ?',
                            [$compacto]
                        );
                    }
                });
            });
            $codigo = $conProv->value('codigosenasa');
            if ($codigo !== null && $codigo !== '') {
                return (int) $codigo;
            }
        }

        $codigos = $base->pluck('codigosenasa')->map(fn ($v) => (int) $v)->filter()->unique()->values();
        if ($codigos->count() === 1) {
            return (int) $codigos->first();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function equivalentesProvincia(string $provincia): array
    {
        $prov = mb_strtoupper(trim($provincia));
        if ($prov === '') {
            return [];
        }

        $compacto = self::compactarProvincia($prov);
        $out = [$prov];
        if (in_array($compacto, ['BSAS', 'BS', 'BAI', 'PBA', 'GBA'], true)
            || in_array($prov, ['BS AS', 'BS-AS', 'BS.AS', 'BS. AS.', 'PCIA BS AS'], true)
        ) {
            $out[] = 'BUENOS AIRES';
            $out[] = 'BS AS';
            $out[] = 'BS-AS';
        }
        if (in_array($compacto, ['CABA', 'CF', 'CAPFED'], true)
            || str_contains($prov, 'CAPITAL FEDERAL')
        ) {
            $out[] = 'CAPITAL FEDERAL';
        }

        return array_values(array_unique($out));
    }

    public static function compactarProvincia(string $provincia): string
    {
        $p = mb_strtoupper(trim($provincia));
        $p = str_replace(['.', '-', '/'], ' ', $p);
        $p = preg_replace('/\s+/', '', $p) ?? $p;

        return $p;
    }
}
