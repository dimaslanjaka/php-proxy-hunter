import { extractPhoneNumber } from '../Function.js';

// Test cases
console.log(extractPhoneNumber('/login 6287857297039')); // 6287857297039
console.log(extractPhoneNumber('/login 087857297039')); // 6287857297039
console.log(extractPhoneNumber('/login +6287857297039')); // 6287857297039
console.log(extractPhoneNumber('/login +62 878-5729-7039')); // 6287857297039
console.log(extractPhoneNumber('/login 0878-5729-7039')); // 6287857297039
console.log(extractPhoneNumber('My number is +62 8785-4312-284')); // 6287854312284
console.log(extractPhoneNumber('Call me at 087854312284')); // 6287854312284
console.log(extractPhoneNumber('Phone: 6287854312284')); // 6287854312284
console.log(extractPhoneNumber('This text has no phone number')); // ''
