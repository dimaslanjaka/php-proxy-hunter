/* eslint-env jest */
const CustomPasswordHasher = require('../../src/hashers/CustomPasswordHasher.cjs');

describe('CustomPasswordHasher', () => {
  const password = 'my_secure_password';

  test('should generate a salt', () => {
    const salt = CustomPasswordHasher.getSalt();
    expect(typeof salt).toBe('string');
    expect(salt.length).toBeGreaterThan(0);
  });

  test('should hash and verify password (JS)', () => {
    const encoded = CustomPasswordHasher.hash(password);
    expect(typeof encoded).toBe('string');
    expect(encoded.length).toBeGreaterThan(0);
    const isJsValid = CustomPasswordHasher.verify(password, encoded);
    expect(isJsValid).toBe(true);
  });

  test('should verify password from Python hash', () => {
    const from_py = 'd2db5f1a1c8658d87a0e696ca24bd86fba0c1ed43646a55649b714b8b9ff6b0c$558b06a41620e188';
    const isValid = CustomPasswordHasher.verify(password, from_py);
    expect(isValid).toBe(true);
  });
});
