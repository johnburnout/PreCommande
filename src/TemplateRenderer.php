<?php

declare(strict_types=1);

namespace PreCommande;

/**
 * Rendu de gabarits PHP situés dans templates/.
 * Un thème peut surcharger un gabarit en le copiant dans {theme}/precommande/.
 */
final class TemplateRenderer
{
    public function __construct(private string $templatesDir)
    {
    }

    /** @param array<string, mixed> $vars */
    public function render(string $name, array $vars = []): string
    {
        $file = locate_template('precommande/' . $name . '.php') ?: $this->templatesDir . '/' . $name . '.php';

        if (!is_file($file)) {
            throw new \InvalidArgumentException(sprintf('Gabarit introuvable : %s', $name));
        }

        return (static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            include $__file;
            return (string) ob_get_clean();
        })($file, $vars);
    }
}
