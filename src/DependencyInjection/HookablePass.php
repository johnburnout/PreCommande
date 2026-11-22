<?php

declare(strict_types=1);

namespace PreCommande\DependencyInjection;

use PreCommande\HookableInterface;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tague « precommande.hookable » chaque service qui implémente HookableInterface.
 *
 * Remplace `$services->instanceof(...)` : la passe Symfony qui gère `instanceof`
 * convertit les définitions par sérialisation avec des positions codées en dur
 * pour le nom de classe d'origine, ce qui casse une fois Symfony préfixé par Strauss.
 */
final class HookablePass implements CompilerPassInterface
{
    public const TAG = 'precommande.hookable';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();
            if (
                $class !== null
                && !$definition->isAbstract()
                && !$definition->hasTag(self::TAG)
                && class_exists($class)
                && is_subclass_of($class, HookableInterface::class)
            ) {
                $definition->addTag(self::TAG);
            }
        }
    }
}
