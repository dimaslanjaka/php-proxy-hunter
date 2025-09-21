/**
 * Represents a user info object for list and single user.
 */
export interface UserInfo {
  authenticated: boolean;
  email?: string;
  first_name?: string;
  last_name?: string;
  saldo?: number;
  username?: string;
  phone?: string;
  id?: number;
}
