# Building SQLite from Source (Linux, Autoconf Method)

This guide shows how to build the latest SQLite from source on Linux using the official autoconf tarball. This method is recommended for advanced users who want the latest features or custom build options.

## Prerequisites

- **Build tools**: gcc, make, wget, tar
- **Internet connection**

## 1. Remove System SQLite (Optional)

Remove the system-provided SQLite and development headers to avoid conflicts:

```bash
sudo apt remove -y --purge sqlite3 libsqlite3-dev
sudo apt autoremove -y
```

## 2. Download the Latest SQLite Autoconf Tarball

Go to the [SQLite download page](https://www.sqlite.org/download.html) and copy the latest autoconf tarball link. For example:

```bash
cd /usr/local/src
wget -O sqlite-autoconf-3500400.tar.gz https://www.sqlite.org/2025/sqlite-autoconf-3500400.tar.gz
```

## 3. Extract the Source

```bash
tar xvzf sqlite-autoconf-3500400.tar.gz
cd sqlite-autoconf-3500400/
```

## 4. Configure the Build

You can enable extra features with CFLAGS. For example, to enable column metadata:

```bash
CFLAGS="-O2 -DSQLITE_ENABLE_COLUMN_METADATA=1" ./configure
```

## 5. Build and Install

```bash
make
sudo make install
```

## 6. Verify Installation

```bash
which sqlite3
sqlite3 --version
```

You should see the new version and path (usually `/usr/local/bin/sqlite3`).

## Notes
- You can use a different tarball version by changing the URL in the `wget` command.
- For more build options, see the `./configure --help` output or the [official documentation](https://www.sqlite.org/howtocompile.html).

---

*This method gives you the latest SQLite CLI and library, independent of your system package manager.*
