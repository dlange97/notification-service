# notification-service

## Overview

Notification microservice handling user inbox and message templates (inbox/email/push channels).

## Contents

- `src/` — notification API and domain logic.
- `migrations/` — database migrations.
- `tests/` — PHPUnit test suite.

## Run (in stack)

```bash
docker compose -f ../../my-dashboard-docker/docker-compose.yml up -d notification-php
```

## Common Operations

```bash
# Migrations
docker compose -f ../../my-dashboard-docker/docker-compose.yml exec -T notification-php php bin/console doctrine:migrations:migrate --no-interaction

# Tests
docker compose -f ../../my-dashboard-docker/docker-compose.yml exec -T notification-php php bin/phpunit
```
