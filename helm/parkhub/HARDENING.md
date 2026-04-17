# ParkHub Helm Chart — Kubernetes Hardening

Status: **enabled by default** (T-1746).

## What you get out of the box

The chart renders a Deployment that satisfies the Kubernetes [Pod Security
Standards **restricted**](https://kubernetes.io/docs/concepts/security/pod-security-standards/#restricted)
profile:

| Control                           | Setting                                       |
|-----------------------------------|-----------------------------------------------|
| Runs as non-root                  | `runAsNonRoot: true`, `runAsUser: 33` (www-data) |
| Privilege escalation              | `allowPrivilegeEscalation: false`             |
| Linux capabilities                | `drop: [ALL]`                                 |
| Read-only root filesystem         | `readOnlyRootFilesystem: true`                |
| Seccomp profile                   | `seccompProfile.type: RuntimeDefault`         |
| Writable state via tmpfs          | `emptyDir` with `sizeLimit: 128Mi` per mount  |

## Writable mounts when `readOnlyRootFilesystem` is on

Laravel + Apache cannot run out of a purely read-only root. The chart
injects `emptyDir` volumes at each path the stack actually writes to:

| Mount path                        | Why Laravel/Apache needs it                                                      |
|-----------------------------------|----------------------------------------------------------------------------------|
| `/var/www/html/bootstrap/cache`   | Compiled config, route, event, and package manifest caches (`php artisan config:cache`). |
| `/var/www/html/storage`           | Logs, framework cache, sessions, file-driver cache. *Skipped when `persistence.enabled: true` — the PVC mounts here instead.* |
| `/var/run/apache2`                | Apache mpm_prefork pidfile + scoreboard.                                         |
| `/var/lock/apache2`               | Advisory locks taken by mod_rewrite and other modules.                           |
| `/tmp`                            | PHP upload buffer + `sys_get_temp_dir()` writes.                                 |

Each mount is capped at `security.readOnlyRootFilesystem.sizeLimit` (default
`128Mi`) so a runaway log or cache cannot fill the node's ephemeral
storage and evict other pods.

## Opting out

Some workflows (dev images running `composer install` or `php artisan
tinker` with on-the-fly vendor writes) want a writable root. Flip the
flag:

```yaml
security:
  readOnlyRootFilesystem:
    enabled: false
```

This removes **only** `readOnlyRootFilesystem: true` and the emptyDir
mounts it implies. The seccomp profile, non-root user, dropped
capabilities, and privilege-escalation block all stay on.

## Verifying after a deploy

```bash
# Pod-level seccomp + non-root
kubectl get pod -l app.kubernetes.io/name=parkhub -o jsonpath='{.items[0].spec.securityContext}'

# Container-level readOnlyRootFilesystem + caps
kubectl get pod -l app.kubernetes.io/name=parkhub -o jsonpath='{.items[0].spec.containers[0].securityContext}'

# Kyverno / PSS admission — if the namespace is labelled
# pod-security.kubernetes.io/enforce=restricted the pod must admit
# cleanly with zero warnings.
kubectl describe pod -l app.kubernetes.io/name=parkhub | grep -i -E 'warning|forbidden'
```

## Relationship to the Rust chart

The `parkhub-rust` sibling chart runs a static Rust binary (redb as its
store). It carries the same PSS-restricted profile, but without the
Laravel/Apache emptyDir mounts — the binary only writes to the PVC
mount at `/data`.
