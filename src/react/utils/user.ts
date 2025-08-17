import { createUrl } from './url';
import axios from 'axios';

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
  const response = await axios.get<UserInfo>(createUrl('/php_backend/user-info.php'));
  return response.data;
}
