# mcarchivist

mcarchivist is a self-hosted archiving solution for everything about Minecraft: Java Edition.

**All platforms you love** - This application combines many APIs into one simple interface, which lets you quickly archive your favorite projects, loaders and game versions.  
**Automatic archiving** - Thanks to built in automatic archiver, you won't have to worry about manually downloading anything again.  
**Many configurable options** - With many included configurable options and archive rules that cover broad spectrum of the use cases, you can control what, how and when files are archived.


mcarchivist currently supports archiving:
- Game versions: Mojang
- Addon platforms: Modrinth, Curseforge (API key required)
- Mod loaders: Forge, NeoForge, Fabric + Fabric Intermediary

## Installation
### Option 1 - Docker
The easiest way to install mcarchivist will definitely be through Docker. Compose image includes & configures HTTP server, PHP, PostgreSQL database and the app itself.

To get started, clone the repository and simply run:
```bash
docker compose -f docker-compose.yml up -d
```

Archived files will be placed inside the container volume. To pass them out to the real filesystem you'll need to configure custom Docker bind volume. Instead of editing app `docker-compose.yml`, create a new docker overrides file and paste the following configuration, changing all `source` keys to point to your selected directory:
```yaml
services:
  php:
    volumes:
      - type: bind
        source: /home/user/archive
        target: /mnt/archive
  scheduler:
    volumes:
      - type: bind
        source: /home/user/archive
        target: /mnt/archive
  queue-worker:
    volumes:
      - type: bind
        source: /home/user/archive
        target: /mnt/archive
```

Now, run the container specifying your new overrides file:
```bash
docker compose -f docker-compose.yml -f your-docker-overrides.yml up -d
```
Remember to update the storage directories in application settings too.

### Option 2 - Build from source

#### Requirements
- HTTP server
- PHP 8.2 or newer with following extensions enabled: curl, fileinfo, mbstring, openssl, zip + PDO for database server of your choosing
- Laravel compatible database server e.g. MariaDB or PostgreSQL
- [Composer](https://getcomposer.org)
- [Node](https://nodejs.org)
- Git

1. Assuming you already have web server and PHP configured:
```bash
# Clone the repository
git clone https://github.com/kubaska/mcarchivist.git .
cd mcarchivist

# Install PHP dependencies
composer install --no-dev

# Install JS dependencies and compile frontend
npm i
npm run build

# Make sure storage directory is writable
chown -R www-data:www-data ./storage

# Set up application environment
cp .env.prod.example .env

# Open .env file and configure the database connection
# DB_CONNECTION should be set to database engine you're using e.g. pgsql or mariadb
nano .env

# Import initial data
php artisan mca:setup-initial-data
```

2. To process archive tasks, you'll need to run Laravel queue worker. In a detached window, or a `screen` run:
```
php artisan queue:work
```
For a more advanced worker setup, please check [Laravel docs](https://laravel.com/docs/12.x/queues#supervisor-configuration)

3. Automatic archive tasks require Laravel scheduler to be configured. To do so, add a new entry to your system cron; for example:
```bash
crontab -e

# Add new entry to the end of file:
* * * * * cd /path/to/app && php artisan schedule:run
```
> [!WARNING]  
> mcarchivist is dedicated for local use only. Please do not expose it to the Internet.

## Attributions
- Modrinth team for their exceptional design of versions table, which I took a lot of inspiration from.

## Disclaimer
mcarchivist is not affiliated or endorsed by Mojang Studios or Microsoft.
