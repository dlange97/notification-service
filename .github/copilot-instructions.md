# Copilot Instructions - Notification Service

Scope: This repository only (my-dashboard-backend/notification-service).

## Stack
- PHP 8.2+, Symfony, Doctrine, Messenger.

## Rules
- Keep message handlers idempotent.
- Keep transport and queue-related changes explicit and documented.
- Use service classes for business logic and keep controllers thin.
- Keep OpenAPI/Swagger docs up to date for all endpoint changes.

## Quality
- Run service tests after changes: docker compose exec notification-php bin/phpunit.
- Validate both API and worker paths after async-flow changes.
