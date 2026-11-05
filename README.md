<div align="center">

<img src="https://img.shields.io/badge/Internet%20Apps-E--Commerce%20Platform-blue?style=for-the-badge&logo=shopify&logoColor=white" alt="Internet Apps E-Commerce Platform"/>

# Internet Apps E-Commerce Platform

### A modular e-commerce application with a Laravel API and React frontend

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![React](https://img.shields.io/badge/React-19.x-61DAFB?style=flat-square&logo=react&logoColor=black)](https://react.dev)
[![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)](https://docker.com)

> An e-commerce application organized into Laravel modules, with a React interface for signing in, browsing products, using a cart, and viewing orders.

[Features](#-features) • [Prerequisites](#-prerequisites) • [Getting Started](#-getting-started) • [Docker](#-docker-build-and-run) • [Project Structure](#-project-structure)

</div>

---

## ✨ Features

- **React storefront interface:** Login, home/products, cart, and orders pages, with product and order detail components.
- **Modular Laravel backend:** Separate `User`, `Product`, `Category`, `Order`, and `Notification` modules.
- **User access:** Registration and sign-in API logic, with protected resource-route scaffolding.
- **Product catalog foundation:** Category and product models/migrations, including product price, stock, photo, and category fields.
- **gRPC inventory service:** Laravel and Java communicate over gRPC; inventory quantities and persistent reservations are stored in a database dedicated to the Java service.
- **Separate service databases:** Laravel owns catalog and order data in `mysql`; Java owns available stock and reservations in `inventory-db`.
- **Requirement 2 walkthrough:** See [README-REQUIREMENT-2.md](README-REQUIREMENT-2.md) for the architecture, code flow, database ownership, and verification steps.
- **Docker Compose environment:** Laravel MySQL, inventory MySQL, Java gRPC inventory, Laravel backend, and React frontend services.



## 🧰 Prerequisites

### Local development

- PHP **8.2+**
- [Composer](https://getcomposer.org/) 2
- [Node.js](https://nodejs.org/) **22+** and npm
- MySQL **8.x** (the Compose service uses MySQL 8.4)
- Java **21** (only needed to run the gRPC service locally; Docker builds it otherwise)
- PHP gRPC extension (only needed when Laravel runs locally with gRPC enabled)

### Docker

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) or Docker Engine with the Docker Compose plugin

## 🚀 Getting Started

### Run locally

1. Install the backend dependencies and create the environment file:

   ```powershell
   composer install
   if (-not (Test-Path .env)) { Copy-Item .env.example .env }
   ```

2. Edit `.env` for your local MySQL server. Set `DB_HOST=127.0.0.1`, and provide the database name, username, and password for a database you have created.

3. Generate the application key and apply database migrations:

   ```powershell
   php artisan key:generate
   php artisan migrate
   ```

4. To use inventory over gRPC locally, set `ORDER_STOCK_MANAGER=grpc` and `GRPC_INVENTORY_HOST=127.0.0.1:9090` in `.env`. Start Java with a separate inventory database:

   ```powershell
   $env:SPRING_DATASOURCE_URL = "jdbc:mysql://127.0.0.1:3306/inventory_db?useSSL=false&serverTimezone=UTC&allowPublicKeyRetrieval=true"
   $env:INVENTORY_DB_USERNAME = "inventory"
   $env:INVENTORY_DB_PASSWORD = "inventory_dev_password"
   Set-Location inventory-service
   .\mvnw.cmd spring-boot:run
   ```

   The inventory service creates its own tables from `inventory-service/src/main/resources/schema.sql`. Laravel initializes inventory records for existing products on first catalog access; the Laravel `products.stock` value is only the initial quantity for a missing inventory record. Keep `ORDER_STOCK_MANAGER=local` if you do not want to run the Java service.

5. Start the Laravel backend in one terminal:

   ```powershell
   php artisan serve
   ```

   The backend is available at [http://localhost:8000](http://localhost:8000).

6. In another terminal, install and start the React frontend:

   ```powershell
   Set-Location frontend
   npm install
   npm run dev
   ```

   The frontend is available at [http://localhost:5173](http://localhost:5173).

## 🐳 Docker: Build and Run

Docker Compose builds and starts the database, schema migration, Java gRPC inventory service, Laravel backend, and React frontend:

| Service / container | Purpose | Host access |
|---|---|---|
| `frontend` | React + Vite development server | [http://localhost:5173](http://localhost:5173) |
| `backend` | Laravel application served by Nginx/PHP-FPM | [http://localhost:8080](http://localhost:8080) |
| `inventory-service` | Java gRPC stock service | `127.0.0.1:9090` |
| `mysql` | MySQL database | `127.0.0.1:3307` (MySQL client; not a browser URL) |
| `inventory-db` | Inventory database, private to the Compose network | No host port exposed |

The ports on the host are configurable with `FRONTEND_PORT`, `BACKEND_PORT`, `DB_FORWARD_PORT`, and `GRPC_FORWARD_PORT` in `.env`. Inside the Compose network, Laravel uses `mysql:3306`, the inventory service uses the separate `inventory-db:3306` database, and Laravel calls gRPC at `inventory-service:9090`.

### 1. Prepare the environment, build, and start all services

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
docker compose build
docker compose run --rm --no-deps backend php artisan key:generate
docker compose up -d
```

Compose waits for both databases, runs Laravel migrations, then starts the inventory service and backend. The Java service creates the independent inventory schema on startup. Check service status with:

```powershell
docker compose ps
```

For migrations added later, run `docker compose run --rm migrate`. The backend image already installs Composer dependencies during the build; do not run `composer install` inside the container unless you intentionally change the dependency installation workflow.

### 3. Open or access an individual container

Run the matching command from the repository root:

```powershell
docker compose exec backend sh
docker compose exec frontend sh
docker compose exec inventory-service sh
docker compose exec mysql sh
docker compose exec inventory-db sh
```

To open a MySQL client inside the database container instead of a shell:

```powershell
docker compose exec mysql sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

Use the browser links in the table above to open the frontend or backend. MySQL is a database service, not a website; connect to `127.0.0.1:3307` with a MySQL client. From the backend, use host `mysql` and port `3306`. The inventory service connects only to `inventory-db:3306`.

### Useful Compose commands

```powershell
# Follow logs from all services
docker compose logs -f

# Follow logs from one service
docker compose logs -f backend

# Stop and remove the containers and network (keeps the database volume)
docker compose down

# Stop and remove containers, network, and the database volume
docker compose down --volumes
```

> `docker compose down --volumes` deletes both persisted MySQL databases and product-image data. Use it only when you intend to remove the local data.

The backend's `product_images` volume keeps the bundled and uploaded product images available across container rebuilds. It is mounted at the products directory so a newly created volume is initialized from the images in the backend image. `docker compose down --volumes` also deletes this image data. If you previously used the `product_storage` volume, it remains on disk but is no longer used.

## 📂 Project Structure

```text
.
├── app/                    # Laravel application-level code
├── Modules/
│   ├── Category/           # Category module
│   ├── Notification/       # Notification module
│   ├── Order/              # Order module
│   ├── Product/            # Product module
│   └── User/               # User and authentication module
├── database/               # Shared Laravel migrations and seeders
├── docker/                 # Nginx and Supervisor configuration
├── frontend/
│   └── src/
│       └── features/       # React auth, home, cart, orders, notifications
├── resources/              # Laravel views, CSS, and JavaScript
├── routes/                 # Application-level Laravel routes
├── tests/                  # Laravel feature and unit tests
├── Dockerfile              # Backend image
└── docker-compose.yml      # Separate MySQL databases, migrations, gRPC inventory, backend, and frontend
```

## 🧪 Tests

Run the Laravel tests with:

```powershell
composer test
```

The PHPUnit bootstrap forces tests to use an in-memory SQLite database and the local stock manager, then exits before Laravel starts if those safeguards or PDO SQLite are unavailable. This prevents inherited Docker/MySQL environment variables from redirecting destructive test migrations to the application database.

To run the live inventory integration tests, first start the Compose services, then run:

```powershell
docker compose run --rm --no-deps -e RUN_INVENTORY_INTEGRATION_TESTS=1 backend php artisan test --testsuite=Integration
```

These tests contact the running gRPC inventory service, use generated test-only product IDs, and verify stock reads, reservation/release, concurrent reservations, order-failure compensation, an unavailable service, a nonresponsive service exceeding the five-second deadline, and commit behavior. They do not use or modify Laravel's MySQL database. The inventory database retains the synthetic test product rows; they are not catalog products.

## 📚 Laravel Framework Resources

The following build, package, learning, sponsor, contribution, conduct, and security links were part of the original Laravel README template. They refer to the Laravel framework, not project-specific services or contacts.

[![Laravel Framework Build Status](https://github.com/laravel/framework/workflows/tests/badge.svg)](https://github.com/laravel/framework/actions)
[![Laravel Framework Downloads](https://img.shields.io/packagist/dt/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![Laravel Framework Version](https://img.shields.io/packagist/v/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![Laravel Framework License](https://img.shields.io/packagist/l/laravel/framework)](https://packagist.org/packages/laravel/framework)
