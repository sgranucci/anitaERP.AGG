<?php

namespace App\Services\Compras\Ferli;

use App\ApiAnita;
use App\Models\Compras\Columna_Ivacompra;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Concepto_Ivacompra_Empresa;
use App\Models\Compras\Condicioncompra;
use App\Models\Compras\Condicionentrega;
use App\Models\Compras\Condicionpago;
use App\Models\Compras\Retencionganancia;
use App\Models\Compras\Retencioniva;
use App\Models\Compras\Retencionsuss;
use App\Models\Compras\Tipoempresa;
use App\Models\Compras\Tiposervicio_Proveedor;
use App\Models\Contable\Cuentacontable;
use App\Services\Compras\ComprasIvaMaestrosAnitaImportService;
use App\Support\Compras\ConceptoIvaAnitaEsquemaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Maestros de compras para Calzados Ferli.
 *
 * Anita /usr2/ferli (verificado 11/sep/2026):
 * - tipoemp vive en ventas (compras.tipoemp vacío).
 * - condcmae / condemae / t_comp / colivacomp / provibr vacíos.
 * - conccomp sin tipo/alícuota/retiene IIBB y sin concciva.
 * - retiva / retencion / retsmae / condpmae sí tienen filas.
 * - FKs de promae (cond. compra/entrega, tipo empresa 0) se cubren con stubs.
 */
