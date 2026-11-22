<?php

declare(strict_types=1);

namespace PreCommande\Front;

use PreCommande\Assets;
use PreCommande\HookableInterface;
use PreCommande\Repository\ModelRepository;
use PreCommande\Repository\ParticipationRepository;
use PreCommande\TemplateRenderer;

/**
 * [precommande] : affiche le compteur de fiches et le formulaire.
 * [precommande_compteur] : affiche uniquement le compteur.
 */
final class Shortcode implements HookableInterface
{
    public function __construct(
        private TemplateRenderer $renderer,
        private ModelRepository $models,
        private ParticipationRepository $participations,
    ) {
    }

    public function register(): void
    {
        add_shortcode('precommande', [$this, 'render']);
        add_shortcode('precommande_compteur', [$this, 'renderCounter']);
    }

    public function render(): string
    {
        wp_enqueue_style(Assets::FRONT);
        wp_enqueue_script(Assets::FRONT);

        $errors = [];
        $old = [];
        $token = isset($_GET['pc_err']) ? sanitize_key(wp_unslash($_GET['pc_err'])) : '';
        if ($token !== '') {
            $data = get_transient('pc_err_' . $token);
            if (\is_array($data)) {
                $errors = $data['errors'] ?? [];
                $old = $data['old'] ?? [];
                delete_transient('pc_err_' . $token);
            }
        }

        return $this->renderer->render('front/form', [
            'count' => $this->participations->count(),
            'models' => $this->models->findActive(),
            'errors' => $errors,
            'old' => $old,
            'success' => isset($_GET['pc_ok']),
            'action' => admin_url('admin-post.php'),
            'formAction' => SubmissionHandler::ACTION,
            'redirectTo' => remove_query_arg(['pc_ok', 'pc_err']),
        ]);
    }

    public function renderCounter(): string
    {
        wp_enqueue_style(Assets::FRONT);

        return $this->renderer->render('front/counter', ['count' => $this->participations->count()]);
    }
}
