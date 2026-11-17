<?php

declare(strict_types=1);

namespace Tests\Unit\Support\ReglaNegocio;

use App\Models\Configuracion\ReglaNegocio;
use App\Models\Configuracion\ReglaNegocioVersion;
use App\Support\ReglaNegocio\ReglaNegocioVersionSupport;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReglaNegocioVersionSupportTest extends TestCase
{
    private bool $listo = false;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (config('database.default') === 'sqlite'
                || (string) config('database.connections.'.config('database.default').'.database') === ':memory:') {
                $this->markTestSkipped('Versionado: saltado bajo sqlite de phpunit.xml');
            }
            if (! Schema::hasTable('regla_negocio') || ! Schema::hasTable('regla_negocio_version')) {
                $this->markTestSkipped('Migración F4 no aplicada.');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB no disponible: '.$e->getMessage());
        }

        ReglaNegocio::query()->where('codigo', 'like', 'UT_VER_%')->delete();
        $this->listo = true;
    }

    protected function tearDown(): void
    {
        if ($this->listo) {
            try {
                ReglaNegocio::query()->where('codigo', 'like', 'UT_VER_%')->delete();
            } catch (\Throwable) {
                //
            }
        }
        parent::tearDown();
    }

    public function test_snapshot_y_restaurar(): void
    {
        $regla = ReglaNegocio::query()->create([
            'codigo' => 'UT_VER_A',
            'nombre' => 'Original',
            'dominio' => 'configuracion',
            'punto' => 'demo.punto.piloto',
            'activo' => false,
            'prioridad' => 10,
            'stop_on_match' => true,
            'condiciones' => ['logic' => 'and', 'rules' => []],
            'acciones' => [['tipo' => 'noop']],
        ]);

        ReglaNegocioVersionSupport::snapshot($regla, 'create');
        $regla->nombre = 'Modificada';
        $regla->save();
        ReglaNegocioVersionSupport::snapshot($regla, 'update');

        $v1 = ReglaNegocioVersion::query()
            ->where('regla_negocio_id', $regla->id)
            ->where('nro_version', 1)
            ->first();
        $this->assertNotNull($v1);
        $this->assertSame('Original', $v1->snapshot['nombre'] ?? null);

        ReglaNegocioVersionSupport::restaurar($regla, (int) $v1->id);
        $this->assertSame('Original', $regla->fresh()->nombre);
    }
}
