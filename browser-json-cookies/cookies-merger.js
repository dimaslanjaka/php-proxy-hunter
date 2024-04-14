const allCookies = require('./dolphin-anty-cookies.json');
const exported = require('./dolphin-anty-export.json');
const fs = require('fs');
const path = require('path');
const { deepmerge } = require('deepmerge-ts');

const cookies = deepmerge(allCookies, exported);

// Create a map to store unique objects based on name and domain
let uniqueMap = {};

// Reduce the array to get unique objects based on name and domain
let uniqueArray = cookies.reduce((accumulator, currentValue) => {
  // Create a unique key using name and domain
  let key = currentValue.name + '-' + currentValue.domain + '-' + currentValue.path;

  // Check if the key is already in the map
  if (!uniqueMap[key]) {
    // If not, add it to the map and push the object to the accumulator
    uniqueMap[key] = true;
    accumulator.push(currentValue);
  }
  return accumulator;
}, []);

for (let i = 0; i < uniqueArray.length; i++) {
  const cookie = uniqueArray[i];
  // skip important cookies
  if (cookie.domain.includes('webmanajemen.com')) {
    if (cookie.name == 'currentAds') {
      console.log('skip', cookie);
      continue;
    }
  }
  // randomize cookie value
  cookie.value = generateRandomAlphanumeric(getRandomInt(30, 100));
}

fs.writeFileSync(path.join(__dirname, 'dolphin-anty-cookies.json'), JSON.stringify(uniqueArray));

function generateRandomAlphanumeric(length) {
  const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let result = '';
  for (let i = 0; i < length; i++) {
    result += charset.charAt(Math.floor(Math.random() * charset.length));
  }
  return result;
}

function getRandomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}
