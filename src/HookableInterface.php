<?php

declare(strict_types=1);

namespace PreCommande;

/**
 * Service qui enregistre ses propres hooks WordPress.
 */
interface HookableInterface
{
    public function register(): void;
}
