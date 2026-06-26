# Guide Agents - Module Auth API

Lire d'abord:

1. `P:\AGENTS.md`
2. `P:\ECOSYSTEM.md`
3. `P:\README.md`
4. `P:\DEV\GitHub\README.md`
5. `P:\DEV\GitHub\AGENTS.md`
6. `P:\DEV\GitHub\Module-Auth-API\README.md`

## Perimetre

Ce repo est la source de l'API centrale Auth.

- Source: `P:\DEV\GitHub\Module-Auth-API`
- Runtime: `P:\PROD\API\auth`
- Endpoint: `https://api.shinederu.ch/auth/`
- Code projet: `auth`

Regle de controle:

```text
Est-ce que je suis en train de modifier un autre projet que Module-Auth-API?
Si oui, je m'arrete et je documente le besoin au lieu de modifier.
```

Exceptions possibles uniquement si l'utilisateur les inclut clairement dans le
perimetre: `Module-ShinedeCore-PHP`, `Module-Auth-Core`, `Module-Auth-React`.

## Regles locales

- Travailler sur `main`.
- Faire `git pull --rebase` avant de modifier.
- Ne jamais commit `.env`, `vendor/`, logs, caches ou secrets.
- Ne jamais recopier de secret depuis `P:\DEV\Access`.
- Garder les reponses API au format `{ success, data, error }`.
- Garder les actions HTTP historiques en `camelCase`.
- Utiliser `ProjectAccessService` pour les permissions stables.
- `auth.users.manage` protege l'administration des utilisateurs.
- `core.super_admin` protege l'administration du modele `core_*`.
- `users.role = 'admin'` et `users.is_admin` sont des fallbacks de transition.
- Auth ne publie pas d'evenement Mercure actuellement; documenter tout nouveau
  flux avant de l'ajouter.
- Ne pas ajouter Corelink ou un autre projet au snapshot `project_access` sans
  demande explicite couvrant Auth/permissions et le consommateur frontend.

## Documentation

Mettre a jour `README.md` quand une information durable change:

- endpoint ou parametre;
- permission;
- table/champ DB;
- variable d'environnement;
- dependance inter-projets;
- procedure de verification ou deploiement;
- limite connue.

Une documentation d'analyse destinee a une autre IA va dans
`P:\DEV\AI-Exchange\Reports\<Auteur>` et ne doit contenir aucun secret.

## Verification

```powershell
Get-ChildItem P:\DEV\GitHub\Module-Auth-API -Recurse -Filter *.php |
  ? { $_.FullName -notmatch '\\vendor\\' } |
  % { php -l $_.FullName }
```

Si Composer est disponible:

```powershell
$env:GIT_CONFIG_COUNT='1'
$env:GIT_CONFIG_KEY_0='safe.directory'
$env:GIT_CONFIG_VALUE_0='*'
composer validate --no-check-publish --working-dir='P:\DEV\GitHub\Module-Auth-API'
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
- logs ou fichiers generes

Ne pas copier `README.md`, `AGENTS.md`, `sql/`, `.env.example`, `.gitignore`,
`.git`, tests, caches ou brouillons dans `P:\PROD\API\auth`.

Une modification documentaire seule ne demande pas de deploiement PROD.
