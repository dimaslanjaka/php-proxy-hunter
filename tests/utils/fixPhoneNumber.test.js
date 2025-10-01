import { fixPhoneNumber } from '../../src/utils/number.js';

describe('fixPhoneNumber', () => {
  describe('valid phone numbers', () => {
    test('should handle plain numeric string', () => {
      expect(fixPhoneNumber('1234567890')).toBe('1234567890');
    });

    test('should handle phone number with dashes', () => {
      expect(fixPhoneNumber('123-456-7890')).toBe('1234567890');
    });

    test('should handle phone number with parentheses and spaces', () => {
      expect(fixPhoneNumber('(123) 456-7890')).toBe('1234567890');
    });

    test('should handle phone number with dots', () => {
      expect(fixPhoneNumber('123.456.7890')).toBe('1234567890');
    });

    test('should handle phone number with plus sign', () => {
      expect(fixPhoneNumber('+1234567890')).toBe('1234567890');
    });

    test('should handle Indonesian phone number format', () => {
      expect(fixPhoneNumber('+62 812 3456 7890')).toBe('6281234567890');
    });

    test('should handle phone number with mixed formatting', () => {
      expect(fixPhoneNumber('+1 (234) 567-8900')).toBe('12345678900');
    });

    test('should handle phone number with country code and formatting', () => {
      expect(fixPhoneNumber('+62-812-3456-7890')).toBe('6281234567890');
    });
  });

  describe('edge cases', () => {
    test('should return empty string for null input', () => {
      expect(fixPhoneNumber(null)).toBe('');
    });

    test('should return empty string for undefined input', () => {
      expect(fixPhoneNumber(undefined)).toBe('');
    });

    test('should return empty string for empty string', () => {
      expect(fixPhoneNumber('')).toBe('');
    });

    test('should return empty string for whitespace only', () => {
      expect(fixPhoneNumber('   ')).toBe('');
    });

    test('should handle phone number with only non-numeric characters', () => {
      expect(fixPhoneNumber('abc-def-ghij')).toBe('');
    });

    test('should handle phone number with letters mixed in', () => {
      expect(fixPhoneNumber('123abc456def7890')).toBe('1234567890');
    });
  });

  describe('special characters', () => {
    test('should remove all special characters', () => {
      expect(fixPhoneNumber('!@#$123%^&*456()7890')).toBe('1234567890');
    });

    test('should handle phone number with extension', () => {
      expect(fixPhoneNumber('123-456-7890 ext. 123')).toBe('1234567890123');
    });

    test('should handle phone number with brackets and slashes', () => {
      expect(fixPhoneNumber('[123]/456\\7890')).toBe('1234567890');
    });

    test('should handle phone number with various punctuation', () => {
      expect(fixPhoneNumber('123,456;7890:1234')).toBe('12345678901234');
    });
  });

  describe('international formats', () => {
    test('should handle US format with country code', () => {
      expect(fixPhoneNumber('+1 (555) 123-4567')).toBe('15551234567');
    });

    test('should handle UK format', () => {
      expect(fixPhoneNumber('+44 20 7946 0958')).toBe('442079460958');
    });

    test('should handle German format', () => {
      expect(fixPhoneNumber('+49 30 12345678')).toBe('493012345678');
    });

    test('should handle Indonesian mobile format', () => {
      expect(fixPhoneNumber('+62 812-3456-7890')).toBe('6281234567890');
    });
  });

  describe('numeric input', () => {
    test('should handle numeric input as number', () => {
      expect(fixPhoneNumber(1234567890)).toBe('1234567890');
    });

    test('should handle zero', () => {
      expect(fixPhoneNumber(0)).toBe('0');
    });
  });

  describe('malformed inputs', () => {
    test('should handle very long string with numbers', () => {
      const longPhone = '1'.repeat(50) + 'abc' + '2'.repeat(50);
      const expected = '1'.repeat(50) + '2'.repeat(50);
      expect(fixPhoneNumber(longPhone)).toBe(expected);
    });

    test('should handle string with only one digit', () => {
      expect(fixPhoneNumber('a1b')).toBe('1');
    });

    test('should handle phone number with unicode characters', () => {
      expect(fixPhoneNumber('123-456-7890™')).toBe('1234567890');
    });
  });
});
