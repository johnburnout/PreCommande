<?php

declare(strict_types=1);

namespace PreCommande\Dto;

use PreCommande\Vendor\Symfony\Component\Validator\Constraints as Assert;

/**
 * Données d'une fiche saisie sur le formulaire public.
 */
final class ParticipationRequest
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom est trop long.')]
    public string $nom = '';

    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le prénom est trop long.')]
    public string $prenom = '';

    #[Assert\NotBlank(message: "L'adresse e-mail est obligatoire.")]
    #[Assert\Email(message: "L'adresse e-mail n'est pas valide.")]
    #[Assert\Length(max: 190)]
    public string $email = '';

    #[Assert\Length(max: 30, maxMessage: 'Le numéro de téléphone est trop long.')]
    #[Assert\Regex(pattern: '/^[0-9 +().-]*$/', message: "Le numéro de téléphone n'est pas valide.")]
    public string $telephone = '';

    /**
     * @var list<array{modele_id:int, taille:string, quantite:int, prix_unitaire:?float}>
     */
    #[Assert\Count(min: 1, minMessage: 'Indiquez au moins un article (quantité supérieure à 0).')]
    public array $lignes = [];
}
