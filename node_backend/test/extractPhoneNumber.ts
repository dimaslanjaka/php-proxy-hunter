import { extractPhoneNumber } from '../Function.js';

// Test cases
console.log(extractPhoneNumber('/login 6287857297039')); // 6287857297039
console.log(extractPhoneNumber('/login 087857297039')); // 6287857297039
console.log(extractPhoneNumber('/login +6287857297039')); // 6287857297039
console.log(extractPhoneNumber('/login +62 878-5729-7039')); // 6287857297039
console.log(extractPhoneNumber('/login 0878-5729-7039')); // 6287857297039
