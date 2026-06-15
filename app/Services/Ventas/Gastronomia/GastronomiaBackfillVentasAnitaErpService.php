<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Config;

/**
 * Backfill Anita → ERP: importa ventas faltantes detectadas por correlatividad (huecos + solo Anita).
 */
final class GastronomiaBackfillVentasAnitaErpService
{
    public function __construct(
        private readonly GastronomiaControlCorrelatividadAnitaErpService $correlatividadService,
        private readonly GastronomiaFacturaImportacionAnitaService $importacionService,
    ) {}

    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *   ventana: array<string, string>,
     *   correlatividad_previa: array<string, mixed>,
     *   rangos: list<array<string, mixed>>,
     *   importados: int,
     *   omitidos: int,
     *   errores: list<string>
     * }
     */
    public function ejecutar(
        string $fechaJornada,
        array $empresaIds,
        int $usuarioId,
        bool $dryRun = false,
    ): array {
        Config::set(
            'gastronomia.genera_contabilidad_al_cobrar',
            (bool) config('gastronomia_anita_import.genera_contabilidad_cobranza', false),
        );

        $corr = $this->correlatividadService->ejecutar($fechaJornada, $empresaIds);
        $numerosPorPv = $this->filtrarNumerosPorAnitaEmpresa($this->recolectarNumerosFaltantes($corr));
        $rangos = $this->agruparRangos($numerosPorPv);

        $ret = [
            'jornada' => ['fecha' => $fechaJornada],
            'correlatividad_previa' => $corr['resumen'],
            'rangos' => $rangos,
            'importados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        foreach ($rangos as $rango) {
            $resultado = $this->importacionService->importarRango(
                (int) $rango['sucursal'],
                (int) $rango['desde'],
                (int) $rango['hasta'],
                (int) $rango['empresa_id'],
                $usuarioId,
                $dryRun,
                (string) ($rango['identificador_pc'] ?? ''),
            );

            $ret['importados'] += $resultado['importados'];
            $ret['omitidos'] += $resultado['omitidos'];
            $ret['errores'] = array_merge($ret['errores'], $resultado['errores']);
        }

        return $ret;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *   ventana: array<string, string>,
     *   correlatividad_previa: array<string, mixed>,
     *   rangos: list<array<string, mixed>>
     * }
     */
    public function planificar(
        string $fechaJornada,
        array $empresaIds,
    ): array {
        $corr = $this->correlatividadService->ejecutar($fechaJornada, $empresaIds);
        $numerosPorPv = $this->filtrarNumerosPorAnitaEmpresa($this->recolectarNumerosFaltantes($corr));

        return [
            'jornada' => ['fecha' => $fechaJornada],
            'correlatividad_previa' => $corr['resumen'],
            'rangos' => $this->agruparRangos($numerosPorPv),
        ];
    }

    /**
     * Conserva solo números que existen en Anita para la empresa del PV (ven_empresa + FAK/FAC).
     *
     * @param  array<string, array{pv_codigo:string,empresa_id:int,sucursal:int,numeros:list<int>}>  $numerosPorPv
     * @return array<string, array{pv_codigo:string,empresa_id:int,sucursal:int,numeros:list<int>,descartados:list<int>}>
     */
    private function filtrarNumerosPorAnitaEmpresa(array $numerosPorPv): array
    {
        foreach ($numerosPorPv as $clave => $row) {
            $numeros = $row['numeros'] ?? [];
            if ($numeros === []) {
                $numerosPorPv[$clave]['descartados'] = [];
                continue;
            }

            $pvCodigo = (string) $row['pv_codigo'];
            $empresaId = (int) $row['empresa_id'];
            $sucursal = (int) $row['sucursal'];
            $pc = $this->resolverIdentificadorPc($sucursal, $empresaId, $pvCodigo);
            $disponibles = $this->importacionService->listarNumerosDisponiblesEnAnita(
                $sucursal,
                min($numeros),
                max($numeros),
                $empresaId,
                $pc,
            );
            $set = array_fill_keys($disponibles, true);
            $validos = [];
            $descartados = [];

            foreach ($numeros as $nro) {
                if (isset($set[$nro])) {
                    $validos[] = $nro;
                } else {
                    $descartados[] = $nro;
                }
            }

            $numerosPorPv[$clave]['numeros'] = $validos;
            $numerosPorPv[$clave]['descartados'] = $descartados;
        }

        return $numerosPorPv;
    }

    /**
     * @param  array{huecos:list<array<string,mixed>>,filas:list<array<string,mixed>>}  $corr
     * @return array<string, array{pv_codigo:string,empresa_id:int,sucursal:int,numeros:list<int>}>
     */
    private function recolectarNumerosFaltantes(array $corr): array
    {
        $porPv = [];

        foreach ($corr['huecos'] as $hueco) {
            $pvCodigo = (string) ($hueco['pv_codigo'] ?? '');
            $empresaId = (int) ($hueco['empresa_id'] ?? 0);
            if (! $this->esPuntoventaGastronomia($pvCodigo, $empresaId)) {
                continue;
            }
            $clave = $this->clavePv($pvCodigo, $empresaId);
            if ($clave === '') {
                continue;
            }

            foreach (explode(',', (string) ($hueco['faltantes'] ?? '')) as $part) {
                $nro = (int) trim($part);
                if ($nro <= 0) {
                    continue;
                }
                $porPv[$clave]['pv_codigo'] = $pvCodigo;
                $porPv[$clave]['empresa_id'] = $empresaId;
                $porPv[$clave]['sucursal'] = $this->sucursalDesdeCodigoPv($pvCodigo);
                $porPv[$clave]['numeros'][$nro] = $nro;
            }
        }

        foreach ($corr['filas'] as $fila) {
            if (($fila['estado'] ?? '') !== 'solo_anita') {
                continue;
            }

            $pvCodigo = (string) ($fila['pv_codigo'] ?? '');
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            if (! $this->esPuntoventaGastronomia($pvCodigo, $empresaId)) {
                continue;
            }
            $nro = (int) ($fila['anita_nro'] ?? $fila['numero'] ?? 0);
            if ($nro <= 0) {
                continue;
            }

            $clave = $this->clavePv($pvCodigo, $empresaId);
            if ($clave === '') {
                continue;
            }

            if ($this->ventaErpExiste($pvCodigo, $nro)) {
                continue;
            }

            $porPv[$clave]['pv_codigo'] = $pvCodigo;
            $porPv[$clave]['empresa_id'] = $empresaId;
            $porPv[$clave]['sucursal'] = $this->sucursalDesdeCodigoPv($pvCodigo);
            $porPv[$clave]['numeros'][$nro] = $nro;
        }

        foreach ($porPv as $clave => $row) {
            $numeros = array_values($row['numeros'] ?? []);
            sort($numeros, SORT_NUMERIC);
            $porPv[$clave]['numeros'] = $numeros;
        }

        uasort($porPv, static fn (array $a, array $b) => strcmp((string) $a['pv_codigo'], (string) $b['pv_codigo']));

        return $porPv;
    }

    /**
     * @param  array<string, array{pv_codigo:string,empresa_id:int,sucursal:int,numeros:list<int>}>  $numerosPorPv
     * @return list<array<string, mixed>>
     */
    private function agruparRangos(array $numerosPorPv): array
    {
        $rangos = [];

        foreach ($numerosPorPv as $row) {
            $numeros = $row['numeros'] ?? [];
            if ($numeros === []) {
                continue;
            }

            $pvCodigo = (string) $row['pv_codigo'];
            $empresaId = (int) $row['empresa_id'];
            $sucursal = (int) $row['sucursal'];
            $pc = $this->resolverIdentificadorPc($sucursal, $empresaId, $pvCodigo);

            $desde = null;
            $prev = null;

            foreach ($numeros as $nro) {
                if ($desde === null) {
                    $desde = $nro;
                    $prev = $nro;
                    continue;
                }

                if ($nro === $prev + 1) {
                    $prev = $nro;
                    continue;
                }

                $rangos[] = [
                    'pv_codigo' => $pvCodigo,
                    'empresa_id' => $empresaId,
                    'sucursal' => $sucursal,
                    'tipo_anita' => $this->tipoAnitaEtiqueta($empresaId, $pvCodigo),
                    'desde' => $desde,
                    'hasta' => $prev,
                    'cantidad' => ($prev - $desde + 1),
                    'identificador_pc' => $pc,
                    'descartados' => $row['descartados'] ?? [],
                ];
                $desde = $nro;
                $prev = $nro;
            }

            if ($desde !== null && $prev !== null) {
                $rangos[] = [
                    'pv_codigo' => $pvCodigo,
                    'empresa_id' => $empresaId,
                    'sucursal' => $sucursal,
                    'tipo_anita' => $this->tipoAnitaEtiqueta($empresaId, $pvCodigo),
                    'desde' => $desde,
                    'hasta' => $prev,
                    'cantidad' => ($prev - $desde + 1),
                    'identificador_pc' => $pc,
                    'descartados' => $row['descartados'] ?? [],
                ];
            }
        }

        return $rangos;
    }

    private function tipoAnitaEtiqueta(int $empresaId, string $pvCodigo): string
    {
        $pv = Puntoventa::query()->with('empresas')->where('codigo', $pvCodigo)->where('empresa_id', $empresaId)->first();
        if ($pv === null) {
            return 'FAC';
        }

        return \App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport::tipoVentaAnita(
            $pv,
            \App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport::codigoEmpresa($empresaId),
        );
    }

    private function ventaErpExiste(string $pvCodigo, int $numero): bool
    {
        $digSuc = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digNro = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);
        $codigo = 'FAC B-'
            .str_pad($pvCodigo, $digSuc, '0', STR_PAD_LEFT).'-'
            .str_pad((string) $numero, $digNro, '0', STR_PAD_LEFT);

        return Venta::query()->where('codigo', $codigo)->exists();
    }

    private function resolverIdentificadorPc(int $sucursal, int $empresaId, string $pvCodigo): string
    {
        $map = config('gastronomia_anita_import.identificador_pc_por_sucursal', []);
        $pc = trim((string) ($map[$sucursal] ?? ''));
        if ($pc !== '') {
            return $pc;
        }

        $pv = Puntoventa::query()
            ->where('codigo', $pvCodigo)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($pv === null) {
            throw new \InvalidArgumentException('Sin identificador_pc para sucursal '.$sucursal.' empresa '.$empresaId.'.');
        }

        $pcDb = Venta::query()
            ->join('venta_gastronomia_emision', 'venta_gastronomia_emision.venta_id', '=', 'venta.id')
            ->where('venta.puntoventa_id', $pv->id)
            ->whereNotNull('venta_gastronomia_emision.identificador_pc')
            ->selectRaw('venta_gastronomia_emision.identificador_pc as pc, COUNT(*) as n')
            ->groupBy('venta_gastronomia_emision.identificador_pc')
            ->orderByDesc('n')
            ->value('pc');

        $pcDb = is_string($pcDb) ? trim($pcDb) : '';
        if ($pcDb === '') {
            throw new \InvalidArgumentException('Sin identificador_pc para sucursal '.$sucursal.' empresa '.$empresaId.'.');
        }

        return $pcDb;
    }

    private function sucursalDesdeCodigoPv(string $pvCodigo): int
    {
        return (int) ltrim($pvCodigo, '0');
    }

    private function clavePv(string $pvCodigo, int $empresaId): string
    {
        if ($pvCodigo === '' || $empresaId <= 0) {
            return '';
        }

        return $pvCodigo.'|'.$empresaId;
    }

    private function esPuntoventaGastronomia(string $pvCodigo, int $empresaId): bool
    {
        $pv = Puntoventa::query()
            ->where('codigo', $pvCodigo)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($pv === null) {
            return false;
        }

        return ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($pv): void {
                $q->where('puntoventa_cae_id', $pv->id)
                    ->orWhere('puntoventa_caea_id', $pv->id);
            })
            ->exists();
    }
}
