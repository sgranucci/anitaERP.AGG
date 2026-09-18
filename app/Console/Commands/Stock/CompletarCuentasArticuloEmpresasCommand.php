<?php

namespace App\Console\Commands\Stock;

use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloCuentacontableEmpresasSupport;
use Illuminate\Console\Command;

class CompletarCuentasArticuloEmpresasCommand extends Command
{
    protected $signature = 'stock:completar-cuentas-articulo-empresas
                            {--empresas=1,2,3 : Empresas objetivo (Biyemas, Kandiko, Rebisco)}
                            {--origen=1 : Empresa origen preferida para copiar tipos}
                            {--aplicar : Graba altas y homologaciones (sin esto solo informa)}
                            {--usuario=1 : creousuario_id para altas sin usuario origen}
                            {--mostrar=20 : Filas de muestra en consola (0 = omitir)}
                            {--csv= : Exporta altas/errores a CSV}';

    protected $description = 'Completa cuentas contables de artículos en las 3 empresas y homóloga IDs al plan de cada una.';

    public function handle(): int
    {
        $empresaIds = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('empresas'))
        ), fn (int $id) => $id > 0));
        if ($empresaIds === []) {
            $empresaIds = ArticuloCuentacontableEmpresasSupport::empresasObjetivo();
        }
        $origen = (int) $this->option('origen');
        $aplicar = (bool) $this->option('aplicar');
        $usuarioId = max(1, (int) $this->option('usuario'));
        $mostrar = max(0, (int) $this->option('mostrar'));

        if ($origen <= 0 || ! in_array($origen, $empresaIds, true)) {
            $this->error('Origen inválido: debe ser una de las empresas objetivo.');

            return self::FAILURE;
        }

        if (! $aplicar) {
            $this->warn('Simulación: no se graba. Use --aplicar para completar.');
        }

        $plan = ArticuloCuentacontableEmpresasSupport::ejecutar($aplicar, $empresaIds, $origen, $usuarioId);

        $this->info(sprintf(
            'Empresas [%s] | origen %d%s',
            implode(', ', $empresaIds),
            $origen,
            $plan['aplicado'] ? ' | APLICADO' : ''
        ));
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Artículos totales', $plan['articulos_total']],
                ['Con alguna cuenta', $plan['articulos_con_cuenta']],
                ['Sin cuentas (no se pueden inventar)', $plan['articulos_sin_cuenta']],
                ['Solo en empresa origen', $plan['articulos_solo_origen']],
                ['Incompletos (faltan filas)', $plan['articulos_incompletos']],
                ['Altas a crear', count($plan['altas'])],
                ['Homologaciones (cuenta de otra empresa)', count($plan['homologaciones'])],
                ['Errores (sin cuenta homologada)', count($plan['errores'])],
                ['Altas grabadas', $plan['altas_aplicadas']],
                ['Homologaciones grabadas', $plan['homologaciones_aplicadas']],
            ]
        );

        $idsMuestra = [];
        $csv = $this->option('csv');
        $cargarTodos = is_string($csv) && $csv !== '';
        $fuenteAltas = $cargarTodos ? $plan['altas'] : array_slice($plan['altas'], 0, max($mostrar, 50));
        $fuenteErrores = $cargarTodos ? $plan['errores'] : array_slice($plan['errores'], 0, max($mostrar, 50));
        foreach ($fuenteAltas as $alta) {
            $idsMuestra[(int) $alta['articulo_id']] = true;
        }
        foreach ($fuenteErrores as $error) {
            $idsMuestra[(int) $error['articulo_id']] = true;
        }
        $articulos = $idsMuestra === []
            ? collect()
            : Articulo::query()
                ->whereIn('id', array_keys($idsMuestra))
                ->get(['id', 'sku', 'descripcion', 'estado'])
                ->keyBy('id');

        if ($mostrar > 0 && $plan['altas'] !== []) {
            $this->newLine();
            $this->comment('Muestra de altas (hasta '.$mostrar.'):');
            $this->table(
                ['Artículo', 'SKU', 'Estado', 'Emp dest', 'Tipo', 'Código'],
                collect($plan['altas'])->take($mostrar)->map(function (array $alta) use ($articulos) {
                    $art = $articulos->get((int) $alta['articulo_id']);

                    return [
                        $alta['articulo_id'],
                        $art->sku ?? '',
                        $art->estado ?? '',
                        $alta['empresa_id'],
                        $alta['tipoimputacion'],
                        $alta['codigo'],
                    ];
                })->all()
            );
        }

        if ($mostrar > 0 && $plan['errores'] !== []) {
            $this->newLine();
            $this->warn('Errores (hasta '.$mostrar.'):');
            $this->table(
                ['Artículo', 'SKU', 'Emp', 'Tipo', 'Código', 'Detalle'],
                collect($plan['errores'])->take($mostrar)->map(function (array $error) use ($articulos) {
                    $art = $articulos->get((int) $error['articulo_id']);

                    return [
                        $error['articulo_id'],
                        $art->sku ?? '',
                        $error['empresa_id'],
                        $error['tipoimputacion'],
                        $error['codigo'] ?? '',
                        $error['detalle'],
                    ];
                })->all()
            );
        }

        if (is_string($csv) && $csv !== '') {
            $this->exportarCsv($csv, $plan, $articulos);
            $this->info('CSV: '.$csv);
        }

        if (! $aplicar && (count($plan['altas']) > 0 || count($plan['homologaciones']) > 0)) {
            $this->comment('Para grabar: php artisan stock:completar-cuentas-articulo-empresas --aplicar');
        }

        return count($plan['errores']) > 0 && count($plan['altas']) === 0 && count($plan['homologaciones']) === 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function exportarCsv(string $path, array $plan, $articulos): void
    {
        $dir = dirname($path);
        if ($dir !== '' && $dir !== '.' && ! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new \RuntimeException('No se pudo escribir '.$path);
        }
        fputcsv($fh, ['accion', 'articulo_id', 'sku', 'estado', 'empresa_id', 'tipoimputacion', 'codigo', 'detalle']);
        foreach ($plan['altas'] as $alta) {
            $art = $articulos->get((int) $alta['articulo_id']);
            fputcsv($fh, [
                'alta',
                $alta['articulo_id'],
                $art->sku ?? '',
                $art->estado ?? '',
                $alta['empresa_id'],
                $alta['tipoimputacion'],
                $alta['codigo'],
                'origen empresa '.$alta['origen_empresa_id'],
            ]);
        }
        foreach ($plan['errores'] as $error) {
            $art = $articulos->get((int) $error['articulo_id']);
            fputcsv($fh, [
                'error',
                $error['articulo_id'],
                $art->sku ?? '',
                $art->estado ?? '',
                $error['empresa_id'],
                $error['tipoimputacion'],
                $error['codigo'] ?? '',
                $error['detalle'],
            ]);
        }
        fclose($fh);
    }
}
