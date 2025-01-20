const CustomPasswordHasher = require('../../src/hashers/CustomPasswordHasher.cjs');

const password = 'my_secure_password';
const salt = CustomPasswordHasher.getSalt();
const encoded = CustomPasswordHasher.hash(password);
const isJsValid = CustomPasswordHasher.verify(password, encoded);
const from_py = 'd2db5f1a1c8658d87a0e696ca24bd86fba0c1ed43646a55649b714b8b9ff6b0c$558b06a41620e188';
const isValid = CustomPasswordHasher.verify(password, from_py);
console.log(salt, encoded, isJsValid, isValid);
