<?php

declare(strict_types=1);

namespace PreCommande\Front;

use PreCommande\Dto\ParticipationRequest;
use PreCommande\HookableInterface;
use PreCommande\Mailer;
use PreCommande\Repository\ModelRepository;
use PreCommande\Repository\ParticipationRepository;
use PreCommande\Vendor\Symfony\Component\HttpFoundation\Request;
use PreCommande\Vendor\Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Traite l'envoi du formulaire public (via admin-post.php), puis redirige (PRG).
 */
final class SubmissionHandler implements HookableInterface
{
    public const ACTION = 'precommande_submit';
    private const MAX_QTY = 99;

    public function __construct(
        private ValidatorInterface $validator,
        private ParticipationRepository $participations,
        private ModelRepository $models,
        private Mailer $mailer,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_nopriv_' . self::ACTION, [$this, 'handle']);
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        $request = Request::createFromGlobals();
        /** @var array<string, mixed> $post */
        $post = wp_unslash($request->request->all());

        $back = wp_validate_redirect((string) ($post['redirect_to'] ?? ''), home_url('/'));
        $back = remove_query_arg(['pc_ok', 'pc_err'], $back);

        if (!wp_verify_nonce((string) ($post['_pc_nonce'] ?? ''), self::ACTION)) {
            wp_die('Votre session a expiré, merci de recharger la page.', 'PréCommande', ['response' => 403, 'back_link' => true]);
        }

        // Pot de miel anti-robots : champ invisible qui doit rester vide.
        if (!empty($post['site_web'])) {
            $this->redirect(add_query_arg('pc_ok', '1', $back));
        }

        $models = [];
        foreach ($this->models->findActive() as $model) {
            $models[$model['id']] = $model;
        }

        $dto = new ParticipationRequest();
        $dto->nom = sanitize_text_field((string) ($post['nom'] ?? ''));
        $dto->prenom = sanitize_text_field((string) ($post['prenom'] ?? ''));
        $dto->email = strtolower(sanitize_text_field((string) ($post['email'] ?? '')));
        $dto->telephone = sanitize_text_field((string) ($post['telephone'] ?? ''));
        $dto->lignes = $this->parseLines($post['qte'] ?? [], $models);

        $errors = [];
        foreach ($this->validator->validate($dto) as $violation) {
            $errors[] = (string) $violation->getMessage();
        }
        if (!$errors && $this->participations->emailExists($dto->email)) {
            $errors[] = 'Une fiche a déjà été saisie avec cette adresse e-mail.';
        }

        if (!$errors) {
            try {
                $this->participations->save($dto);
            } catch (\RuntimeException $e) {
                error_log('[PréCommande] ' . $e->getMessage());
                $errors[] = "Une erreur technique est survenue, votre fiche n'a pas été enregistrée.";
            }
        }

        if ($errors) {
            $token = bin2hex(random_bytes(8));
            set_transient('pc_err_' . $token, [
                'errors' => $errors,
                'old' => [
                    'nom' => $dto->nom,
                    'prenom' => $dto->prenom,
                    'email' => $dto->email,
                    'telephone' => $dto->telephone,
                    'qte' => \is_array($post['qte'] ?? null) ? $post['qte'] : [],
                ],
            ], 10 * MINUTE_IN_SECONDS);
            $this->redirect(add_query_arg('pc_err', $token, $back));
        }

        $this->mailer->sendConfirmation($dto, $models);
        $this->redirect(add_query_arg('pc_ok', '1', $back));
    }

    /**
     * Champs attendus : qte[{id modèle}][{index taille}] = quantité.
     *
     * @param array<int, array{tailles:list<array{label:string, prix:?float}>}> $models
     * @return list<array{modele_id:int, taille:string, quantite:int, prix_unitaire:?float}>
     */
    private function parseLines(mixed $raw, array $models): array
    {
        if (!\is_array($raw)) {
            return [];
        }

        $lines = [];
        foreach ($raw as $modelId => $sizes) {
            $model = $models[(int) $modelId] ?? null;
            if ($model === null || !\is_array($sizes)) {
                continue;
            }
            foreach ($sizes as $index => $qty) {
                $taille = $model['tailles'][(int) $index] ?? null;
                $qty = min(self::MAX_QTY, max(0, (int) $qty));
                if ($taille !== null && $qty > 0) {
                    // Le prix est figé au moment de la saisie.
                    $lines[] = [
                        'modele_id' => (int) $modelId,
                        'taille' => $taille['label'],
                        'quantite' => $qty,
                        'prix_unitaire' => $taille['prix'],
                    ];
                }
            }
        }
        return $lines;
    }

    private function redirect(string $url): never
    {
        wp_safe_redirect($url . '#precommande');
        exit;
    }
}
