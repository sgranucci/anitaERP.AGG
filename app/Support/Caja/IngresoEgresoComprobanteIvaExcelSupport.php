<?php

namespace App\Support\Caja;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Contable\Cuentacontable;
use App\Support\Contable\CuentacontableArbolSupport;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lee la planilla de gastos (código, cuenta contable, descripción, acumulados)
 * y propone la vinculación con el concepto de IVA por cuenta o por código.
 */
final class IngresoEgresoComprobanteIvaExcelSupport
{
    /**
     * @return array{mensaje: string, filas: array<int, array<string, mixed>>, aviso: string}
     */
    public function previsualizar(string $path, int $empresaId): array
    {
        $hoja = $this->leerFilas($path);
        $indice = $this->indiceVinculos($empresaId, $this->candidatosCodigo($hoja));
        $filas = [];
        foreach ($hoja as $n => $celdas) {
            $analizada = $this->analizarCeldas($celdas, $indice);
            if ($analizada === null) {
                continue;
            }
            $analizada['fila'] = $n + 1;
            $filas[] = $analizada;
        }

        return [
            'mensaje' => 'ok',
            'filas' => $filas,
            'aviso' => 'Cada fila indica si el dato es una cuenta contable o un código de concepto. Si ambos coinciden, se usa la cuenta contable del concepto. Los importes se cargan en valor absoluto.',
        ];
    }

