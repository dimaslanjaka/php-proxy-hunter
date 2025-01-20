const crypto = require('crypto');

class CustomPasswordHasher {
  static hash(password) {
    const salt = crypto.randomBytes(16).toString('hex'); // Generate a random salt
    const hashedPassword = crypto
      .createHash('sha256')
      .update(password + salt)
      .digest('hex');
    return `${hashedPassword}$${salt}`; // Return the hash with salt
  }

  static verify(password, encoded) {
    const [passwordHash, salt] = encoded.split('$');
    const hashedPassword = crypto
      .createHash('sha256')
      .update(password + salt)
      .digest('hex');
    return passwordHash === hashedPassword; // Compare the hashes
  }

  static summary(encoded) {
    const [passwordHash, salt] = encoded.split('$');
    return { algorithm: 'custom', salt };
  }
}

module.exports = CustomPasswordHasher;
