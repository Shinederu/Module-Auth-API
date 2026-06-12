# Guide Agents - Module Auth API

Lire d'abord:

1. `P:\AGENTS.md`
2. `P:\ECOSYSTEM.md`
3. `P:\DEV\GitHub\README.md`
4. `P:\DEV\GitHub\AGENTS.md`
5. `P:\DEV\GitHub\Module-Auth-API\README.md`

## Perimetre

Ce repo est la source de l'API centrale Auth.

- Source: `P:\DEV\GitHub\Module-Auth-API`
- Runtime: `P:\PROD\API\auth`
- Endpoint: `https://api.shinederu.ch/auth/`
- Code projet: `auth`

## Regles locales

- Travailler sur `main`.
- Ne jamais commit `.env`, `vendor/`, logs, caches ou secrets.
- Ne jamais recopier de secret depuis `P:\DEV\Access`.
- Garder les reponses API au format `{ success, data, error }`.
- Garder les actions HTTP historiques en `camelCase`.
- Utiliser `ProjectAccessService` pour les permissions stables.
- `auth.users.manage` protege l'administration des utilisateurs.
- `core.super_admin` protege l'administration du modele `core_*`.
- Auth ne publie pas d'evenement Mercure actuellement; documenter tout nouveau
  flux avant de l'ajouter.

## Verification

```powershell
Get-ChildItem P:\DEV\GitHub\Module-Auth-API -Recurse -Filter *.php |
  ? { $_.FullName -notmatch '\\vendor\\' } |
  % { php -l $_.FullName }
```

## Deploiement PROD

Copier seulement le runtime:

- `index.php`
- `config/`
- `controllers/`
- `middlewares/`
- `services/`
- `utils/`

Preserver en PROD:

- `.env`
- `vendor/`

Ne pas copier `README.md`, `AGENTS.md`, `sql/`, `.env.example`, `.git`, tests,
caches ou brouillons dans `P:\PROD\API\auth`.