    /**
     * @param  array<int, mixed>  $celdas
     * @param  array{cuentas: array<string, array<string, mixed>>, conceptos_por_cuenta: array<int, array<int, array<string, mixed>>>, conceptos_por_codigo: array<string, array<int, array<string, mixed>>>}  $indice
     * @return array<string, mixed>|null
     */
    public function analizarCeldas(array $celdas, array $indice): ?array
    {
        $tokens = [];
        foreach ($celdas as $valor) {
            $token = $this->tokenCelda($valor);
            if ($token !== null) {
                $tokens[] = $token;
            }
        }
        if ($tokens === []) {
            return null;
        }

        $hayEncabezado = false;
        $cuentas = [];
        $codigos = [];
        $importes = [];
        $descripcion = '';
        foreach ($tokens as $token) {
            if ($token['tipo'] === 'encabezado') {
                $hayEncabezado = true;
            } elseif ($token['tipo'] === 'cuenta') {
                $cuentas[] = $token['texto'];
            } elseif ($token['tipo'] === 'codigo_concepto' || $token['tipo'] === 'posible_codigo') {
                $codigos[] = $token['texto'];
            } elseif ($token['tipo'] === 'importe') {
                $importes[] = (float) $token['numero'];
            } elseif ($token['tipo'] === 'descripcion' && $descripcion === '') {
                $descripcion = $token['texto'];
            }
        }

        if ($hayEncabezado && $cuentas === [] && $importes === []) {
            return null;
        }
        if ($descripcion !== '' && preg_match('/^total\b/i', $descripcion) && $cuentas === [] && $codigos === []) {
            return null;
        }
        if ($cuentas === [] && $codigos === [] && $descripcion === '') {
            return null;
        }

        $importeOriginal = 0.0;
        foreach ($importes as $importe) {
            if (abs($importe) > abs($importeOriginal)) {
                $importeOriginal = $importe;
            }
        }

        $cuenta = $this->resolverCuenta($cuentas, $indice['cuentas']);
        $porCuenta = [];
        if ($cuenta !== null) {
            $porCuenta = $indice['conceptos_por_cuenta'][(int) $cuenta['id']] ?? [];
        }
        $porCodigo = $this->conceptosPorCodigos($codigos, $indice['conceptos_por_codigo']);

        $alternativas = $this->unirConceptos($porCuenta, $porCodigo);
        $concepto = null;
        $detectado = 'sin_vinculo';
        $etiqueta = 'No se reconoció cuenta ni código de concepto';

        if (count($porCuenta) === 1 && ($porCodigo === [] || $this->mismosIds($porCuenta, $porCodigo))) {
            $concepto = $porCuenta[0];
            $detectado = $porCodigo !== [] ? 'ambos' : 'cuenta_contable';
            $etiqueta = $detectado === 'ambos'
                ? 'Cuenta contable y código de concepto coinciden'
                : 'Cuenta contable';
        } elseif (count($porCuenta) > 1) {
            $concepto = $porCuenta[0];
            $detectado = 'ambiguo';
            $etiqueta = 'La cuenta contable está en más de un concepto';
        } elseif ($porCuenta !== [] && $porCodigo !== [] && ! $this->mismosIds($porCuenta, $porCodigo)) {
            $concepto = $porCuenta[0];
            $detectado = 'ambiguo';
            $etiqueta = 'La cuenta y el código apuntan a conceptos distintos. Se propone la cuenta.';
        } elseif (count($porCodigo) === 1 && $porCuenta === []) {
            $concepto = $porCodigo[0];
            $detectado = 'codigo_concepto';
            $etiqueta = 'Código de concepto';
        } elseif (count($porCodigo) > 1 && $porCuenta === []) {
            $concepto = $porCodigo[0];
            $detectado = 'ambiguo';
            $etiqueta = 'El código coincide con más de un concepto';
        } elseif ($cuenta !== null) {
            $detectado = 'cuenta_sin_concepto';
            $etiqueta = 'Es una cuenta contable, sin concepto vinculado';
        } elseif ($codigos !== []) {
            $detectado = 'codigo_sin_concepto';
            $etiqueta = 'Parece un código de concepto y no está en el maestro';
        }

        $valorCuenta = $cuenta['codigo'] ?? ($cuentas[0] ?? '');
        $valorCodigo = $codigos[0] ?? '';

        return [
            'descripcion' => $descripcion,
            'detectado' => $detectado,
            'detectado_etiqueta' => $etiqueta,
            'valor_cuenta' => $valorCuenta,
            'cuenta_id' => (int) ($cuenta['id'] ?? 0),
            'cuenta_nombre' => (string) ($cuenta['nombre'] ?? ''),
            'valor_codigo' => $valorCodigo,
            'concepto_id' => (int) ($concepto['id'] ?? 0),
            'concepto_codigo' => (string) ($concepto['codigo'] ?? ''),
            'concepto_nombre' => (string) ($concepto['nombre'] ?? ''),
            'tipoconcepto' => (string) ($concepto['tipoconcepto'] ?? ''),
            'cuenta_debe_id' => (int) ($concepto['cuenta_debe_id'] ?? ($cuenta['id'] ?? 0)),
            'cuenta_debe_codigo' => (string) ($concepto['cuenta_debe_codigo'] ?? ($cuenta['codigo'] ?? '')),
            'cuenta_debe_nombre' => (string) ($concepto['cuenta_debe_nombre'] ?? ($cuenta['nombre'] ?? '')),
            'alternativas' => $alternativas,
            'importe_original' => round($importeOriginal, 2),
            'importe' => round(abs($importeOriginal), 2),
            'aplicar' => $concepto !== null && abs($importeOriginal) >= 0.01,
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function leerFilas(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $filas = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        return $filas;
    }

    /**
     * @param  array<int, array<int, mixed>>  $hoja
     * @return array<int, string>
     */
    private function candidatosCodigo(array $hoja): array
    {
        $codigos = [];
        foreach ($hoja as $celdas) {
            foreach ($celdas as $valor) {
                $token = $this->tokenCelda($valor);
                if ($token === null) {
                    continue;
                }
                if (in_array($token['tipo'], ['cuenta', 'codigo_concepto', 'posible_codigo'], true)) {
                    foreach (CuentacontableArbolSupport::variantesCodigoBusqueda($token['texto']) as $variante) {
                        $codigos[$variante] = $variante;
                    }
                    $codigos[$token['texto']] = $token['texto'];
                }
            }
        }

        return array_values($codigos);
    }

    /**
     * @param  array<int, string>  $candidatos
     * @return array{cuentas: array<string, array<string, mixed>>, conceptos_por_cuenta: array<int, array<int, array<string, mixed>>>, conceptos_por_codigo: array<string, array<int, array<string, mixed>>>}
     */
    private function indiceVinculos(int $empresaId, array $candidatos): array
    {
        $cuentas = [];
        if ($candidatos !== [] && $empresaId > 0) {
            $filasCuenta = Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->whereIn('codigo', $candidatos)
                ->get(['id', 'codigo', 'nombre']);
            foreach ($filasCuenta as $cuenta) {
                $payload = [
                    'id' => (int) $cuenta->id,
                    'codigo' => (string) $cuenta->codigo,
                    'nombre' => (string) $cuenta->nombre,
                ];
                foreach (CuentacontableArbolSupport::variantesCodigoBusqueda((string) $cuenta->codigo) as $variante) {
                    $cuentas[$this->clave($variante)] = $payload;
                }
            }
        }

        $conceptos = Concepto_Ivacompra::query()
            ->with(['concepto_ivacompra_empresas.cuentacontabledebe', 'cuentacontablesdebe'])
            ->get();

        $porCuenta = [];
        $porCodigo = [];
        foreach ($conceptos as $concepto) {
            $cuentaId = $concepto->cuentacontableDebeIdParaEmpresa($empresaId > 0 ? $empresaId : null);
            $cuentaCodigo = '';
            $cuentaNombre = '';
            if ($cuentaId > 0) {
                $linea = $concepto->lineaEmpresa($empresaId > 0 ? $empresaId : null);
                $cta = $linea?->cuentacontabledebe ?? $concepto->cuentacontablesdebe;
                if ($cta !== null && (int) $cta->id === $cuentaId) {
                    $cuentaCodigo = (string) ($cta->codigo ?? '');
                    $cuentaNombre = (string) ($cta->nombre ?? '');
                } elseif (isset($cuentas[$this->clave((string) ($cta->codigo ?? ''))])) {
                    $cuentaCodigo = (string) $cuentas[$this->clave((string) $cta->codigo)]['codigo'];
                    $cuentaNombre = (string) $cuentas[$this->clave((string) $cta->codigo)]['nombre'];
                }
            }
            if ($cuentaCodigo === '' && $cuentaId > 0) {
                $ctaDirecta = Cuentacontable::query()->find($cuentaId);
                $cuentaCodigo = (string) ($ctaDirecta->codigo ?? '');
                $cuentaNombre = (string) ($ctaDirecta->nombre ?? '');
            }

            $item = [
                'id' => (int) $concepto->id,
                'codigo' => (string) $concepto->codigo,
                'nombre' => (string) $concepto->nombre,
                'tipoconcepto' => (string) ($concepto->tipoconcepto ?? ''),
                'cuenta_debe_id' => $cuentaId,
                'cuenta_debe_codigo' => $cuentaCodigo,
                'cuenta_debe_nombre' => $cuentaNombre,
                'via' => '',
            ];
            $codigoClave = $this->clave((string) $concepto->codigo);
            if ($codigoClave !== '') {
                $porCodigo[$codigoClave][] = $item;
                $sinPorcentaje = rtrim($codigoClave, '%');
                if ($sinPorcentaje !== $codigoClave) {
                    $porCodigo[$sinPorcentaje][] = $item;
                }
            }
            if ($cuentaId > 0) {
                $itemCuenta = $item;
                $itemCuenta['via'] = 'cuenta_contable';
                $porCuenta[$cuentaId][] = $itemCuenta;
            }
        }

        return [
            'cuentas' => $cuentas,
            'conceptos_por_cuenta' => $porCuenta,
            'conceptos_por_codigo' => $porCodigo,
        ];
    }

    /**
     * @return array{tipo: string, texto: string, numero?: float}|null
     */
    private function tokenCelda(mixed $valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric(trim($valor)) && ! str_contains(trim((string) $valor), '%'))) {
            $texto = trim((string) $valor);
            $numero = (float) $texto;
            $entero = abs($numero - round($numero)) < 0.0000001;
            if ($entero && abs($numero) >= 1 && abs($numero) < 100000 && ! str_contains($texto, '.') && ! str_contains($texto, ',')) {
                $compacto = (string) (int) round($numero);
                if (strlen(preg_replace('/\D/', '', $compacto) ?? '') >= 6) {
                    return ['tipo' => 'cuenta', 'texto' => $compacto];
                }

                return ['tipo' => 'posible_codigo', 'texto' => $compacto, 'numero' => $numero];
            }
            if (abs($numero) >= 0.01) {
                return ['tipo' => 'importe', 'texto' => $texto, 'numero' => round($numero, 2)];
            }

            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        if (preg_match('/acumulado|ajuste\s*diario/i', $texto)) {
            return ['tipo' => 'encabezado', 'texto' => $texto];
        }
        if (preg_match('/^\d{1,3}\s*%$/', $texto)) {
            return ['tipo' => 'codigo_concepto', 'texto' => str_replace(' ', '', $texto)];
        }

        $compacto = preg_replace('/\s+/', '', $texto) ?? '';
        if (preg_match('/^\d{4,8}-\d{2,4}$/', $compacto)) {
            return ['tipo' => 'cuenta', 'texto' => $compacto];
        }

        $importe = $this->parseImporteTexto($texto);
        if ($importe !== null && abs($importe) >= 0.01) {
            return ['tipo' => 'importe', 'texto' => $texto, 'numero' => $importe];
        }
        if (preg_match('/^\d{1,5}$/', $compacto)) {
            return ['tipo' => 'posible_codigo', 'texto' => $compacto];
        }
        if (mb_strlen($texto) >= 3) {
            return ['tipo' => 'descripcion', 'texto' => $texto];
        }

        return ['tipo' => 'texto', 'texto' => $texto];
    }

    private function parseImporteTexto(string $texto): ?float
    {
        $limpio = str_replace(['$', ' '], '', $texto);
        if (! preg_match('/^-?[\d.,]+$/', $limpio)) {
            return null;
        }
        $ultimaComa = strrpos($limpio, ',');
        $ultimoPunto = strrpos($limpio, '.');
        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                $limpio = str_replace('.', '', $limpio);
                $limpio = str_replace(',', '.', $limpio);
            } else {
                $limpio = str_replace(',', '', $limpio);
            }
        } elseif ($ultimaComa !== false) {
            $decimales = strlen($limpio) - $ultimaComa - 1;
            if ($decimales === 2) {
                $limpio = str_replace('.', '', $limpio);
                $limpio = str_replace(',', '.', $limpio);
            } else {
                $limpio = str_replace(',', '', $limpio);
            }
        }
        if (! is_numeric($limpio)) {
            return null;
        }

