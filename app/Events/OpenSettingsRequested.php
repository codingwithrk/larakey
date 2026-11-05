<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OpenSettingsRequested
{
    use Dispatchable;

    /**
     * NativePHP passes the global shortcut key positionally and menu clicks as
     * named "item"/"combo" arguments, so accept any payload.
     *
     * @var array<int|string, mixed>
     */
    public array $payload;

    public function __construct(mixed ...$payload)
    {
        $this->payload = $payload;
    }
}
