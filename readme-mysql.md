
# MySQL Setup Guide (Ubuntu)

This guide provides a simple step-by-step tutorial to install and configure MySQL on **Ubuntu**. For other Debian-based systems, please refer to their respective documentation.

## 1. Update System Packages

```bash
sudo apt update && sudo apt upgrade -y
```

## 2. Install MySQL Server

```bash
sudo apt install mysql-server -y
```

## 3. Secure MySQL Installation

```bash
sudo mysql_secure_installation
```
Follow the prompts to set a root password and secure your installation.

**Manually Change MySQL Root Password:**

```bash
sudo mysql -u root
```
Then run:
```sql
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'NEWPASSWORD';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Start and Enable MySQL Service

```bash
sudo systemctl start mysql
sudo systemctl enable mysql
```

## 5. Check MySQL Status

```bash
systemctl status mysql
```

## 6. Log in to MySQL

```bash
sudo mysql -u root -p
```

## 7. Create a Database and User

Replace `mydb`, `myuser`, and `mypassword` with your own values:
```sql
CREATE DATABASE mydb;
CREATE USER 'myuser'@'localhost' IDENTIFIED BY 'mypassword';
GRANT ALL PRIVILEGES ON mydb.* TO 'myuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 8. Allow Remote Connections (Optional)

Edit the MySQL config file:
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```
Change `bind-address` to `0.0.0.0` to allow remote connections. Restart MySQL:
```bash
sudo systemctl restart mysql
```
Open the firewall for MySQL (default port 3306):
```bash
sudo ufw allow 3306
```

## 9. Backup and Restore

Backup a database:
```bash
mysqldump -u myuser -p mydb > mydb_backup.sql
```
Restore a database:
```bash
mysql -u myuser -p mydb < mydb_backup.sql
```

---
For more details, see the [MySQL documentation](https://dev.mysql.com/doc/).
