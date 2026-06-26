<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Unidadmedida;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;

final class MovimientosArticuloDepositoSupport
{
    /**
     * Mostrar columna / etiqueta de empresa en listados de depósito cuando el usuario
     * tiene acceso total (sin empresas asignadas) o a más de una empresa.
     */
    public static function mostrarEmpresaEnListados(): bool
    {
        $asignadas = collect(Session::get('usuario_empresas', []))
            ->pluck('id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->count();

        return $asignadas !== 1;
    }

    public static function puedeConsultar(): bool
    {
        return can('listar-articulos', false)
            || can('editar-articulos', false)
            || can('listar-recuento', false)
            || can('crear-recuento', false)
            || can('editar-recuento', false)
            || can('ver-recuento', false)
            || can('listar-movimientos-de-stock', false)
            || can('crear-movimientos-de-stock', false)
            || can('editar-movimientos-de-stock', false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function parametrosUrlKardex(int $articuloId, int $depositoId = 0, ?string $volver = null): array
    {
        return array_filter([
            'articulo_id' => $articuloId,
            'deposito_id' => $depositoId > 0 ? $depositoId : 0,
            'vista' => 'consulta',
            'volver' => $volver,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array{
     *     id: int,
     *     sku: string,
     *     descripcion: string,
     *     unidad_medida: string,
     *     unidad_medida_abreviatura: string,
     *     unidad_medida_nombre: string
     * }
     */
    public static function articuloResumen(Articulo $articulo): array
    {
        $articulo->loadMissing('unidadesdemedidas:id,nombre,abreviatura');

        return [
            'id' => (int) $articulo->id,
            'sku' => trim((string) ($articulo->sku ?? '')),
            'descripcion' => (string) ($articulo->descripcion ?? ''),
            'unidad_medida' => self::etiquetaUnidadMedida($articulo->unidadesdemedidas),
            'unidad_medida_abreviatura' => trim((string) (optional($articulo->unidadesdemedidas)->abreviatura ?? '')),
            'unidad_medida_nombre' => trim((string) (optional($articulo->unidadesdemedidas)->nombre ?? '')),
        ];
    }

    public static function etiquetaUnidadMedida(?Unidadmedida $unidad): string
    {
        if (! $unidad) {
            return '';
        }

        $abreviatura = trim((string) ($unidad->abreviatura ?? ''));
        if ($abreviatura !== '') {
            return $abreviatura;
        }

        return trim((string) ($unidad->nombre ?? ''));
    }

    public static function sufijoColumnaCantidad(?string $unidadMedida): string
    {
        $unidad = trim((string) $unidadMedida);

        return $unidad !== '' ? ' ('.$unidad.')' : '';
    }

    /**
     * Empresas asignadas + depósitos autorizados (misma regla que consultaDeposito sin empresa_id).
     *
     * @param  Builder<Depmae>  $query
     * @return Builder<Depmae>
     */
    public static function aplicarFiltroConsultaDeposito(Builder $query): Builder
    {
        app(EmpresaRepositoryInterface::class)->aplicarFiltroEmpresasAsignadas($query);

        return UsuarioDepositoAutorizado::aplicarFiltroQuery($query);
    }

    /**
     * IDs de depósitos visibles en saldos/kardex, o null si el usuario no tiene restricción.
     *
     * @return array<int>|null
     */
    public static function idsDepositosConsultables(): ?array
    {
        $tieneEmpresasAsignadas = self::cantidadEmpresasAsignadas() >= 1;
        $tieneDepositosAsignados = UsuarioDepositoAutorizado::tieneRestriccion();

        if (! $tieneEmpresasAsignadas && ! $tieneDepositosAsignados) {
            return null;
        }

        return Depmae::query()
            ->select('id')
            ->tap(fn (Builder $q) => self::aplicarFiltroConsultaDeposito($q))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function depositoConsultable(int $depositoId): bool
    {
        if ($depositoId <= 0) {
            return false;
        }

        $deposito = Depmae::query()->select('id', 'empresa_id')->find($depositoId);
        if (! $deposito) {
            return false;
        }

        $empresaId = (int) ($deposito->empresa_id ?? 0);
        if (! app(EmpresaRepositoryInterface::class)->empresaIdPermitida($empresaId)) {
            return false;
        }

        return UsuarioDepositoAutorizado::depositoAutorizado($depositoId);
    }

    private static function cantidadEmpresasAsignadas(): int
    {
        return collect(Session::get('usuario_empresas', []))
            ->pluck('id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->count();
    }
}
