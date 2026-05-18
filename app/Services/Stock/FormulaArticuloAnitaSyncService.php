<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Formula_Articulo;
use App\Models\Stock\Formula_Articulo_Estado;
use App\Models\Stock\Formula_Articulo_Hijo;
use App\Repositories\Stock\Formula_Articulo_EstadoRepositoryInterface;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Stock\FormulaArticuloSku;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importa stkcmae / stkcmov (Anita Informix) a formula_articulo / formula_articulo_hijo.
 * Cabecera: stkcmae.stkcm_articulo se cruza con articulo.sku (sin ceros a la izquierda) para formula_articulo.articulo_id.
 *
 * Conexión on-line: mismo criterio que {@see Articulo::sincronizarConAnita()} — {@see ApiAnita::apiCall()}
 * con acc=list, tabla y campos (UNLOAD vía puente SSH/HTTP según ANITA_BRIDGE_TYPE).
 *
 * Formato archivo stkcmae: | con columnas UNLOAD en orden; si hay 6+ campos, el segundo es stkcm_articulo (código artículo Anita).
 * stkcmov en FRASLE puede incluir stkcv_ranura como novena columna.
 */
class FormulaArticuloAnitaSyncService
{
    private const OBS_ALTA_ANITA = 'Alta desde anita';

    public function __construct(
        private ApiAnita $apiAnita,
        private Formula_Articulo_EstadoRepositoryInterface $formulaArticuloEstadoRepository,
        private FormulaArticuloVinculoService $formulaArticuloVinculoService,
    ) {}

    /**
     * @return array{formulas:int, lineas:int, advertencias:list<string>}
     */
    public function sincronizarDesdeArchivos(string $pathMae, string $pathMov, int $usuarioId): array
    {
        $mae = $this->parseArchivoStkcmae($pathMae);
        $mov = $this->parseArchivoStkcmov($pathMov);

        return $this->sincronizarInterno($mae, $mov, $usuarioId);
    }

    /**
     * Lista remota stkcmae vía ApiAnita (equivalente a un list masivo como en Articulo::traerRegistroDeAnita).
     *
     * @return list<array{stkcm_formula:int, stkcm_formula_codigo:string, stkcm_articulo:string, stkcm_detalle:string, stkcm_cant_porcion:float}>
     */
    public function listarStkcmaeDesdeAnita(): array
    {
        $camposMae = 'stkcm_formula, stkcm_articulo, stkcm_detalle, stkcm_coef_venta, stkcm_cod_impuesto, stkcm_cant_porcion';
        $payload = [
            'acc' => 'list',
            'tabla' => 'stkcmae',
            'campos' => $camposMae,
            'orderBy' => 'stkcm_formula',
        ];
        return $this->normalizarFilasStkcmae($this->decodificarRespuestaList($this->apiAnita->apiCall($payload)));
    }

    /**
     * @return list<array{stkcv_formula:int, stkcv_linea:int, stkcv_art_hijo:string, stkcv_cantidad:float, stkcv_formula_hija:int, stkcv_factor_costo:float, stkcv_deposito:int, stkcv_opcional:string, stkcv_ranura:?int}>
     */
    public function listarStkcmovDesdeAnita(): array
    {
        $camposMov = 'stkcv_formula, stkcv_linea, stkcv_art_hijo, stkcv_cantidad, stkcv_formula_hija, stkcv_factor_costo, stkcv_deposito, stkcv_opcional';
        if (strtoupper((string) config('app.empresa')) === 'FRASLE') {
            $camposMov .= ', stkcv_ranura';
        }
        $payload = [
            'acc' => 'list',
            'tabla' => 'stkcmov',
            'campos' => $camposMov,
            'orderBy' => 'stkcv_formula, stkcv_linea',
        ];

        return $this->normalizarFilasStkcmov($this->decodificarRespuestaList($this->apiAnita->apiCall($payload)));
    }

    /**
     * @return array{formulas:int, lineas:int, advertencias:list<string>}
     */
    public function sincronizarDesdeApi(int $usuarioId): array
    {
        return $this->sincronizarInterno(
            $this->listarStkcmaeDesdeAnita(),
            $this->listarStkcmovDesdeAnita(),
            $usuarioId
        );
    }