final class ComprasMaestrosAnitaFerliSyncService
{
    private const PATH_DEFAULT = '/usr2/ferli';

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
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('ComprasMaestrosAnitaFerliSyncService solo aplica a EMPRESA=CALZADOS FERLI.');
        }

        $path = rtrim($pathSistema ?: (string) config('anita.bdd_path', self::PATH_DEFAULT), '/') ?: self::PATH_DEFAULT;
        $api = new ApiAnita();

        $diagnostico = [
            'Anita Ferli: promae 50 columnas (hasta prom_concepto); sin promadic/proexcl/propago/listapmae.',
            'tipoempresa se lee de ventas.tipoemp; se agrega stub codigo 0 referenciado en promae.',
            'condicioncompra / condicionentrega: stubs desde códigos de promae (condcmae/condemae vacíos).',
            'concepto IVA: conccomp sin concc_tipo_conc/alicuota/retiene_ibr y sin concciva.',
            'colivacomp / t_comp / cont_comp: via ComprasIvaMaestrosAnitaImportService.',
            'retsmae UNLOAD devuelve filas corruptas (códigos 808464439, valores científicos): SUSS se stubbea desde promae.',
            'tiposervicio_proveedor es maestro ERP (no Anita): se siembra Bienes/Servicios/Eventual si falta.',
        ];

        $iva = app(ComprasIvaMaestrosAnitaImportService::class)
            ->{$dryRun ? 'analizar' : 'ejecutar'}($path);

        $maestros = [
            'tiposervicio_proveedor' => $this->syncTiposervicioProveedor($dryRun),
            'tipoempresa' => $this->syncTipoempresa($api, $path, $dryRun),
            'condicionpago' => $this->syncCondicionpago($api, $path, $dryRun),
            'condicioncompra' => $this->syncStubDesdePromae(
                $api,
                $path,
                $dryRun,
                'prom_cond_compra',
                Condicioncompra::class,
                'Condición de compra Anita'
            ),
            'condicionentrega' => $this->syncStubCondicionentrega($api, $path, $dryRun),
            'retencioniva' => $this->syncRetencioniva($api, $path, $dryRun),
            'retencionganancia' => $this->syncYaPresente('retencion', Retencionganancia::class, $api, $path, 'ret_codigo'),
            'retencionsuss' => $this->syncRetencionsussDesdePromae($api, $path, $dryRun),
            'columna_ivacompra' => $this->adaptarStatsIva($iva['columna_ivacompra']),
            'concepto_ivacompra' => $this->adaptarStatsIva($iva['concepto_ivacompra']),
            'tipotransaccion_compra' => $this->adaptarStatsIva($iva['tipotransaccion_compra']),
        ];

        foreach ($iva['errores'] as $err) {
            $diagnostico[] = $err;
        }

        return [
            'dry_run' => $dryRun,
            'path' => $path,
            'maestros' => $maestros,
            'diagnostico' => $diagnostico,
        ];
    }

    /**
     * Adapta stats del import IVA al formato de tabla del comando Ferli.
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function adaptarStatsIva(array $stats): array
    {
        $crear = (int) ($stats['crear'] ?? 0);
        $actualizar = (int) ($stats['actualizar'] ?? 0);

        return [
            'fuente' => (string) ($stats['fuente'] ?? ''),
            'en_anita' => (int) ($stats['en_anita'] ?? 0),
            'insertados' => $crear + $actualizar,
            'omitidos' => (int) ($stats['omitidos'] ?? 0),
            'muestra' => $stats['muestra'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncTiposervicioProveedor(bool $dryRun): array
    {
        $semilla = [
            ['nombre' => 'Bienes', 'controla_unicidad_cuit' => Tiposervicio_Proveedor::UNICIDAD_CUIT_CONTROLA],
            ['nombre' => 'Servicios', 'controla_unicidad_cuit' => Tiposervicio_Proveedor::UNICIDAD_CUIT_CONTROLA],
            ['nombre' => 'Eventual', 'controla_unicidad_cuit' => Tiposervicio_Proveedor::UNICIDAD_CUIT_CONTROLA],
        ];
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($semilla as $fila) {
            if (Tiposervicio_Proveedor::query()->where('nombre', $fila['nombre'])->exists()) {
                $omitidos++;

                continue;
            }
            $previstos[] = $fila['nombre'];
            if (! $dryRun) {
                Tiposervicio_Proveedor::query()->create($fila);
            }
            $insertados++;
        }

        return [
            'fuente' => 'ERP (semilla Ferli, no Anita)',
            'en_anita' => 0,
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => $previstos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncTipoempresa(ApiAnita $api, string $path, bool $dryRun): array
    {
        $filas = $this->listar($api, $path, 'ventas', 'tipoemp', 'tipoe_codigo, tipoe_desc');
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->tipoe_codigo ?? ''));
            if ($codigo === '') {
                continue;
            }
            $nombre = trim((string) ($fila->tipoe_desc ?? ''));
            if ($nombre === '') {
                $nombre = "Tipo empresa Anita {$codigo}";
            }

            if ($this->existeCodigo(Tipoempresa::class, $codigo)) {
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

        $stub = $this->altaStubSiFalta(Tipoempresa::class, '0', 'Tipo empresa Anita 0', $dryRun, $previstos);
        $insertados += $stub;

        return [
            'fuente' => 'ventas.tipoemp + stub promae.prom_tipo_empresa=0',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => array_slice($previstos, 0, 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncCondicionpago(ApiAnita $api, string $path, bool $dryRun): array
    {
        $filas = $this->listar($api, $path, 'compras', 'condpmae', 'conpm_codigo, conpm_desc');
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->conpm_codigo ?? ''));
            if ($codigo === '') {
                continue;
            }
            if ($this->existeCodigo(Condicionpago::class, $codigo)) {
                $omitidos++;

                continue;
            }
            $nombre = trim((string) ($fila->conpm_desc ?? '')) ?: "Condición de pago Anita {$codigo}";
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

        $stub = $this->altaStubSiFalta(Condicionpago::class, '0', 'Condición de pago Anita 0', $dryRun, $previstos, [
            'aplicacion' => 'C',
        ]);
        $insertados += $stub;

        return [
            'fuente' => 'compras.condpmae + stub promae.prom_cond_pago=0',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => array_slice($previstos, 0, 15),
        ];
    }

    /**
     * @param  class-string  $modelo
     * @return array<string, mixed>
     */
    private function syncStubDesdePromae(
        ApiAnita $api,
        string $path,
        bool $dryRun,
        string $columnaPromae,
        string $modelo,
        string $nombreBase,
    ): array {
        $codigos = $this->codigosUnicosPromae($api, $path, $columnaPromae);
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($codigos as $codigo) {
            if ($this->existeCodigo($modelo, $codigo)) {
                $omitidos++;

                continue;
            }
            $nombre = "{$nombreBase} {$codigo}";
            $previstos[] = "{$codigo} — {$nombre}";
            if (! $dryRun) {
                $modelo::query()->create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                ]);
            }
            $insertados++;
        }

        return [
            'fuente' => "compras.promae UNIQUE {$columnaPromae} (stub)",
            'en_anita' => count($codigos),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => array_slice($previstos, 0, 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncStubCondicionentrega(ApiAnita $api, string $path, bool $dryRun): array
    {
        $codigos = $this->codigosUnicosPromae($api, $path, 'prom_cond_entrega');
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($codigos as $codigo) {
            if ($this->existeCodigo(Condicionentrega::class, $codigo)) {
                $omitidos++;

                continue;
            }
            $nombre = "Condición de entrega Anita {$codigo}";
            $previstos[] = "{$codigo} — {$nombre}";
            if (! $dryRun) {
                Condicionentrega::query()->create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'dias' => 0,
                ]);
            }
            $insertados++;
        }

        return [
            'fuente' => 'compras.promae UNIQUE prom_cond_entrega (stub; condemae vacío)',
            'en_anita' => count($codigos),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => $previstos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncRetencioniva(ApiAnita $api, string $path, bool $dryRun): array
    {
        $filas = $this->listar($api, $path, 'compras', 'retiva', 'reti_codigo, reti_desc, reti_porcentaje, reti_minimo, reti_cod_regimen, reti_aplica');
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->reti_codigo ?? ''));
            if ($codigo === '') {
                continue;
            }
            if ($this->existeCodigo(Retencioniva::class, $codigo)) {
                $omitidos++;

                continue;
            }
            $nombre = trim((string) ($fila->reti_desc ?? '')) ?: "Retención IVA Anita {$codigo}";
            $previstos[] = "{$codigo} — {$nombre}";
            if (! $dryRun) {
                Retencioniva::query()->create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'regimen' => (string) ($fila->reti_cod_regimen ?? '0'),
                    'formacalculo' => (string) ($fila->reti_aplica ?? 'N'),
                    'porcentajeretencion' => (float) ($fila->reti_porcentaje ?? 0),
                    'minimoimponible' => (float) ($fila->reti_minimo ?? 0),
                    'baseimponible' => 0,
                    'cantidadperiodoacumula' => 0,
                    'valorunitario' => 0,
                ]);
            }
            $insertados++;
        }

        $stub = $this->altaStubSiFalta(Retencioniva::class, '0', 'Retención IVA Anita 0', $dryRun, $previstos, [
            'regimen' => '0',
            'formacalculo' => 'N',
            'porcentajeretencion' => 0,
            'minimoimponible' => 0,
            'baseimponible' => 0,
            'cantidadperiodoacumula' => 0,
            'valorunitario' => 0,
        ]);
        $insertados += $stub;

        return [
            'fuente' => 'compras.retiva (sin reti_base/cant_per/valor_unit) + stub 0',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => $previstos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncRetencionsussDesdePromae(ApiAnita $api, string $path, bool $dryRun): array
    {
        $codigos = $this->codigosUnicosPromae($api, $path, 'prom_cod_ret_suss');
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];

        foreach ($codigos as $codigo) {
            if ($this->existeCodigo(Retencionsuss::class, $codigo)) {
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
                    'valorretencion' => 0,
                    'minimoimponible' => 0,
                    'minimoretencion' => 0,
                ]);
            }
            $insertados++;
        }

        return [
            'fuente' => 'compras.promae UNIQUE prom_cod_ret_suss (stub; retsmae UNLOAD ilegible)',
            'en_anita' => count($codigos),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => $previstos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncConceptoIvacompra(ApiAnita $api, string $path, bool $dryRun): array
    {
        $filas = $this->listar($api, $path, 'compras', 'conccomp', ConceptoIvaAnitaEsquemaSupport::sqlCamposCabecera());
        $insertados = 0;
        $omitidos = 0;
        $previstos = [];
        $empresaIdDefault = (int) (Cuentacontable::query()->orderBy('id')->value('empresa_id') ?? 1);

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->concc_concepto ?? ''));
            if ($codigo === '') {
                continue;
            }
            if ($this->existeCodigo(Concepto_Ivacompra::class, $codigo)) {
                $omitidos++;

                continue;
            }

            $nombre = trim((string) ($fila->concc_desc ?? '')) ?: "Concepto IVA Anita {$codigo}";
            $contenido = (string) ($fila->concc_contenido ?? '');
            $retieneGanancia = $contenido === 'C' ? 'S' : 'N';
            $ctaDebe = $this->cuentaIdPorCodigo((string) ($fila->concc_cta_debe ?? ''));
            $ctaHaber = $this->cuentaIdPorCodigo((string) ($fila->concc_cta_haber ?? ''));
            $columna = Columna_Ivacompra::query()
                ->where('numerocolumna', (string) ($fila->concc_columna_sub ?? ''))
                ->first();

            $previstos[] = "{$codigo} — {$nombre}";
            if (! $dryRun) {
                $concepto = Concepto_Ivacompra::query()->create([
                    'nombre' => $nombre,
                    'codigo' => $codigo,
                    'formula' => (string) ($fila->concc_formula ?? ''),
                    'columna_ivacompra_id' => $columna?->id,
                    'empresa_id' => null,
                    'cuentacontabledebe_id' => $ctaDebe,
                    'cuentacontablehaber_id' => $ctaHaber,
                    'tipoconcepto' => (string) ($fila->concc_tipo_conc ?? 'N'),
                    'retieneganancia' => $retieneGanancia,
                    'retieneIIBB' => (string) ($fila->concc_retiene_ibr ?? 'N'),
                    'provincia_id' => null,
                    'impuesto_id' => null,
                ]);

                if ($ctaDebe || $ctaHaber) {
                    $empresaIdLinea = $this->empresaIdDeCuenta($ctaDebe) ?: $this->empresaIdDeCuenta($ctaHaber) ?: $empresaIdDefault;
                    if ($empresaIdLinea <= 0) {
                        $empresaIdLinea = 1;
                    }
                    Concepto_Ivacompra_Empresa::query()->create([
                        'concepto_ivacompra_id' => $concepto->id,
                        'empresa_id' => $empresaIdLinea,
                        'cuentacontabledebe_id' => $ctaDebe,
                        'cuentacontablehaber_id' => $ctaHaber,
                    ]);
                }
            }
            $insertados++;
        }

        return [
            'fuente' => 'compras.conccomp (esquema Ferli, sin concciva)',
            'en_anita' => count($filas),
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'muestra' => array_slice($previstos, 0, 15),
        ];
    }

    /**
     * @param  class-string  $modelo
     * @return array<string, mixed>
     */
    private function syncYaPresente(string $tablaAnita, string $modelo, ApiAnita $api, string $path, string $campo): array
    {
        $filas = $this->listar($api, $path, 'compras', $tablaAnita, $campo);
        $enErp = (int) $modelo::query()->count();

        return [
            'fuente' => "compras.{$tablaAnita} (ya en ERP)",
            'en_anita' => count($filas),
            'insertados' => 0,
            'omitidos' => $enErp,
            'muestra' => [],
        ];
    }

    /**
     * @param  class-string  $modelo
     * @return array<string, mixed>
     */
    private function syncVacio(string $tablaAnita, string $modelo, ApiAnita $api, string $path, string $campo): array
    {
        $filas = $this->listar($api, $path, 'compras', $tablaAnita, $campo);

        return [
            'fuente' => "compras.{$tablaAnita}",
            'en_anita' => count($filas),
            'insertados' => 0,
            'omitidos' => (int) $modelo::query()->count(),
            'muestra' => count($filas) === 0 ? ['Tabla Anita vacía'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tablaAnitaVacia(ApiAnita $api, string $path, string $tabla, string $campo): array
    {
        $filas = $this->listar($api, $path, 'compras', $tabla, $campo);

        return [
            'fuente' => "compras.{$tabla}",
            'en_anita' => count($filas),
            'insertados' => 0,
            'omitidos' => 0,
            'muestra' => ['Tabla Anita vacía: no hay tipos de transacción de compra para importar'],
        ];
    }

    /**
     * @param  class-string  $modelo
     * @param  list<string>  $previstos
     * @param  array<string, mixed>  $extra
     */
    private function altaStubSiFalta(string $modelo, string $codigo, string $nombre, bool $dryRun, array &$previstos, array $extra = []): int
    {
        if ($this->existeCodigo($modelo, $codigo)) {
            return 0;
        }
        $previstos[] = "{$codigo} — {$nombre}";
        if (! $dryRun) {
            $modelo::query()->create(array_merge([
                'codigo' => $codigo,
                'nombre' => $nombre,
            ], $extra));
        }

        return 1;
    }

    /**
     * @param  class-string  $modelo
     */
    private function existeCodigo(string $modelo, string $codigo): bool
    {
        return $modelo::query()->where('codigo', $codigo)->exists()
            || ($codigo !== ltrim($codigo, '0') && $modelo::query()->where('codigo', ltrim($codigo, '0'))->exists());
    }

    private function cuentaIdPorCodigo(string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        $cuenta = Cuentacontable::query()->where('codigo', $codigo)->first();

        return $cuenta ? (int) $cuenta->id : null;
    }

    private function empresaIdDeCuenta(?int $cuentaId): int
    {
        if (! $cuentaId) {
            return 0;
        }

        return (int) (Cuentacontable::query()->whereKey($cuentaId)->value('empresa_id') ?? 0);
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
        $parsed = ApiAnita::parsearRespuestaLista($raw);
        if ($parsed['error_lectura'] !== null) {
            throw new RuntimeException("Anita {$sistema}.{$tabla}: {$parsed['error_lectura']}");
        }

        return $parsed['filas'];
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
            if ($codigo === '') {
                continue;
            }
            $out[$codigo] = $codigo;
        }
        $lista = array_values($out);
        natcasesort($lista);

        return array_values($lista);
    }
}
