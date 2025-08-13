import { createUrl } from './url';

export interface UserInfo {
  authenticated: boolean;
  email?: string;
  first_name?: string;
  last_name?: string;
  saldo?: number;
  uid?: string;
  username?: string;
}

export async function fetchUserInfo(): Promise<UserInfo> {
  const response = await fetch(createUrl('/php_backend/user-info.php'));
  if (!response.ok) throw new Error('Failed to fetch user info');
  return response.json();
}
