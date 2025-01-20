const CustomPasswordHasher = require('../../src/hashers/CustomPasswordHasher.cjs');

const password = 'my_secure_password';
const encoded = CustomPasswordHasher.hash(password);
const isJsValid = CustomPasswordHasher.verify(password, encoded);
const from_py = 'b09067ff3bdbaf24c708b893499d9c783d425688dc91c185e15461035ad6f59b$44469f22d81a4d137a9772fe26a6b230';
const isValid = CustomPasswordHasher.verify(password, from_py);
console.log(password, encoded, isJsValid, isValid);
