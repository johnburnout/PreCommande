<?php

declare(strict_types=1);

use PreCommande\Plugin;
use PreCommande\Validation\ValidatorFactory;
use PreCommande\Vendor\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use PreCommande\Vendor\Symfony\Component\Validator\Validator\ValidatorInterface;

use function PreCommande\Vendor\Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            // Pas d'autoconfigure ni d'instanceof : incompatibles avec le préfixage Strauss
            // (voir src/DependencyInjection/HookablePass.php).
            ->bind('string $pluginFile', '%plugin.file%')
            ->bind('string $templatesDir', '%plugin.dir%/templates');

    $services->load('PreCommande\\', '../src/')
        ->exclude(['../src/Kernel.php', '../src/Dto/', '../src/DependencyInjection/']);

    // $wpdb est injecté à l'exécution par le Kernel.
    $services->set(\wpdb::class)->synthetic();

    $services->set(ValidatorInterface::class)
        ->factory([ValidatorFactory::class, 'create']);

    $services->set(Plugin::class)
        ->public()
        ->arg('$hookables', tagged_iterator('precommande.hookable'));
};
