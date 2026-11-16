.PHONY: up down logs

up:
	docker compose --env-file .env -f docker/compose.dev.yaml up --build -d --wait --remove-orphans

down:
	docker compose --env-file .env -f docker/compose.dev.yaml down

logs:
	docker compose --env-file .env -f docker/compose.dev.yaml logs --tail=100 -f
