<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

## Deploy to Google Cloud Run (Docker)

This repo includes a production `Dockerfile` for Cloud Run.

### 1) Build & run locally (optional)

```bash
docker build -t gdgoc-challange:local .
docker run --rm -p 8080:8080 \
	-e APP_KEY="base64:CHANGE_ME" \
	-e APP_URL="http://localhost:8080" \
	-e DB_CONNECTION="pgsql" \
	-e DB_HOST="YOUR_DB_HOST" \
	-e DB_PORT="5432" \
	-e DB_DATABASE="YOUR_DB" \
	-e DB_USERNAME="YOUR_DB_USER" \
	-e DB_PASSWORD="YOUR_DB_PASSWORD" \
	gdgoc-challange:local
```

### 2) Deploy to Cloud Run

Enable required APIs once:

```bash
gcloud services enable run.googleapis.com cloudbuild.googleapis.com artifactregistry.googleapis.com secretmanager.googleapis.com
```

Create secrets (recommended) and upload values:

```bash
# Example: APP_KEY should be a real Laravel key
gcloud secrets create APP_KEY --replication-policy="automatic"
printf "%s" "base64:YOUR_REAL_APP_KEY" | gcloud secrets versions add APP_KEY --data-file=-

gcloud secrets create DB_PASSWORD --replication-policy="automatic"
printf "%s" "YOUR_DB_PASSWORD" | gcloud secrets versions add DB_PASSWORD --data-file=-
```

Deploy from source (Cloud Build will build the Dockerfile):

```bash
gcloud run deploy gdgoc-challange \
	--source . \
	--region asia-southeast2 \
	--allow-unauthenticated \
	--set-env-vars "APP_ENV=production,APP_DEBUG=false,LOG_CHANNEL=stderr" \
	--set-env-vars "DB_CONNECTION=pgsql,DB_HOST=YOUR_DB_HOST,DB_PORT=5432,DB_DATABASE=YOUR_DB,DB_USERNAME=YOUR_DB_USER" \
	--set-secrets "APP_KEY=APP_KEY:latest,DB_PASSWORD=DB_PASSWORD:latest"
```

Notes:

- Cloud Run sets `PORT` automatically; the container listens on `8080`.
- Do not bake `.env` into the image. Use Cloud Run env vars and Secret Manager.
- If you need migrations, run them separately (e.g., Cloud Run Jobs / CI step) instead of on every container start.

## Deploy to Railway

Railway's default PHP builder may use PHP 8.2.x. This project (Laravel 12 + Symfony 8) requires PHP 8.4+, so `composer install` will fail on PHP 8.2.

Recommended: deploy using the included `Dockerfile` (PHP 8.4).

- In Railway, configure the service to build from `Dockerfile` (Docker build) instead of the default PHP/Nixpacks builder.
- Ensure your service listens on `PORT` (the Dockerfile uses `php artisan serve --host=0.0.0.0 --port=${PORT:-8080}`).

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
