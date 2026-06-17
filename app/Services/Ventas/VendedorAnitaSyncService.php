<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Vendedor;
use App\Repositories\Ventas\VendedorRepositoryInterface;
use App\Support\Ventas\AnitaSync\Vendedor\VendedorFieldMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendedorAnitaSyncService
{
    public function __construct(
        private readonly VendedorRepositoryInterface $vendedorRepository,
    ) {}

    /**
     * @return array{en_anita:int, importados:int, actualizados:int, omitidos:int, errores:list<string>}
     */
    public function sincronizarConAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = ['en_anita' => 0, 'importados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];
        $lista = $this->listarDesdeAnita();
        $ret['en_anita'] = count($lista);

        foreach ($lista as $row) {
            $codigoAnita = VendedorFieldMapper::mapCodigo($row);
            if ($codigoAnita === null) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $estado = $this->importarFila($row, $codigoAnita);
                if ($estado === 'importado') {
                    $ret['importados']++;
                } elseif ($estado === 'actualizado') {
                    $ret['actualizados']++;
                } else {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $msg = "Vendedor Anita vend_codigo={$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('VendedorAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita(string $vendCodigo): string
    {
        $row = $this->leerVendedorEnAnita($vendCodigo);
        if ($row === null) {
            return 'omitido';
        }

        $codigoAnita = VendedorFieldMapper::mapCodigo($row);
        if ($codigoAnita === null) {
            return 'omitido';
        }

        return $this->importarFila($row, $codigoAnita);
    }

    /**
     * @return list<object>
     */
    public function listarDesdeAnita(): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('vendedor_anita.tabla', 'vendedor'),
            'campos' => config('vendedor_anita.campos_listado'),
            'orderBy' => 'vend_codigo',
        ];
        $sistema = config('vendedor_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($payload));
        if ($parsed['error_lectura'] !== null) {
            Log::warning('VendedorAnitaSync listar: '.$parsed['error_lectura']);

            return [];
        }

        return $parsed['filas'];
    }

    private function leerVendedorEnAnita(string $vendCodigo): ?object
    {
        $api = new ApiAnita;
        $codigoSql = addslashes(trim($vendCodigo));
        $payload = [
            'acc' => 'list',
            'tabla' => config('vendedor_anita.tabla', 'vendedor'),
            'campos' => config('vendedor_anita.campos_listado'),
            'whereArmado' => " WHERE vend_codigo = '".$codigoSql."' ",
        ];
        $sistema = config('vendedor_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($payload));
        if ($parsed['error_lectura'] !== null || $parsed['filas'] === []) {
            return null;
        }

        return $parsed['filas'][0];
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function importarFila(object $row, string $codigoAnita): string
    {
        $payload = VendedorFieldMapper::mapAll($row);
        $this->validarPayloadMinimo($payload, $codigoAnita);

        $existente = $this->buscarVendedorErp($codigoAnita);
        $datos = $payload;
        $datos['codigo'] = $codigoAnita;

        DB::beginTransaction();
        try {
            if ($existente) {
                if (! $this->hayCambios($existente, $datos)) {
                    DB::commit();

                    return 'omitido';
                }
                $this->vendedorRepository->update($datos, $existente->id, false);
                DB::commit();

                return 'actualizado';
            }

            $this->crearVendedorDesdeAnita($datos, $codigoAnita);
            DB::commit();

            return 'importado';
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearVendedorDesdeAnita(array $datos, string $codigoAnita): void
    {
        if (ctype_digit($codigoAnita)) {
            $idDeseado = (int) $codigoAnita;
            if ($idDeseado > 0 && ! Vendedor::query()->whereKey($idDeseado)->exists()) {
                $vendedor = new Vendedor($datos);
                $vendedor->id = $idDeseado;
                $vendedor->save();

                return;
            }
        }

        $this->vendedorRepository->create($datos, false);
    }

    private function buscarVendedorErp(string $codigoAnita): ?Vendedor
    {
        $variantes = $this->variantesCodigo($codigoAnita);
        $query = Vendedor::query()->where(function ($q) use ($variantes, $codigoAnita) {
            $q->whereIn('codigo', $variantes);
            if (ctype_digit($codigoAnita)) {
                $q->orWhere('id', (int) $codigoAnita);
            }
        });

        return $query->orderBy('id')->first();
    }

    /**
     * @return list<string>
     */
    private function variantesCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }
        if (! ctype_digit($codigo)) {
            return [$codigo];
        }
        $norm = ltrim($codigo, '0');
        $norm = $norm !== '' ? $norm : '0';

        return array_values(array_unique([$codigo, $norm, str_pad($norm, 6, '0', STR_PAD_LEFT)]));
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function hayCambios(Vendedor $existente, array $datos): bool
    {
        $comparar = [
            'nombre',
            'comisionventa',
            'comisioncobranza',
            'aplicasobre',
            'empresa_id',
            'legajo_id',
            'email',
            'codigo',
            'estado',
        ];

        foreach ($comparar as $campo) {
            $nuevo = $datos[$campo] ?? null;
            $actual = $existente->{$campo};

            if (in_array($campo, ['comisionventa', 'comisioncobranza'], true)) {
                if (round((float) $actual, 2) !== round((float) $nuevo, 2)) {
                    return true;
                }

                continue;
            }

            if ((string) ($actual ?? '') !== (string) ($nuevo ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validarPayloadMinimo(array $payload, string $codigoAnita): void
    {
        if (trim((string) ($payload['nombre'] ?? '')) === '') {
            throw new \InvalidArgumentException("nombre vacío (vendedor {$codigoAnita}).");
        }

        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        if ($empresaId <= 0 || ! Empresa::query()->where('id', $empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente (vendedor {$codigoAnita}).");
        }
    }
}
