import { createUrl } from './url';
import axios from 'axios';

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

export interface SingleUserInfo extends UserInfo {
  uid: string;
  admin?: boolean;
}

export async function fetchUserInfo() {
  const response = await axios.get<SingleUserInfo>(createUrl('/php_backend/user-info.php'));
  return response.data;
}

export async function getListOfUsers() {
  const response = await axios.get<UserInfo[]>(createUrl('/php_backend/list-user.php'));
  return response.data;
}

export async function addSaldoToUser(userId: string, amount: number) {
  const response = await axios.post(createUrl('/php_backend/refill-saldo.php'), {
    user: userId,
    amount: amount
  });
  return response.data;
}
