# Control Tower

Workshop management system for tracking vehicle service jobs, from entry through invoicing.

## Features

### 🛠️ Job Operations
- **Job Management** - End-to-end tracking from vehicle entry to invoicing
- **Booking System** - Schedule appointments with capacity planning
- **PDI & Towing** - Specialized modules for Pre-Delivery Inspection and Towing services
- **Invoicing** - Generate standard invoices and track payment status
- **Workflows** - Automated status transitions based on parts/work progress

### 📱 Customer Experience
- **Customer Portal** - Dedicated login for customers to view service history
- **Vehicle Tracking** - Real-time status updates for customers
- **Invoice Access** - Customers can download their own invoices
- **PWA Support** - Installable as a native app on mobile/desktop
- **Mobile Optimized** - Fully responsive design for on-the-go access

### 🛡️ Security & Access
- **Two-Factor Authentication** - TOTP support (Google Authenticator) for staff
- **Session Management** - View and terminate active sessions
- **LDAP Integration** - Corporate directory authentication support
- **Role-Based Access** - Granular permissions (Admin, Manager, SA, Foreman, Sparepart)
- **Audit Logging** - Comprehensive tracking of all data changes

### 📊 Reports & Data
- **Report Builder** - Create custom reports with filters and column selection
- **Trends & Comparisons** - Period comparison, SA performance trends, aging analysis
- **Scheduled Reports** - Automated email delivery (daily, weekly, monthly)
- **Saved Reports** - Save frequently used report configurations
- **Dashboards** - SA Performance, Aging Reports, and Financial overviews
- **Data Import with Preview** - Validate data before import with error detection
- **Data Cleanup** - Tools to manage database growth and archive old records

### 🤖 Automation & Tools
- **Keyboard Shortcuts** - Navigate quickly (N=new job, S=search, G+D/J/R/C)
- **Global Search** - Command palette (Ctrl+K) to find anything quickly
- **Recently Viewed** - Quick access to last 5 viewed jobs in sidebar
- **Print-Optimized** - Job details page optimized for A4 printing
- **Notifications** - In-app alerts for job assignments and updates
- **Stale Job Alerts** - Automated flagging of jobs needing attention
- **Customer Merging** - Smart detection and merging of duplicate customer records
- **Dark Mode** - Toggle between light and dark themes

---

## Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, `bcmath`
- MySQL 5.7+ or MariaDB 10.3+
- Composer 2.x
- Node.js 18+ & npm

---

## Quick Start (Local Development)

### 1. Clone Repository
```bash
git clone https://github.com/yourusername/control_tower.git
cd control_tower
```

### 2. Install Dependencies
```bash
composer install
npm install
```

### 3. Configure Environment
```bash
cp .env.example .env
```

Edit `.env` with your database settings:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=control_tower
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Setup Database
```bash
php artisan key:generate
php artisan migrate
php artisan storage:link
```

### 5. Create Admin User
```bash
php artisan tinker
```
```php
App\Models\User::create(['name'=>'Admin', 'username'=>'admin', 'email'=>'admin@example.com', 'password'=>bcrypt('password'), 'role'=>'admin']);
exit
```

### 6. Run Development Server
```bash
php artisan serve
```

Access: http://localhost:8000

---

## Docker Deployment

### Using Docker Compose
```bash
docker-compose up -d
docker exec control_tower_app php artisan key:generate
docker exec control_tower_app php artisan migrate --force
```

### Using Portainer
See [docs/PORTAINER_DEPLOYMENT.md](docs/PORTAINER_DEPLOYMENT.md)

---

## Production Deployment (LAMP)

See [docs/DEPLOYMENT_GUIDE.md](docs/DEPLOYMENT_GUIDE.md)

Quick deploy:
```bash
./deploy.sh
```

---

## User Roles

| Role | Access |
|------|--------|
| Admin | Full access |
| Manager | All operations |
| Control Tower | Job management, imports |
| SA | View jobs, add remarks |
| Foreman | View jobs, add remarks |
| Sparepart | Edit Order & Parts fields |

---

## Documentation

- [Comprehensive Documentation](docs/DOCUMENTATION.md) - Full feature reference
- [Function Reference](docs/FUNCTION_REFERENCE.md) - Technical API reference
- [Workflow Guide](docs/WORKFLOW_GUIDE.md) - Step-by-step operational workflows
- [Role Permissions](docs/ROLE_PERMISSIONS.md) - Permission system details
- [Deployment Guide](docs/DEPLOYMENT_GUIDE.md) - Installation and deployment
- [Portainer Deployment](docs/PORTAINER_DEPLOYMENT.md) - Docker deployment

---

## Development

### After making changes:
```bash
git add .
git commit -m "Your message"
git push
```

### On another PC, get updates:
```bash
git pull
composer install
npm install
php artisan migrate
```

---

## Tech Stack

- Laravel 10
- MySQL
- Bootstrap 5
- Bootstrap Icons
- PhpSpreadsheet (Excel import/export)
- Dompdf (PDF export)

---

## License

Private - All rights reserved.
