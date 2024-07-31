# Documentation for Python Proxy Hunter

> major packages needed

```bash
sudo apt-get update -y
sudo apt-get install build-essential gdb lcov pkg-config libcurl4-openssl-dev libbz2-dev libffi-dev libgdbm-dev libgdbm-compat-dev liblzma-dev libncurses5-dev libreadline6-dev libsqlite3-dev libssl-dev curl lzma tk-dev uuid-dev zlib1g-dev software-properties-common -y
```

### Install python 3.11 in ubuntu

```bash
sudo add-apt-repository ppa:deadsnakes/ppa
sudo apt update -y
sudo apt install python3.11
```

when above not working try

### Install python 3.11 from source in ubuntu

```bash
cd /tmp
curl -L https://www.python.org/ftp/python/3.11.9/Python-3.11.9.tar.xz -o python3.11.tar.xz
tar -xf python3.11.tar.xz
cd /tmp/Python-3.11.9
./configure
make
sudo make install
```

### Initialize virtual environtment python 3.11 on ubuntu

#### Configure And Install

- rename **.env_sample** to **.env** and edit with your data
- edit **django_backend/settings.py** with your data
- edit your nginx settings based on **.htaccess_nginx.conf**
- fix permissions on ubuntu/UNIX OS
  > change **www-data** with your nginx user
  >
  > change **/var/www/html** with your nginx root directory

  ```bash
  python3.11 -m venv venv
  # OR run using spesific user
  sudo -u www-data -H bash -c "python3.11 -m venv /var/www/html/venv"
  ```
- install dependencies
  > change **www-data** with your nginx user
  >
  > change **/var/www/html** with your nginx root directory

  ```bash
  source venv/bin/activate
  pip install --upgrade pip
  python requirements_install.py

  # OR run using spesific user
  sudo -u www-data -H bash -c "source /var/www/html/venv/bin/activate && bash"
  sudo chown www-data:www-data /var/www/venv
  sudo -u www-data -H bash -c "source /var/www/html/venv/bin/activate && pip install --upgrade pip"
  sudo -u www-data -H bash -c "source /var/www/html/venv/bin/activate && python /var/www/html/requirements_install.py"
  ```

- run the django server
  ```bash
  python manage.py runserver
  ```
  open new terminal to run background task executor
  ```bash
  python manage.py run_huey
  ```