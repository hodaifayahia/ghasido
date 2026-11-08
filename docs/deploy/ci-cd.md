# CI/CD

Two GitHub Actions workflows, in `.github/workflows/`:

| Workflow     | Runs when                                                                              | What it does                                                                                                                                                                                                                                                                                                                                                                                                      |
| ------------ | -------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `tests.yml`  | every pull request, every push to `main`                                               | **CI.** `composer setup` then `composer ci:check`: formatting + lint (`vp check`), Vue/TS types, Pint, PHPStan level 7 and the PHPUnit suite. A newer push to the same pull request cancels the older run.                                                                                                                                                                                                        |
| `deploy.yml` | after `tests` passes on a push to `main`; or by hand (Actions → deploy → Run workflow) | **CD.** Signs in to Hostinger over SSH and, in the server's git clone (`~/ghasido-src`), fast-forwards `main` to the exact commit that passed, snapshots the live SQLite database (last 10 kept in `database/backups/*-predeploy.sqlite`), runs `docs/deploy/scripts/server-deploy.sh` (build, copy, migrate, caches, workers), prints `php artisan ai:doctor`, then checks `https://ghasido.com/up` answers 200. |

So the flow is: open a pull request → `tests` runs → merge → `tests` runs on `main` → `deploy` ships that commit. A red `tests` run never deploys. One deploy runs at a time; a newer one waits.

If `main` moves on while a deploy waits, the older run skips and the newer one deploys its own commit.

## One-time setup

### 1. A deploy key for GitHub (on the server)

Run in the Hostinger SSH terminal:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/github-deploy -N "" -C "github-actions-deploy" && cat ~/.ssh/github-deploy.pub >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys
```

Print the private key once, copy it into the `HOSTINGER_SSH_KEY` secret (step 3), then delete the file:

```bash
cat ~/.ssh/github-deploy
```

```bash
rm ~/.ssh/github-deploy
```

The public half stays in `~/.ssh/authorized_keys`. To revoke GitHub's access later, delete the line ending in `github-actions-deploy` from that file.

### 2. The server's host key (pins the server, so the deploy cannot be redirected)

```bash
ssh-keyscan -p 65002 89.117.116.239 2>/dev/null
```

Copy every line it prints into the `HOSTINGER_SSH_KNOWN_HOSTS` secret.

### 3. GitHub secrets

GitHub → the repository → **Settings → Secrets and variables → Actions → New repository secret**:

| Secret                      | Value                                       |
| --------------------------- | ------------------------------------------- |
| `HOSTINGER_SSH_HOST`        | `89.117.116.239`                            |
| `HOSTINGER_SSH_PORT`        | `65002` (optional; this is the default)     |
| `HOSTINGER_SSH_USER`        | `u673635734`                                |
| `HOSTINGER_SSH_KEY`         | the private key from step 1 (all its lines) |
| `HOSTINGER_SSH_KNOWN_HOSTS` | the lines from step 2                       |

Optional **variables** (same page, Variables tab): `APP_URL` (default `https://ghasido.com`), `DEPLOY_DOMAIN` (folder under `~/domains`, default `ghasido.com`), `DEPLOY_SRC` (the clone under `~`, default `ghasido-src`).

### 4. Optional: approve each deploy by hand

The deploy job uses the GitHub environment `production`. Under **Settings → Environments → production → Required reviewers**, add yourself: every deploy then waits for your click in the Actions tab.

## Everyday use

- Ship: merge the pull request. Watch it under **Actions → deploy**.
- Redeploy the current `main` (e.g. after changing `.env` on the server): **Actions → deploy → Run workflow**.
- The manual way still works exactly as before:

```bash
cd ~/ghasido-src && git checkout main && git pull origin main && bash docs/deploy/scripts/server-deploy.sh
```

## If a deploy fails

- **"Secret HOSTINGER\_… is not set"**: finish step 3.
- **`Host key verification failed`**: the `HOSTINGER_SSH_KNOWN_HOSTS` secret is missing or the server's key changed; redo step 2.
- **`Permission denied (publickey)`**: the public key is not in `~/.ssh/authorized_keys`, or the secret holds the wrong half; redo step 1.
- **`Not possible to fast-forward`**: someone committed inside `~/ghasido-src` on the server. Run `cd ~/ghasido-src && git status` and remove the local change.
- **Build or migration error**: the log shows the `server-deploy.sh` output. The live site keeps running the previous release until the copy step. To roll back a migration, restore the newest `database/backups/*-predeploy.sqlite` over `database/database.sqlite` (site in maintenance: `php artisan down`, copy, `php artisan up`).
- **`/up did not answer 200`**: open the site and `storage/logs/laravel-*.log` on the server.
