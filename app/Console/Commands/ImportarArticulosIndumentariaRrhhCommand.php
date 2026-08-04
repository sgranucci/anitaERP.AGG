<?php

namespace App\Console\Commands;

use App\Models\Contable\Cuentacontable;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Cuentacontable;
use App\Models\Stock\Articulo_Estado;
use App\Models\Stock\Categoria;
use App\Models\Stock\Tipoarticulo;
use App\Models\Stock\Unidadmedida;
use App\Models\Stock\Usoarticulo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Alta de artículos de indumentaria RRHH desde la solapa "Detalle de prendas" del Excel de Capital Humano.
 */
class ImportarArticulosIndumentariaRrhhCommand extends Command
{
    protected $signature = 'stock:importar-articulos-indumentaria-rrhh
                            {--file=/home/sergio/tmp/Uniformes x Puesto + Articulos actualizados Final (1).xlsx : Excel origen}
                            {--dry-run : Solo informar, no grabar}
                            {--sin-anita : No sincronizar alta a Anita}
                            {--usuario-id=2 : Usuario para Auth/estado/Anita}
                            {--empresas=1,2,3 : IDs de empresa para cuentas COMPRAS/GASTOS}';

    protected $description = 'Importa artículos CH-IND* (indumentaria RRHH) desde Excel Capital Humano';

    private const CODIGO_CUENTA = '521070001';

