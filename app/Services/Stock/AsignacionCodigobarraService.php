<?php

namespace App\Services\Stock;

use App\Models\Compras\Proveedor;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;
use App\Models\Stock\Depmae;
use App\Support\Stock\ArticuloSeleccionOperativaSupport;
use App\Support\Stock\TransferenciaMercaderiaPickeoSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Support\Facades\DB;

class AsignacionCodigobarraService
{
    /**
     * Artículos con saldo > 0 en el depósito y sin código de barras en el maestro.
     *
     * @return list<array{
     *   articulo_id: int,
     *   sku: string,
     *   descripcion: string,
     *   saldo: float,
     *   codigobarra: string,
     *   necesita_proveedor: bool,
     *   proveedor_id: int|null,
     *   proveedor_nombre: string|null
     * }>
     */
    public function pendientesDeposito(int $depositoId): array
    {
        $this->assertDepositoAutorizado($depositoId);

        $agg = DB::table('articulo_saldo_deposito as sd')
            ->join('articulo as a', 'a.id', '=', 'sd.articulo_id')
            ->where('sd.deposito_id', $depositoId)
            ->where('sd.cantidad', '>', 0)
            ->where('a.estado', ArticuloSeleccionOperativaSupport::ESTADO_ACTIVO)
            ->whereNotNull('a.sku')
            ->whereRaw("TRIM(a.sku) <> ''")
            ->where(function ($q) {
                $q->whereNull('a.codigobarra')
                    ->orWhereRaw("TRIM(a.codigobarra) = ''");
            })
            // No tiene sentido etiquetar servicios / cuentas contables / impuestos.
            ->where(function ($q) {
                $q->whereNull('a.tipoarticulo_id')
                    ->orWhereNotIn('a.tipoarticulo_id', [10, 11, 12]); // SERVICIO, CONTABLE, IMPUESTO
            })
            ->groupBy('a.id', 'a.sku', 'a.descripcion')
            ->orderByDesc(DB::raw('SUM(sd.cantidad)'))
            ->orderBy('a.descripcion')
            ->select([
                'a.id as articulo_id',
                'a.sku',
                'a.descripcion',
                DB::raw('SUM(sd.cantidad) as saldo'),
            ])
            ->get();

        if ($agg->isEmpty()) {
            return [];
        }

        $porArticulo = [];
        foreach ($agg as $row) {
            $id = (int) $row->articulo_id;
            $porArticulo[$id] = [
                'articulo_id' => $id,
                'sku' => trim((string) $row->sku),
                'descripcion' => (string) ($row->descripcion ?? ''),
                'saldo' => (float) $row->saldo,
                'codigobarra' => '',
            ];
        }

        $ids = array_keys($porArticulo);
        $vinculos = Articulo_Proveedor::query()
            ->with('proveedores:id,codigo,nombre')
            ->whereIn('articulo_id', $ids)
            ->where('activo', true)
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->get()
            ->groupBy('articulo_id');

        $out = [];
        foreach ($porArticulo as $id => $fila) {
            $grupo = $vinculos->get($id);
            $elegido = null;
            if ($grupo !== null && $grupo->isNotEmpty()) {
                $elegido = $grupo->first(static fn ($v) => (bool) $v->preferido) ?? $grupo->first();
            }

            $necesita = $elegido === null;
            $fila['necesita_proveedor'] = $necesita;
            $fila['proveedor_id'] = $elegido ? (int) $elegido->proveedor_id : null;
            $fila['proveedor_nombre'] = $elegido && $elegido->proveedores
                ? trim((string) ($elegido->proveedores->codigo ?? '').' '.(string) ($elegido->proveedores->nombre ?? ''))
                : null;
            $out[] = $fila;
        }

        // Ya viene ordenado por saldo DESC desde SQL.
        return array_values($out);
    }

    /**
     * @return list<array{id: int, codigo: string, nombre: string, etiqueta: string}>
     */
    public function opcionesProveedor(?string $busqueda = null, int $limite = 80): array
    {
        $q = Proveedor::query()
            ->select(['id', 'codigo', 'nombre', 'estado'])
            ->whereIn('estado', ['0', 'Activo', '3', 'Regularizado'])
            ->orderBy('nombre');

        $busqueda = trim((string) $busqueda);
        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $q->where(function ($w) use ($like, $busqueda) {
                $w->where('nombre', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhere('fantasia', 'like', $like);
                if (ctype_digit($busqueda)) {
                    $w->orWhere('id', (int) $busqueda);
                }
            });
        }

        return $q->limit(max(1, min($limite, 200)))
            ->get()
            ->map(static function (Proveedor $p): array {
                $codigo = trim((string) ($p->codigo ?? ''));
                $nombre = trim((string) ($p->nombre ?? ''));

                return [
                    'id' => (int) $p->id,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'etiqueta' => trim($codigo.' — '.$nombre),
                ];
            })
            ->all();
    }