        return round((float) $limpio, 2);
    }

    /**
     * @param  array<int, string>  $codigosCuenta
     * @param  array<string, array<string, mixed>>  $cuentas
     * @return array<string, mixed>|null
     */
    private function resolverCuenta(array $codigosCuenta, array $cuentas): ?array
    {
        foreach ($codigosCuenta as $codigo) {
            foreach (CuentacontableArbolSupport::variantesCodigoBusqueda($codigo) as $variante) {
                $clave = $this->clave($variante);
                if (isset($cuentas[$clave])) {
                    return $cuentas[$clave];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $codigos
     * @param  array<string, array<int, array<string, mixed>>>  $indice
     * @return array<int, array<string, mixed>>
     */
    private function conceptosPorCodigos(array $codigos, array $indice): array
    {
        $lista = [];
        $vistos = [];
        foreach ($codigos as $codigo) {
            $clave = $this->clave($codigo);
            foreach ($indice[$clave] ?? [] as $concepto) {
                $id = (int) $concepto['id'];
                if (isset($vistos[$id])) {
                    continue;
                }
                $vistos[$id] = true;
                $concepto['via'] = 'codigo_concepto';
                $lista[] = $concepto;
            }
        }

        return $lista;
    }

    /**
     * @param  array<int, array<string, mixed>>  $porCuenta
     * @param  array<int, array<string, mixed>>  $porCodigo
     * @return array<int, array<string, mixed>>
     */
    private function unirConceptos(array $porCuenta, array $porCodigo): array
    {
        $lista = [];
        $vistos = [];
        foreach (array_merge($porCuenta, $porCodigo) as $concepto) {
            $id = (int) ($concepto['id'] ?? 0);
            if ($id <= 0 || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $lista[] = [
                'id' => $id,
                'codigo' => (string) ($concepto['codigo'] ?? ''),
                'nombre' => (string) ($concepto['nombre'] ?? ''),
                'tipoconcepto' => (string) ($concepto['tipoconcepto'] ?? ''),
                'via' => (string) ($concepto['via'] ?? ''),
                'cuenta_debe_id' => (int) ($concepto['cuenta_debe_id'] ?? 0),
                'cuenta_debe_codigo' => (string) ($concepto['cuenta_debe_codigo'] ?? ''),
                'cuenta_debe_nombre' => (string) ($concepto['cuenta_debe_nombre'] ?? ''),
            ];
        }

        return $lista;
    }

    /**
     * @param  array<int, array<string, mixed>>  $a
     * @param  array<int, array<string, mixed>>  $b
     */
    private function mismosIds(array $a, array $b): bool
    {
        $idsA = array_map(static fn (array $item): int => (int) $item['id'], $a);
        $idsB = array_map(static fn (array $item): int => (int) $item['id'], $b);
        sort($idsA);
        sort($idsB);

        return $idsA === $idsB;
    }

    private function clave(string $codigo): string
    {
        return strtoupper(str_replace(' ', '', trim($codigo)));
    }
}
