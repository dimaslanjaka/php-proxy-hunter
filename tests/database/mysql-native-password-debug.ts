import mariadb from 'mariadb';
import dotenv from 'dotenv';
dotenv.config();

async function updateUserAuthPlugin() {
  const connection = await mariadb.createConnection({
    host: process.env.MYSQL_HOST,
    user: process.env.MYSQL_USER,
    password: process.env.MYSQL_PASS,
    database: process.env.MYSQL_DBNAME,
    port: 3306,
    allowPublicKeyRetrieval: true
  });
  try {
    const user = process.env.MYSQL_USER;
    const pass = process.env.MYSQL_PASS;
    // Use 'localhost' for root user
    await connection.query(`ALTER USER '${user}'@'localhost' IDENTIFIED WITH caching_sha2_password BY '${pass}';`);
    await connection.query('FLUSH PRIVILEGES;');
    console.log(`User ${user}@localhost updated to use caching_sha2_password.`);
  } catch (err) {
    console.error('Error updating user authentication plugin:', err);
  } finally {
    await connection.end();
  }
}

async function printUserPlugin() {
  const connection = await mariadb.createConnection({
    host: process.env.MYSQL_HOST,
    user: process.env.MYSQL_USER,
    password: process.env.MYSQL_PASS,
    database: process.env.MYSQL_DBNAME,
    port: 3306,
    allowPublicKeyRetrieval: true
  });
  try {
    const user = process.env.MYSQL_USER;
    const rows = await connection.query(`SELECT User, Host, plugin FROM mysql.user WHERE User = ?`, [user]);
    console.log('User plugin info:', rows);
  } catch (err) {
    console.error('Error fetching user plugin info:', err);
  } finally {
    await connection.end();
  }
}

async function main() {
  await updateUserAuthPlugin();
  await printUserPlugin();
}

main();