    /**
     * @return array{
     *   ok: bool,
     *   mensaje?: string,
     *   necesita_proveedor?: bool,
     *   articulo_id?: int,
     *   sku?: string,
     *   codigobarra?: string,
     *   proveedor_id?: int|null
     * }
     */
    public function guardar(int $articuloId, string $codigobarra, ?int $proveedorId = null): array
    {
        $codigo = self::normalizarCodigo($codigobarra);
        if ($codigo === null) {
            return ['ok' => false, 'mensaje' => 'Ingrese o lea un código de barras válido.'];
        }
        if ($articuloId <= 0) {
            return ['ok' => false, 'mensaje' => 'Artículo inválido.'];
        }

        $articulo = Articulo::query()->find($articuloId);
        if ($articulo === null || ! ArticuloSeleccionOperativaSupport::esSeleccionable($articulo)) {
            return ['ok' => false, 'mensaje' => 'Artículo no encontrado o inactivo.'];
        }

        $conflicto = $this->conflictoCodigo($codigo, $articuloId);
        if ($conflicto !== null) {
            return ['ok' => false, 'mensaje' => $conflicto];
        }

        $vinculo = $this->resolverVinculoProveedor($articuloId, $proveedorId);
        if ($vinculo['necesita_proveedor'] ?? false) {
            return [
                'ok' => false,
                'necesita_proveedor' => true,
                'mensaje' => 'Elegí un proveedor para vincular el código de barras.',
                'articulo_id' => $articuloId,
            ];
        }

        DB::transaction(function () use ($articulo, $codigo, $vinculo) {
            $barraActual = self::normalizarCodigo((string) ($articulo->codigobarra ?? ''));
            if ($barraActual === null) {
                $articulo->codigobarra = substr($codigo, 0, 50);
                $articulo->save();
            }

            /** @var Articulo_Proveedor $fila */
            $fila = $vinculo['fila'];
            $barraProv = self::normalizarCodigo((string) ($fila->codigobarra ?? ''));
            if ($barraProv === null) {
                $fila->codigobarra = substr($codigo, 0, 50);
                $fila->save();
            }
        });

        return [
            'ok' => true,
            'mensaje' => 'Código de barras guardado.',
            'articulo_id' => $articuloId,
            'sku' => (string) ($articulo->sku ?? ''),
            'codigobarra' => $codigo,
            'proveedor_id' => (int) ($vinculo['fila']->proveedor_id ?? 0) ?: null,
        ];
    }

    public function assertDepositoAutorizado(int $depositoId): void
    {
        if ($depositoId <= 0) {
            throw new \InvalidArgumentException('Depósito inválido.');
        }
        if (! Depmae::query()->whereKey($depositoId)->exists()) {
            throw new \InvalidArgumentException('Depósito no encontrado.');
        }
        if (! UsuarioDepositoAutorizado::depositoAutorizado($depositoId)) {
            throw new \InvalidArgumentException('No está autorizado para operar el depósito seleccionado.');
        }
    }

    /**
     * @return array{necesita_proveedor?: bool, fila?: Articulo_Proveedor}
     */
    private function resolverVinculoProveedor(int $articuloId, ?int $proveedorId): array
    {
        if ($proveedorId !== null && $proveedorId > 0) {
            if (! $this->proveedorOperativo($proveedorId)) {
                throw new \InvalidArgumentException('Proveedor inválido o no operativo.');
            }

            $fila = Articulo_Proveedor::query()
                ->where('articulo_id', $articuloId)
                ->where('proveedor_id', $proveedorId)
                ->first();

            if ($fila === null) {
                $fila = Articulo_Proveedor::query()->create([
                    'articulo_id' => $articuloId,
                    'proveedor_id' => $proveedorId,
                    'activo' => true,
                    'preferido' => false,
                    'nombre_articulo_proveedor' => null,
                    'codigobarra' => null,
                    'codigo_articulo_proveedor' => null,
                ]);
            }

            return ['fila' => $fila];
        }

        $existentes = Articulo_Proveedor::query()
            ->where('articulo_id', $articuloId)
            ->where('activo', true)
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->get();

        if ($existentes->isEmpty()) {
            return ['necesita_proveedor' => true];
        }

        $preferido = $existentes->first(static fn ($v) => (bool) $v->preferido);

        return ['fila' => $preferido ?? $existentes->first()];
    }

    private function proveedorOperativo(int $proveedorId): bool
    {
        return Proveedor::query()
            ->whereKey($proveedorId)
            ->whereIn('estado', ['0', 'Activo', '3', 'Regularizado'])
            ->exists();
    }

    private function conflictoCodigo(string $codigo, int $articuloId): ?string
    {
        $variantes = TransferenciaMercaderiaPickeoSupport::variantesCodigo($codigo);
        if ($variantes === []) {
            return 'Código de barras inválido.';
        }

        $otroArt = Articulo::query()
            ->where(function ($q) use ($variantes) {
                $q->whereIn('codigobarra', $variantes);
                foreach ($variantes as $v) {
                    $q->orWhereRaw('UPPER(TRIM(codigobarra)) = ?', [strtoupper($v)]);
                }
            })
            ->where('id', '<>', $articuloId)
            ->first(['id', 'sku', 'descripcion']);

        if ($otroArt !== null) {
            return 'El código ya está en el artículo '.$otroArt->sku.' ('.$otroArt->descripcion.').';
        }

        $otroProv = Articulo_Proveedor::query()
            ->with('articulos:id,sku,descripcion')
            ->where('activo', true)
            ->where('articulo_id', '<>', $articuloId)
            ->where(function ($q) use ($variantes) {
                $q->whereIn('codigobarra', $variantes);
                foreach ($variantes as $v) {
                    $q->orWhereRaw('UPPER(TRIM(codigobarra)) = ?', [strtoupper($v)]);
                }
            })
            ->first();

        if ($otroProv !== null) {
            $sku = (string) ($otroProv->articulos->sku ?? '#'.$otroProv->articulo_id);

            return 'El código ya está en el catálogo proveedor del artículo '.$sku.'.';
        }

        return null;
    }

    public static function normalizarCodigo(?string $codigo): ?string
    {
        $codigo = preg_replace('/\s+/', '', trim((string) $codigo)) ?? '';
        if ($codigo === '') {
            return null;
        }

        return substr($codigo, 0, 50);
    }
}
