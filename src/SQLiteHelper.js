import Database from 'better-sqlite3';

// electron-rebuild -f -w sqlite3
// electron-rebuild -f -w better-sqlite3

/**
 * SQLiteHelper class for interacting with SQLite databases using better-sqlite3.
 */
class SQLiteHelper {
  /**
   * @type {import('better-sqlite3').Database}
   */
  db;

  /**
   * Initializes a SQLiteHelper instance.
   * @param {string} databasePath - The file path to the SQLite database.
   */
  constructor(databasePath) {
    this.databasePath = databasePath;
    this.init();
  }

  /**
   * Initializes the database connection and sets foreign key constraints.
   */
  init() {
    try {
      this.db = new Database(this.databasePath);
      this.db.pragma('foreign_keys = ON');
    } catch (err) {
      console.error(`Failed to connect to the database at ${this.databasePath}:`, err.message);
    }
  }

  /**
   * Executes a SQL query.
   * @param {string} sql - The SQL query to execute.
   * @param {Array} [params=[]] - Optional parameters for the SQL query.
   * @returns {Database.RunResult} - Information about the query execution.
   */
  runQuery(sql, params = []) {
    try {
      const stmt = this.db.prepare(sql);
      return stmt.run(params);
    } catch (err) {
      console.error(`Error running query: ${sql}`, err.message);
      throw err;
    }
  }

  /**
   * Fetches all rows for a given query.
   * @param {string} sql - The SQL query to execute.
   * @param {Array} [params=[]] - Optional parameters for the SQL query.
   * @returns {Array<Record<string, any>>} - An array of rows.
   */
  fetchAll(sql, params = []) {
    try {
      const stmt = this.db.prepare(sql);
      return stmt.all(params);
    } catch (err) {
      console.error(`Error fetching data: ${sql}`, err.message);
      throw err;
    }
  }

  /**
   * Fetches a single row for a given query.
   * @param {string} sql - The SQL query to execute.
   * @param {Array} [params=[]] - Optional parameters for the SQL query.
   * @returns {Object|null} - A single row or null if no row is found.
   */
  fetchOne(sql, params = []) {
    try {
      const stmt = this.db.prepare(sql);
      return stmt.get(params);
    } catch (err) {
      console.error(`Error fetching data: ${sql}`, err.message);
      throw err;
    }
  }

  /**
   * Inserts data into the specified table.
   * @param {string} tableName - The name of the table.
   * @param {Record<string, any>} data - A dictionary where keys are column names and values are the values to insert.
   */
  insert(tableName, data) {
    const columns = Object.keys(data).join(', ');
    const placeholders = Object.keys(data)
      .map(() => '?')
      .join(', ');
    const sql = `INSERT INTO ${tableName} (${columns}) VALUES (${placeholders})`;
    try {
      const stmt = this.db.prepare(sql);
      stmt.run(Object.values(data));
    } catch (err) {
      console.error(`Error inserting data into ${tableName}`, err.message);
      throw err;
    }
  }

  /**
   * Selects rows from the table based on the given conditions.
   * @param {string} tableName - The name of the table.
   * @param {string} [columns='*'] - The columns to select (default is '*').
   * @param {string} [where] - The WHERE clause without the 'WHERE' keyword (default is '').
   * @param {Array<any>} [params=[]] - Parameters to substitute in the query.
   * @param {boolean} [rand=false] - Whether to order the results randomly.
   * @param {number} [limit=Number.MAX_VALUE] - The maximum number of rows to return.
   * @param {string} [additionalSQLQuery=''] - Additional SQL clauses (e.g., OFFSET, ORDER BY).
   * @returns {Array<Record<string, any>>} - An array of rows.
   */
  select(
    tableName,
    columns = '*',
    where = '',
    params = [],
    rand = false,
    limit = Number.MAX_VALUE,
    additionalSQLQuery = ''
  ) {
    let sql = `SELECT ${columns} FROM ${tableName}`;

    if (where) {
      sql += ` WHERE ${where}`;
    }

    if (rand) {
      sql += ' ORDER BY RANDOM()';
    }

    if (limit < Number.MAX_VALUE) {
      sql += ` LIMIT ${limit}`;
    }

    // Append additional SQL clauses (e.g., OFFSET, custom ORDER BY)
    if (additionalSQLQuery) {
      sql += ` ${additionalSQLQuery}`;
    }

    try {
      const stmt = this.db.prepare(sql);
      return stmt.all(params);
    } catch (err) {
      console.error(`Error selecting data from ${tableName}:`, err.message);
      throw err;
    }
  }

  /**
   * Returns the number of rows matching the given conditions.
   * @param {string} tableName - The name of the table.
   * @param {string} [where] - The WHERE clause without the 'WHERE' keyword.
   * @param {Array<any>} [params=[]] - Parameters to substitute in the query.
   * @returns {number} - The count of rows.
   */
  count(tableName, where = '', params = []) {
    let sql = `SELECT COUNT(*) as count FROM ${tableName}`;
    if (where) {
      sql += ` WHERE ${where}`;
    }
    try {
      const stmt = this.db.prepare(sql);
      const row = stmt.get(params);
      return row.count || 0;
    } catch (err) {
      console.error(`Error counting rows in ${tableName}`, err.message);
      throw err;
    }
  }

