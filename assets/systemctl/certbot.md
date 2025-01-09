## Install with snapd

> when you installed python from source

```bash
sudo apt install snapd -y
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot
sudo certbot certonly --nginx
# OR
sudo certbot certonly --webroot -w /var/www/html -d sh.webmanajemen.com --cert-name sh.webmanajemen.com
```

## Install with apt and python

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d sh.webmanajemen.com
# verify
sudo certbot renew --dry-run
```