.PHONY: up down logs prod-up prod-down prod-logs test
up:
	docker compose -f compose.dev.yaml up --build -d --wait --remove-orphans
down:
	docker compose -f compose.dev.yaml down
logs:
	docker compose -f compose.dev.yaml logs --tail=100 -f
prod-up:
	docker compose --env-file .env.production up --build -d --wait
prod-down:
	docker compose --env-file .env.production down
prod-logs:
	docker compose --env-file .env.production logs --tail=100 -f
test:
	php tests/config.php
