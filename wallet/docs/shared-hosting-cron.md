## Shared hosting: queues + scheduler (required for notifications)

On shared hosting you typically **cannot** run long-lived daemons (Supervisor). This project uses:

- **Queues** for push + email delivery
- **Scheduler** for Expo push receipts processing

So you must configure **cron jobs**.

### 1) Laravel scheduler (runs `schedule:run` every minute)

Add this cron (every minute):

```bash
* * * * * /usr/bin/php /path/to/project/artisan schedule:run >> /dev/null 2>&1
```

This will execute scheduled tasks, including Expo receipts processing (every 5 minutes).

### 2) Queue worker (runs frequently, exits quickly)

Add this cron (every minute):

```bash
* * * * * /usr/bin/php /path/to/project/artisan queue:work --queue=push,default --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

Notes:
- `--stop-when-empty`: makes it safe to run from cron (it exits).
- `--max-time=50`: ensures the command exits before the next minute tick.

### 3) Required environment

Ensure `.env` in production includes:

- `QUEUE_CONNECTION=database`
- `EXPO_PUSH_ENABLED=true`

### 4) Replace paths

Replace:
- `/usr/bin/php` with your hosting PHP path (sometimes `/usr/local/bin/php`).
- `/path/to/project` with the full absolute path to your Laravel app root (the folder containing `artisan`).

