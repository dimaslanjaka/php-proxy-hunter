# Access Home MySQL From Anywhere Using Tailscale

This guide shows how to expose a MySQL server running on a home laptop
to a VPS or other devices using Tailscale.

Tailscale creates a secure private network between your devices without
port forwarding or public exposure.

---

# 1. Install Tailscale

## Windows (Laptop)

Download and install: https://tailscale.com/download

After installation open **Command Prompt** and run:

    tailscale up

A browser will open asking you to login.

Login with your preferred account (Google, GitHub, etc).

---

## Linux (VPS)

Install with:

```bash
curl -fsSL https://tailscale.com/install.sh | sh
```

Then start Tailscale:

```bash
sudo tailscale up
```

Login using the **same account** used on your laptop.

---

# 2. Verify Devices Are Connected

Run on either machine:

    tailscale status

Example output:

    100.82.212.71  laptop
    100.101.22.33  vps-server

Each device receives a **private Tailscale IP** in the `100.x.x.x`
range.

---

# 3. Get Your Laptop Tailscale IP

Run on the laptop:

    tailscale ip

Example:

    100.82.212.71

This is the address other devices will use to reach your laptop.

---

# 4. Configure MySQL to Allow Remote Connections

Edit MySQL configuration (`my.ini` or `my.cnf`).

Find the `[mysqld]` section and ensure:

    bind-address = 0.0.0.0
    port = 3306

Restart MySQL.

---

# 5. Allow Remote MySQL User

Login to MySQL:

    mysql -u root -p

Create a user:

```sql
CREATE USER 'user'@'%' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON *.* TO 'user'@'%';
FLUSH PRIVILEGES;
```

Make existing user to accept all hostnames:

```sql
DROP USER IF EXISTS 'root'@'%';
CREATE USER 'root'@'%' IDENTIFIED BY 'yourpassword';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
```

Verify:

```sql
SELECT
  user,
  host
FROM
  mysql.user;
```

---

# 6. Open Windows Firewall (Laptop)

Run PowerShell as Administrator:

```powershell
New-NetFirewallRule -DisplayName "MySQL 3306" -Direction Inbound -Protocol TCP -LocalPort 3306 -Action Allow
```

---

# 7. Connect From VPS

Use the laptop's Tailscale IP:

    mysql -h 100.82.212.71 -P 3306 -u user -p

---

# Network Architecture

    VPS
      │
      │ encrypted Tailscale network
      │
    Laptop (MySQL Server)
    100.x.x.x:3306

---

# Advantages

- No router configuration
- Works behind CGNAT
- End‑to‑end encrypted
- Safer than exposing MySQL publicly
- Accessible anywhere

---

# Useful Commands

Check status:

    tailscale status

Get device IP:

    tailscale ip

Stop Tailscale:

    tailscale down

Reconnect:

    tailscale up
