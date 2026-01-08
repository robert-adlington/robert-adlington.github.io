# SSH Deployment Instructions

## One-time Setup on Hostinger

1. **SSH into your Hostinger server:**
   ```bash
   ssh your_username@your_domain.com
   ```

2. **Navigate to web root:**
   ```bash
   cd ~/public_html
   ```

3. **Clone your repository:**
   ```bash
   git clone https://github.com/robert-adlington/robert-adlington.github.io.git .
   ```

4. **Create your config.php:**
   ```bash
   cd api
   cp config.example.php config.php
   nano config.php  # Edit with your database credentials
   chmod 600 config.php
   ```

## Deploying Updates

After pushing to GitHub, SSH in and pull:

```bash
ssh your_username@your_domain.com
cd ~/public_html
git pull origin main
```

## Automated Pull (Optional)

Create a webhook script on your server that GitHub can call to auto-pull:

**File: ~/public_html/deploy.php**
```php
<?php
// Verify request is from GitHub (optional but recommended)
$secret = 'your_webhook_secret';
$payload = file_get_contents('php://input');
$signature = hash_hmac('sha256', $payload, $secret);

if (hash_equals('sha256=' . $signature, $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '')) {
    exec('cd /home/your_username/public_html && git pull origin main 2>&1', $output);
    echo implode("\n", $output);
} else {
    http_response_code(403);
    echo 'Invalid signature';
}
```

Then add this URL as a webhook in GitHub:
- Go to GitHub repo → Settings → Webhooks
- Add webhook: `https://yourdomain.com/deploy.php`
- Secret: (the secret you used in the PHP file)
- Content type: `application/json`
- Events: Just the push event
