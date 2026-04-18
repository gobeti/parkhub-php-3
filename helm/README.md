# ParkHub PHP Helm Chart

Deploy ParkHub PHP (Laravel) to Kubernetes using Helm.

## Prerequisites

- Kubernetes 1.25+
- Helm 3.10+
- MySQL 8+ or PostgreSQL 15+ (external or in-cluster)
- Redis 7+ (for cache/queue/session)

## Install

```bash
# Generate an APP_KEY first (no Docker required)
APP_KEY="base64:$(openssl rand -base64 32)"

helm install parkhub ./helm/parkhub \
  --namespace parkhub --create-namespace \
  --set config.appKey="$APP_KEY" \
  --set config.dbHost=mysql.parkhub.svc \
  --set config.dbPassword=secret \
  --set config.redisHost=redis.parkhub.svc
```

## Install with custom values

```bash
helm install parkhub ./helm/parkhub \
  --namespace parkhub --create-namespace \
  -f my-values.yaml
```

## Configuration

Key values (see `helm/parkhub/values.yaml` for full reference):

| Parameter | Default | Description |
|-----------|---------|-------------|
| `replicaCount` | `1` | Number of replicas |
| `image.repository` | `ghcr.io/nash87/parkhub-php` | Container image |
| `image.tag` | `appVersion` | Image tag |
| `service.type` | `ClusterIP` | Service type |
| `service.port` | `80` | Service port (Apache) |
| `ingress.enabled` | `false` | Enable ingress |
| `persistence.enabled` | `true` | Enable persistent storage |
| `persistence.size` | `2Gi` | PVC size (Laravel storage) |
| `config.appKey` | `""` | **Required** -- Laravel APP_KEY |
| `config.dbConnection` | `mysql` | Database driver (`mysql`, `pgsql`, `sqlite`) |
| `config.dbHost` | `""` | Database host |
| `config.dbPassword` | `""` | Database password |
| `config.cacheDriver` | `redis` | Cache backend |
| `config.redisHost` | `""` | Redis host |
| `config.smtp.*` | `""` | SMTP settings |
| `config.stripe.*` | `""` | Stripe payment keys |
| `config.oauth.*` | `""` | OAuth provider credentials |
| `modules.*` | `true` | Feature flag toggles |
| `autoscaling.enabled` | `false` | Enable HPA |
| `resources.limits.memory` | `512Mi` | Memory limit |

## Module flags

All 52 module flags are exposed in `values.yaml` under `modules.*`. Set any to `false` to disable:

```yaml
modules:
  evCharging: false
  dynamicPricing: false
```

## Database & Redis

ParkHub PHP requires MySQL/PostgreSQL and Redis. Either provide external connection details or use in-cluster services:

```yaml
config:
  dbConnection: mysql
  dbHost: mysql.parkhub.svc
  dbPort: 3306
  dbDatabase: parkhub
  dbUsername: parkhub
  dbPassword: secret
  redisHost: redis.parkhub.svc
  redisPort: 6379
```

## Post-install: Run migrations

```bash
kubectl exec -n parkhub deploy/parkhub-parkhub-php -- php artisan migrate --force
```

## Ingress with TLS

```yaml
ingress:
  enabled: true
  className: nginx
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
  hosts:
    - host: parking.example.com
      paths:
        - path: /
          pathType: Prefix
  tls:
    - secretName: parkhub-tls
      hosts:
        - parking.example.com
```

## Upgrade

```bash
helm upgrade parkhub ./helm/parkhub --namespace parkhub
kubectl exec -n parkhub deploy/parkhub-parkhub-php -- php artisan migrate --force
```

## Uninstall

```bash
helm uninstall parkhub --namespace parkhub
```
