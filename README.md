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
- **Complete gRPC guide:** See [GRPC-README.md](GRPC-README.md) for the full contract, configuration, reservation state machine, error handling, testing, troubleshooting, and known limitations.
- **Docker Compose environment:** Laravel MySQL, inventory MySQL, Java gRPC inventory, Laravel backend, and React frontend services.
- **Product response caching:** Redis-backed product-list cache with automatic version-based invalidation, plus conditional HTTP responses using ETags.



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

2. Edit `.env` for your local MySQL server. Set `DB_HOST`, `DB_WRITE_HOST`, and `DB_READ_HOST` to `127.0.0.1`, and provide the database name, username, and password for a database you have created.

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

Docker Compose builds and starts the MySQL primary and asynchronously replicated read replica, schema migration, Java gRPC inventory service, Laravel backend, and React frontend:

| Service / container | Purpose | Host access |
|---|---|---|
| `frontend` | React + Vite development server | [http://localhost:5173](http://localhost:5173) |
| `backend` | Laravel application served by Nginx/PHP-FPM | [http://localhost:8080](http://localhost:8080) |
| `inventory-service` | Java gRPC stock service | `127.0.0.1:9090` |
| `mysql-primary` | Laravel MySQL write primary | `127.0.0.1:3307` (MySQL client; not a browser URL) |
| `mysql-replica` | Asynchronous Laravel MySQL read replica | Private to the Compose network |
| `inventory-db` | Inventory database, private to the Compose network | No host port exposed |
| `redis` | Laravel product cache | Private to the Compose network |

The ports on the host are configurable with `FRONTEND_PORT`, `BACKEND_PORT`, `DB_FORWARD_PORT`, and `GRPC_FORWARD_PORT` in `.env`. Inside the Compose network, Laravel writes to `mysql-primary:3306` and reads from `mysql-replica:3306`; the inventory service uses the separate `inventory-db:3306` database, and Laravel calls gRPC at `inventory-service:9090`. Set a strong `DB_REPLICATION_PASSWORD` in `.env` before using this setup outside local development.

### 1. Prepare the environment, build, and start all services

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
docker compose build
docker compose run --rm --no-deps backend php artisan key:generate
docker compose up -d
```

Compose waits for the primary and replica (including both replication threads), runs Laravel migrations against the primary, then starts the inventory service, Redis, and backend. The Java service creates the independent inventory schema on startup. The backend connects to Redis by the Compose service name `redis`; Redis is intentionally not exposed on a host port. Check service status with:

```powershell
docker compose ps
```

For migrations added later, run `docker compose run --rm migrate`. The backend image already installs Composer dependencies during the build, including Predis; do not run `composer install` inside the container unless you intentionally change the dependency installation workflow.

### Verify Redis product caching and HTTP ETags

The product-list route requires a signed-in user. Log in with an existing account to get a token, then call the same products URL twice:

```powershell
$login = Invoke-RestMethod `
  -Method Post `
  -Uri http://localhost:8080/api/v1/users/sign-in `
  -ContentType "application/json" `
  -Body (@{ email = "your-email@example.com"; password = "your-password" } | ConvertTo-Json)
$token = $login.data.token
$authorization = "Bearer " + $token

curl.exe -i `
  -H "Authorization: $authorization" `
  -H "Accept: application/json" `
  "http://localhost:8080/api/v1/products?per_page=5"
```

The first request should return `200 OK` with an `ETag` header. Copy its quoted value and send it back on the next request:

```powershell
$etag = '"paste-the-exact-etag-value-here"'
curl.exe -i `
  -H "Authorization: $authorization" `
  -H "Accept: application/json" `
  -H "If-None-Match: $etag" `
  "http://localhost:8080/api/v1/products?per_page=5"
```

An unchanged response should now be `304 Not Modified` with no response body. The second request also exercises the Redis cache. To show cache diagnostics, inspect Laravel's log after making the request:

```powershell
Get-Content .\storage\logs\laravel.log -Wait | Select-String "CACHE HIT|CACHE MISS"
```

The first uncached list request logs `CACHE MISS`; repeating the same query logs `CACHE HIT`. Query parameters such as category, page, and page size use distinct cache keys. Saving or deleting a product triggers the observer and advances the cache version, so the next list request misses the old key and caches fresh catalog data. The cache uses a one-hour TTL; changing the version makes old keys unreachable without needing to enumerate or delete them.

For an interview walkthrough, explain the two layers separately: Redis avoids re-running the product/category database query for an identical list request, while ETag avoids retransmitting the identical HTTP representation to a client that already has it. The API still authenticates the request and computes the response before the ETag comparison; `304` saves response bandwidth, not all server-side work. Product stock is fetched from the inventory service on each request, so a stock change can change the response ETag even while the catalog list is cached.

### 3. Open or access an individual container

Run the matching command from the repository root:

```powershell
docker compose exec backend sh
docker compose exec frontend sh
docker compose exec inventory-service sh
docker compose exec mysql-primary sh
docker compose exec mysql-replica sh
docker compose exec inventory-db sh
```

To open a MySQL client inside the database container instead of a shell:

```powershell
docker compose exec mysql-primary sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

Use the browser links in the table above to open the frontend or backend. MySQL is a database service, not a website; connect to the primary at `127.0.0.1:3307` with a MySQL client. The replica is private to `app-network`. The primary, replica, and inventory MySQL data volumes persist independently. The automatic replication account and replica initialization run only for fresh data volumes. When migrating from the former `mysql` Compose service, its `mysql_data` volume is kept separate from the new `mysql_primary_data` volume. Back up the application database, stop the old container without removing its volume, restore the dump to the new primary, and only then start the fresh replica so it can replicate the restored schema and data. Do not delete existing volumes just to trigger initialization.

The replica uses TLS for its connection to the primary (`SOURCE_SSL=1`) because MySQL 8.4's default `caching_sha2_password` authentication requires a secure connection. Certificate verification is disabled for this local Compose network (`SOURCE_SSL_VERIFY_SERVER_CERT=0`); use trusted certificates and enable verification outside local development. The replication initialization script runs only when a MySQL data directory is first initialized. To apply this change to an existing replica volume, run `STOP REPLICA; CHANGE REPLICATION SOURCE TO SOURCE_SSL = 1, SOURCE_SSL_VERIFY_SERVER_CERT = 0; START REPLICA;` once on the replica; this updates its persisted replication channel without deleting data.

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

> `docker compose down --volumes` deletes the primary, replica, and inventory MySQL databases as well as product-image data. Use it only when you intend to remove the local data.

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
└── docker-compose.yml      # MySQL primary/replica, inventory, migrations, backend, and frontend
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

### Verify MySQL read/write routing with k6

The k6 verification script logs in with an administrator account, creates one order, then immediately requests that order through the admin read endpoint. This is intentionally one iteration by default because each successful iteration creates an order and consumes one unit of inventory. `PRODUCT_ID` must identify a product that exists in the catalog and inventory service and has available stock. The account must have permission to view all orders.

Set the credentials and product ID in the current PowerShell session, then run the test from the repository root:

```powershell
$env:TEST_EMAIL = "admin@example.com"
$env:TEST_PASSWORD = "<admin-password>"
$env:PRODUCT_ID = "1"
docker compose --profile load-test run --rm --no-deps `
  -e TEST_EMAIL -e TEST_PASSWORD -e PRODUCT_ID -e TEST_ITERATIONS -e TEST_VUS `
  k6
```

The script uses `http://backend` when run inside Compose. To change it, set `API_BASE_URL` and pass `-e API_BASE_URL`. Results include `order_write_duration` and `order_immediate_read_duration`. The `checks` threshold requires the write and immediate read assertions to pass. Use `TEST_ITERATIONS` and `TEST_VUS` to increase the load only with dedicated test data and enough inventory; both default to `1`.

Important: Laravel's `sticky => true` routes reads to the write PDO only after a write **within the same Laravel request/connection lifecycle**. A k6 POST and the following GET are two separate HTTP requests, so sticky alone does not guarantee read-your-writes across them. This test detects whether the order is immediately visible through the read endpoint; a failure points to replica lag or a need for an application-level consistency strategy (for example, directing the immediate follow-up read to the primary). A successful immediate read does not by itself prove which database host served it; verify routing separately using database-side connection/query monitoring.

## 📚 Laravel Framework Resources

The following build, package, learning, sponsor, contribution, conduct, and security links were part of the original Laravel README template. They refer to the Laravel framework, not project-specific services or contacts.

[![Laravel Framework Build Status](https://github.com/laravel/framework/workflows/tests/badge.svg)](https://github.com/laravel/framework/actions)
[![Laravel Framework Downloads](https://img.shields.io/packagist/dt/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![Laravel Framework Version](https://img.shields.io/packagist/v/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![Laravel Framework License](https://img.shields.io/packagist/l/laravel/framework)](https://packagist.org/packages/laravel/framework)
