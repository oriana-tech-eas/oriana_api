# Docker Configuration for Oriana API

This directory contains all Docker-related configuration files for the Oriana API service.

## Structure
```
docker/
├── nginx/           # Nginx web server configuration
├── supervisor/      # Supervisor process manager configuration
├── php/            # PHP-FPM configuration
├── scripts/        # Startup and utility scripts
└── Dockerfile      # Main Docker image definition
```

## Building
```bash
docker-compose build api
```

## Running
```bash
docker-compose up -d api
```

## Debugging
```bash
# View logs
docker-compose logs -f api

# Enter container
docker-compose exec api sh

# Check processes
docker-compose exec api ps aux

# Check PHP extensions
docker-compose exec api php -m
```

## Configuration Files

- **nginx/default.conf**: Web server routing and PHP-FPM connection
- **supervisor/supervisord.conf**: Process manager for running both Nginx and PHP-FPM
- **php/php-fpm.conf**: PHP-FPM pool configuration
- **scripts/entrypoint.sh**: Container startup script (database wait, migrations, etc.)

## Customization

To modify configurations:
1. Edit the relevant config file
2. Rebuild the image: `docker-compose build --no-cache api`
3. Restart the service: `docker-compose up -d api`