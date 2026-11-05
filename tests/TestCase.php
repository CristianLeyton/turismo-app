<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * La caché de Setting es estática (por proceso): sin limpiarla, un valor
     * guardado en un test se filtraría a los siguientes aunque la base se
     * revierta.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();
    }
}
