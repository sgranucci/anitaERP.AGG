<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\ComprobanteImpresionCopia;
use App\Models\Ventas\ComprobanteImpresionFormularioLinea;
use App\Models\Ventas\ComprobanteImpresionPrograma;
use App\Models\Ventas\ComprobanteImpresionRegla;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\ComprobanteImpresionReglaClave;
use App\Support\Ventas\ProgramaImpresionListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ComprobanteImpresionProgramaRepository implements ComprobanteImpresionProgramaRepositoryInterface
{
    public function __construct(
        protected ComprobanteImpresionPrograma $model,
        protected EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function leeProgramas($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(ProgramaImpresionListadoFiltros::filtrosVacios(), [
                'valor' => $texto,
                'busqueda' => $texto,
            ]);
        } elseif (! is_array($filtros)) {
            $filtros = ProgramaImpresionListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->with(['empresa:id,nombre,codigo'])
            ->withCount(['formularios', 'reglas'])
            ->orderBy('codigo');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id', true);

        if (ProgramaImpresionListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ProgramaImpresionListadoFiltros::aplicar($query, $filtros);
        }

        if ($flPaginando === true) {
            return $query->paginate(10);
        }

        return $query->get();
    }

    public function create(array $data)
    {
        $programa = $this->model->create($this->datosCabecera($data));
        $this->sincronizarHijas($programa, $data);

        return $programa->fresh(['formularios.copias', 'reglas']);
    }

    public function update(array $data, $id)
    {
        $programa = $this->findOrFail($id);
        $programa->update($this->datosCabecera($data));
        $this->sincronizarHijas($programa, $data);

        return $programa->fresh(['formularios.copias', 'reglas']);
    }

    public function delete($id)
    {
        $programa = $this->model->find($id);
        if (! $programa) {
            return false;
        }
        $formIds = ComprobanteImpresionFormularioLinea::query()
            ->where('programa_id', $programa->id)
            ->pluck('id')
            ->all();
        if ($formIds !== []) {
            EloquentAuditDeleteSupport::each(
                ComprobanteImpresionCopia::query()->whereIn('formulario_id', $formIds)
            );
        }
        EloquentAuditDeleteSupport::each(
            ComprobanteImpresionFormularioLinea::query()->where('programa_id', $programa->id)
        );
        EloquentAuditDeleteSupport::each(
            ComprobanteImpresionRegla::query()->where('programa_id', $programa->id)
        );
        $programa->delete();

        return true;
    }

    public function find($id)
    {
        $programa = $this->model->with(['formularios.copias.salida', 'reglas', 'empresa'])->find($id);
        if (! $programa) {
            throw new ModelNotFoundException('Programa de impresión no encontrado');
        }

        return $programa;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    /** @return array<string, mixed> */
    private function datosCabecera(array $data): array
    {
        $empresaId = (int) ($data['empresa_id'] ?? 0);

        return [
            'codigo' => strtoupper(trim((string) ($data['codigo'] ?? ''))),
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'permite_disparo_al_grabar' => ! empty($data['permite_disparo_al_grabar']),
        ];
    }

    private function sincronizarHijas(ComprobanteImpresionPrograma $programa, array $data): void
    {
        $formularios = $data['formularios'] ?? [];
        $idsForm = [];
        foreach ($formularios as $orden => $fila) {
            if (! is_array($fila) || empty($fila['formulario'])) {
                continue;
            }
            $formId = (int) ($fila['id'] ?? 0);
            $attrs = [
                'programa_id' => $programa->id,
                'orden' => (int) ($fila['orden'] ?? (($orden + 1) * 10)),
                'formulario' => (string) $fila['formulario'],
            ];
            $form = $formId > 0
                ? ComprobanteImpresionFormularioLinea::query()->where('programa_id', $programa->id)->find($formId)
                : null;
            if ($form) {
                $form->update($attrs);
            } else {
                $form = ComprobanteImpresionFormularioLinea::query()->create($attrs);
            }
            $idsForm[] = (int) $form->id;
            $this->sincronizarCopias($form, $fila['copias'] ?? []);
        }
        $formsQuitar = ComprobanteImpresionFormularioLinea::query()
            ->where('programa_id', $programa->id);
        if ($idsForm !== []) {
            $formsQuitar->whereNotIn('id', $idsForm);
        }
        $idsQuitar = $formsQuitar->pluck('id')->all();
        if ($idsQuitar !== []) {
            EloquentAuditDeleteSupport::each(
                ComprobanteImpresionCopia::query()->whereIn('formulario_id', $idsQuitar)
            );
        }
        EloquentAuditDeleteSupport::exceptIds(
            ComprobanteImpresionFormularioLinea::query()->where('programa_id', $programa->id),
            $idsForm
        );

        $reglas = $data['reglas'] ?? [];
        $idsRegla = [];
        foreach ($reglas as $fila) {
            if (! is_array($fila) || empty($fila['clave'])) {
                continue;
            }
            $clave = (string) $fila['clave'];
            $prioridad = ComprobanteImpresionReglaClave::PRECEDENCIA[$clave] ?? (int) ($fila['prioridad'] ?? 0);
            $reglaId = (int) ($fila['id'] ?? 0);
            $attrs = [
                'programa_id' => $programa->id,
                'clave' => $clave,
                'valor_id' => $clave === ComprobanteImpresionReglaClave::DEFAULT
                    ? null
                    : ((int) ($fila['valor_id'] ?? 0) ?: null),
                'prioridad' => $prioridad,
            ];
            $regla = $reglaId > 0
                ? ComprobanteImpresionRegla::query()->where('programa_id', $programa->id)->find($reglaId)
                : null;
            if ($regla) {
                $regla->update($attrs);
            } else {
                $regla = ComprobanteImpresionRegla::query()->create($attrs);
            }
            $idsRegla[] = (int) $regla->id;
        }
        EloquentAuditDeleteSupport::exceptIds(
            ComprobanteImpresionRegla::query()->where('programa_id', $programa->id),
            $idsRegla
        );
    }

    private function sincronizarCopias(ComprobanteImpresionFormularioLinea $form, array $copias): void
    {
        $ids = [];
        foreach ($copias as $orden => $fila) {
            if (! is_array($fila) || trim((string) ($fila['leyenda'] ?? '')) === '') {
                continue;
            }
            $copiaId = (int) ($fila['id'] ?? 0);
            $attrs = [
                'formulario_id' => $form->id,
                'orden' => (int) ($fila['orden'] ?? (($orden + 1) * 10)),
                'codigo' => strtoupper(trim((string) ($fila['codigo'] ?? 'ORI'))),
                'leyenda' => trim((string) $fila['leyenda']),
                'destinatario' => trim((string) ($fila['destinatario'] ?? '')) ?: null,
                'salida_id' => ((int) ($fila['salida_id'] ?? 0)) ?: null,
                'incluir_en_pdf_sesion' => ! empty($fila['incluir_en_pdf_sesion']),
            ];
            $copia = $copiaId > 0
                ? ComprobanteImpresionCopia::query()->where('formulario_id', $form->id)->find($copiaId)
                : null;
            if ($copia) {
                $copia->update($attrs);
            } else {
                $copia = ComprobanteImpresionCopia::query()->create($attrs);
            }
            $ids[] = (int) $copia->id;
        }
        EloquentAuditDeleteSupport::exceptIds(
            ComprobanteImpresionCopia::query()->where('formulario_id', $form->id),
            $ids
        );
    }
}
