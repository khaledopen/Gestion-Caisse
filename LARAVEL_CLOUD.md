# Déploiement sur Laravel Cloud

## Réglages de l’environnement

- Dépôt : `khaledopen/Gestion-Caisse`
- Branche : `main`
- Runtime : PHP 8.4
- Build : `composer install --no-dev --prefer-dist --optimize-autoloader`
- Déploiement : `php artisan migrate --force`
- Health check : `/up`

Le projet ne possède pas de `package.json`. Ne pas ajouter `npm run build` à la commande de build.

## Ressources

Dans le canvas Infrastructure, attacher une base **Laravel MySQL** dans la même région que l’application. Laravel Cloud injecte automatiquement `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` et `DB_PASSWORD`.

Variables applicatives recommandées :

```dotenv
APP_NAME="Clair — Gestion de caisse"
APP_ENV=production
APP_DEBUG=false
APP_LOCALE=fr
APP_TIMEZONE=UTC
LOG_CHANNEL=stderr
SESSION_DRIVER=cookie
CACHE_STORE=file
CAISSE_CURRENCY=XOF
```

Laravel Cloud crée et injecte `APP_KEY`. Ne jamais copier le fichier `.env` local dans Cloud.

## Premier administrateur

Après le premier déploiement :

1. Ouvrir `/inscription` et créer le compte responsable.
2. Dans les commandes de l’environnement Cloud, exécuter :

```bash
php artisan caisse:admin responsable@entreprise.com
```

## Transfert facultatif de la base locale

Pour conserver les données locales, activer temporairement l’endpoint public de la base Cloud, exporter MySQL localement puis importer le dump avec les identifiants affichés dans **Connection details**. Le dump contient des données sensibles : il est ignoré par Git et doit être supprimé après vérification. Désactiver ensuite l’endpoint public.
