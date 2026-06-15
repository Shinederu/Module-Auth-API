# Module Auth API

Backend central d'authentification, utilisateurs, sessions et administration des
droits de l'ecosysteme Shinede.

## Role

Ce repo est extrait de l'ancien monorepo `Legacy-Shinederu-API`.

Il porte:

- inscription, verification e-mail, login, logout et session cookie `sid`;
- profils utilisateur, avatars et flux e-mail/mot de passe;
- moderation de comptes (`is_banned`, motif et suppression des sessions);
- endpoints admin utilisateurs;
- endpoints super-admin du modele de droits centralises `core_*`;
- snapshot `project_access` des projets actifs, expose par `me`.

## Repo et deploiement

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

Le `api-code` stable est `auth`. Le nom Git `Module-Auth-API` ne change pas le
chemin runtime ni l'URL publique.

## Endpoints

L'API PHP historique route les appels via le parametre `action` en `camelCase`.
Les reponses JSON suivent le contrat commun:

```json
{ "success": true, "data": {} }
```

```json
{ "success": false, "error": "Message lisible" }
```

Actions publiques:

- `POST action=register`
- `POST action=verifyEmail`
- `POST action=revokeRegister`
- `POST action=login`
- `POST action=requestPasswordReset`
- `PUT action=resetPassword`
- `POST action=confirmEmailUpdate`
- `POST action=revokeEmailUpdate`
- `GET action=getAvatar`

Actions authentifiees par cookie `sid`:

- `GET action=me`
- `POST action=logout`
- `POST action=logoutAll`
- `PUT action=requestEmailUpdate`
- `POST action=updateProfile`
- `POST action=updateAvatar`
- `DELETE action=deleteAccount`

Actions admin Auth avec permission `auth.users.manage`:

- `GET action=listUsers`
- `PUT action=updateUserRole`
- `PUT action=updateUserAdmin` pour pseudo, blocage et changement de mot de passe admin
- `POST action=updateUserAvatarAdmin`

`updateUserAdmin` accepte notamment `userId`, `username`, `is_banned`,
`ban_reason`, `password` et `passwordConfirm`. Un changement de mot de passe
admin invalide les sessions de l'utilisateur cible quand il ne s'agit pas du
compte administrateur courant. Le mot de passe d'un super-admin ne peut etre
modifie que par lui-meme ou par un autre super-admin.

Actions super-admin avec `core.super_admin`:

- `GET action=listCoreAccess`
- `PUT action=saveCoreProject`
- `PUT action=saveCoreRole`
- `PUT action=saveCorePermission`
- `PUT action=setCoreRolePermissions`
- `PUT action=setCoreUserProjectRoles`

## Authentification et permissions

- Cookie session: `sid`
- Domaine attendu: `.shinederu.ch`
- Table sessions: `auth_sessions`
- Table utilisateurs centrale: `users`

`AuthMiddleware` valide la session, charge l'utilisateur et refuse un compte
bloque (`users.is_banned`). En cas de blocage, la session courante est supprimee.

Les controles de droits passent par `Module-ShinedeCore-PHP`:

```php
hasPermission($userId, 'auth', 'users.manage')
```

`core.super_admin` donne l'acces aux endpoints de configuration `core_*`.
`users.role = 'admin'` reste seulement un fallback de transition synchronise
avec le role `core.super_admin`.

`me` expose `user.project_access` pour les frontends:

- `project_access.is_global_admin`
- `project_access.roles`
- `project_access.permissions`

Permissions actuellement projetees dans le snapshot:

- `auth.users.manage`
- `main.announcements.manage`
- `melodyquest.catalog.manage`
- `box.files.manage`
- `wake.devices.wake`
- `wake.devices.manage`
- `wake.users.manage`
- `arcadia.servers.view`
- `arcadia.servers.manage`
- `arcadia.players.manage`
- `arcadia.actions.execute`
- `arcadia.admin`

## Base de donnees

Schema partage: `ShinedeCore`.

Tables principales:

- `users`
- `auth_sessions`
- `auth_password_reset_tokens`
- `auth_email_verification_tokens`
- `core_*` via `Module-ShinedeCore-PHP`

Migrations versionnees:

