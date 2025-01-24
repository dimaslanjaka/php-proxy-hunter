export interface UserInfoResponse {
  authenticated: boolean;
  email: string;
  first_name: string;
  last_name: string;
  saldo: number;
  uid: string;
  username: string;
}

export type UserLists = UserListData[];

export interface UserListData {
  id: string;
  last_login: string;
  is_superuser: string;
  username: string;
  last_name: string;
  email: string;
  is_staff: string;
  is_active: string;
  date_joined: string;
  first_name: string;
}
