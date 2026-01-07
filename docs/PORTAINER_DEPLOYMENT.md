# Control Tower - Portainer Deployment Guide

Deploy Control Tower to your Portainer server at `192.168.99.123`.

---

## Prerequisites

- Portainer running on 192.168.99.123
- Git repository access (or ability to transfer files)
- Docker and Docker Compose on the host

---

## Method 1: Deploy via Portainer Stacks (Recommended)

### Step 1: Prepare the Code

**Option A: Push to Git Repository**
```bash
# On your development machine
cd /home/yudi/dev/control_tower/control_tower_app

# Initialize git (if not already)
git init
git add .
git commit -m "Initial commit for Docker deployment"

# Push to your Git server (GitLab, GitHub, etc.)
git remote add origin https://your-git-server.com/control_tower.git
git push -u origin main
```

**Option B: Copy files via SCP**
```bash
# Create archive
cd /home/yudi/dev/control_tower
tar -czvf control_tower.tar.gz control_tower_app

# Copy to server
scp control_tower.tar.gz user@192.168.99.123:/opt/stacks/

# Extract on server
ssh user@192.168.99.123
cd /opt/stacks
tar -xzvf control_tower.tar.gz
mv control_tower_app control_tower
```

### Step 2: Access Portainer

1. Open browser: `http://192.168.99.123:9000`
2. Login to Portainer

### Step 3: Create Stack

1. Go to **Stacks** → **+ Add Stack**
2. Name: `control_tower`
3. Choose **Repository** or **Upload**

**If using Repository:**
- Repository URL: `https://your-git-server.com/control_tower.git`
- Compose path: `docker-compose.yml`

**If using Upload/Web Editor:**
- Copy paste the content below:

### Step 4: Docker Compose for Portainer

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: control_tower_app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - app_data:/var/www/storage
    environment:
      - APP_NAME=Control Tower
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_URL=http://192.168.99.123:8080
      - DB_CONNECTION=mysql
      - DB_HOST=db
      - DB_PORT=3306
      - DB_DATABASE=control_tower
      - DB_USERNAME=control_tower
      - DB_PASSWORD=secret123
    networks:
      - control_tower_net
    depends_on:
      - db

  # Scheduler service - runs Laravel scheduler every minute
  scheduler:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: control_tower_scheduler
    restart: unless-stopped
    working_dir: /var/www
    entrypoint: /bin/sh -c "while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done"
    volumes:
      - app_data:/var/www/storage
    environment:
      - APP_NAME=Control Tower
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_URL=http://192.168.99.123:8080
      - DB_CONNECTION=mysql
      - DB_HOST=db
      - DB_PORT=3306
      - DB_DATABASE=control_tower
      - DB_USERNAME=control_tower
      - DB_PASSWORD=secret123
    networks:
      - control_tower_net
    depends_on:
      - app
      - db

  webserver:
    image: nginx:alpine
    container_name: control_tower_web
    restart: unless-stopped
    ports:
      - "8080:80"
    volumes:
      - ./docker/nginx/conf.d:/etc/nginx/conf.d:ro
      - .:/var/www:ro
    networks:
      - control_tower_net
    depends_on:
      - app

  db:
    image: mysql:8.0
    container_name: control_tower_db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: control_tower
      MYSQL_ROOT_PASSWORD: rootpassword123
      MYSQL_USER: control_tower
      MYSQL_PASSWORD: secret123
    ports:
      - "3307:3306"
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - control_tower_net
    command: --default-authentication-plugin=mysql_native_password

networks:
  control_tower_net:
    driver: bridge

volumes:
  app_data:
  db_data:
```

### Step 5: Deploy Stack

1. Click **Deploy the stack**
2. Wait for containers to build and start (may take 5-10 minutes first time)

### Step 6: Initialize Application

1. Go to **Containers** → find `control_tower_app`
2. Click on container → **Console** → **Connect**
3. Run these commands:

```bash
# Generate app key
php artisan key:generate --force

# Run migrations
php artisan migrate --force

# Create storage link
php artisan storage:link

# Cache config
php artisan config:cache
php artisan route:cache

# Create admin user
php artisan tinker --execute="
App\Models\User::create([
    'name' => 'Administrator',
    'username' => 'admin',
    'password' => bcrypt('admin123'),
    'role' => 'admin'
]);
"
```

### Step 7: Access Application

- **App URL:** http://192.168.99.123:8080
- **Login:** admin / admin123

---

## Method 2: Build Image and Push to Registry

### Step 1: Build & Push Image

```bash
# On development machine
cd /home/yudi/dev/control_tower/control_tower_app

# Build image
docker build -t 192.168.99.123:5000/control_tower:latest .

# Push to registry (if you have private registry)
docker push 192.168.99.123:5000/control_tower:latest
```

### Step 2: Create Stack in Portainer

Use this simplified compose:

```yaml
version: '3.8'

services:
  app:
    image: 192.168.99.123:5000/control_tower:latest
    container_name: control_tower_app
    restart: unless-stopped
    environment:
      - APP_URL=http://192.168.99.123:8080
      - DB_HOST=db
      - DB_DATABASE=control_tower
      - DB_USERNAME=control_tower
      - DB_PASSWORD=secret123
    networks:
      - control_tower_net
    depends_on:
      - db

  db:
    image: mysql:8.0
    container_name: control_tower_db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: control_tower
      MYSQL_USER: control_tower
      MYSQL_PASSWORD: secret123
      MYSQL_ROOT_PASSWORD: rootpass
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - control_tower_net

networks:
  control_tower_net:

volumes:
  db_data:
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Container won't start | Check logs in Portainer → Containers → Logs |
| Database connection error | Ensure DB container is running, check credentials |
| 502 Bad Gateway | App container needs more time to start |
| Permission errors | Run `chown -R www-data:www-data /var/www` in app container |

---

## Quick Reference

| Service | URL/Port |
|---------|----------|
| Control Tower App | http://192.168.99.123:8080 |
| MySQL (external) | 192.168.99.123:3307 |
| Portainer | http://192.168.99.123:9000 |
