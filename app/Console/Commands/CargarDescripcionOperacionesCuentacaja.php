<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Carga cuentacaja.descripcion_operaciones desde Anita valormae.valm_desc
 * resolviendo por valm_cuenta_fin → cuentacaja.codigo (y empresa).
 *
 * Misma resolución de cuenta que rendicion-maquina:mapear-valormae-uso.
 */
class CargarDescripcionOperacionesCuentacaja extends Command
{
    protected $signature = 'cuentacaja:cargar-descripcion-operaciones
        {--aplicar : Persiste descripcion_operaciones (sin esto solo informa)}
        {--solo-vacias : No sobrescribe si ya hay descripción}';

    protected $description = 'Carga descripción para operaciones desde Anita valormae (valm_desc por valm_cuenta_fin)';

    /** Preferir tipos de valores de operación / rendición al resolver conflictos. */
    private const TIPOS_PREFERIDOS = ['0', '1', '2', '5', '7', '8', '9', '6', 'A', 'B', '3', '4'];

    public function handle(): int
    {
        $empresasPorCodigoAnita = $this->mapaEmpresaAnitaAErp();

        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'caja',
            'tabla' => 'valormae',
            'campos' => 'valm_codigo,valm_desc,valm_tipo_valor,valm_cuenta_fin,valm_empresa',
        ]);
        $filas = ApiAnita::decodificarListaFilas($raw);
        if ($filas === []) {
            $this->warn('Anita no devolvió filas de valormae.');

            return self::FAILURE;
        }

        /** @var array<int, array{desc: string, tipo: string, valormae: int, alcance: string}> */
        $porCuenta = [];
        $sinCuenta = 0;
        $sinEmpresa = 0;
        $report = [];

        foreach ($filas as $row) {
            $codigoValor = (int) ($row->valm_codigo ?? 0);
            $desc = trim((string) ($row->valm_desc ?? ''));
            $cuentaFin = trim((string) ($row->valm_cuenta_fin ?? ''));
            $tipo = trim((string) ($row->valm_tipo_valor ?? ''));
            $empresaAnita = (int) ($row->valm_empresa ?? 0);

            if ($desc === '' || $cuentaFin === '') {
                continue;
            }

            $codigoCaja = ltrim($cuentaFin, '0');
            if ($codigoCaja === '') {
                $codigoCaja = '0';
            }

            $empresaErpId = $empresasPorCodigoAnita[$empresaAnita] ?? null;
            if ($empresaAnita > 0 && $empresaErpId === null) {
                $sinEmpresa++;
                $report[] = [
                    'estado' => 'SIN_EMPRESA_ERP',
                    'valormae' => $codigoValor,
                    'desc' => $desc,
                    'cuenta_fin' => $cuentaFin,
                    'emp_anita' => $empresaAnita,
                    'cuentacaja_id' => null,
                    'alcance' => null,
                ];
                continue;
            }

            $resuelto = $this->resolverCuentacaja($codigoCaja, $empresaErpId);
            if ($resuelto === null) {
                $sinCuenta++;
                $report[] = [
                    'estado' => 'SIN_CUENTACAJA',
                    'valormae' => $codigoValor,
                    'desc' => $desc,
                    'cuenta_fin' => $cuentaFin,
                    'emp_anita' => $empresaAnita,
                    'cuentacaja_id' => null,
                    'alcance' => null,
                ];
                continue;
            }

            [$cuenta, $alcance] = $resuelto;
            $cuentaId = (int) $cuenta->id;
            $candidato = [
                'desc' => mb_substr($desc, 0, 60),
                'tipo' => $tipo,
                'valormae' => $codigoValor,
                'alcance' => $alcance,
            ];

            if (! isset($porCuenta[$cuentaId]) || $this->esPreferible($candidato, $porCuenta[$cuentaId])) {
                $porCuenta[$cuentaId] = $candidato;
            }

            $report[] = [
                'estado' => 'OK',
                'valormae' => $codigoValor,
                'desc' => $desc,
                'cuenta_fin' => $cuentaFin,
                'emp_anita' => $empresaAnita,
                'cuentacaja_id' => $cuentaId,
                'alcance' => $alcance,
            ];
        }

        $actualizadas = 0;
        $omitidas = 0;
        $yaIguales = 0;

        foreach ($porCuenta as $cuentaId => $candidato) {
            $cuenta = Cuentacaja::query()->find($cuentaId);
            if (! $cuenta) {
                continue;
            }

            $actual = trim((string) ($cuenta->descripcion_operaciones ?? ''));
            $nueva = $candidato['desc'];

            if ($actual === $nueva) {
                $yaIguales++;
                continue;
            }

            if ($this->option('solo-vacias') && $actual !== '') {
                $omitidas++;
                continue;
            }

            if ($this->option('aplicar')) {
                $cuenta->descripcion_operaciones = $nueva;
                $cuenta->save();
                $actualizadas++;
            } else {
                $actualizadas++;
            }
        }

        $this->table(
            ['estado', 'valormae', 'desc', 'cuenta_fin', 'emp_anita', 'cuentacaja_id', 'alcance'],
            $report
        );

        $this->newLine();
        $this->line('Sin empresa ERP: '.$sinEmpresa);
        $this->line('Sin cuentacaja: '.$sinCuenta);
        $this->line('Cuentas candidatas: '.count($porCuenta));
        $this->line('Ya iguales: '.$yaIguales);
        $this->line('Omitidas (solo-vacias): '.$omitidas);
        $this->line($this->option('aplicar')
            ? 'Cuentas actualizadas: '.$actualizadas
            : 'Pendientes (dry-run): '.$actualizadas.' — reejecutar con --aplicar');

        return self::SUCCESS;
    }

    /**
     * @param  array{desc: string, tipo: string, valormae: int, alcance: string}  $candidato
     * @param  array{desc: string, tipo: string, valormae: int, alcance: string}  $actual
     */
    private function esPreferible(array $candidato, array $actual): bool
    {
        $prioC = $this->prioridadTipo($candidato['tipo']);
        $prioA = $this->prioridadTipo($actual['tipo']);
        if ($prioC !== $prioA) {
            return $prioC < $prioA;
        }

        // Particular sobre compartida
        if ($candidato['alcance'] === 'particular' && $actual['alcance'] !== 'particular') {
            return true;
        }

        return false;
    }

    private function prioridadTipo(string $tipo): int
    {
        $idx = array_search($tipo, self::TIPOS_PREFERIDOS, true);

        return $idx === false ? 100 : $idx;
    }

    /**
     * @return array{0: Cuentacaja, 1: string}|null
     */
    private function resolverCuentacaja(string $codigoCaja, ?int $empresaErpId): ?array
    {
        if ($empresaErpId !== null && $empresaErpId > 0) {
            $particular = Cuentacaja::query()
                ->where('codigo', $codigoCaja)
                ->where('empresa_id', $empresaErpId)
                ->orderBy('id')
                ->first();
            if ($particular) {
                return [$particular, 'particular'];
            }
        }

        $compartida = Cuentacaja::query()
            ->where('codigo', $codigoCaja)
            ->whereNull('empresa_id')
            ->orderBy('id')
            ->first();
        if ($compartida) {
            return [$compartida, 'compartida'];
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function mapaEmpresaAnitaAErp(): array
    {
        /** @var Collection<int, Empresa> $empresas */
        $empresas = Empresa::query()->get(['id', 'codigo']);
        $mapa = [];
        foreach ($empresas as $empresa) {
            $codigo = trim((string) ($empresa->codigo ?? ''));
            if ($codigo !== '' && ctype_digit($codigo)) {
                $mapa[(int) $codigo] = (int) $empresa->id;
            }
        }

        return $mapa;
    }
}
