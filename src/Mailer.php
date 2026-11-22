<?php

declare(strict_types=1);

namespace PreCommande;

use PreCommande\Dto\ParticipationRequest;

final class Mailer
{
    /**
     * @param array<int, array{titre:string}> $modelsById
     */
    public function sendConfirmation(ParticipationRequest $request, array $modelsById): bool
    {
        $lines = [];
        $total = 0.0;
        foreach ($request->lignes as $l) {
            $line = sprintf(
                '- %s (%s) x %d',
                $modelsById[$l['modele_id']]['titre'] ?? ('Modèle #' . $l['modele_id']),
                $l['taille'],
                $l['quantite']
            );
            if ($l['prix_unitaire'] !== null) {
                $amount = $l['quantite'] * $l['prix_unitaire'];
                $total += $amount;
                $line .= sprintf(' — %s', $this->plain(Money::format($amount)));
            }
            $lines[] = $line;
        }
        if ($total > 0) {
            $lines[] = '';
            $lines[] = 'Total estimé : ' . $this->plain(Money::format($total));
        }

        $body = sprintf(
            "Bonjour %s,\n\nNous avons bien enregistré votre participation :\n\n%s\n\nMerci !\n%s",
            $request->prenom,
            implode("\n", $lines),
            $this->siteName()
        );

        return wp_mail($request->email, sprintf('[%s] Confirmation de votre précommande', $this->siteName()), $body);
    }

    /**
     * Envoi individuel (un e-mail par destinataire, adresses jamais exposées).
     * Variables disponibles dans le message : {prenom}, {nom}.
     *
     * @param list<array{email:string, prenom:string, nom:string}> $recipients
     */
    public function sendBulk(array $recipients, string $subject, string $message): int
    {
        $sent = 0;
        foreach ($recipients as $r) {
            $body = strtr($message, ['{prenom}' => $r['prenom'], '{nom}' => $r['nom']]);
            if (wp_mail($r['email'], $subject, $body)) {
                ++$sent;
            }
        }
        return $sent;
    }

    private function plain(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }

    private function siteName(): string
    {
        return wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
    }
}
