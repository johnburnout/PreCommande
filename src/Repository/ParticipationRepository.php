<?php

declare(strict_types=1);

namespace PreCommande\Repository;

use PreCommande\Dto\ParticipationRequest;

/**
 * Accès aux fiches (participations) et à leurs lignes (modèle / taille / quantité).
 */
final class ParticipationRepository
{
    public const TABLE_PARTICIPATIONS = 'pc_participations';
    public const TABLE_LIGNES = 'pc_lignes';

    public function __construct(private \wpdb $db)
    {
    }

    public function count(): int
    {
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->participations()}");
    }

    public function emailExists(string $email): bool
    {
        return (bool) $this->db->get_var(
            $this->db->prepare("SELECT 1 FROM {$this->participations()} WHERE email = %s LIMIT 1", $email)
        );
    }

    public function save(ParticipationRequest $request): int
    {
        $this->db->query('START TRANSACTION');

        $ok = $this->db->insert($this->participations(), [
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s']);

        if ($ok === false) {
            $this->db->query('ROLLBACK');
            throw new \RuntimeException('Enregistrement de la participation impossible : ' . $this->db->last_error);
        }

        $id = (int) $this->db->insert_id;

        foreach ($request->lignes as $ligne) {
            $data = [
                'participation_id' => $id,
                'modele_id' => $ligne['modele_id'],
                'taille' => $ligne['taille'],
                'quantite' => $ligne['quantite'],
            ];
            $format = ['%d', '%d', '%s', '%d'];
            if ($ligne['prix_unitaire'] !== null) {
                $data['prix_unitaire'] = $ligne['prix_unitaire'];
                $format[] = '%f';
            }
            $ok = $this->db->insert($this->lignes(), $data, $format);

            if ($ok === false) {
                $this->db->query('ROLLBACK');
                throw new \RuntimeException('Enregistrement des articles impossible : ' . $this->db->last_error);
            }
        }

        $this->db->query('COMMIT');

        return $id;
    }

    public function delete(int $id): void
    {
        $this->db->delete($this->lignes(), ['participation_id' => $id], ['%d']);
        $this->db->delete($this->participations(), ['id' => $id], ['%d']);
    }

    /**
     * Fiches avec leurs lignes, les plus récentes d'abord.
     *
     * @return list<array{id:int, nom:string, prenom:string, email:string, telephone:string, created_at:string, total:float, lignes:list<array{modele_id:int, taille:string, quantite:int, prix_unitaire:?float}>}>
     */
    public function findAllWithLines(): array
    {
        $rows = $this->db->get_results(
            "SELECT * FROM {$this->participations()} ORDER BY created_at DESC, id DESC",
            ARRAY_A
        ) ?: [];

        $lines = $this->db->get_results(
            "SELECT participation_id, modele_id, taille, quantite, prix_unitaire FROM {$this->lignes()} ORDER BY id",
            ARRAY_A
        ) ?: [];

        $byParticipation = [];
        foreach ($lines as $line) {
            $byParticipation[(int) $line['participation_id']][] = [
                'modele_id' => (int) $line['modele_id'],
                'taille' => (string) $line['taille'],
                'quantite' => (int) $line['quantite'],
                'prix_unitaire' => $line['prix_unitaire'] === null ? null : (float) $line['prix_unitaire'],
            ];
        }

        $total = static fn (array $lignes): float => array_sum(array_map(
            static fn (array $l): float => $l['quantite'] * ($l['prix_unitaire'] ?? 0.0),
            $lignes
        ));

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'nom' => (string) $row['nom'],
            'prenom' => (string) $row['prenom'],
            'email' => (string) $row['email'],
            'telephone' => (string) $row['telephone'],
            'created_at' => (string) $row['created_at'],
            'lignes' => $byParticipation[(int) $row['id']] ?? [],
            'total' => $total($byParticipation[(int) $row['id']] ?? []),
        ], $rows);
    }

    /**
     * Totaux par modèle et par taille.
     *
     * @return array<int, array<string, array{total:int, participants:int, montant:float}>>
     */
    public function statsBySize(): array
    {
        $rows = $this->db->get_results(
            "SELECT modele_id, taille, SUM(quantite) AS total, COUNT(DISTINCT participation_id) AS participants,
                    SUM(quantite * COALESCE(prix_unitaire, 0)) AS montant
             FROM {$this->lignes()} GROUP BY modele_id, taille",
            ARRAY_A
        ) ?: [];

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['modele_id']][(string) $row['taille']] = [
                'total' => (int) $row['total'],
                'participants' => (int) $row['participants'],
                'montant' => (float) $row['montant'],
            ];
        }
        return $stats;
    }

    /**
     * Destinataires : tous les participants, ou ceux ayant choisi un modèle donné.
     *
     * @return list<array{email:string, prenom:string, nom:string}>
     */
    public function findRecipients(?int $modeleId = null): array
    {
        if ($modeleId) {
            $sql = $this->db->prepare(
                "SELECT DISTINCT p.email, p.prenom, p.nom FROM {$this->participations()} p
                 INNER JOIN {$this->lignes()} l ON l.participation_id = p.id
                 WHERE l.modele_id = %d",
                $modeleId
            );
        } else {
            $sql = "SELECT email, prenom, nom FROM {$this->participations()}";
        }

        return $this->db->get_results($sql, ARRAY_A) ?: [];
    }

    private function participations(): string
    {
        return $this->db->prefix . self::TABLE_PARTICIPATIONS;
    }

    private function lignes(): string
    {
        return $this->db->prefix . self::TABLE_LIGNES;
    }
}
