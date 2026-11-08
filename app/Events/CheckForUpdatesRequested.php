<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CheckForUpdatesRequested
{
    use Dispatchable;

    /**
     * NativePHP passes menu clicks as named "item"/"combo" arguments, so accept any payload.
     *
     * @var array<int|string, mixed>
     */
    public array $payload;

    public function __construct(mixed ...$payload)
    {
        $this->payload = $payload;
    }
}
