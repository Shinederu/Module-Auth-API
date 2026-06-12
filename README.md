# Module Auth API

Backend central d'authentification, utilisateurs, sessions et administration des
droits de l'ecosysteme Shinede.

## Role

Ce repo est extrait de l'ancien monorepo `Legacy-Shinederu-API`.

Il porte:

- login/register/logout et session cookie `sid`;
- profils utilisateur, avatars et email/password flows;
- moderation de comptes (`is_banned`, motif et suppression des sessions);
- endpoints admin utilisateurs;
- endpoints admin du modele de droits centralises `core_*`.

## Deploiement

Source:

```text
P:\DEV\GitHub\Module-Auth-API
```

Runtime production:

```text
P:\PROD\API\auth
```

Endpoint public:

```text
https://api.shinederu.ch/auth/
```

La structure de `P:\PROD\API` reste stable malgre le decoupage Git.

## Dependances

`composer.json` declare:

- `catfan/medoo`;
- `phpmailer/phpmailer`;
- `vlucas/phpdotenv`.

`vendor/` n'est pas versionne. Ne pas ecraser le `vendor/` runtime en prod sans
besoin explicite.

## Configuration

Les secrets restent dans `.env` local/runtime, jamais dans Git.

Variables principales:

- `DB_*`;
- `SMTP_*`;
- options cookie/session;
- URLs publiques du domaine.

Les autres APIs PHP peuvent reutiliser les credentials deployes sous
`P:\PROD\API\auth\.env`.

## Base de donnees

Schema partage: `ShinedeCore`.

Tables principales:

- `users`;
- `auth_sessions`;
- `auth_password_reset_tokens`;
- `auth_email_verification_tokens`;
- `core_*` via `Module-ShinedeCore-PHP`.

Migrations:

- `sql/001_auth_prefix_tables.sql`;
- `sql/002_user_account_moderation.sql`.

Les changements DB non destructifs sont autorises quand necessaires. Ne jamais
supprimer de donnees sans demande explicite.

## Verifications

```powershell
Get-ChildItem P:\DEV\GitHub\Module-Auth-API -Recurse -Filter *.php |
  ? { $_.FullName -notmatch '\\vendor\\' } |
  % { php -l $_.FullName }
```

## Notes de reprise

- Le panneau frontend principal est dans `App-ShinedeHub` (ShinedeHub).
- Les droits et permissions sont administres depuis `/permissions`.
- `users.role = 'admin'` reste un fallback historique; la source durable des
  droits est le modele `core_*`.
