<?php

declare(strict_types=1);

namespace PreCommande;

final class Plugin
{
    public const VERSION = '0.2.0';

    /**
     * @param iterable<HookableInterface> $hookables
     */
    public function __construct(private iterable $hookables)
    {
    }

    public function register(): void
    {
        foreach ($this->hookables as $hookable) {
            $hookable->register();
        }
    }
}