  /**
   * Updates rows in the table that match the given conditions.
   * @param {string} tableName - The name of the table.
   * @param {Record<string, any>} data - The data to update in the format { column: value }.
   * @param {string} where - The WHERE clause without the 'WHERE' keyword.
   * @param {Array<any>} [params=[]] - Parameters to substitute in the query.
   */
  update(tableName, data, where, params = []) {
    const setValues = Object.keys(data)
      .map((key) => `${key} = ?`)
      .join(', ');
    const sql = `UPDATE ${tableName} SET ${setValues} WHERE ${where}`;
    try {
      const stmt = this.db.prepare(sql);
      stmt.run([...Object.values(data), ...params]);
    } catch (err) {
      console.error(`Error updating data in ${tableName}`, err.message);
      throw err;
    }
  }

  /**
   * Updates a row if it exists; otherwise, inserts a new row.
   * @param {string} tableName - The name of the table.
   * @param {Record<string, any>} data - The data to update or insert, in the format { column: value }.
   * @param {string} where - The WHERE clause without the 'WHERE' keyword to identify the rows to update.
   * @param {Array<any>} [params=[]] - Parameters for the WHERE clause.
   * @returns {void}
   */
  updateOrInsert(tableName, data, where, params = []) {
    // Try to update the existing row(s)
    const setValues = Object.keys(data)
      .map((key) => `${key} = ?`)
      .join(', ');
    const updateSql = `UPDATE ${tableName} SET ${setValues} WHERE ${where}`;

    try {
      const updateStmt = this.db.prepare(updateSql);
      const result = updateStmt.run([...Object.values(data), ...params]);

      if (result.changes === 0) {
        // No rows updated, so insert the new row
        this.insert(tableName, data);
      }
    } catch (err) {
      console.error(`Error in updateOrInsert operation for ${tableName}`, err.message);
      throw err;
    }
  }

  /**
   * Deletes rows from the table that match the given conditions.
   * @param {string} tableName - The name of the table.
   * @param {string} where - The WHERE clause without the 'WHERE' keyword.
   * @param {Array<any>} [params=[]] - Parameters to substitute in the query.
   */
  delete(tableName, where, params = []) {
    const sql = `DELETE FROM ${tableName} WHERE ${where}`;
    try {
      const stmt = this.db.prepare(sql);
      stmt.run(params);
    } catch (err) {
      console.error(`Error deleting data from ${tableName}`, err.message);
      throw err;
    }
  }

  /**
   * Executes a custom SQL query with optional parameters.
   * @param {string} sql - The SQL query to execute.
   * @param {Array} [params] - The parameters for the SQL query (optional).
   */
  executeQuery(sql, params = []) {
    try {
      const stmt = this.db.prepare(sql);
      stmt.run(...params);
    } catch (err) {
      console.error(`Error executing query: ${sql}`, err.message);
      throw err;
    }
  }

  /**
   * Deletes all rows from the specified table.
   * @param {string} tableName - The name of the table.
   */
  truncateTable(tableName) {
    const sql = `DELETE FROM ${tableName}`;
    try {
      const stmt = this.db.prepare(sql);
      stmt.run();
    } catch (err) {
      console.error(`Error truncating table ${tableName}`, err.message);
      throw err;
    }
  }

  /**
   * Check if table exist
   * @param {string} tableName
   */
  doesTableExist(tableName) {
    const stmt = this.db.prepare(`
      SELECT count(*)
      FROM sqlite_master
      WHERE type='table' AND name=?;
    `);
    const result = stmt.get(tableName);
    // console.log({ result }, result['count(*)']);
    return result && result['count(*)'] === 1;
  }

  /**
   * Creates a table in the SQLite database if it doesn't already exist.
   *
   * @param {string} tableName - The name of the table to create.
   * @param {string} tableSchema - The SQL schema to define the table.
   * @returns {void}
   * * @example
   * // Usage example
   * const tableName = 'users';
   * const tableSchema = `
   *   id INTEGER PRIMARY KEY AUTOINCREMENT,
   *   name TEXT NOT NULL,
   *   email TEXT NOT NULL UNIQUE
   * `;
   * createTable(tableName, tableSchema);
   */
  createTable(tableName, tableSchema) {
    // Create the table if it doesn't exist
    const createTableStatement = this.db.prepare(`
      CREATE TABLE IF NOT EXISTS ${tableName} (${tableSchema});
    `);

    // Execute the create table statement
    createTableStatement.run();

    console.log(`Table '${tableName}' created successfully or already exists.`);
  }

  /**
   * Closes the connection to the SQLite database.
   */
  close() {
    try {
      this.db.close();
      console.log('Closed the SQLite database connection');
    } catch (err) {
      console.error('Error closing the database connection:', err.message);
      throw err;
    }
  }
}

export default SQLiteHelper;
