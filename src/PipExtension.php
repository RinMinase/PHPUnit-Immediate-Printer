<?php
declare(strict_types = 1);

namespace ScriptFUSION\Pip;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class PipExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $config = new PipConfig();
        $parameters->has('perf.slow') && $config->perfSlow = +$parameters->get('perf.slow');
        $parameters->has('perf.vslow') && $config->perfVslow = +$parameters->get('perf.vslow');

        $facade->registerTracer(new Printer($config));
        $facade->replaceProgressOutput();
    }
}
