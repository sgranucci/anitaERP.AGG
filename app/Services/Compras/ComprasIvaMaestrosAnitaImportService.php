<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Columna_Ivacompra;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Concepto_Ivacompra_Condicioniva;
use App\Models\Compras\Concepto_Ivacompra_Empresa;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Compras\Tipotransaccion_Compra_Concepto_Ivacompra;
use App\Models\Contable\Cuentacontable;
use App\Support\Compras\ConceptoIvaAnitaEsquemaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Importa maestros IVA compras Anita → ERP:
 * colivacomp → columna_ivacompra
 * conccomp (+ concciva si aplica) → concepto_ivacompra
 * t_comp (+ cont_comp) → tipotransaccion_compra
 *
 * No escribe Anita. Dry-run por defecto vía analizar().
 */
final class ComprasIvaMaestrosAnitaImportService
{
    /**
     * @return array<string, mixed>
     */
    public function analizar(?string $pathSistema = null): array
    {
        return $this->procesar(false, $pathSistema);
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(?string $pathSistema = null): array
    {
        return $this->procesar(true, $pathSistema);
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   path: string,
     *   columna_ivacompra: array<string, mixed>,
     *   concepto_ivacompra: array<string, mixed>,
     *   tipotransaccion_compra: array<string, mixed>,
     *   errores: list<string>,
     *   diagnostico: list<string>
     * }
     */
    private function procesar(bool $persistir, ?string $pathSistema): array
    {
        $path = rtrim($pathSistema ?: (string) config('anita.bdd_path', ''), '/') ?: null;
        $api = new ApiAnita();
        $errores = [];
        $diagnostico = [
            'Orden: columnas IVA → conceptos → tipos de transacción (con vínculos cont_comp).',
            'Anita es fuente de verdad en altas y actualización de cabecera; no borra vínculos ERP extra.',
            'concciva / ccostcomp se omiten si UNLOAD falla (esquema Ferli).',
        ];

        $columnas = $this->syncColumnas($api, $path, $persistir, $errores);
        $indiceColumnas = $this->indiceColumnasTrasSync($persistir, $columnas);
        $conceptos = $this->syncConceptos($api, $path, $persistir, $errores, $indiceColumnas);
        $tipos = $this->syncTipos($api, $path, $persistir, $errores);

        return [
            'dry_run' => ! $persistir,
            'path' => $path ?? '(default)',
            'columna_ivacompra' => $columnas,
            'concepto_ivacompra' => $conceptos,
            'tipotransaccion_compra' => $tipos,
            'errores' => $errores,
            'diagnostico' => $diagnostico,
        ];
    }

    /**
     * Mapa numerocolumna → id (o 0 en dry-run si aún no existe) tras sync de columnas.
     *
     * @param  array<string, mixed>  $statsColumnas
     * @return array<string, int>
     */
    private function indiceColumnasTrasSync(bool $persistir, array $statsColumnas): array
    {
        $indice = [];
        foreach (Columna_Ivacompra::query()->get(['id', 'numerocolumna']) as $col) {
            $num = trim((string) $col->numerocolumna);
            $indice[$num] = (int) $col->id;
            $alt = ltrim($num, '0');
            if ($alt !== '' && $alt !== $num) {
                $indice[$alt] = (int) $col->id;
            }
        }

        // En dry-run las altas aún no tienen id: marcamos pendiente con 0 para detectar diff de vínculo.
        if (! $persistir) {
            foreach ($statsColumnas['pendientes'] ?? [] as $numero) {
                $numero = trim((string) $numero);
                if ($numero === '' || isset($indice[$numero])) {
                    continue;
                }
                $indice[$numero] = 0;
            }
        }

        return $indice;
    }

    /**
     * @param  list<string>  $errores
     * @return array<string, mixed>
     */
    private function syncColumnas(ApiAnita $api, ?string $path, bool $persistir, array &$errores): array
    {
        $filas = $this->listar(
            $api,
            $path,
            'colivacomp',
            'coli_columna,coli_desc,coli_desc_columna,coli_tipo_dato,coli_formula',
            $errores
        );

        $crear = 0;
        $omitidos = 0;
        $detalle = [];
        $pendientes = [];

        foreach ($filas as $fila) {
            $numero = trim((string) ($fila->coli_columna ?? ''));
            if ($numero === '') {
                continue;
            }
            $nombre = trim((string) ($fila->coli_desc ?? '')) ?: "Columna IVA {$numero}";
            $nombreColumna = trim((string) ($fila->coli_desc_columna ?? ''));
            if (mb_strlen($nombreColumna) > 20) {
                $nombreColumna = mb_substr($nombreColumna, 0, 20);
            }

            $existente = $this->buscarColumna($numero);
            if ($existente !== null) {
                $omitidos++;
                continue;
            }

            $detalle[] = "{$numero} — {$nombre}";
            $pendientes[] = $numero;
            $crear++;
            if ($persistir) {
                Columna_Ivacompra::query()->create([
                    'numerocolumna' => $numero,
                    'nombre' => $nombre,
                    'nombrecolumna' => $nombreColumna !== '' ? $nombreColumna : null,
                ]);
            }
        }

        return [
            'fuente' => 'compras.colivacomp',
            'en_anita' => count($filas),
            'crear' => $crear,
            'actualizar' => 0,
            'omitidos' => $omitidos,
            'pendientes' => $pendientes,
            'muestra' => array_slice($detalle, 0, 20),
        ];
    }

    /**
     * @param  list<string>  $errores
     * @param  array<string, int>  $indiceColumnas
     * @return array<string, mixed>
     */
    private function syncConceptos(
        ApiAnita $api,
        ?string $path,
        bool $persistir,
        array &$errores,
        array $indiceColumnas
    ): array
    {
        $filas = $this->listar(
            $api,
            $path,
            'conccomp',
            ConceptoIvaAnitaEsquemaSupport::sqlCamposCabecera(),
            $errores
        );

        $crear = 0;
        $actualizar = 0;
        $omitidos = 0;
        $detalle = [];
        $empresaIdDefault = (int) (Cuentacontable::query()->orderBy('id')->value('empresa_id') ?? 1);

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->concc_concepto ?? ''));
            if ($codigo === '') {
                continue;
            }

