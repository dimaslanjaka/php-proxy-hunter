import { UserLogResponse } from '../../../types/php_backend/logs';
import { UserInfo } from '../../../types/php_backend/user';
import { createUrl } from './url';
import axios from 'axios';

/**
 * Represents a single user info object, extends UserInfo.
 */
export interface SingleUserInfo extends UserInfo {
  uid?: string;
  admin?: boolean;
}

/**
 * Fetches the current logged-in user's information.
 * @returns The user info object.
 */
export async function getUserInfo(): Promise<SingleUserInfo> {
  const response = await axios.get<SingleUserInfo>(createUrl('/php_backend/user-info.php'));
  return response.data;
}

/**
 * Get logs for the current user with pagination.
 * @param page
 * @param pageSize
 * @returns
 */
export async function getUserLogs(page: number = 1, pageSize: number = 20) {
  const response = await axios.get<UserLogResponse>(createUrl('/php_backend/logs.php'), {
    params: { page, per_page: pageSize, me: 1 }
  });
  return response.data;
}

/**
 * Fetches the list of all users.
 * @returns Array of user info objects.
 */
export async function getListOfUsers(): Promise<UserInfo[]> {
  const response = await axios.get<UserInfo[]>(createUrl('/php_backend/list-user.php'));
  return response.data;
}

/**
 * Adds saldo to a user (increment by amount).
 * @param userId The user ID.
 * @param amount The amount to add.
 * @returns The response data from the backend.
 */
export async function addSaldoToUser(userId: string, amount: number) {
  const response = await axios.post(createUrl('/php_backend/refill-saldo.php'), {
    user: userId,
    amount: amount
  });
  return response.data;
}

/**
 * Sets saldo to an exact value for a user (replace saldo).
 * @param userId The user ID.
 * @param amount The saldo value to set.
 * @returns The response data from the backend.
 */
export async function setSaldoToUser(userId: string, amount: number) {
  const response = await axios.post(createUrl('/php_backend/refill-saldo.php'), {
    user: userId,
    amount: amount,
    set: true
  });
  return response.data;
}