- `sql/001_auth_prefix_tables.sql`: migration historique de renommage vers le
  prefixe `auth_*`.
- `sql/002_user_account_moderation.sql`: ajout idempotent des champs de
  moderation utilisateur.

Les changements DB non destructifs sont autorises quand necessaires. Ne jamais
supprimer de donnees sans demande explicite.

## Dossiers runtime et fichiers partages

`P:\PROD\API\auth` doit contenir uniquement le runtime utile:

- `index.php`
- `config/`
- `controllers/`
- `middlewares/`
- `services/`
- `utils/`
- `vendor/` si installe en production
- `.env` runtime deja present en production

Ne pas copier `.git`, `.github`, docs de dev, tests, caches, brouillons ou
secrets depuis `DEV`. Preserver le `.env` et le `vendor/` runtime existants sauf
demande explicite.

Les avatars utilisateurs sont servis par l'API Auth et stockes via les donnees
utilisateur; il n'y a pas de dossier de stockage partage propre a Auth.

## Temps reel et evenements

Auth ne publie aucun evenement Mercure actuellement et ne consomme aucun flux
temps reel. Les frontends doivent relire l'etat via HTTP (`me`, `listUsers` ou
`listCoreAccess`) apres reconnexion ou changement de contexte.

Si Auth publie un jour des evenements, ils devront suivre les topics communs:

```text
https://api.shinederu.ch/auth/topics/<resource>
```

## Dependances inter-projets

- `Module-ShinedeCore-PHP`: service `ProjectAccessService` attendu en runtime
  sous `P:\PROD\API\core`.
- `App-ShinedeHub`: interface admin utilisateurs et permissions.
- `Module-Auth-Core` et `Module-Auth-React`: clients frontends privilegies.

Les autres projets doivent passer par l'API Auth ou par les tables centrales
documentees (`users`, `auth_sessions`) pour la validation de session. Une
integration metier ne doit pas modifier directement les tables privees d'un
autre projet.

## Configuration

Les secrets restent dans `.env` local/runtime, jamais dans Git. `.env.example`
ne contient que des noms et valeurs factices.

Variables attendues:

- `BASE_API`
- `ALLOWED_MIME`
- `SESSION_DURATION_HOURS`
- `DB_TYPE`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `SMTP_HOST`
- `SMTP_AUTH`
- `SMTP_USER`
- `SMTP_PASS`
- `SMTP_PORT`
- `SMTP_SECURE`
- `SMTP_FROM`
- `SMTP_NAME`

## Verifications

Lint PHP:

```powershell
Get-ChildItem P:\DEV\GitHub\Module-Auth-API -Recurse -Filter *.php |
  ? { $_.FullName -notmatch '\\vendor\\' } |
  % { php -l $_.FullName }
```

Statut Git:

```powershell
git -c safe.directory=* -C P:\DEV\GitHub\Module-Auth-API status --short --branch
```

## Deploiement

Workflow:

```powershell
cd P:\DEV\GitHub\Module-Auth-API
git checkout main
git pull --rebase
# modifications
git status
git add <files>
git commit -m "Message clair"
git push origin main
```

Puis synchroniser vers `P:\PROD\API\auth` uniquement:

- `index.php`
- `config/`
- `controllers/`
- `middlewares/`
- `services/`
- `utils/`

Preserver en PROD:

- `.env`
- `vendor/`

Ne pas deployer:

- `.git`
- `.github`
- `README.md`
- `AGENTS.md`
- `sql/`
- `.env.example`
- caches, logs, tests ou brouillons

## Logs et observabilite

L'API utilise les erreurs PHP/PHP-FPM et `error_log` pour les erreurs serveur.
Ne jamais logger de mot de passe, token de reset, token e-mail, cookie `sid` ou
secret SMTP/DB complet.

## Notes de reprise

- Le panneau frontend principal est dans `App-ShinedeHub` (ShinedeHub).
- Les droits et permissions sont administres depuis `/permissions`.
- `users.role = 'admin'` reste un fallback historique; la source durable des
  droits est le modele `core_*`.
- CORS est gere principalement par Nginx; ne pas reactiver
  `CorsMiddleware::apply()` sans besoin documente.