    public function handle(): int
    {
        $path = (string) $this->option('file');
        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $sinAnita = (bool) $this->option('sin-anita');
        $usuarioId = (int) $this->option('usuario-id');
        $empresaIds = array_values(array_filter(array_map(
            static fn ($v) => (int) trim((string) $v),
            explode(',', (string) $this->option('empresas'))
        )));

        $usuario = Usuario::query()->find($usuarioId);
        if (! $usuario) {
            $this->error("Usuario id={$usuarioId} no encontrado.");

            return self::FAILURE;
        }
        Auth::login($usuario);

        $tipo = Tipoarticulo::query()->where('nombre', 'INDUMENTARIA')->first();
        $uso = Usoarticulo::query()->where('nombre', 'RRHH')->first();
        $categoria = Categoria::query()->where('nombre', 'INDUMENTARIA DE TRABAJO')->first();
        $um = Unidadmedida::query()->where('nombre', 'UNIDADES')->first();

        foreach ([
            'tipoarticulo INDUMENTARIA' => $tipo,
            'usoarticulo RRHH' => $uso,
            'categoria INDUMENTARIA DE TRABAJO' => $categoria,
            'unidadmedida UNIDADES' => $um,
        ] as $label => $model) {
            if (! $model) {
                $this->error("Falta maestro: {$label}");

                return self::FAILURE;
            }
        }

        $cuentasPorEmpresa = Cuentacontable::query()
            ->where('codigo', self::CODIGO_CUENTA)
            ->whereIn('empresa_id', $empresaIds)
            ->get()
            ->keyBy('empresa_id');

        foreach ($empresaIds as $empresaId) {
            if (! $cuentasPorEmpresa->has($empresaId)) {
                $this->error('No existe cuenta '.self::CODIGO_CUENTA." para empresa_id={$empresaId}");

                return self::FAILURE;
            }
        }

        $this->info('Leyendo solapa "Detalle de prendas"…');
        $wb = IOFactory::load($path);
        $sheetNames = $wb->getSheetNames();
        $sheet = null;
        foreach ($sheetNames as $name) {
            if (trim($name) === 'Detalle de prendas') {
                $sheet = $wb->getSheetByName($name);
                break;
            }
        }
        if (! $sheet) {
            // Fallback: 2.ª solapa
            $sheet = $wb->getSheet(1);
        }

        $rows = $sheet->toArray(null, true, true, false);
        if ($rows === []) {
            $this->error('Solapa vacía.');

            return self::FAILURE;
        }

        $headers = array_map(static fn ($h) => mb_strtolower(trim((string) $h)), $rows[0]);
        $idxCodigo = $this->buscarColumna($headers, ['codigo']);
        $idxArticulo = $this->buscarColumna($headers, ['articulo']);
        if ($idxCodigo === null || $idxArticulo === null) {
            $this->error('No se encontraron columnas Codigo / Articulo. Headers: '.implode(' | ', $headers));

            return self::FAILURE;
        }

        unset($rows[0]);
        $filas = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row[$idxCodigo] ?? ''));
            $descripcion = trim((string) ($row[$idxArticulo] ?? ''));
            if ($sku === '' || $descripcion === '') {
                continue;
            }
            $filas[] = [
                'sku' => $sku,
                'descripcion' => mb_substr($descripcion, 0, 100),
            ];
        }

        $this->line('Filas a procesar: '.count($filas));
        $this->line(sprintf(
            'Maestros: tipo=%d uso=%d cat=%d um=%d | cuenta=%s | empresas=%s | anita=%s',
            $tipo->id,
            $uso->id,
            $categoria->id,
            $um->id,
            self::CODIGO_CUENTA,
            implode(',', $empresaIds),
            $sinAnita ? 'no' : 'si'
        ));

        $altas = 0;
        $omitidos = 0;
        $errores = 0;
        $anitaOk = 0;
        $anitaFail = 0;

        foreach ($filas as $fila) {
            $sku = $fila['sku'];
            $existente = Articulo::query()->where('sku', $sku)->first();
            if ($existente) {
                $this->line("  SKIP {$sku} (ya existe id={$existente->id})");
                $omitidos++;

                continue;
            }

            if ($dry) {
                $this->line("  DRY  {$sku} | {$fila['descripcion']}");
                $altas++;

                continue;
            }

            try {
                DB::transaction(function () use (
                    $fila,
                    $tipo,
                    $uso,
                    $categoria,
                    $um,
                    $empresaIds,
                    $cuentasPorEmpresa,
                    $usuarioId,
                    $sinAnita,
                    &$altas,
                    &$anitaOk,
                    &$anitaFail
                ) {
                    $articulo = Articulo::create([
                        'sku' => $fila['sku'],
                        'descripcion' => $fila['descripcion'],
                        'categoria_id' => $categoria->id,
                        'tipoarticulo_id' => $tipo->id,
                        'usoarticulo_id' => $uso->id,
                        'unidadmedida_id' => $um->id,
                        'unidadmedidaalternativa_id' => $um->id,
                        'estado' => 'ACTIVO',
                        'nofactura' => 0,
                        'oficinacompra_id' => 1,
                        'maneja_stock_color_talle' => 1,
                        'fl_precio_promedio_transferencia' => 0,
                    ]);

                    Articulo_Estado::create([
                        'articulo_id' => $articulo->id,
                        'fecha' => now(),
                        'estado' => 'ACTIVO',
                        'usuario_id' => $usuarioId,
                        'observacion' => 'Alta de Artículo (import indumentaria RRHH)',
                    ]);

                    foreach ($empresaIds as $empresaId) {
                        $cuentaId = (int) $cuentasPorEmpresa[$empresaId]->id;
                        foreach (['COMPRAS', 'GASTOS'] as $tipoImputacion) {
                            Articulo_Cuentacontable::create([
                                'articulo_id' => $articulo->id,
                                'empresa_id' => $empresaId,
                                'tipoimputacion' => $tipoImputacion,
                                'cuentacontable_id' => $cuentaId,
                                'creousuario_id' => $usuarioId,
                            ]);
                        }
                    }

                    if (! $sinAnita) {
                        $producto = Articulo::query()
                            ->with([
                                'categorias',
                                'unidadesdemedidas',
                                'unidadesdemedidasalternativas',
                                'lineas',
                                'mventas',
                                'articulo_cuentacontables.cuentacontables',
                            ])
                            ->findOrFail($articulo->id);

                        $anita = (new Articulo)->guardarAnita($producto);
                        if (isset($anita['error']) && $anita['error'] === 'Error') {
                            throw new \RuntimeException('Error Anita: '.($anita['mensaje'] ?? ''));
                        }
                        $anitaOk++;
                    }

                    $altas++;
                    $this->line("  OK   {$fila['sku']} id={$articulo->id} | {$fila['descripcion']}");
                });
            } catch (\Throwable $e) {
                $errores++;
                $anitaFail++;
                $this->error("  ERR  {$sku}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Resumen: altas=%d omitidos=%d errores=%d anita_ok=%d%s',
            $altas,
            $omitidos,
            $errores,
            $anitaOk,
            $dry ? ' (dry-run)' : ''
        ));

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $candidatos
     */
    private function buscarColumna(array $headers, array $candidatos): ?int
    {
        foreach ($headers as $i => $h) {
            foreach ($candidatos as $c) {
                if ($h === $c || str_starts_with($h, $c)) {
                    return $i;
                }
            }
        }

        return null;
    }
}
