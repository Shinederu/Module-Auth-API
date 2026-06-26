# Module Auth API

API centrale d'authentification, utilisateurs, sessions et administration des
droits de l'ecosysteme Shinede.

## Role

`Module-Auth-API` est le proprietaire du projet integre `auth`.

Il porte:

- inscription, verification e-mail, login, logout et session cookie `sid`;
- sessions utilisateurs partagees par les APIs PHP de l'ecosysteme;
- profils utilisateur, avatars et flux e-mail/mot de passe;
- moderation de comptes (`is_banned`, motif, date, admin auteur);
- endpoints admin utilisateurs;
- endpoints super-admin pour administrer le modele de droits centralise
  `core_*`;
- snapshot `project_access` consomme par les frontends.

Ce repo est extrait de l'ancien monorepo `Legacy-Shinederu-API`. Les endpoints
historiques restent routes par `action` en `camelCase`.

## Repo et deploiement

- Source DEV: `P:\DEV\GitHub\Module-Auth-API`
- Runtime PROD: `P:\PROD\API\auth`
- Endpoint public: `https://api.shinederu.ch/auth/`
- Code projet stable: `auth`
- Branche normale: `main`
- Remote: `https://github.com/Shinederu/Module-Auth-API.git`

Le nom du repo ne remplace jamais le contrat runtime. L'URL publique et le
dossier PROD restent bases sur le code `auth`.

## Structure du repo

- `index.php`: routeur HTTP unique.
- `controllers/`: orchestration des endpoints.
- `services/`: acces DB, sessions, profils, e-mails, tokens et permissions.
- `middlewares/`: validation de session et CORS historique.
- `config/`: configuration lue depuis `.env`.
- `utils/`: helpers requete, reponse JSON et sanitization.
- `sql/`: migrations DB utiles a la reprise, non deployees par defaut.
- `.env.example`: exemple sans secret.
- `README.md` et `AGENTS.md`: documentation source uniquement.

## Endpoints

Base:

```text
https://api.shinederu.ch/auth/
```

Les requetes `POST` et `PUT` acceptent un corps JSON ou un fallback formulaire.
Les requetes `GET` et `DELETE` lisent aussi `action` depuis la query string ou
la requete selon le cas.

Reponse succes:

```json
{
  "success": true,
  "data": {}
}
```

Reponse erreur:

```json
{
  "success": false,
  "error": "Message lisible"
}
```

Codes HTTP attendus:

- `400`: entree invalide;
- `401`: non authentifie, session absente ou expiree;
- `403`: authentifie mais interdit, compte bloque ou protection super-admin;
- `404`: action ou ressource inexistante;
- `409`: conflit fonctionnel, par exemple utilisateur/e-mail deja pris;
- `500`: erreur serveur.

### Actions publiques

| Methode | Action | Role |
| --- | --- | --- |
| `POST` | `register` | Cree un compte non verifie et envoie l'e-mail de verification. |
| `POST` | `verifyEmail` | Valide un compte via token e-mail. |
| `POST` | `revokeRegister` | Annule une inscription non verifiee via token. |
| `POST` | `login` | Cree une session `sid` si les identifiants et l'e-mail sont valides. |
| `POST` | `requestPasswordReset` | Envoie un lien de reset si le compte existe. |
| `PUT` | `resetPassword` | Change le mot de passe via token et supprime les sessions. |
| `POST` | `confirmEmailUpdate` | Confirme le changement d'e-mail via token. |
| `POST` | `revokeEmailUpdate` | Annule le changement d'e-mail via token. |
| `GET` | `getAvatar` | Retourne l'avatar PNG stocke pour `user_id`. |

Parametres principaux:

- `register`: `username`, `email`, `password`, `password_confirm`.
- `verifyEmail`, `revokeRegister`, `confirmEmailUpdate`, `revokeEmailUpdate`:
  `token`.
- `login`: `username` (pseudo ou e-mail), `password`.
- `requestPasswordReset`: `email`.
- `resetPassword`: `token`, `password`, `passwordConfirm`.
- `getAvatar`: `user_id`, optionnellement `v` pour le cache navigateur.