    /**
     * Igual que json_decode en Articulo tras apiCall: filas como objetos o arrays asociativos.
     *
     * @return list<mixed>
     */
    private function decodificarRespuestaList(string|false $json): array
    {
        if ($json === false || $json === '') {
            return [];
        }
        $decoded = json_decode($json, false);
        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param  list<mixed>  $filas
     * @return list<array{stkcm_formula:int, stkcm_formula_codigo:string, stkcm_articulo:string, stkcm_detalle:string, stkcm_cant_porcion:float}>
     */
    private function normalizarFilasStkcmae(array $filas): array
    {
        $maeNorm = [];
        foreach ($filas as $row) {
            if (! is_array($row) && ! is_object($row)) {
                continue;
            }
            $row = $this->filaAnitaAArray($row);
            $s = trim((string) ($row['stkcm_formula'] ?? ''));
            $maeNorm[] = [
                'stkcm_formula' => (int) $s,
                'stkcm_formula_codigo' => $s === '' ? '' : mb_substr($s, 0, 50),
                'stkcm_articulo' => trim((string) ($row['stkcm_articulo'] ?? '')),
                'stkcm_detalle' => trim((string) ($row['stkcm_detalle'] ?? '')),
                'stkcm_cant_porcion' => (float) ($row['stkcm_cant_porcion'] ?? 0),
            ];
        }

        return $maeNorm;
    }

    /**
     * @param  list<mixed>  $filas
     * @return list<array{stkcv_formula:int, stkcv_linea:int, stkcv_art_hijo:string, stkcv_cantidad:float, stkcv_formula_hija:int, stkcv_factor_costo:float, stkcv_deposito:int, stkcv_opcional:string, stkcv_ranura:?int}>
     */
    private function normalizarFilasStkcmov(array $filas): array
    {
        $movNorm = [];
        foreach ($filas as $row) {
            if (! is_array($row) && ! is_object($row)) {
                continue;
            }
            $row = $this->filaAnitaAArray($row);
            $movNorm[] = [
                'stkcv_formula' => (int) ($row['stkcv_formula'] ?? 0),
                'stkcv_linea' => (int) ($row['stkcv_linea'] ?? 0),
                'stkcv_art_hijo' => (string) ($row['stkcv_art_hijo'] ?? ''),
                'stkcv_cantidad' => (float) ($row['stkcv_cantidad'] ?? 0),
                'stkcv_formula_hija' => (int) ($row['stkcv_formula_hija'] ?? 0),
                'stkcv_factor_costo' => (float) ($row['stkcv_factor_costo'] ?? 0),
                'stkcv_deposito' => (int) ($row['stkcv_deposito'] ?? 0),
                'stkcv_opcional' => (string) ($row['stkcv_opcional'] ?? ''),
                'stkcv_ranura' => isset($row['stkcv_ranura']) && $row['stkcv_ranura'] !== '' && $row['stkcv_ranura'] !== null
                    ? (int) $row['stkcv_ranura']
                    : null,
            ];
        }

        return $movNorm;
    }

    /**
     * @param  object|array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function filaAnitaAArray(object|array $row): array
    {
        if (is_array($row)) {
            return $row;
        }

        return get_object_vars($row);
    }

    /**
     * @param  list<array{stkcm_formula:int, stkcm_formula_codigo:string, stkcm_articulo:string, stkcm_detalle:string, stkcm_cant_porcion:float}>  $mae
     * @param  list<array{stkcv_formula:int, stkcv_linea:int, stkcv_art_hijo:string, stkcv_cantidad:float, stkcv_formula_hija:int, stkcv_factor_costo:float, stkcv_deposito:int, stkcv_opcional:string, stkcv_ranura:?int}>  $mov
     * @return array{formulas:int, lineas:int, advertencias:list<string>}
     */
    public function sincronizarInterno(array $mae, array $mov, int $usuarioId): array
    {
        $advertencias = [];
        if ($mae === []) {
            return ['formulas' => 0, 'lineas' => 0, 'advertencias' => ['No hay registros en stkcmae.']];
        }

        usort($mae, fn ($a, $b) => $a['stkcm_formula'] <=> $b['stkcm_formula']);
        usort($mov, function ($a, $b) {
            $c = $a['stkcv_formula'] <=> $b['stkcv_formula'];

            return $c !== 0 ? $c : $a['stkcv_linea'] <=> $b['stkcv_linea'];
        });

        $movPorFormula = [];
        foreach ($mov as $linea) {
            $fid = $linea['stkcv_formula'];
            if ($fid <= 0) {
                continue;
            }
            $movPorFormula[$fid][] = $linea;
        }

        $idsFormulasMae = [];
        foreach ($mae as $cab) {
            $f = (int) $cab['stkcm_formula'];
            if ($f > 0) {
                $idsFormulasMae[$f] = true;
            }
        }
        $movLineasHuerfanasPorFormula = [];
        foreach ($mov as $linea) {
            $fid = (int) $linea['stkcv_formula'];
            if ($fid <= 0 || isset($idsFormulasMae[$fid])) {
                continue;
            }
            $movLineasHuerfanasPorFormula[$fid] = ($movLineasHuerfanasPorFormula[$fid] ?? 0) + 1;
        }
        if ($movLineasHuerfanasPorFormula !== []) {
            arsort($movLineasHuerfanasPorFormula);
            $totalHuerf = array_sum($movLineasHuerfanasPorFormula);
            $ejemplos = array_slice(array_keys($movLineasHuerfanasPorFormula), 0, 20);
            $advertencias[] = sprintf(
                'stkcmov tiene %d línea(s) con stkcv_formula sin cabecera en stkcmae (no se importan). Ejemplos de fórmula Anita: %s.',
                $totalHuerf,
                implode(', ', $ejemplos)
            );
        }

        $movLineasFormulaCero = 0;
        foreach ($mov as $linea) {
            if ((int) $linea['stkcv_formula'] <= 0) {
                $movLineasFormulaCero++;
            }
        }
        if ($movLineasFormulaCero > 0) {
            $advertencias[] = sprintf(
                'stkcmov: %d línea(s) con stkcv_formula en cero o inválido (no se importan).',
                $movLineasFormulaCero
            );
        }

        $estadoActiva = $this->nombreEstadoActiva();
        $mapAnitaAErp = [];
        $formulas = 0;
        $lineas = 0;

        DB::beginTransaction();
        try {
            foreach ($mae as $cab) {
                $anitaF = (int) $cab['stkcm_formula'];
                if ($anitaF <= 0) {
                    continue;
                }

                $stkcmArticulo = (string) ($cab['stkcm_articulo'] ?? '');
                $articuloCabeceraId = $this->resolverArticuloCabeceraId($anitaF, $stkcmArticulo);
                $existente = Formula_Articulo::query()->where('anita_stkcm_formula', $anitaF)->first();
                $codigoAnita = (string) ($cab['stkcm_formula_codigo'] ?? '');
                $codigoDb = $codigoAnita !== '' ? $codigoAnita : ($anitaF > 0 ? (string) $anitaF : null);

                $payload = [
                    'anita_stkcm_formula' => $anitaF,
                    'codigo' => $codigoDb,
                    'articulo_id' => $articuloCabeceraId,
                    'detalle' => $cab['stkcm_detalle'] !== '' ? $cab['stkcm_detalle'] : null,
                    'cantidadunidad' => (float) $cab['stkcm_cant_porcion'],
                    'estado' => $estadoActiva,
                    'creousuario_id' => $existente ? (int) $existente->creousuario_id : $usuarioId,
                ];

                if ($existente) {
                    $existente->update([
                        'articulo_id' => $articuloCabeceraId,
                        'codigo' => $codigoDb,
                        'detalle' => $payload['detalle'],
                        'cantidadunidad' => $payload['cantidadunidad'],
                        'estado' => $estadoActiva,
                    ]);
                    $formula = $existente->fresh();
                } else {
                    $formula = Formula_Articulo::query()->create($payload);
                    $this->formulaArticuloEstadoRepository->creaEstado(
                        (int) $formula->id,
                        Carbon::now()->toDateTimeString(),
                        $estadoActiva,
                        $usuarioId,
                        self::OBS_ALTA_ANITA
                    );
                }

                if (trim($stkcmArticulo) !== '' && $articuloCabeceraId === null) {
                    $advertencias[] = "Fórmula Anita {$anitaF}: stkcm_articulo \"".trim($stkcmArticulo)."\" no coincide con articulo.sku (con o sin ceros a la izquierda) y no hay artículo vinculado por articulo.formula ni cabecera previa en formula_articulo.";
                }

                $mapAnitaAErp[$anitaF] = (int) $formula->id;
                $formulas++;
            }

            foreach ($mae as $cab) {
                $anitaF = (int) $cab['stkcm_formula'];
                if ($anitaF <= 0) {
                    continue;
                }
                $erpId = $mapAnitaAErp[$anitaF] ?? null;
                if ($erpId === null) {
                    continue;
                }

                Formula_Articulo_Hijo::query()->where('formula_articulo_id', $erpId)->delete();

                foreach ($movPorFormula[$anitaF] ?? [] as $det) {
                    $lineas++;
                    $hijoArticuloId = $this->resolverArticuloIdPorCodigoAnita($det['stkcv_art_hijo']);
                    if ($hijoArticuloId === null && ($det['stkcv_formula_hija'] ?? 0) <= 0) {
                        $advertencias[] = "Fórmula Anita {$anitaF} línea {$det['stkcv_linea']}: sin artículo hijo ni subfórmula (código ".trim($det['stkcv_art_hijo']).').';

                        continue;
                    }

                    $formulaHijaErp = null;
                    $anitaHija = (int) ($det['stkcv_formula_hija'] ?? 0);
                    if ($anitaHija > 0) {
                        $formulaHijaErp = $mapAnitaAErp[$anitaHija] ?? null;
                        if ($formulaHijaErp === null) {
                            $advertencias[] = "Fórmula Anita {$anitaF} línea {$det['stkcv_linea']}: stkcv_formula_hija={$anitaHija} no resuelta en stkcmae.";
                        }
                    }

                    $gastronomia = FormulaArticuloGastronomia::opcionalesHabilitados();
                    $esOpcional = $gastronomia && $this->anitaOpcionalEsSi($det['stkcv_opcional'] ?? '');
                    $ordenOpcional = null;
                    if ($gastronomia && $esOpcional && Schema::hasColumn('formula_articulo_hijo', 'ordenopcional')) {
                        $ordenOpcional = (int) $det['stkcv_linea'];
                    }

                    $depositoId = $this->resolverDepositoId((int) $det['stkcv_deposito']);

                    $hijoPayload = [
                        'formula_articulo_id' => $erpId,
                        'articulo_id' => $hijoArticuloId,
                        'cantidad' => (float) $det['stkcv_cantidad'],
                        'factorcosto' => (float) $det['stkcv_factor_costo'],
                        'formula_hija_id' => $formulaHijaErp,
                        'esopcional' => $esOpcional,
                        'deposito_id' => $depositoId,
                    ];

                    if (Schema::hasColumn('formula_articulo_hijo', 'ordenopcional')) {
                        $hijoPayload['ordenopcional'] = $ordenOpcional;
                    }

                    if (strtoupper((string) config('app.empresa')) === 'FRASLE'
                        && Schema::hasColumn('formula_articulo_hijo', 'ranura')) {
                        $r = $det['stkcv_ranura'] ?? null;
                        $hijoPayload['ranura'] = ($r === null || $r === 0) ? null : (int) $r;
                    }

                    Formula_Articulo_Hijo::query()->create($hijoPayload);
                }

                $this->actualizarArticulosFormulaAnita($anitaF, $erpId);
                $articuloCabeceraId = $this->resolverArticuloCabeceraId($anitaF, (string) ($cab['stkcm_articulo'] ?? ''));
                if ($articuloCabeceraId !== null) {
                    Articulo::query()->where('id', $articuloCabeceraId)->update(['formula' => $erpId]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $vinculo = $this->formulaArticuloVinculoService->vincularPorCodigoSku(false);
        if ($vinculo['formulas_vinculadas'] > 0 || $vinculo['articulos_corregidos'] > 0) {
            $advertencias[] = 'Vínculo código→SKU: '.$vinculo['formulas_vinculadas'].' fórmula(s) actualizada(s), '
                .$vinculo['articulos_corregidos'].' artículo(s) corregido(s).';
        }
        foreach (array_slice($vinculo['sin_articulo'], 0, 20) as $msg) {
            $advertencias[] = $msg;
        }

        return ['formulas' => $formulas, 'lineas' => $lineas, 'advertencias' => $advertencias];
    }

    /**
     * @return list<array{stkcm_formula:int, stkcm_formula_codigo:string, stkcm_articulo:string, stkcm_detalle:string, stkcm_cant_porcion:float}>
     */
    private function parseArchivoStkcmae(string $path): array
    {
        if (! is_readable($path)) {
            throw new \InvalidArgumentException("No se puede leer stkcmae: {$path}");
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (! $this->esLineaDatosPipe($line)) {
                continue;
            }
            $p = array_map('trim', explode('|', $line));
            if (count($p) < 5) {
                continue;
            }
            $s = $p[0];
            if (count($p) >= 6) {
                $stkcmArticulo = $p[1];
                $detalle = $p[2];
                $cantPorcion = (float) $p[5];
            } else {
                $stkcmArticulo = '';
                $detalle = $p[1];
                $cantPorcion = (float) $p[4];
            }
            $out[] = [
                'stkcm_formula' => (int) $s,
                'stkcm_formula_codigo' => $s === '' ? '' : mb_substr($s, 0, 50),
                'stkcm_articulo' => $stkcmArticulo,
                'stkcm_detalle' => $detalle,
                'stkcm_cant_porcion' => $cantPorcion,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{stkcv_formula:int, stkcv_linea:int, stkcv_art_hijo:string, stkcv_cantidad:float, stkcv_formula_hija:int, stkcv_factor_costo:float, stkcv_deposito:int, stkcv_opcional:string, stkcv_ranura:?int}>
     */
    private function parseArchivoStkcmov(string $path): array
    {
        if (! is_readable($path)) {
            throw new \InvalidArgumentException("No se puede leer stkcmov: {$path}");
        }
        $esFrasle = strtoupper((string) config('app.empresa')) === 'FRASLE';
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (! $this->esLineaDatosPipe($line)) {
                continue;
            }
            $p = array_map('trim', explode('|', $line));
            if (count($p) < 8) {
                continue;
            }
            $ranura = null;
            if ($esFrasle && count($p) >= 9 && $p[8] !== '') {
                $ranura = (int) $p[8];
            }
            $out[] = [
                'stkcv_formula' => (int) $p[0],
                'stkcv_linea' => (int) $p[1],
                'stkcv_art_hijo' => $p[2],
                'stkcv_cantidad' => (float) $p[3],
                'stkcv_formula_hija' => (int) $p[4],
                'stkcv_factor_costo' => (float) $p[5],
                'stkcv_deposito' => (int) $p[6],
                'stkcv_opcional' => $p[7] ?? '',
                'stkcv_ranura' => $ranura,
            ];
        }

        return $out;
    }

    private function esLineaDatosPipe(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '{')) {
            return false;
        }
        if (preg_match('/^\s*create\s+/i', $line) || preg_match('/^\s*revoke\s+/i', $line)) {
            return false;
        }

        return str_contains($line, '|');
    }

    private function nombreEstadoActiva(): string
    {
        foreach (Formula_Articulo_Estado::$enumEstado as $e) {
            if (($e['valor'] ?? '') === 'A') {
                return (string) $e['nombre'];
            }
        }

        return 'ACTIVA';
    }

    private function anitaOpcionalEsSi(string $flag): bool
    {
        $f = strtoupper(trim($flag));

        return in_array($f, ['S', '1', 'Y', 'O', 'SI'], true);
    }

    /**
     * Resuelve el artículo cabecera de la fórmula: primero stkcmae.stkcm_articulo contra articulo.sku
     * (sin ceros a la izquierda, igual que en líneas stkcmov); si no, artículo con articulo.formula igual al número Anita;
     * si no, articulo_id ya guardado en formula_articulo para esa anita_stkcm_formula.
     */
    private function resolverArticuloCabeceraId(int $anitaFormula, string $stkcmArticulo = ''): ?int
    {
        $stkcmArticulo = trim($stkcmArticulo);
        if ($stkcmArticulo !== '') {
            $porSku = $this->resolverArticuloIdPorCodigoAnita($stkcmArticulo);
            if ($porSku !== null) {
                return $porSku;
            }
        }

        $porFormulaAnita = Articulo::query()
            ->where('formula', $anitaFormula)
            ->orderBy('id')
            ->first();
        if ($porFormulaAnita) {
            return (int) $porFormulaAnita->id;
        }

        $desdeTabla = Formula_Articulo::query()
            ->where('anita_stkcm_formula', $anitaFormula)
            ->value('articulo_id');

        return $desdeTabla !== null ? (int) $desdeTabla : null;
    }

    private function resolverArticuloIdPorCodigoAnita(string $codigo13): ?int
    {
        $c = trim($codigo13);
        if ($c === '') {
            return null;
        }
        $sku = ltrim($c, '0');
        if ($sku === '') {
            $sku = '0';
        }

        $id = Articulo::query()->where('sku', $sku)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        $id = Articulo::query()->where('sku', $c)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        if (ctype_digit($c)) {
            $skuV = FormulaArticuloSku::skuDesdeCodigo((int) $c);
            $id = Articulo::query()->where('sku', $skuV)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }

    private function resolverDepositoId(int $codDepAnita): ?int
    {
        if ($codDepAnita <= 0) {
            return null;
        }
        $cod = (string) $codDepAnita;

        return Depmae::query()->where('codigo', $cod)->value('id');
    }

    private function actualizarArticulosFormulaAnita(int $anitaFormula, int $erpFormulaId): void
    {
        Articulo::query()->where('formula', $anitaFormula)->update(['formula' => $erpFormulaId]);
    }
}
