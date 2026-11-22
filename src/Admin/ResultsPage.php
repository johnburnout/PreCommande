<?php

declare(strict_types=1);

namespace PreCommande\Admin;

use PreCommande\HookableInterface;
use PreCommande\Money;
use PreCommande\Repository\ModelRepository;
use PreCommande\Repository\ParticipationRepository;
use PreCommande\TemplateRenderer;

/**
 * Statistiques par modèle et par taille, détail des fiches, export CSV, suppression.
 */
final class ResultsPage implements HookableInterface
{
    public const EXPORT_ACTION = 'precommande_export';
    public const DELETE_ACTION = 'precommande_delete';

    public function __construct(
        private TemplateRenderer $renderer,
        private ModelRepository $models,
        private ParticipationRepository $participations,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::EXPORT_ACTION, [$this, 'export']);
        add_action('admin_post_' . self::DELETE_ACTION, [$this, 'delete']);
    }

    public function render(): void
    {
        $models = $this->models->findAllIndexed();

        echo $this->renderer->render('admin/results', [
            'count' => $this->participations->count(),
            'stats' => $this->buildStats($models, $this->participations->statsBySize()),
            'participations' => $this->participations->findAllWithLines(),
            'models' => $models,
            'exportUrl' => wp_nonce_url(admin_url('admin-post.php?action=' . self::EXPORT_ACTION), self::EXPORT_ACTION),
            'deleteAction' => self::DELETE_ACTION,
            'deleted' => isset($_GET['deleted']),
        ]);
    }

    public function export(): void
    {
        $this->guard(self::EXPORT_ACTION);

        $models = $this->models->findAllIndexed();
        $filename = 'precommande-' . gmdate('Y-m-d') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
        fputcsv($out, ['Date', 'Nom', 'Prénom', 'E-mail', 'Téléphone', 'Modèle', 'Taille', 'Quantité', 'Prix unitaire', 'Montant'], ';');
        $decimal = static fn (?float $v): string => $v === null ? '' : number_format($v, 2, ',', '');

        foreach ($this->participations->findAllWithLines() as $p) {
            foreach ($p['lignes'] as $l) {
                fputcsv($out, [
                    $p['created_at'], $p['nom'], $p['prenom'], $p['email'], $p['telephone'],
                    $models[$l['modele_id']]['titre'] ?? ('Modèle #' . $l['modele_id']),
                    $l['taille'], $l['quantite'],
                    $decimal($l['prix_unitaire']),
                    $decimal($l['prix_unitaire'] === null ? null : $l['quantite'] * $l['prix_unitaire']),
                ], ';');
            }
        }
        fclose($out);
        exit;
    }

    public function delete(): void
    {
        $this->guard(self::DELETE_ACTION);

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id > 0) {
            $this->participations->delete($id);
        }

        wp_safe_redirect(admin_url('admin.php?page=' . AdminMenu::SLUG . '&deleted=1'));
        exit;
    }

    /**
     * Ordonne les tailles selon la définition du modèle ; conserve les tailles
     * et modèles supprimés depuis pour ne perdre aucune réponse.
     *
     * @return list<array{titre:string, image:?string, tailles:list<array{taille:string, prix:?float, total:int, participants:int, montant:float}>, total:int, montant:float}>
     */
    private function buildStats(array $models, array $stats): array
    {
        $result = [];
        $ids = array_unique(array_merge(array_keys($models), array_keys($stats)));

        foreach ($ids as $id) {
            $model = $models[$id] ?? null;
            $byModel = $stats[$id] ?? [];
            $prices = array_column($model['tailles'] ?? [], 'prix', 'label');
            $sizes = array_unique(array_merge(array_column($model['tailles'] ?? [], 'label'), array_map('strval', array_keys($byModel))));

            $rows = [];
            $total = 0;
            $montant = 0.0;
            foreach ($sizes as $size) {
                $row = $byModel[$size] ?? ['total' => 0, 'participants' => 0, 'montant' => 0.0];
                $rows[] = ['taille' => (string) $size, 'prix' => $prices[$size] ?? null] + $row;
                $total += $row['total'];
                $montant += $row['montant'];
            }

            $result[] = [
                'titre' => $model['titre'] ?? sprintf('Modèle #%d (supprimé)', $id),
                'image' => $model['image'] ?? null,
                'tailles' => $rows,
                'total' => $total,
                'montant' => $montant,
            ];
        }

        return $result;
    }

    private function guard(string $action): void
    {
        if (!current_user_can(AdminMenu::CAPABILITY)) {
            wp_die('Accès refusé.', '', ['response' => 403]);
        }
        check_admin_referer($action);
    }
}
