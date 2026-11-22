# PréCommande — plugin WordPress (v0.3.0)

Sondage / précommande de vêtements ou d'objets.

## Installation

Le plus simple : télécharger `precommande.zip` depuis la [dernière release](https://github.com/johnburnout/PreCommande/releases/latest) et l'installer via *Extensions › Ajouter › Téléverser* (dépendances incluses, pas besoin de Composer). Sinon, depuis les sources :

1. Copier le dossier `precommande/` dans `wp-content/plugins/`.
2. Dans ce dossier : `composer install --no-dev -o`
   (le script Strauss télécharge `bin/strauss.phar` depuis GitHub, recopie Symfony dans `vendor-prefixed/` sous l'espace de noms `PreCommande\Vendor\` puis supprime les originaux de `vendor/`).
   Pour distribuer le plugin, zipper le dossier **avec** `vendor/` et `vendor-prefixed/` : le site cible n'a alors pas besoin de Composer.
3. Activer le plugin (les tables sont créées à l'activation).
4. *PréCommande › Modèles* : créer les modèles (titre, description, photo = image mise en avant, prix, tailles une par ligne, case « Proposé »).
5. Insérer le shortcode `[precommande]` dans une page. `[precommande_compteur]` affiche uniquement le compteur.

## Fonctionnalités

**Front**
- Compteur du nombre de fiches saisies.
- Formulaire : nom, prénom, e-mail (obligatoires), téléphone (facultatif).
- Pour chaque modèle actif : photo, description, prix (« dès … » si les tailles ont des prix différents), une quantité par taille.
- Récapitulatif en direct : nombre d'articles et total estimé.
- Validation (Symfony Validator), anti-doublon par e-mail, pot de miel anti-robots, nonce, redirection après envoi (PRG).
- E-mail de confirmation récapitulatif au participant (avec montants et total).

**Back-office** (menu *PréCommande*, droit `manage_options`)
- *Résultats* : indicateurs (fiches, articles, montant estimé, modèles), synthèse par modèle et par taille (prix, quantités, montants, nb de participants, barres), détail de chaque fiche avec son total, suppression, export CSV avec prix unitaire et montant (compatible Excel FR).
- *Modèles* : type de contenu `pc_modele`, colonnes photo / prix / tailles / proposé, ordre via « Attributs ».
- *Contacter* : message à tous les participants ou à ceux ayant choisi un modèle, avec variables `{prenom}` `{nom}` (un e-mail individuel par personne).

### Prix
- Prix unitaire facultatif par modèle (« 25,00 » ou « 25.00 »).
- Prix spécifique par taille dans la liste des tailles : `XXL = 27,50`.
- Le prix est **figé dans chaque fiche** au moment de la saisie : modifier un prix ensuite ne change pas les montants déjà enregistrés.
- Devise : euro (constante `Money::CURRENCY`).

## Architecture

```
precommande.php            Amorçage : autoload Composer, activation, boot du Kernel
config/services.php        Configuration du conteneur Symfony (autowiring)
src/DependencyInjection/   HookablePass : tague les services HookableInterface
src/Kernel.php             Construction / mise en cache du conteneur (var/cache/ si WP_DEBUG = false)
src/Plugin.php             Point d'entrée : appelle register() sur chaque service tagué
src/HookableInterface.php  Contrat des services qui posent des hooks WordPress (tag « precommande.hookable »)
src/Installer.php          Tables wp_pc_participations / wp_pc_lignes (dbDelta + versionnage)
src/Dto/                   ParticipationRequest + contraintes de validation (attributs)
src/Repository/            ModelRepository (CPT) et ParticipationRepository ($wpdb)
src/PostType/              Type de contenu « Modèle » + metabox
src/Front/                 Shortcode et traitement du formulaire
src/Admin/                 Menu, pages Résultats et Contact
src/Mailer.php             Confirmation + envoi groupé (wp_mail)
src/Money.php              Saisie et affichage des montants
templates/                 Gabarits PHP, surchargeables par le thème dans {theme}/precommande/
assets/                    CSS front et admin, JS du total en direct
vendor-prefixed/           Dépendances Symfony renommées (générées par Strauss)
```

Composants Symfony : `dependency-injection`, `config`, `http-foundation`, `validator` (6.4 LTS, PHP ≥ 8.1).

### Cohabitation avec d'autres plugins Symfony
Strauss renomme toutes les classes Symfony et PSR en `PreCommande\Vendor\…` : plusieurs plugins peuvent embarquer des versions différentes de Symfony sans conflit. Le code du plugin référence directement ces noms préfixés (`use PreCommande\Vendor\Symfony\…`).
Seuls les polyfills (`symfony/polyfill-*`) et `symfony/deprecation-contracts` ne sont pas préfixés : ils définissent des fonctions globales protégées par `function_exists`, donc sans risque de conflit.
Le conteneur compilé en cache porte aussi un nom propre au plugin (`PreCommandeCachedContainer`).

**Ne pas utiliser `instanceof`, `autoconfigure()` ni `parent()` dans `config/services.php`** : ces fonctions de Symfony convertissent les définitions par sérialisation avec des positions codées en dur pour le nom de classe d'origine, ce qui casse une fois Symfony préfixé. Le tag `precommande.hookable` est posé par `src/DependencyInjection/HookablePass.php`.

### Modèle de données
- `wp_pc_participations` : id, nom, prenom, email (unique), telephone, created_at
- `wp_pc_lignes` : id, participation_id, modele_id, taille, quantite, prix_unitaire (NULL si pas de prix)

La taille est stockée en texte : si un modèle est modifié après coup, les réponses existantes restent visibles.

## Mises à jour et publication

Le plugin se met à jour depuis les releases GitHub, comme un plugin du répertoire WordPress (notification puis bouton « Mettre à jour » dans *Extensions*), grâce à [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker), initialisé dans `precommande.php` hors du conteneur Symfony.

Publier une version :
1. Mettre à jour le numéro dans l'en-tête de `precommande.php` (`Version:`) **et** dans `Plugin::VERSION`.
2. Commiter, pousser, puis créer la release avec un tag `vX.Y.Z` correspondant (`gh release create vX.Y.Z`). Les notes de la release servent de journal des modifications dans WordPress.
3. Le workflow `.github/workflows/release.yml` vérifie les numéros de version, lance `composer install --no-dev`, construit `precommande.zip` et l'attache à la release. Il peut être relancé à la main (*Actions › Release › Run workflow*) pour un tag existant.

Seul l'asset `precommande.zip` est installé : une release sans cet asset (workflow en échec) est ignorée par les sites, qui ne reçoivent jamais le zip source de GitHub, dépourvu de `vendor/`.

## Points d'attention
- Toujours lancer `composer install` (et non `composer install --no-scripts`) pour que le préfixage soit appliqué.
- Après modification de `config/` ou de `src/` en production, supprimer `var/cache/container.php`.
- Envois groupés via `wp_mail` : au-delà de quelques centaines de destinataires, prévoir un SMTP transactionnel et un envoi en file d'attente.
- RGPD : prévoir la mention d'information complète et la purge des données après la campagne.

## Pistes pour la suite
- Plusieurs campagnes (dates d'ouverture/fermeture, shortcode `[precommande campagne="…"]`).
- Modification de sa fiche par le participant (lien unique par e-mail).
- Pagination / recherche dans le détail des fiches.
- Tests (PHPUnit + WP test suite) et traductions.

## Licence
Ce plugin est distribué sous licence GNU General Public License, version 2 ou ultérieure (GPL-2.0-or-later).
Voir le fichier [`LICENSE`](LICENSE) pour le texte complet.
