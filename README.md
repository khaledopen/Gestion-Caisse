# Clair — Gestion de caisse

Application multi-utilisateur en français pour gérer une caisse d’entreprise partagée. Laravel 12, Bootstrap 5, MySQL/SQLite, Chart.js, DomPDF et Laravel Excel. Les employés peuvent s’inscrire ; tous consultent le même dashboard et le nom de l’auteur accompagne chaque opération.

## État de livraison

Sur ce poste, PHP 8.4.26 et Composer 2.10.3 sont installés localement dans `.tools`. Les dépendances sont installées, la clé d’application est générée et l’application utilise MySQL. La suite automatisée passe : **14 tests, 58 assertions**. Le compte administrateur doit être créé localement et les identifiants ne sont jamais versionnés.

Les commandes `php.cmd` et `composer.cmd` utilisent l’environnement local, sans dépendre du `PATH` Windows. Le fichier `.tools` n’est pas versionné.

## Démarrage immédiat sur ce poste

Démarrer l’application :

```powershell
.\demarrer.cmd
```

Ouvrir http://127.0.0.1:8000. Pour vérifier le projet à nouveau :

```powershell
.\php.cmd artisan test
```

## Installation sur un autre poste

Prérequis : PHP 8.2+ avec les extensions Laravel et PhpSpreadsheet (notamment pdo_mysql, pdo_sqlite pour les tests, mbstring, dom, xml, zip, gd, fileinfo, intl), Composer et MySQL 8. Créer une base vide `gestion_caisse` en utf8mb4.

Dans le dossier du projet, sous PowerShell :

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Renseigner les accès MySQL dans `.env`, puis :

```powershell
php artisan migrate
php artisan caisse:user responsable@entreprise.example --admin
php artisan test
php artisan serve
```

Ouvrir http://localhost:8000. La commande crée le premier administrateur et demande son nom ainsi qu’un mot de passe masqué de 12 caractères minimum. Les employés créent ensuite leur propre compte depuis la page d’inscription.

Les migrations nécessaires sont déjà incluses dans le projet. Conserver le `composer.lock` pour reproduire les versions validées.

## Passer de SQLite à MySQL 8.4 sous Windows

SQLite suffit pour essayer l’application. Pour un usage partagé ou en production, installer **MySQL Community Server 8.4 LTS** avec le MSI officiel et MySQL Configurator :

1. Exécuter le MSI en administrateur et installer MySQL Server.
2. Dans MySQL Configurator, choisir un profil de développement, conserver TCP/IP sur le port `3306` et installer MySQL comme service Windows automatique.
3. Définir et conserver le mot de passe `root`. Ne pas utiliser `root` dans l’application.
4. Dans MySQL Command Line Client, créer une base et un compte dédiés :

```sql
CREATE DATABASE gestion_caisse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'caisse_app'@'localhost' IDENTIFIED BY 'REMPLACER_PAR_UN_MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON gestion_caisse.* TO 'caisse_app'@'localhost';
FLUSH PRIVILEGES;
```

5. Remplacer la section base de données de `.env` :

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestion_caisse
DB_USERNAME=caisse_app
DB_PASSWORD="REMPLACER_PAR_UN_MOT_DE_PASSE_FORT"
```

6. Initialiser la nouvelle base et créer l’administrateur initial :

```powershell
.\php.cmd artisan config:clear
.\php.cmd artisan migrate --force
.\php.cmd artisan caisse:user responsable@entreprise.example --admin
```

Cette procédure initialise une base MySQL vide. Elle ne transfère pas automatiquement d’éventuelles données SQLite.

## Fonctionnalités

- Tableau de bord : solde global, entrées/sorties de la sélection, opérations validées du jour et graphiques des flux.
- Recettes, dépenses, approvisionnements et retraits, avec motif, date, paiement et référence de justificatif textuelle.
- Reçu PDF de chaque opération, portant la mention d’annulation le cas échéant.
- Annulation tracée avec motif, auteur et date ; aucune suppression ni modification d’écriture validée.
- Historique paginé, recherche par motif, filtre de type, statut, dates, jour/semaine/mois.
- Exports PDF et Excel reprenant les mêmes filtres, limités à 5 000 lignes par export.
- Inscription des employés avec accès immédiat au dashboard commun.
- Toutes les opérations sont partagées et affichent le nom de leur créateur.
- Vue administrateur globale des comptes et du nombre d’opérations créées.
- Blocage et déblocage des utilisateurs sans suppression du compte ni de son historique.

## Règles et choix fonctionnels

1. Une seule caisse et une seule devise pour cette version. `CAISSE_CURRENCY=XOF` est une hypothèse configurable avant la première utilisation ; changer le code devise ne convertit pas les écritures existantes.
2. Montants strictement positifs, deux décimales maximum, stockage entier en centièmes pour éviter les erreurs d’arrondi. Les montants XOF peuvent être saisis sans décimales.
3. Solde initial nul : enregistrer un approvisionnement pour le fonds d’ouverture.
4. Sorties refusées si elles dépassent le solde global. Espèces, Mobile Money et virement sont des modes du registre consolidé, pas des sous-comptes à soldes distincts.
5. Les flux d’entrée regroupent recettes et approvisionnements. Les flux de sortie regroupent dépenses et retraits. Chaque type reste identifiable séparément dans l’historique et les graphiques détaillés.
6. Annulation d’une entrée refusée si les fonds ont déjà été utilisés et que le solde deviendrait négatif. L’écriture reste consultable.
7. Verrou MySQL sur la caisse et transaction atomique pour sérialiser les mutations concurrentes. Identifiant de requête unique pour éviter les doubles saisies par resoumission.
8. Dates futures interdites ; dates passées autorisées. L’absence de solde négatif est vérifiée au moment de la saisie, pas à chaque date historique. Pas de clôture comptable dans cette version.
9. Fuseau configurable via `APP_TIMEZONE`, UTC par défaut. Semaine du lundi jusqu’à aujourd’hui ; mois depuis le premier jour.
10. Les annulations sont exclues des totaux actifs. Les rapports recalculent les écritures selon leur statut actuel ; ce ne sont pas des arrêtés comptables figés.

## Vérification et déploiement

Les tests couvrent les montants exacts, doubles soumissions, refus de découvert, annulations, inscription, dashboard partagé, attribution des opérations, droits administrateur et blocage des comptes. La sérialisation concurrente doit être validée sur MySQL ; SQLite ne reproduit pas les verrous InnoDB. Vérifier également les PDF, le téléchargement Excel et le rendu responsive sur l’environnement cible.

Les styles Bootstrap, Chart.js et polices sont chargés depuis des CDN et nécessitent Internet. Pour un déploiement hors ligne, les héberger localement et ajuster les liens. Aucun build Node n’est nécessaire.

En production : HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, accès web limité au dossier `public`, sauvegardes de la base et permissions d’écriture sur `storage`/`bootstrap/cache`. Exécuter `php artisan optimize` après configuration. Conserver la clé applicative et ne pas publier `.env`.

Les pièces jointes, la réinitialisation de mot de passe par e-mail, les caisses multiples et les clôtures ne sont pas incluses.

## Références

- [Laravel 12](https://laravel.com/docs/12.x/installation)
- [Laravel Excel](https://docs.laravel-excel.com/3.1/getting-started/installation.html)