### Actions authentifiees

Ces actions necessitent un cookie `sid` valide.

| Methode | Action | Role |
| --- | --- | --- |
| `GET` | `me` | Retourne l'utilisateur courant et son `project_access`. |
| `POST` | `logout` | Supprime la session courante. |
| `POST` | `logoutAll` | Supprime toutes les sessions de l'utilisateur courant. |
| `PUT` | `requestEmailUpdate` | Lance un changement d'e-mail avec double notification. |
| `POST` | `updateProfile` | Change le pseudo de l'utilisateur courant. |
| `POST` | `updateAvatar` | Remplace l'avatar de l'utilisateur courant. |
| `DELETE` | `deleteAccount` | Supprime le compte apres verification du mot de passe. |

Parametres principaux:

- `requestEmailUpdate`: `email`.
- `updateProfile`: `username`.
- `updateAvatar`: `image_base64` ou fichier upload `file`.
- `deleteAccount`: `password`.

### Actions admin Auth

Ces actions necessitent la permission stable `auth.users.manage`.

| Methode | Action | Role |
| --- | --- | --- |
| `GET` | `listUsers` | Liste les utilisateurs avec metadata admin et `project_access`. |
| `PUT` | `updateUserRole` | Synchronise le role historique `admin/user` et `core.super_admin`. |
| `PUT` | `updateUserAdmin` | Modifie pseudo, blocage et/ou mot de passe d'un utilisateur gere. |
| `POST` | `updateUserAvatarAdmin` | Remplace l'avatar d'un utilisateur gere. |

Parametres principaux:

- `updateUserRole`: `userId`, `role` (`admin` ou `user`) ou `is_admin`.
- `updateUserAdmin`: `userId`/`user_id`/`id`, `username`, `is_banned`,
  `ban_reason`, `password` ou `new_password`, `passwordConfirm`,
  `password_confirm` ou `new_password_confirm`.
- `updateUserAvatarAdmin`: `userId`/`user_id`/`id`, `image_base64` ou `file`.

Regles importantes pour `updateUserAdmin`:

- Un admin ne peut pas bloquer son propre compte.
- Un mot de passe admin doit faire au moins 8 caracteres et etre confirme.
- Quand un admin change le mot de passe d'un autre utilisateur, les sessions de
  l'utilisateur cible sont supprimees.
- Le mot de passe d'un super-admin ne peut etre modifie que par lui-meme ou par
  un autre super-admin.

### Actions super-admin Core

Ces actions necessitent `core.super_admin`.

| Methode | Action | Role |
| --- | --- | --- |
| `GET` | `listCoreAccess` | Retourne projets, roles, permissions, utilisateurs et assignations. |
| `PUT` | `saveCoreProject` | Cree ou met a jour un projet `core_projects`. |
| `PUT` | `saveCoreRole` | Cree ou met a jour un role `core_project_roles`. |
| `PUT` | `saveCorePermission` | Cree ou met a jour une permission `core_project_permissions`. |
| `PUT` | `setCoreRolePermissions` | Remplace les permissions d'un role. |
| `PUT` | `setCoreUserProjectRoles` | Remplace les roles d'un utilisateur pour un projet. |

Parametres principaux:

- `saveCoreProject`: `id`, `code`, `name`, `description`, `is_active`.
- `saveCoreRole`: `id`, `project_id`, `role_key`, `label`, `description`,
  `sort_order`, `is_active`.
- `saveCorePermission`: `id`, `project_id`, `permission_key`, `label`,
  `description`, `is_active`.
- `setCoreRolePermissions`: `role_id`, `permission_ids`.
- `setCoreUserProjectRoles`: `user_id`, `project_code`, `role_keys`.

Protections:

- Le projet `core` ne peut pas etre desactive.
- Le role `core.super_admin` ne peut pas etre desactive.
- Un super-admin ne peut pas retirer son propre role `core.super_admin`.
- Les permissions assignees a un role doivent appartenir au meme projet.

