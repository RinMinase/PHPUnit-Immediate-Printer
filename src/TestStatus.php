<?php

declare(strict_types=1);

namespace ScriptFUSION\Pip;

enum TestStatus {
    case Passed;
    case Flawed;
    case Failed;
    case Errored;
    case Skipped;
    case Incomplete;
    case Risky;
    case Notice;
    case Warning;
    case Deprecated;

    public function getStatusCode(): string {
        return match ($this) {
            self::Passed => '✓',
            self::Flawed => '!',
            self::Failed => '⨯',  // changed
            self::Errored => '⨯', // changed
            self::Skipped => 'S',
            self::Incomplete => 'I',
            self::Risky => '!', // changed
            self::Notice => 'N',
            self::Warning => 'W',
            self::Deprecated => 'D',
        };
    }

    public function getStatusColour(): string {
        return match ($this) {
            self::Passed => 'green',
            self::Flawed => 'red',
            default => $this->getColour(),
        };
    }

    public function getColour(): string {
        return match ($this) {
            self::Passed => 'green,bold', // changed
            self::Flawed => 'green,bold',
            self::Failed,
            self::Errored => 'red,bold',
            self::Skipped => 'cyan,bold',
            self::Incomplete,
            self::Risky => 'yellow,bold', //changed
            self::Notice,
            self::Warning,
            self::Deprecated, => 'yellow,bold',
        };
    }
}
