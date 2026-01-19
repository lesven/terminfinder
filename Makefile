# Terminfinder Makefile
# Praktische Befehle für Docker-Verwaltung

.PHONY: help start stop restart logs clean status shell db install

# Standard Target
help: ## Zeigt diese Hilfe an
	@echo "Terminfinder - Verfügbare Make-Befehle:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'
	@echo ""

# Docker Container Management
start: ## Startet alle Container (hoch)
	@echo "🚀 Starte Terminfinder Container..."
	docker-compose up -d
	@echo "✅ Container gestartet!"
	@echo "   Terminfinder: http://localhost:8070"
	@echo "   phpMyAdmin:   http://localhost:8081"

up: start ## Alias für start

hoch: start ## Alias für start (deutsch)

stop: ## Stoppt alle Container (runter)
	@echo "🛑 Stoppe Terminfinder Container..."
	docker-compose down
	@echo "✅ Container gestoppt!"

down: stop ## Alias für stop

runter: stop ## Alias für stop (deutsch)

restart: ## Startet alle Container neu
	@echo "🔄 Starte Container neu..."
	docker-compose restart
	@echo "✅ Container neu gestartet!"

neustart: restart ## Alias für restart (deutsch)

# Logs und Monitoring
logs: ## Zeigt Container-Logs an
	docker-compose logs -f

logs-web: ## Zeigt nur Webserver-Logs an
	docker-compose logs -f web

logs-db: ## Zeigt nur Datenbank-Logs an
	docker-compose logs -f database

status: ## Zeigt Container-Status an
	@echo "📊 Container Status:"
	docker-compose ps

# Development
shell: ## Öffnet Shell im Web-Container
	docker-compose exec web bash

shell-db: ## Öffnet Shell im Datenbank-Container
	docker-compose exec database bash

db: ## Öffnet MySQL-Kommandozeile
	docker-compose exec database mysql -u terminfinder_user -pterminfinder_pass terminfinder

mysql: db ## Alias für db

# Installation und Setup
install: ## Komplette Neuinstallation (löscht alle Daten!)
	@echo "⚠️  WARNUNG: Dieser Befehl löscht alle bestehenden Daten!"
	@read -p "Fortfahren? (y/N): " confirm && [ "$$confirm" = "y" ]
	@echo "🗑️  Lösche alte Container und Volumes..."
	docker-compose down -v
	@echo "🏗️  Baue Container neu..."
	docker-compose build --no-cache
	@echo "🚀 Starte Container..."
	docker-compose up -d
	@echo "✅ Installation abgeschlossen!"

# Cleanup
clean: ## Stoppt Container und entfernt Volumes (löscht Datenbank!)
	@echo "⚠️  WARNUNG: Dieser Befehl löscht die Datenbank!"
	@read -p "Fortfahren? (y/N): " confirm && [ "$$confirm" = "y" ]
	docker-compose down -v
	@echo "✅ Cleanup abgeschlossen!"

clean-all: ## Entfernt Container, Volumes und Images
	@echo "⚠️  WARNUNG: Dieser Befehl löscht alles (Container, Volumes, Images)!"
	@read -p "Fortfahren? (y/N): " confirm && [ "$$confirm" = "y" ]
	docker-compose down -v --rmi all
	@echo "✅ Vollständiges Cleanup abgeschlossen!"

# Backup und Restore
backup: ## Erstellt Datenbank-Backup
	@echo "💾 Erstelle Datenbank-Backup..."
	mkdir -p backups
	docker-compose exec database mysqldump -u terminfinder_user -pterminfinder_pass terminfinder > backups/backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "✅ Backup erstellt in backups/"

# Update
update: ## Updated Container Images
	@echo "⬇️  Lade neue Container Images..."
	docker-compose pull
	@echo "🔄 Starte Container neu..."
	docker-compose up -d
	@echo "✅ Update abgeschlossen!"

# Quick Actions
open: ## Öffnet Terminfinder im Browser
	@echo "🌐 Öffne http://localhost:8070"
	@command -v xdg-open >/dev/null && xdg-open http://localhost:8070 || \
	command -v open >/dev/null && open http://localhost:8070 || \
	echo "Bitte öffne http://localhost:8070 manuell in deinem Browser"

phpmyadmin: ## Öffnet phpMyAdmin im Browser
	@echo "🗄️  Öffne http://localhost:8081"
	@command -v xdg-open >/dev/null && xdg-open http://localhost:8081 || \
	command -v open >/dev/null && open http://localhost:8081 || \
	echo "Bitte öffne http://localhost:8081 manuell in deinem Browser"

# Development helpers
dev: ## Startet im Development-Modus (mit Logs)
	@echo "🛠️  Starte Development-Modus..."
	docker-compose up

tail: logs ## Alias für logs

ps: status ## Alias für status