## Authentification et permissions

Source d'auth commune:

- Cookie session: `sid`
- Domaine cookie attendu: `.shinederu.ch`
- Session: `auth_sessions`
- Utilisateur central: `users`

`AuthMiddleware`:

1. lit le cookie `sid`;
2. verifie la session dans `auth_sessions`;
3. applique une expiration glissante;
4. charge l'utilisateur;
5. refuse un compte bloque (`users.is_banned`);
6. supprime la session courante si le compte est bloque.

Les permissions applicatives passent par `Module-ShinedeCore-PHP`:

```php
hasPermission($userId, 'auth', 'users.manage')
```

Permissions stables utilisees par Auth:

- `auth.users.manage`
- `core.super_admin`

`users.role = 'admin'` et `users.is_admin` restent des fallbacks de transition.
Le modele durable est `core_*`. Les endpoints de role synchronisent autant que
possible le fallback historique et `core.super_admin`.

## Payload `project_access`

`GET action=me` et les endpoints admin utilisateurs exposent un snapshot
`project_access` pour aider les frontends a afficher ou masquer les vues.

Forme generale:

```json
{
  "is_global_admin": false,
  "roles": {
    "core": [],
    "auth": []
  },
  "permissions": {
    "auth": {
      "users_manage": false
    }
  }
}
```

Permissions actuellement projetees:

- `auth.users.manage` -> `permissions.auth.users_manage`
- `main.announcements.manage` -> `permissions.main.announcements_manage`
- `melodyquest.catalog.manage` -> `permissions.melodyquest.catalog_manage`
- `box.files.manage` -> `permissions.box.files_manage`
- `wake.devices.wake` -> `permissions.wake.devices_wake`
- `wake.devices.manage` -> `permissions.wake.devices_manage`
- `wake.users.manage` -> `permissions.wake.users_manage`
- `arcadia.servers.view` -> `permissions.arcadia.servers_view`
- `arcadia.servers.manage` -> `permissions.arcadia.servers_manage`
- `arcadia.players.manage` -> `permissions.arcadia.players_manage`
- `arcadia.actions.execute` -> `permissions.arcadia.actions_execute`
- `arcadia.admin` -> `permissions.arcadia.admin`

Note de reprise: le code liste actuellement les projets `core`, `auth`, `main`,
`melodyquest`, `box`, `wake` et `arcadia`. `corelink` existe dans le contrat
workspace, mais n'est pas encore projete par ce snapshot Auth. Ne pas l'ajouter
depuis ce repo sans demande explicite incluant Auth/permissions et le besoin
frontend correspondant.

## Base de donnees

Schema partage: `ShinedeCore`.

Tables lues/ecrites par Auth:

- `users`
- `auth_sessions`
- `auth_password_reset_tokens`
- `auth_email_verification_tokens`
- `core_projects`
- `core_project_roles`
- `core_project_permissions`
- `core_project_role_permissions`
- `core_user_project_roles`

Champs `users` attendus par le code:

- `id`
- `username`
- `email`
- `password_hash`
- `avatar_url`
- `avatar_image`
- `role`
- `email_verified`
- `created_at`

Champs optionnels geres si presents:

- `is_admin`
- `is_banned`
- `banned_at`
- `banned_by_user_id`
- `ban_reason`

Migrations versionnees:

- `sql/001_auth_prefix_tables.sql`: migration historique de `sessions`,
  `password_reset_tokens` et `email_verification_tokens` vers le prefixe
  `auth_*`. Cette migration n'est pas idempotente et sert de reference
  historique.
- `sql/002_user_account_moderation.sql`: ajout idempotent des champs de
  moderation utilisateur et de l'index `idx_users_is_banned`.

Regles DB:

- Changements non destructifs autorises si necessaires et documentes.
- Preferer une migration SQL idempotente pour tout changement significatif.
- Ne jamais supprimer de donnees sans demande explicite.
- Ne pas modifier les tables metier d'un autre projet depuis Auth.

