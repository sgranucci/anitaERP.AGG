<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Usocuentacaja;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaVariables;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Inventario / asignación del uso "Rendición de máquinas" desde valormae Anita.
 *
 * Resolución de cuenta (por empresa):
 * 1) particular: cuentacaja.codigo + empresa_id = ERP de valm_empresa
 * 2) compartida: cuentacaja.codigo + empresa_id NULL
 * Nunca toma la cuenta particular de otra empresa.
 */
class MapearValormaeUsoRendicionMaquina extends Command
{
    protected $signature = 'rendicion-maquina:mapear-valormae-uso
        {--aplicar : Asigna el uso a las cuentacaja resueltas (sin esto solo informa)}
        {--incluir-tickets : Incluye tipos ticket/canje (4) además de efectivo/QR/divisas}';

    protected $description = 'Inventario valormae→cuentacaja (por empresa) y uso Rendición de máquinas';

    /** @var list<string> */
    private const TIPOS_DEFAULT = ['0', '1', '2', '5', '7', '8', '9'];

    public function handle(): int
    {
        $usoNombre = RendicionMaquinaVariables::USO_CUENTACAJA_NOMBRE;
        $uso = Usocuentacaja::query()->where('nombre', $usoNombre)->first();
        if (! $uso) {
            $this->error("No existe usocuentacaja '{$usoNombre}'. Correr migración de apertura de gasto.");

            return self::FAILURE;
        }

        $tipos = self::TIPOS_DEFAULT;
        if ($this->option('incluir-tickets')) {
            $tipos[] = '4';
        }

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

        $omitidasTipo = 0;
        $sinCuenta = 0;
        $sinEmpresa = 0;
        $yaAsignadas = 0;
        $pendientes = 0;
        $asignadas = 0;
        $report = [];
        /** @var array<int, true> cuentacaja ya tocadas en este run (evitar doble sync) */
        $cuentasProcesadas = [];

        foreach ($filas as $row) {
            $tipo = trim((string) ($row->valm_tipo_valor ?? ''));
            if (! in_array($tipo, $tipos, true)) {
                $omitidasTipo++;
                continue;
            }

            $codigoValor = (int) ($row->valm_codigo ?? 0);
            $desc = trim((string) ($row->valm_desc ?? ''));
            $cuentaFin = trim((string) ($row->valm_cuenta_fin ?? ''));
            $empresaAnita = (int) ($row->valm_empresa ?? 0);
            $codigoCaja = ltrim($cuentaFin, '0');
            if ($codigoCaja === '') {
                $codigoCaja = '0';
            }

            $empresaErpId = $empresasPorCodigoAnita[$empresaAnita] ?? null;
            if ($empresaAnita > 0 && $empresaErpId === null) {
                $sinEmpresa++;
                $report[] = $this->filaReporte(
                    'SIN_EMPRESA_ERP',
                    $codigoValor,
                    $desc,
                    $tipo,
                    $cuentaFin,
                    $empresaAnita,
                    null,
                    null,
                    null
                );
                continue;
            }

            $resuelto = $this->resolverCuentacaja($codigoCaja, $empresaErpId);
            if ($resuelto === null) {
                $sinCuenta++;
                $report[] = $this->filaReporte(
                    'SIN_CUENTACAJA',
                    $codigoValor,
                    $desc,
                    $tipo,
                    $cuentaFin,
                    $empresaAnita,
                    $empresaErpId,
                    null,
                    null
                );
                continue;
            }

            [$cuenta, $alcance] = $resuelto;
            $cuentaId = (int) $cuenta->id;
            $tieneUso = $cuenta->usocuentacajas()->where('usocuentacaja.id', $uso->id)->exists();

            if ($tieneUso) {
                $yaAsignadas++;
                $estado = 'YA_ASIGNADA';
            } elseif ($this->option('aplicar')) {
                if (! isset($cuentasProcesadas[$cuentaId])) {
                    $cuenta->usocuentacajas()->syncWithoutDetaching([$uso->id]);
                    $cuentasProcesadas[$cuentaId] = true;
                    $asignadas++;
                    $estado = 'ASIGNADA';
                } else {
                    $estado = 'ASIGNADA_DUP';
                }
            } else {
                $pendientes++;
                $estado = 'PENDIENTE';
            }

            $report[] = $this->filaReporte(
                $estado,
                $codigoValor,
                $desc,
                $tipo,
                $cuentaFin,
                $empresaAnita,
                $empresaErpId,
                $cuentaId,
                $alcance
            );
        }

        $this->table(
            ['estado', 'valormae', 'desc', 'tipo', 'cuenta_fin', 'emp_anita', 'emp_erp', 'cuentacaja_id', 'alcance'],
            $report
        );

        $this->newLine();
        $this->info("Uso id={$uso->id} '{$usoNombre}'");
        $this->line('Omitidas por tipo: '.$omitidasTipo);
        $this->line('Sin empresa ERP: '.$sinEmpresa);
        $this->line('Sin cuentacaja: '.$sinCuenta);
        $this->line('Ya asignadas: '.$yaAsignadas);
        $this->line($this->option('aplicar')
            ? 'Cuentas con uso asignado ahora: '.$asignadas
            : 'Pendientes (dry-run): '.$pendientes.' — reejecutar con --aplicar');

        return self::SUCCESS;
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
     * Anita valm_empresa → ERP empresa.id (vía empresa.codigo numérico).
     *
     * @return array<int, int> codigoAnita => empresaId
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

    /**
     * @return array<string, int|string|null>
     */
    private function filaReporte(
        string $estado,
        int $valormae,
        string $desc,
        string $tipo,
        string $cuentaFin,
        int $empresaAnita,
        ?int $empresaErp,
        ?int $cuentacajaId,
        ?string $alcance,
    ): array {
        return [
            'estado' => $estado,
            'valormae' => $valormae,
            'desc' => $desc,
            'tipo' => $tipo,
            'cuenta_fin' => $cuentaFin,
            'emp_anita' => $empresaAnita,
            'emp_erp' => $empresaErp,
            'cuentacaja_id' => $cuentacajaId,
            'alcance' => $alcance,
        ];
    }
}
