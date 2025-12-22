# Real-Time Notifications - Production Deployment

## Quick Start (Local Testing)

### 1. Update your `.env` file:
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=control-tower
REVERB_APP_KEY=control-tower-key
REVERB_APP_SECRET=control-tower-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

### 2. Start the Reverb WebSocket server:
```bash
php artisan reverb:start
```

### 3. Test in browser:
- Open console (F12) - should see: `[Echo] Connected to Reverb`
- Trigger a notification (add remark, assign job)
- Should see toast popup without page refresh

---

## Production Deployment (Docker + Traefik)

### Option A: Separate WebSocket Subdomain (Recommended)

**1. Create DNS record:**
```
ws.tower.hartonomotor-group.com → your-server-ip
```

**2. Update `docker-compose.yml`:**
```yaml
  reverb:
    image: php:8.2-cli
    command: ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=8080"]
    working_dir: /var/www/html
    volumes:
      - ./control_tower_app:/var/www/html
    restart: unless-stopped
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.reverb.rule=Host(`ws.tower.hartonomotor-group.com`)"
      - "traefik.http.routers.reverb.entrypoints=websecure"
      - "traefik.http.routers.reverb.tls.certresolver=myresolver"
      - "traefik.http.services.reverb.loadbalancer.server.port=8080"
    depends_on:
      - app
```

**3. Update production `.env`:**
```env
REVERB_HOST=ws.tower.hartonomotor-group.com
REVERB_PORT=443
REVERB_SCHEME=https
```

### Option B: Same Domain with Path Routing

If you can't create a subdomain, route `/reverb` to the WebSocket server:

```yaml
labels:
  - "traefik.http.routers.reverb.rule=Host(`tower.hartonomotor-group.com`) && PathPrefix(`/reverb`)"
```

---

## Verify WebSocket Connection

### Browser Console Check:
```
[Echo] Connected to Reverb, listening for notifications
```

### Test Notification:
```bash
# SSH into container or run locally
php artisan tinker

# Create test notification
\App\Models\Notification::notify(1, 'system', 'Test', 'This is a test', '/dashboard');
```

---

## Troubleshooting

### WebSocket not connecting:
1. Check Traefik labels are correct
2. Verify DNS resolves to server
3. Check SSL certificate is valid
4. Browser console for errors

### Notifications not appearing:
1. Verify `BROADCAST_CONNECTION=reverb` in `.env`
2. Check Reverb container is running
3. Verify channel authorization (`routes/channels.php`)

### Common Errors:

**"Connection refused"**
- Reverb container not running
- Port not exposed correctly

**"401 Unauthorized"**
- Channel auth failing
- Check `/broadcasting/auth` endpoint