## Dossiers runtime et fichiers partages

Runtime attendu dans `P:\PROD\API\auth`:

- `index.php`
- `config/`
- `controllers/`
- `middlewares/`
- `services/`
- `utils/`
- `.env` runtime deja present en PROD
- `vendor/` si installe en PROD

Fichiers source a ne pas deployer:

- `.git`
- `.github`
- `.env.example`
- `.gitignore`
- `README.md`
- `AGENTS.md`
- `sql/` sauf demande de reprise/migration explicite
- caches, logs, tests, brouillons, scripts de dev

Les avatars utilisateurs sont stockes dans `users.avatar_image` et servis via
`GET action=getAvatar`. Il n'existe pas de dossier de stockage persistant propre
a Auth.

## Temps reel et evenements

Auth ne publie aucun evenement Mercure et ne consomme aucun flux temps reel
actuellement.

Les frontends doivent resynchroniser par HTTP:

- `GET action=me` pour la session courante;
- `GET action=listUsers` pour l'administration utilisateurs;
- `GET action=listCoreAccess` pour l'administration des permissions.

Si Auth publie un jour des evenements, utiliser des topics conformes au contrat:

```text
https://api.shinederu.ch/auth/topics/<resource>
```

Mercure ne doit pas devenir un bus de commandes. Les commandes restent des
appels HTTP Auth ou Core.

## Dependances inter-projets

Dependances runtime:

- `P:\PROD\API\core` via `Module-ShinedeCore-PHP` pour
  `services/ProjectAccessService.php`.
- MySQL `ShinedeCore`.
- SMTP configure par `.env`.
- PHP-FPM avec extensions PHP compatibles Medoo, PHPMailer, Dotenv et GD pour
  la normalisation d'avatars.

Dependances fonctionnelles:

- `App-ShinedeHub`: dashboard admin utilisateurs et permissions.
- `Module-Auth-Core`: client TypeScript framework-agnostic.
- `Module-Auth-React`: bindings React.

Les autres projets peuvent valider les sessions via `auth_sessions` et `users`
selon le contrat workspace, mais ne doivent pas modifier les tables privees Auth
comme mecanisme d'integration.

## Configuration

Les valeurs reelles vivent dans `.env` local/runtime ou dans `P:\DEV\Access`.
Ne jamais les copier dans Git, PROD public, documentation publique ou reponse
utilisateur.

Variables attendues:

| Variable | Role |
| --- | --- |
| `BASE_API` | URL publique de base pour construire les URLs d'avatar. |
| `ALLOWED_MIME` | Liste CSV des types images acceptes. |
| `SESSION_DURATION_HOURS` | Duree de session et prolongation glissante. |
| `DB_TYPE` | Type Medoo/PDO, normalement `mysql`. |
| `DB_HOST` | Hote MySQL. |
| `DB_PORT` | Port MySQL. |
| `DB_NAME` | Schema, normalement `ShinedeCore`. |
| `DB_USER` | Utilisateur DB Auth. |
| `DB_PASS` | Mot de passe DB Auth. |
| `SMTP_HOST` | Hote SMTP. |
| `SMTP_AUTH` | Active l'auth SMTP. |
| `SMTP_USER` | Utilisateur SMTP. |
| `SMTP_PASS` | Mot de passe SMTP. |
| `SMTP_PORT` | Port SMTP. |
| `SMTP_SECURE` | `starttls`, `smtps`, `ssl`, `tls` ou vide. |
| `SMTP_FROM` | Adresse expediteur. |
| `SMTP_NAME` | Nom expediteur. |

`.env.example` contient uniquement des valeurs factices.

## Verifications

Lint PHP complet:

```powershell
Get-ChildItem P:\DEV\GitHub\Module-Auth-API -Recurse -Filter *.php |
  ? { $_.FullName -notmatch '\\vendor\\' } |
  % { php -l $_.FullName }
```

Validation Composer si disponible:

