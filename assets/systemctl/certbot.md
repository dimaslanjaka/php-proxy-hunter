```bash
sudo apt install snapd -y
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot
sudo certbot certonly --nginx
# OR
sudo certbot certonly --webroot -w /var/www/html -d sh.webmanajemen.com --cert-name sh.webmanajemen.com
```