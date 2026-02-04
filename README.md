# Telegram Dual Bot Payment System - Complete Documentation

## 📋 Table of Contents

1. [Overview](#overview)
2. [System Requirements](#system-requirements)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Usage](#usage)
6. [API Endpoints](#api-endpoints)
7. [Commands](#commands)
8. [Testing](#testing)
9. [Deployment](#deployment)
10. [Troubleshooting](#troubleshooting)

---

## Overview

Telegram Dual Bot Payment System là hệ thống thanh toán tự động sử dụng 2 Telegram bots (Client & Admin) với tích hợp USDT TRC20.

### Key Features

- ✅ Dual bot architecture (Client + Admin)
- ✅ USDT TRC20 payment integration
- ✅ Auto-approval for returning customers
- ✅ Admin approval workflow
- ✅ Queue-based async processing
- ✅ Rate limiting & security headers
- ✅ Comprehensive logging
- ✅ Health monitoring

---

## System Requirements

- PHP 8.2+
- MySQL 8.0+
- Redis 7.0+
- Composer 2.x
- 2 Telegram Bot tokens
- USDT Payment Gateway API access

---

## Installation

### 1. Clone & Install Dependencies

```bash
cd /home/truonghocdot/Desktop/Workspace/card_bot_tele
composer install
```

### 2. Environment Configuration

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Configure Database

Update `.env` with your MySQL credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=telegram_payment
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 5. Configure Redis

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_STORE=redis
```

### 6. Run Migrations & Seeders

```bash
php artisan migrate:fresh --seed
```

---

## Configuration

### Telegram Bots

Update `.env` with your bot tokens:

```env
TELEGRAM_CLIENT_BOT_TOKEN=your_client_bot_token
TELEGRAM_ADMIN_BOT_TOKEN=your_admin_bot_token
TELEGRAM_ADMIN_CHAT_ID=your_admin_telegram_id
```

### USDT Payment Gateway

```env
USDT_PAYMENT_API_URL=https://api.payment-gateway.com
USDT_PAYMENT_API_KEY=your_api_key
USDT_PAYMENT_WEBHOOK_SECRET=your_webhook_secret
USDT_WALLET_ADDRESS=your_trc20_wallet_address
```

### Application URL

```env
APP_URL=https://your-domain.com
```

---

## Usage

### Starting the System

#### 1. Start Queue Worker

```bash
php artisan queue:work redis --tries=3 --timeout=60
```

#### 2. Start Scheduler (for cleanup jobs)

Add to crontab:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

#### 3. Setup Telegram Webhooks

```bash
php artisan telegram:setup-webhooks
```

### Client Bot Commands

Users can interact with the client bot using:

- `/start` - Start bot and see welcome message
- `/balance` - Check USDT balance
- `/history` - View transaction history
- `<CODE>` - Submit a code (e.g., ABC123)

### Admin Bot Features

Admins receive approval requests with inline keyboard:

- ✅ Approve - Approve transaction
- ❌ Reject - Reject transaction

Admin commands:

- `/stats` - View system statistics
- `/pending` - List pending approvals

---

## API Endpoints

### Telegram Webhooks

**Client Bot Webhook**

```
POST /api/telegram/client/webhook
Headers:
  X-Telegram-Bot-Api-Secret-Token: <hashed_token>
Rate Limit: 60 requests/minute
```

**Admin Bot Webhook**

```
POST /api/telegram/admin/webhook
Headers:
  X-Telegram-Bot-Api-Secret-Token: <hashed_token>
Rate Limit: 60 requests/minute
```

### Payment Webhook

```
POST /api/payment/webhook
Headers:
  X-Webhook-Signature: <hmac_sha256_signature>
Rate Limit: 60 requests/minute

Body:
{
  "tx_hash": "transaction_hash",
  "amount": 10.00,
  "address": "wallet_address",
  "payment_id": 123,
  "status": "confirmed"
}
```

### Health Check

```
GET /api/health
Rate Limit: 30 requests/minute

Response:
{
  "status": "ok",
  "timestamp": "2026-02-04T14:00:00+07:00",
  "services": {
    "database": "connected",
    "redis": "connected"
  }
}
```

---

## Commands

### Setup Commands

**Setup Telegram Webhooks**

```bash
php artisan telegram:setup-webhooks
```

**Remove Webhooks**

```bash
php artisan telegram:setup-webhooks --remove
```

### Monitoring Commands

**System Health Check**

```bash
php artisan system:health-check
```

**Detailed Health Check**

```bash
php artisan system:health-check --detailed
```

Output:

```
🔍 System Health Check

✅ Database: OK
✅ Redis: OK
✅ Queue: OK
✅ Client Bot: OK
✅ Admin Bot: OK

📊 System Statistics:

Total Customers: 150
Total Transactions: 450
Pending Approvals: 5
Today's Transactions: 25
Today's Revenue: 250 USDT
Pending Payments: 2

✅ All systems operational
```

### Queue Management

**Start Queue Worker**

```bash
php artisan queue:work redis --tries=3 --timeout=60
```

**Monitor Failed Jobs**

```bash
php artisan queue:failed
```

**Retry Failed Jobs**

```bash
php artisan queue:retry all
```

---

## Testing

### Run All Tests

```bash
php artisan test
```

### Run Specific Test Suite

```bash
php artisan test --filter=WebhookTest
php artisan test --filter=PaymentFlowTest
```

### Test Coverage

- ✅ Webhook signature verification
- ✅ Rate limiting
- ✅ Payment flow
- ✅ Balance management
- ✅ Transaction approval

---

## Deployment

### Production Checklist

#### 1. Environment

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure `APP_URL` to production domain
- [ ] Update database credentials
- [ ] Configure Redis connection

#### 2. Optimize Application

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

#### 3. Setup Queue Worker

Use Supervisor to keep queue worker running:

```ini
[program:telegram-payment-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --sleep=3 --tries=3 --timeout=60
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
```

Start Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start telegram-payment-worker:*
```

#### 4. Setup Cron for Scheduler

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

#### 5. Setup Webhooks

```bash
php artisan telegram:setup-webhooks
```

#### 6. Verify Health

```bash
php artisan system:health-check --detailed
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \\.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
}
```

---

## Troubleshooting

### Common Issues

#### 1. Webhook Not Receiving Updates

**Check webhook status:**

```bash
php artisan telegram:setup-webhooks --remove
php artisan telegram:setup-webhooks
```

**Verify APP_URL is correct:**

```env
APP_URL=https://your-domain.com
```

#### 2. Queue Jobs Not Processing

**Check queue worker is running:**

```bash
ps aux | grep queue:work
```

**Check Redis connection:**

```bash
redis-cli ping
```

**Restart queue worker:**

```bash
php artisan queue:restart
```

#### 3. Payment Webhook Fails

**Check webhook signature:**

- Verify `USDT_PAYMENT_WEBHOOK_SECRET` is correct
- Check payment gateway documentation for signature format

**Check logs:**

```bash
tail -f storage/logs/payment.log
```

#### 4. Auto-Approval Not Working

**Check customer eligibility:**

- Must have 3+ approved transactions
- No rejections in last 30 days
- Sufficient balance
- No other pending transactions

**Check logs:**

```bash
tail -f storage/logs/transaction.log
```

### Log Files

- `storage/logs/laravel.log` - General application logs
- `storage/logs/telegram.log` - Telegram bot interactions
- `storage/logs/payment.log` - Payment processing
- `storage/logs/transaction.log` - Transaction flow

### Debug Mode

Enable debug logging in `.env`:

```env
LOG_LEVEL=debug
```

---

## Security Best Practices

1. **Never commit `.env` file**
2. **Use strong webhook secrets**
3. **Enable HTTPS in production**
4. **Regularly update dependencies**
5. **Monitor failed login attempts**
6. **Backup database regularly**
7. **Use rate limiting**
8. **Validate all webhook signatures**

---

## Support & Contact

For issues or questions:

- Check logs in `storage/logs/`
- Run health check: `php artisan system:health-check --detailed`
- Review this documentation

---

## License

Proprietary - All rights reserved
