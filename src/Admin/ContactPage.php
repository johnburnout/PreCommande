<?php

declare(strict_types=1);

namespace PreCommande\Admin;

use PreCommande\HookableInterface;
use PreCommande\Mailer;
use PreCommande\Repository\ModelRepository;
use PreCommande\Repository\ParticipationRepository;
use PreCommande\TemplateRenderer;

/**
 * Envoi d'un message à tous les participants ou à ceux ayant choisi un modèle.
 */
final class ContactPage implements HookableInterface
{
    public const ACTION = 'precommande_contact';

    public function __construct(
        private TemplateRenderer $renderer,
        private ModelRepository $models,
        private ParticipationRepository $participations,
        private Mailer $mailer,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'send']);
    }

    public function render(): void
    {
        echo $this->renderer->render('admin/contact', [
            'models' => $this->models->findAllIndexed(),
            'total' => $this->participations->count(),
            'action' => self::ACTION,
            'sent' => isset($_GET['sent']) ? absint($_GET['sent']) : null,
            'error' => isset($_GET['error']),
        ]);
    }

    public function send(): void
    {
        if (!current_user_can(AdminMenu::CAPABILITY)) {
            wp_die('Accès refusé.', '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION);

        $subject = sanitize_text_field(wp_unslash($_POST['sujet'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $modeleId = absint($_POST['modele'] ?? 0) ?: null;

        $page = admin_url('admin.php?page=' . AdminMenu::SLUG . '-contact');

        if ($subject === '' || $message === '') {
            wp_safe_redirect(add_query_arg('error', '1', $page));
            exit;
        }

        $sent = $this->mailer->sendBulk($this->participations->findRecipients($modeleId), $subject, $message);

        wp_safe_redirect(add_query_arg('sent', $sent, $page));
        exit;
    }
}