            $payload = $this->mapearConcepto($fila, $indiceColumnas);
            $existente = $this->buscarConcepto($codigo);

            if ($existente === null) {
                $detalle[] = "crear {$codigo} — {$payload['nombre']}";
                $crear++;
                if ($persistir) {
                    DB::transaction(function () use ($api, $path, $codigo, $payload, $empresaIdDefault, &$errores) {
                        $concepto = Concepto_Ivacompra::query()->create($payload);
                        $this->asegurarLineaEmpresa($concepto, $empresaIdDefault);
                        $this->sincronizarCondicionesIva($api, $path, $concepto, $codigo, $errores);
                    });
                }
                continue;
            }

            $cambios = $this->diffConcepto($existente, $payload);
            if ($cambios === []) {
                $omitidos++;
                continue;
            }

            $detalle[] = "actualizar {$codigo}: ".implode(', ', array_keys($cambios));
            $actualizar++;
            if ($persistir) {
                // En persistencia real re-resolver columna por id (índice ya tiene ids reales).
                if (array_key_exists('columna_ivacompra_id', $cambios) && (int) $cambios['columna_ivacompra_id'] === 0) {
                    $numCol = trim((string) ($fila->concc_columna_sub ?? ''));
                    $cambios['columna_ivacompra_id'] = $this->buscarColumna($numCol)?->id;
                }
                $existente->fill($cambios);
                $existente->save();
            }
        }

        return [
            'fuente' => 'compras.conccomp'
                .(ConceptoIvaAnitaEsquemaSupport::leeConcciva() ? ' + concciva' : ' (sin concciva)'),
            'en_anita' => count($filas),
            'crear' => $crear,
            'actualizar' => $actualizar,
            'omitidos' => $omitidos,
            'muestra' => array_slice($detalle, 0, 25),
        ];
    }

    /**
     * @param  list<string>  $errores
     * @return array<string, mixed>
     */
    private function syncTipos(ApiAnita $api, ?string $path, bool $persistir, array &$errores): array
    {
        $filas = $this->listar(
            $api,
            $path,
            't_comp',
            'tcomp_clave,tcomp_desc,tcomp_oper,tcomp_refer,tcomp_subdiar,tcomp_oper_stk,'
            .'tcomp_genera_asi,tcomp_concepto,tcomp_tipo_comp,tcomp_tipo_oper,tcomp_toma_ret,tcomp_estado',
            $errores
        );

        $conceptosPorTipo = $this->indexarContComp($api, $path, $errores);

        $crear = 0;
        $actualizar = 0;
        $omitidos = 0;
        $vinculos = 0;
        $detalle = [];

        foreach ($filas as $fila) {
            $abrev = strtoupper(trim((string) ($fila->tcomp_clave ?? '')));
            if ($abrev === '') {
                continue;
            }

            $payload = $this->mapearTipo($fila);
            $existente = Tipotransaccion_Compra::query()
                ->where('abreviatura', $abrev)
                ->first();

            $conceptosCodigo = $conceptosPorTipo[$abrev] ?? [];

            if ($existente === null) {
                $detalle[] = "crear {$abrev} — {$payload['nombre']} (conceptos: ".count($conceptosCodigo).')';
                $crear++;
                if ($persistir) {
                    DB::transaction(function () use ($payload, $conceptosCodigo, &$vinculos) {
                        $tipo = Tipotransaccion_Compra::query()->create($payload);
                        $vinculos += $this->vincularConceptos($tipo, $conceptosCodigo);
                    });
                } else {
                    $vinculos += count($conceptosCodigo);
                }
                continue;
            }

            $cambios = $this->diffTipo($existente, $payload);
            $faltanVinculos = $this->conceptosFaltantes($existente, $conceptosCodigo);

            if ($cambios === [] && $faltanVinculos === []) {
                $omitidos++;
                continue;
            }

            $partes = [];
            if ($cambios !== []) {
                $partes[] = 'cabecera: '.implode(',', array_keys($cambios));
            }
            if ($faltanVinculos !== []) {
                $partes[] = 'conceptos +'.count($faltanVinculos);
            }
            $detalle[] = "actualizar {$abrev} — ".implode('; ', $partes);
            $actualizar++;

            if ($persistir) {
                DB::transaction(function () use ($existente, $cambios, $faltanVinculos, &$vinculos) {
                    if ($cambios !== []) {
                        $existente->fill($cambios);
                        $existente->save();
                    }
                    $vinculos += $this->vincularConceptos($existente, $faltanVinculos);
                });
            } else {
                $vinculos += count($faltanVinculos);
            }
        }

        return [
            'fuente' => 'compras.t_comp + cont_comp',
            'en_anita' => count($filas),
            'crear' => $crear,
            'actualizar' => $actualizar,
            'omitidos' => $omitidos,
            'vinculos_concepto' => $vinculos,
            'muestra' => array_slice($detalle, 0, 40),
        ];
    }

    /**
     * @param  array<string, int>  $indiceColumnas
     * @return array<string, mixed>
     */
    private function mapearConcepto(object $fila, array $indiceColumnas): array
    {
        $codigo = trim((string) ($fila->concc_concepto ?? ''));
        $nombre = trim((string) ($fila->concc_desc ?? '')) ?: "Concepto IVA Anita {$codigo}";
        $contenido = (string) ($fila->concc_contenido ?? '');
        $retieneGanancia = $contenido === 'C' ? 'S' : 'N';
        $tipoConc = (string) ($fila->concc_tipo_conc ?? 'N');
        if ($tipoConc === '') {
            $tipoConc = 'N';
        }
        $retieneIibb = (string) ($fila->concc_retiene_ibr ?? 'N');
        if ($retieneIibb === '') {
            $retieneIibb = 'N';
        }

        $columnaId = null;
        $numCol = trim((string) ($fila->concc_columna_sub ?? ''));
        if ($numCol !== '' && $numCol !== '0') {
            if (array_key_exists($numCol, $indiceColumnas)) {
                $columnaId = $indiceColumnas[$numCol];
            } else {
                $alt = ltrim($numCol, '0');
                if ($alt !== '' && array_key_exists($alt, $indiceColumnas)) {
                    $columnaId = $indiceColumnas[$alt];
                } else {
                    $columnaId = $this->buscarColumna($numCol)?->id;
                }
            }
        }

        return [
            'nombre' => $nombre,
            'codigo' => $codigo,
            'formula' => (string) ($fila->concc_formula ?? ''),
            'columna_ivacompra_id' => $columnaId,
            'empresa_id' => null,
            'cuentacontabledebe_id' => $this->cuentaIdPorCodigo((string) ($fila->concc_cta_debe ?? '')),
            'cuentacontablehaber_id' => $this->cuentaIdPorCodigo((string) ($fila->concc_cta_haber ?? '')),
            'tipoconcepto' => $tipoConc,
            'retieneganancia' => $retieneGanancia,
            'retieneIIBB' => $retieneIibb,
            'provincia_id' => null,
            'impuesto_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function diffConcepto(Concepto_Ivacompra $existente, array $payload): array
    {
        $cambios = [];
        foreach (['nombre', 'formula', 'columna_ivacompra_id', 'tipoconcepto', 'retieneganancia', 'retieneIIBB'] as $campo) {
            $nuevo = $payload[$campo] ?? null;
            $actual = $existente->{$campo};
            if ((string) ($actual ?? '') !== (string) ($nuevo ?? '')) {
                $cambios[$campo] = $nuevo;
            }
        }

        // Solo completar cuentas vacías; no pisar mapeo ERP ya cargado.
        foreach (['cuentacontabledebe_id', 'cuentacontablehaber_id'] as $campo) {
            $nuevo = $payload[$campo] ?? null;
            if ($nuevo && ! $existente->{$campo}) {
                $cambios[$campo] = $nuevo;
            }
        }

        return $cambios;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearTipo(object $fila): array
    {
        $abrev = strtoupper(trim((string) ($fila->tcomp_clave ?? '')));
        $nombre = trim((string) ($fila->tcomp_desc ?? '')) ?: $abrev;
        if (mb_strlen($nombre) > 255) {
            $nombre = mb_substr($nombre, 0, 255);
        }

        $tomaRet = strtoupper(trim((string) ($fila->tcomp_toma_ret ?? '')));
        $retieneIva = in_array($tomaRet, ['T', 'I', 'C'], true) ? 'S' : 'N';
        $retieneGanancia = in_array($tomaRet, ['T', 'G', 'C'], true) ? 'S' : 'N';
        $retieneIibb = in_array($tomaRet, ['T', 'G', 'C'], true) ? 'S' : 'N';

        $tipoOper = strtoupper(trim((string) ($fila->tcomp_tipo_oper ?? '')));
        $operacion = $tipoOper === 'M' ? 'L' : 'I';

        $signoAnita = strtoupper(trim((string) ($fila->tcomp_oper ?? 'S')));
        if (! in_array($signoAnita, ['S', 'R', 'N'], true)) {
            $signoAnita = 'S';
        }

        $subdiar = strtoupper(trim((string) ($fila->tcomp_subdiar ?? '')));
        $subdiario = $subdiar === 'N' ? 'N' : 'C';

        $generaAsi = strtoupper(trim((string) ($fila->tcomp_genera_asi ?? 'N')));
        $asiento = $generaAsi === 'S' ? 'S' : 'N';

        $estadoAnita = strtoupper(trim((string) ($fila->tcomp_estado ?? 'A')));
        $estado = $estadoAnita === 'A' ? 'A' : 'S';

        $tipoComp = preg_replace('/\D+/', '', (string) ($fila->tcomp_tipo_comp ?? '')) ?? '';
        $codigoAfip = $tipoComp !== '' ? str_pad($tipoComp, 3, '0', STR_PAD_LEFT) : '000';

        return [
            'nombre' => $nombre,
            'operacion' => $operacion,
            'abreviatura' => $abrev,
            'codigoafip' => $codigoAfip,
            'signo' => $signoAnita,
            'subdiario' => $subdiario,
            'asientocontable' => $asiento,
            'retieneiva' => $retieneIva,
            'retieneganancia' => $retieneGanancia,
            'retieneIIBB' => $retieneIibb,
            'estado' => $estado,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function diffTipo(Tipotransaccion_Compra $existente, array $payload): array
    {
        $cambios = [];
        foreach (
            [
                'nombre',
                'operacion',
                'codigoafip',
                'signo',
                'subdiario',
                'asientocontable',
                'retieneiva',
                'retieneganancia',
                'retieneIIBB',
                'estado',
            ] as $campo
        ) {
            $nuevo = $payload[$campo] ?? null;
            $actual = $existente->{$campo};
            if ((string) ($actual ?? '') !== (string) ($nuevo ?? '')) {
                $cambios[$campo] = $nuevo;
            }
        }

        return $cambios;
    }

    private function asegurarLineaEmpresa(Concepto_Ivacompra $concepto, int $empresaIdDefault): void
    {
        $ctaDebe = $concepto->cuentacontabledebe_id ? (int) $concepto->cuentacontabledebe_id : null;
        $ctaHaber = $concepto->cuentacontablehaber_id ? (int) $concepto->cuentacontablehaber_id : null;
        if (! $ctaDebe && ! $ctaHaber) {
            return;
        }

        $empresaId = $this->empresaIdDeCuenta($ctaDebe)
            ?: $this->empresaIdDeCuenta($ctaHaber)
            ?: $empresaIdDefault;
        if ($empresaId <= 0) {
            $empresaId = 1;
        }

        Concepto_Ivacompra_Empresa::query()->firstOrCreate(
            [
                'concepto_ivacompra_id' => $concepto->id,
                'empresa_id' => $empresaId,
            ],
            [
                'cuentacontabledebe_id' => $ctaDebe,
                'cuentacontablehaber_id' => $ctaHaber,
            ]
        );
    }

    /**
     * @param  list<string>  $errores
     */
    private function sincronizarCondicionesIva(
        ApiAnita $api,
        ?string $path,
        Concepto_Ivacompra $concepto,
        string $codigo,
        array &$errores
    ): void {
        if (! ConceptoIvaAnitaEsquemaSupport::leeConcciva()) {
            return;
        }

        $filas = $this->listar(
            $api,
            $path,
            'concciva',
            'conci_concepto,conci_cond_iva',
            $errores,
            " WHERE conci_concepto = '".$this->escapar($codigo)."' ",
            silenciarError: true
        );

        foreach ($filas as $fila) {
            $condId = (int) ($fila->conci_cond_iva ?? 0);
            if ($condId <= 0) {
                continue;
            }
            Concepto_Ivacompra_Condicioniva::query()->firstOrCreate([
                'concepto_ivacompra_id' => $concepto->id,
                'condicioniva_id' => $condId,
            ]);
        }
    }

    /**
     * @param  list<string>  $errores
     * @return array<string, list<string>>
     */
    private function indexarContComp(ApiAnita $api, ?string $path, array &$errores): array
    {
        $filas = $this->listar(
            $api,
            $path,
            'cont_comp',
            'contc_tipo,contc_concepto',
            $errores,
            silenciarError: true
        );

        $out = [];
        foreach ($filas as $fila) {
            $tipo = strtoupper(trim((string) ($fila->contc_tipo ?? '')));
            $concepto = trim((string) ($fila->contc_concepto ?? ''));
            if ($tipo === '' || $concepto === '') {
                continue;
            }
            $out[$tipo][$concepto] = $concepto;
        }

        foreach ($out as $tipo => $map) {
            $out[$tipo] = array_values($map);
        }

        return $out;
    }

    /**
     * @param  list<string>  $codigosConcepto
     * @return list<string>
     */
    private function conceptosFaltantes(Tipotransaccion_Compra $tipo, array $codigosConcepto): array
    {
        if ($codigosConcepto === []) {
            return [];
        }

        $ya = Tipotransaccion_Compra_Concepto_Ivacompra::query()
            ->where('tipotransaccion_compra_id', $tipo->id)
            ->whereNotNull('concepto_ivacompra_id')
            ->with('concepto_ivacompras:id,codigo')
            ->get()
            ->map(fn ($l) => trim((string) ($l->concepto_ivacompras?->codigo ?? '')))
            ->filter()
            ->all();

        $faltan = [];
        foreach ($codigosConcepto as $codigo) {
            if (! in_array($codigo, $ya, true) && ! in_array(ltrim($codigo, '0'), $ya, true)) {
                $faltan[] = $codigo;
            }
        }

        return $faltan;
    }

    /**
     * @param  list<string>  $codigosConcepto
     */
    private function vincularConceptos(Tipotransaccion_Compra $tipo, array $codigosConcepto): int
    {
        $n = 0;
        foreach ($codigosConcepto as $codigo) {
            $concepto = $this->buscarConcepto($codigo);
            if ($concepto === null) {
                continue;
            }
            $created = Tipotransaccion_Compra_Concepto_Ivacompra::query()->firstOrCreate([
                'tipotransaccion_compra_id' => $tipo->id,
                'concepto_ivacompra_id' => $concepto->id,
            ]);
            if ($created->wasRecentlyCreated) {
                $n++;
            }
        }

        return $n;
    }

    private function buscarColumna(string $numero): ?Columna_Ivacompra
    {
        $q = Columna_Ivacompra::query()->where('numerocolumna', $numero);
        $hit = $q->first();
        if ($hit) {
            return $hit;
        }
        $alt = ltrim($numero, '0');
        if ($alt !== '' && $alt !== $numero) {
            return Columna_Ivacompra::query()->where('numerocolumna', $alt)->first();
        }

        return null;
    }

    private function buscarConcepto(string $codigo): ?Concepto_Ivacompra
    {
        $hit = Concepto_Ivacompra::query()->where('codigo', $codigo)->first();
        if ($hit) {
            return $hit;
        }
        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            return Concepto_Ivacompra::query()->where('codigo', $alt)->first();
        }

        return null;
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
     * @param  list<string>  $errores
     * @return list<object>
     */
    private function listar(
        ApiAnita $api,
        ?string $path,
        string $tabla,
        string $campos,
        array &$errores,
        ?string $whereArmado = null,
        bool $silenciarError = false
    ): array {
        $payload = [
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => $tabla,
            'campos' => $campos,
        ];
        if ($path) {
            $payload['path_sistema'] = $path;
        }
        if ($whereArmado) {
            $payload['whereArmado'] = $whereArmado;
        }

        $raw = $api->apiCall($payload);
        $parsed = ApiAnita::parsearRespuestaLista($raw);
        if ($parsed['error_lectura'] !== null) {
            $msg = "Anita compras.{$tabla}: {$parsed['error_lectura']}";
            if ($silenciarError) {
                // Esquema incompleto (Ferli: sin concciva / ccostcomp): no es fallo del import.
                return [];
            }
            throw new RuntimeException($msg);
        }

        return $parsed['filas'];
    }

    private function escapar(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
