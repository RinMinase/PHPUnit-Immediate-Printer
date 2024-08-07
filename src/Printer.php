<?php

declare(strict_types=1);

namespace ScriptFUSION\Pip;

use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Event;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Test\ConsideredRisky;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpNoticeTriggered;
use PHPUnit\Event\Test\PhpWarningTriggered;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\Tracer\Tracer;
use PHPUnit\Util\Color;

final class Printer implements Tracer {
    private readonly array $performanceThresholds;

    private int $totalTests;

    private int $testCounter = 0;

    private ?TestStatus $status = null;

    private HRTime $start;

    private ?Throwable $throwable = null;

    private ?Trace $trace = null;

    private bool $flawless = true;

    public function __construct(private readonly PipConfig $config) {
        $this->performanceThresholds = [
            'red' => $config->perfVslow,
            'yellow' => $config->perfSlow,
            'green' => 0,
        ];
    }

    public function trace(Event $event): void {
        if ($event instanceof ExecutionStarted) {
            $this->totalTests = $event->testSuite()->count();
        }

        if ($event instanceof Prepared) {
            $this->start = $event->telemetryInfo()->time();
        }

        if ($event instanceof Passed) {
            $this->status ??= $this->flawless ? TestStatus::Passed : TestStatus::Flawed;
        }
        if ($event instanceof Failed) {
            $this->status ??= TestStatus::Failed;

            $this->throwable = $event->throwable();
            $this->flawless = false;
        }
        if ($event instanceof Errored) {
            $this->status ??= TestStatus::Errored;

            $this->throwable = $event->throwable();
            $this->flawless = false;
        }
        if ($event instanceof Skipped) {
            $this->status ??= TestStatus::Skipped;
        }
        if ($event instanceof MarkedIncomplete) {
            $this->status ??= TestStatus::Incomplete;
        }
        if ($event instanceof ConsideredRisky) {
            // Allow risky status to override passed (or flawed) status only.
            if ($this->status === TestStatus::Passed || $this->status === TestStatus::Flawed) {
                $this->status = TestStatus::Risky;
            }

            $this->trace = new Trace($event->message(), $event->test()->file(), $event->test()->line());
        }
        if ($event instanceof PhpNoticeTriggered) {
            $this->status ??= TestStatus::Notice;

            $this->trace = Trace::fromEvent($event);
        }
        if ($event instanceof PhpWarningTriggered) {
            $this->status ??= TestStatus::Warning;

            $this->trace = Trace::fromEvent($event);
        }
        if ($event instanceof PhpDeprecationTriggered) {
            $this->status ??= TestStatus::Deprecated;

            $this->trace = Trace::fromEvent($event);
        }

        if ($event instanceof Finished) {
            $id = $event->test()->name(); // changed
            $id = str_replace('test', '', $id); //changed
            $id = str_replace('_', ' ', $id); //changed

            // change block
            if ($this->trace && $this->status !== TestStatus::Failed && $this->status !== TestStatus::Errored) {
                $id .= Color::colorize('fg-yellow', ' → ' . $this->trace->message);
            }
            // end change block

            // Data provider case.
            if ($event->test()->isTestMethod() && $event->test()->testData()->hasDataFromDataProvider()) {
                $id = substr($id, 0, strrpos($id, '#'));

                $data = $event->test()->testData()->dataFromDataProvider()->dataAsStringForResultOutput();
                if (!$this->config->testDpArgs) {
                    $dsn = $event->test()->testData()->dataFromDataProvider()->dataSetName();
                    $data = substr($data, 0, (is_int($dsn) ? 16 : 17) + strlen((string)$dsn));
                }

                $id .= $data;
            }

            $ms = round($event->telemetryInfo()->time()->duration($this->start)->asFloat() * 1_000);
            foreach ($this->performanceThresholds as $colour => $threshold) {
                if ($ms >= $threshold) {
                    break;
                }
            }

            printf(
                // "%3d%% %s %s %s%s",
                "  %s %s %s %s%s",
                // "  %s %s%s",
                '[' . str_pad(floor(++$this->testCounter / $this->totalTests * 100) . '', 3, ' ', STR_PAD_LEFT) . '%]',
                $this->status->getStatusColour() === ''
                    ? $this->status->getStatusCode()
                    : Color::colorize("fg-{$this->status->getColour()}", $this->status->getStatusCode()), // changed
                Color::colorize('fg-dim', $id), // changed
                Color::colorize("fg-$colour", "($ms ms)"),
                PHP_EOL,
            );

            // if ($this->status === TestStatus::Failed) {
            //   echo PHP_EOL, Color::colorize('fg-red', $this->throwable->description()), PHP_EOL,
            //   Color::colorize('fg-red', $this->throwable->stackTrace()), PHP_EOL;

            //   $this->throwable = null;
            // }

            while ($this->status === TestStatus::Errored && $this->throwable) {
                echo PHP_EOL, Color::colorize('fg-white,bg-red', " {$this->throwable->className()} "), ' ',
                Color::colorize('fg-red', $this->throwable->message()), PHP_EOL, PHP_EOL,
                Color::colorize('fg-red', $this->throwable->stackTrace()), PHP_EOL;

                if ($this->throwable->hasPrevious()) {
                    echo Color::colorize('fg-red', 'Caused by');

                    $this->throwable = $this->throwable->previous();
                } else {
                    $this->throwable = null;
                }
            }

            // if ($this->trace) {
            //   printf(
            //     Color::colorize("fg-{$this->status->getColour()}", '%s%s: %s in %s on line %s%1$s%1$s'),
            //     PHP_EOL,
            //     $this->status->name,
            //     $this->trace->message,
            //     $this->trace->file,
            //     $this->trace->line
            //   );

            //   $this->trace = null;
            // }

            $this->status = null;
        }

        if ($event instanceof \PHPUnit\Event\TestRunner\Finished) {
            echo PHP_EOL, PHP_EOL;
        }
    }
}
