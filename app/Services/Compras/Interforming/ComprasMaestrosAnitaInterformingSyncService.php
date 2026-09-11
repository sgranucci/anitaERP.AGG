<?php

namespace App\Services\Compras\Interforming;

use App\ApiAnita;
use App\Models\Compras\Condicionpago;
use App\Models\Compras\Retencionganancia;
use App\Models\Compras\Retencioniva;
use App\Models\Compras\Retencionsuss;
use App\Models\Compras\Tipoempresa;
use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Maestros de compras para INTERFORMING.
 *
 * En Anita Interforming las tablas clásicas de `sistema=compras` (condpmae, condcmae,
 * condemae, retiva, retencion, retsmae, t_comp, …) están vacías o no pobladas.
 * Lo que sí existe:
 * - `ventas.tipoemp` (tipoe_codigo/tipoe_desc) — distinto de AGG (`compras.tipoemp`)
 * - Códigos referenciados en `compras.promae` (cond_pago, retenciones, …)
 *
 * Este sync es exclusivo: no usa los repositories AGG que leen `sistema=compras`.
 */
final class ComprasMaestrosAnitaInterformingSyncService
{
    private const PATH_DEFAULT = '/usr2/interforming';

    /**
     * @return array{
     *   dry_run: bool,
     *   path: string,
     *   maestros: array<string, array<string, mixed>>,
     *   diagnostico: list<string>
     * }
     */
    public function sincronizar(bool $dryRun = true, ?string $pathSistema = null): array
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            throw new RuntimeException('ComprasMaestrosAnitaInterformingSyncService solo aplica a EMPRESA=INTERFORMING.');
        }

        $path = rtrim($pathSistema ?: (string) config('anita.bdd_path', self::PATH_DEFAULT), '/') ?: self::PATH_DEFAULT;
        $api = new ApiAnita();

        $diagnostico = [
            'Anita Interforming: tablas compras (condpmae/condcmae/condemae/retiva/retencion/retsmae/t_comp) vacías.',
            'tipoempresa se lee de ventas.tipoemp (no compras.tipoemp como en AGG).',
            'condicionpago / retenciones: se crean stubs desde códigos distintos usados en promae.',
        ];

        $maestros = [
            'tipoempresa' => $this->syncTipoempresaDesdeVentas($api, $path, $dryRun),
            'condicionpago' => $this->syncCondicionpagoDesdePromae($api, $path, $dryRun),
            'retencioniva' => $this->syncRetencionIvaDesdePromae($api, $path, $dryRun),
            'retencionganancia' => $this->syncRetencionGananciaDesdePromae($api, $path, $dryRun),
            'retencionsuss' => $this->syncRetencionSussDesdePromae($api, $path, $dryRun),
        ];

        return [
            'dry_run' => $dryRun,
            'path' => $path,
            'maestros' => $maestros,
            'diagnostico' => $diagnostico,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncTipoempresaDesdeVentas(ApiAnita $api, string $path, bool $dryRun): array
    {
        $filas = $this->listar($api, $path, 'ventas', 'tipoemp', 'tipoe_codigo, tipoe_desc');
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($filas as $fila) {
            $codigo = ltrim(trim((string) ($fila->tipoe_codigo ?? '')), '0');
            if ($codigo === '') {
                $codigo = '0';
            }
            $nombre = trim((string) ($fila->tipoe_desc ?? ''));
            if ($nombre === '') {
                $nombre = "Tipo empresa Anita {$codigo}";
            }

            if (Tipoempresa::query()->where('codigo', $codigo)->exists()) {
                $omitidos++;

                continue;
            }

            $previstos[] = "{$codigo} — {$nombre}";
            if (! $dryRun) {
                Tipoempresa::query()->create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                ]);
            }
            $insertados++;
        }

        return [
            'fuente' => 'ventas.tipoemp',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => array_slice($previstos, 0, 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncCondicionpagoDesdePromae(ApiAnita $api, string $path, bool $dryRun): array
    {
        $codigos = $this->codigosUnicosPromae($api, $path, 'prom_cond_pago');
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($codigos as $codigo) {
            if (Condicionpago::query()->where('codigo', $codigo)->exists()) {
                $omitidos++;

                continue;
            }
            $nombre = "Condición de pago Anita {$codigo}";
            $previstos[] = "{$codigo} — {$nombre}";
            if (! $dryRun) {
                Condicionpago::query()->create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'aplicacion' => 'C',
                ]);
            }
            $insertados++;
        }

        return [
            'fuente' => 'compras.promae UNIQUE prom_cond_pago (stub)',
            'en_anita' => count($codigos),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => array_slice($previstos, 0, 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncRetencionIvaDesdePromae(ApiAnita $api, string $path, bool $dryRun): array
    {
        $codigos = $this->codigosUnicosPromae($api, $path, 'prom_cod_retiva');
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($codigos as $codigo) {
            if (Retencioniva::query()->where('codigo', $codigo)->exists()) {
                $omitidos++;

                continue;
            }
            $nombre = "Retención IVA Anita {$codigo}";
            $previstos[] = "{$codigo} — {$nombre}";
            if (! $dryRun) {
                Retencioniva::query()->create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'regimen' => '0',
                    'formacalculo' => 'N',
                    'porcentajeretencion' => 0,
                    'minimoimponible' => 0,
                    'baseimponible' => 0,
                    'cantidadperiodoacumula' => 0,
                    'valorunitario' => 0,
                ]);
            }
            $insertados++;
        }

        return [
            'fuente' => 'compras.promae UNIQUE prom_cod_retiva (stub)',
            'en_anita' => count($codigos),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => array_slice($previstos, 0, 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncRetencionGananciaDesdePromae(ApiAnita $api, string $path, bool $dryRun): array
    {
        $codigos = $this->codigosUnicosPromae($api, $path, 'prom_cod_retgan');
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($codigos as $codigo) {
            if (Retencionganancia::query()->where('codigo', $codigo)->exists()) {
                $omitidos++;

                continue;
            }
            $nombre = "Retención ganancias Anita {$codigo}";
            $previstos[] = "{$codigo} — {$nombre}";
            if (! $dryRun) {
                Retencionganancia::query()->create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'regimen' => '0',
                    'formacalculo' => 'N',
                    'porcentajeinscripto' => 0,
                    'porcentajenoinscripto' => 0,
                    'montoexcedente' => 0,
                    'minimoretencion' => 0,
                    'baseimponible' => 0,
                    'cantidadperiodoacumula' => 0,
                    'valorunitario' => 0,
                ]);
            }
            $insertados++;
        }

        return [
            'fuente' => 'compras.promae UNIQUE prom_cod_retgan (stub)',
            'en_anita' => count($codigos),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => array_slice($previstos, 0, 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncRetencionSussDesdePromae(ApiAnita $api, string $path, bool $dryRun): array
    {
        $codigos = $this->codigosUnicosPromae($api, $path, 'prom_cod_ret_suss');
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($codigos as $codigo) {
            if (Retencionsuss::query()->where('codigo', $codigo)->exists()) {
                $omitidos++;

                continue;
            }
            $nombre = "Retención SUSS Anita {$codigo}";
            $previstos[] = "{$codigo} — {$nombre}";
            if (! $dryRun) {
                Retencionsuss::query()->create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'regimen' => '0',
                    'formacalculo' => 'N',
                    'minimoimponible' => 0,
                    'valorretencion' => 0,
                    'minimoretencion' => 0,
                ]);
            }
            $insertados++;
        }

        return [
            'fuente' => 'compras.promae UNIQUE prom_cod_ret_suss (stub)',
            'en_anita' => count($codigos),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => array_slice($previstos, 0, 15),
        ];
    }

    /**
     * @return list<object>
     */
    private function listar(ApiAnita $api, string $path, string $sistema, string $tabla, string $campos): array
    {
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'path_sistema' => $path,
        ]);

        return ApiAnita::decodificarListaFilas($raw);
    }

    /**
     * @return list<string>
     */
    private function codigosUnicosPromae(ApiAnita $api, string $path, string $columna): array
    {
        $filas = $this->listar($api, $path, 'compras', 'promae', "UNIQUE {$columna} as codigo");
        $out = [];
        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->codigo ?? ''));
            if ($codigo === '' || $codigo === '0') {
                continue;
            }
            $out[$codigo] = $codigo;
        }
        $lista = array_values($out);
        natcasesort($lista);

        return array_values($lista);
    }
}
