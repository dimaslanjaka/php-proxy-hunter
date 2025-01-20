const nodePath = require('node:path');
const crypto = require('crypto');
const { PROJECT_DIR } = require('../../.env.build.json');
require('dotenv').config({ path: nodePath.join(PROJECT_DIR, '.env') });

class CustomPasswordHasher {
  static getSecretKey() {
    return process.env.DJANGO_SECRET_KEY || 'default_secret_key';
  }

  static getSalt() {
    const secretKey = this.getSecretKey();
    // Generate a deterministic salt in hex (similar to Python and PHP)
    return crypto.createHash('sha256').update(secretKey).digest('hex').slice(0, 16);
  }

  static hash(password) {
    const salt = this.getSalt();
    // Combine password and salt, then hash using SHA256
    const hashedPassword = crypto
      .createHash('sha256')
      .update(password + salt)
      .digest('hex');
    return `${hashedPassword}$${salt}`; // Return hashed password with salt
  }

  static verify(password, encoded) {
    const [passwordHash, salt] = encoded.split('$');
    const hashedPassword = crypto
      .createHash('sha256')
      .update(password + salt)
      .digest('hex');
    return passwordHash === hashedPassword; // Compare hashes
  }

  static summary(encoded) {
    const [passwordHash, salt] = encoded.split('$');
    return { algorithm: 'custom', salt };
  }
}

module.exports = CustomPasswordHasher;
