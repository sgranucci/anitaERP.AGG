<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Cuentacontable;
use App\Repositories\Ventas\Concepto_VentaRepositoryInterface;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Trae concepto + concod + concta de Anita y arma master + cuentas ERP.
 */
class ConceptoVentaAnitaImportService
{
    public function __construct(
        private readonly Concepto_VentaRepositoryInterface $repository,
    ) {}

    /**
     * @return array{
     *     en_anita: int,
     *     crear: int,
     *     actualizar: int,
     *     cuentas: int,
     *     omitidos: int,
     *     errores: list<string>,
     *     detalle: list<array<string, mixed>>
     * }
     */
    public function analizar(): array
    {
        return $this->procesar(false);
    }

    /**
     * @return array{
     *     en_anita: int,
     *     crear: int,
     *     actualizar: int,
     *     cuentas: int,
     *     omitidos: int,
     *     errores: list<string>,
     *     detalle: list<array<string, mixed>>
     * }
     */
    public function ejecutar(): array
    {
        return $this->procesar(true);
    }

    /**
     * @return array{
     *     en_anita: int,
     *     crear: int,
     *     actualizar: int,
     *     cuentas: int,
     *     omitidos: int,
     *     errores: list<string>,
     *     detalle: list<array<string, mixed>>
     * }
     */
    private function procesar(bool $persistir): array
    {
        $ret = [
            'en_anita' => 0,
            'crear' => 0,
            'actualizar' => 0,
            'cuentas' => 0,
            'omitidos' => 0,
            'errores' => [],
            'detalle' => [],
        ];

        $lineas = $this->listarTabla('concepto', 'con_codigo,con_linea,con_concepto', 'con_codigo,con_linea');
        $gtins = $this->indexarGtin($this->listarTabla('concod', 'concd_concepto,concd_codigo', 'concd_concepto'));
        $cuentasAnita = $this->listarTabla('concta', 'conc_concepto,conc_linea,conc_cuenta,conc_sucursal', 'conc_concepto,conc_linea');

        $porCodigo = [];
        foreach ($lineas as $fila) {
            $codigo = (int) ($fila['con_codigo'] ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $texto = trim((string) ($fila['con_concepto'] ?? ''));
            $porCodigo[$codigo][] = $texto;
        }

        $ret['en_anita'] = count($porCodigo);
        $empresas = Empresa::query()->get();
        $usuarioId = (int) (auth()->id() ?? 1);
        if ($usuarioId <= 0) {
            $usuarioId = 1;
        }

        foreach ($porCodigo as $codigoAnita => $textos) {
            $textos = array_values(array_filter($textos, fn ($t) => $t !== ''));
            if ($textos === []) {
                $ret['omitidos']++;
                continue;
            }

            $nombre = mb_substr($textos[0], 0, 80);
            $descripcion = mb_substr(implode(' ', $textos), 0, 255);
            $gtin = $gtins[$codigoAnita] ?? null;
            $existente = $this->repository->findPorCodigoAnita($codigoAnita);
            $codigoErp = $existente?->codigo ?? (string) $codigoAnita;

            $cuentas = $this->resolverCuentas($codigoAnita, $cuentasAnita, $empresas);
            $accion = $existente ? 'actualizar' : 'crear';
            $ret[$accion]++;
            $ret['cuentas'] += count($cuentas);
            $ret['detalle'][] = [
                'codigo_anita' => $codigoAnita,
                'codigo' => $codigoErp,
                'nombre' => $nombre,
                'gtin' => $gtin,
                'accion' => $accion,
                'cuentas' => count($cuentas),
            ];

            if (! $persistir) {
                continue;
            }

            try {
                $payload = [
                    'codigo' => $codigoErp,
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'codigo_gtin' => $gtin,
                    'unidades_mtx' => 1,
                    'activo' => true,
                    'codigo_anita' => $codigoAnita,
                ];
                if ($existente) {
                    $this->repository->update($payload, $existente->id);
                    $conceptoId = (int) $existente->id;
                } else {
                    $concepto = $this->repository->create($payload);
                    $conceptoId = (int) $concepto->id;
                }
                $filasCuenta = [];
                foreach ($cuentas as $cuenta) {
                    $filasCuenta[] = [
                        'empresa_id' => $cuenta['empresa_id'] ?? 0,
                        'cuentacontable_id' => $cuenta['cuentacontable_id'] ?? 0,
                        'creousuario_id' => $usuarioId,
                    ];
                }
                $this->repository->sincronizarCuentas($conceptoId, $filasCuenta);
            } catch (\Throwable $e) {
                $msg = "Concepto Anita {$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('ConceptoVentaAnitaImport: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarTabla(string $tabla, string $campos, string $orderBy): array
    {
        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $tabla,
            'campos' => $campos,
            'orderBy' => $orderBy,
        ]);
        $error = ApiAnita::extraerMensajeError($raw);
        if ($error !== null) {
            throw new \RuntimeException("Anita {$tabla}: {$error}");
        }
        $filas = json_decode((string) $raw, true);

        return is_array($filas) ? $filas : [];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<int, string>
     */
    private function indexarGtin(array $filas): array
    {
        $map = [];
        foreach ($filas as $fila) {
            $codigo = (int) ($fila['concd_concepto'] ?? 0);
            $gtin = preg_replace('/\D+/', '', (string) ($fila['concd_codigo'] ?? ''));
            $gtin = \App\Support\Ventas\GtinEan13Support::normalizar($gtin);
            if ($codigo > 0 && $gtin !== null) {
                $map[$codigo] = $gtin;
            }
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $cuentasAnita
     * @param  \Illuminate\Support\Collection<int, Empresa>  $empresas
     * @return list<array{empresa_id: int, cuentacontable_id: int}>
     */
    private function resolverCuentas(int $codigoAnita, array $cuentasAnita, $empresas): array
    {
        $out = [];
        $vistos = [];
        foreach ($cuentasAnita as $fila) {
            if ((int) ($fila['conc_concepto'] ?? 0) !== $codigoAnita) {
                continue;
            }
            $cuentaAnita = trim((string) ($fila['conc_cuenta'] ?? ''));
            if ($cuentaAnita === '' || $cuentaAnita === '0') {
                continue;
            }
            $sucursal = (int) ($fila['conc_sucursal'] ?? -1);
            $empresasDestino = $sucursal <= 0
                ? $empresas
                : $empresas->filter(function (Empresa $empresa) use ($sucursal) {
                    return SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $empresa->id) === $sucursal
                        || (int) $empresa->codigo === $sucursal
                        || (int) $empresa->id === $sucursal;
                });

            foreach ($empresasDestino as $empresa) {
                $cuenta = Cuentacontable::query()
                    ->where('empresa_id', $empresa->id)
                    ->where('codigo', $cuentaAnita)
                    ->first();
                if ($cuenta === null) {
                    continue;
                }
                $clave = $empresa->id.':'.$cuenta->id;
                if (isset($vistos[$clave])) {
                    continue;
                }
                $vistos[$clave] = true;
                $out[] = [
                    'empresa_id' => (int) $empresa->id,
                    'cuentacontable_id' => (int) $cuenta->id,
                ];
            }
        }

        return $out;
    }
}
