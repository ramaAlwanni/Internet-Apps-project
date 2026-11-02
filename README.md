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
- **Database-backed services:** MySQL is configured for local development and in Docker Compose.
- **Docker Compose environment:** Separate MySQL, Laravel backend, and React frontend services.



## 🧰 Prerequisites

### Local development

- PHP **8.2+**
- [Composer](https://getcomposer.org/) 2
- [Node.js](https://nodejs.org/) **22+** and npm
- MySQL **8.x** (the Compose service uses MySQL 8.4)

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

4. Start the Laravel backend in one terminal:

   ```powershell
   php artisan serve
   ```

   The backend is available at [http://localhost:8000](http://localhost:8000).

5. In another terminal, install and start the React frontend:

   ```powershell
   Set-Location frontend
   npm install
   npm run dev
   ```

   The frontend is available at [http://localhost:5173](http://localhost:5173).

## 🐳 Docker: Build and Run

Docker Compose starts three services:

| Service / container | Purpose | Host access |
|---|---|---|
| `frontend` | React + Vite development server | [http://localhost:5173](http://localhost:5173) |
| `backend` | Laravel application served by Nginx/PHP-FPM | [http://localhost:8080](http://localhost:8080) |
| `mysql` | MySQL database | `127.0.0.1:3307` (MySQL client; not a browser URL) |

The ports on the host are configurable with `FRONTEND_PORT`, `BACKEND_PORT`, and `DB_FORWARD_PORT` in `.env`. Inside the Compose network, the backend connects to the database at `mysql:3306`.

### 1. Prepare environment and build the images


For build images and run the containers :

```powershell
docker compose up --build -d
```

Check container status and wait for MySQL to become healthy:

```powershell
docker compose ps
```

### 2. Initialize the backend from inside its container

Open a shell in the backend container:

```powershell
docker compose exec backend sh
```

Then run these commands at the container prompt:

```sh
php artisan key:generate
php artisan migrate --force
```

Exit the container shell when finished:

```sh
exit
```

The backend image already installs Composer dependencies during the build. Do not run `composer install` inside the container unless you intentionally change the dependency installation workflow.

### 3. Open or access an individual container

Run the matching command from the repository root:

```powershell
docker compose exec backend sh
docker compose exec frontend sh
docker compose exec mysql sh
```

To open a MySQL client inside the database container instead of a shell:

```powershell
docker compose exec mysql sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

Use the browser links in the table above to open the frontend or backend. MySQL is a database service, not a website; connect to `127.0.0.1:3307` with a MySQL client. From other containers, use host `mysql` and port `3306`.

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

> `docker compose down --volumes` deletes the persisted MySQL data. Use it only when you intend to remove the local database contents.

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
└── docker-compose.yml      # MySQL, backend, and frontend services
```

## 🧪 Tests

Run the Laravel test suite with:

```powershell
composer test
```

## 📚 Laravel Framework Resources

The following build, package, learning, sponsor, contribution, conduct, and security links were part of the original Laravel README template. They refer to the Laravel framework, not project-specific services or contacts.

[![Laravel Framework Build Status](https://github.com/laravel/framework/workflows/tests/badge.svg)](https://github.com/laravel/framework/actions)
[![Laravel Framework Downloads](https://img.shields.io/packagist/dt/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![Laravel Framework Version](https://img.shields.io/packagist/v/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![Laravel Framework License](https://img.shields.io/packagist/l/laravel/framework)](https://packagist.org/packages/laravel/framework)