```powershell
$env:GIT_CONFIG_COUNT='1'
$env:GIT_CONFIG_KEY_0='safe.directory'
$env:GIT_CONFIG_VALUE_0='*'
composer validate --no-check-publish --working-dir='P:\DEV\GitHub\Module-Auth-API'
```

Controle Git:

```powershell
git -c safe.directory=* -C P:\DEV\GitHub\Module-Auth-API status --short --branch
```

Smoke tests manuels recommandes apres changement fonctionnel:

- login avec compte valide;
- `GET action=me`;
- logout;
- reset mot de passe;
- update profil/avatar;
- liste utilisateurs avec compte `auth.users.manage`;
- blocage/deblocage d'un compte de test;
- changement de mot de passe admin d'un compte de test;
- `GET action=listCoreAccess` avec super-admin.

## Deploiement

Workflow DEV:

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

Deploiement PROD runtime uniquement:

```powershell
$src='P:\DEV\GitHub\Module-Auth-API'
$dst='P:\PROD\API\auth'
Copy-Item -LiteralPath (Join-Path $src 'index.php') -Destination (Join-Path $dst 'index.php') -Force
foreach ($dir in @('config','controllers','middlewares','services','utils')) {
  Copy-Item -LiteralPath (Join-Path $src $dir '*') -Destination (Join-Path $dst $dir) -Recurse -Force
}
```

Preserver en PROD:

- `.env`
- `vendor/`
- logs ou fichiers generes si presents

Ne pas synchroniser `README.md`, `AGENTS.md`, `.env.example`, `.gitignore` ou
`sql/` lors d'une simple mise a jour runtime.

Une modification documentaire seule ne demande pas de deploiement PROD.

## Logs et observabilite

L'API utilise les erreurs PHP/PHP-FPM et `error_log` pour les erreurs serveur.

Ne jamais logger:

- mot de passe;
- token de reset;
- token e-mail;
- cookie `sid`;
- secrets SMTP ou DB;
- JWT complet si un flux futur en ajoute.

Pour les futures integrations inter-projets, preferer ajouter un `trace_id` ou
`request_id` documente avant d'elargir la surface de logs.

## Securite

- `.env` et `vendor/` ne sont pas versionnes.
- Les tokens e-mail et reset sont secrets et doivent rester hors logs.
- Les comptes bloques ne doivent pas obtenir ni conserver de session active.
- Les suppressions de donnees sont interdites sans demande explicite, sauf les
  operations utilisateur deja exposees par les endpoints (`deleteAccount`,
  `revokeRegister`) et leurs comportements existants.
- CORS est gere principalement par Nginx; ne pas reactiver
  `CorsMiddleware::apply()` sans besoin documente.
- Les acces infra comme UniFi ne concernent pas ce repo Auth. Si un besoin
  apparait, le documenter et ouvrir le projet proprietaire explicitement.

## Limites connues

- Pas de framework de tests automatise configure.
- `sql/001_auth_prefix_tables.sql` est historique et non idempotente.
- Le snapshot `project_access` est explicite dans le code et doit etre mis a
  jour volontairement quand un nouveau projet doit apparaitre cote frontend.
- Certains commentaires/source historiques affichent des caracteres accentues
  mal encodes; ne pas melanger cette correction avec une tache fonctionnelle.
- `CorsMiddleware` existe encore comme heritage, mais n'est pas applique.

## Notes de reprise

- Lire aussi `P:\ECOSYSTEM.md` avant toute integration avec un autre projet.
- Respecter le perimetre strict: ne pas modifier un autre repo depuis Auth sans
  demande explicite.
- Si une correction semble necessaire dans `Module-ShinedeCore-PHP`,
  `Module-Auth-Core`, `Module-Auth-React`, `App-ShinedeHub` ou un projet
  consommateur, documenter le besoin et attendre que ce projet soit ouvert.
- Le panneau frontend principal est dans `App-ShinedeHub`.
- Les droits sont administres depuis Dashboard -> Permissions (`/permissions`).
- Le runtime PROD attendu reste `P:\PROD\API\auth`, meme si le repo s'appelle
  `Module-Auth-API`.
