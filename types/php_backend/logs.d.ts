export interface LogsResponse {
  page: number;
  per_page: number;
  count: number;
  logs: LogEntry[];
}

export type LogEntry = UserLogEntry | PackageLogEntry;

export interface UserLogEntry {
  id: number;
  user_id: number;
  timestamp: string;
  log_level: string;
  message: string;
  source: string;
  extra_info: string | null;
  log_type: string | null;
  log_time: string;
  user_username: string;
  user_email: string;
  user_id_real: number;
}

export interface PackageLogEntry {
  id: number;
  action: string;
  package_id: number;
  details: string;
  created_at: string;
  log_time: string;
  package_name: string;
  package_id_real: number;
}

export interface LogEntry {
  id: number;
  user_id: number;
  target_user_id: number | null;
  action_type: string;
  target_id: number | null;
  target_type: string;
  /** JSON string, could parse into Details if needed */
  details: string;
  ip_address: string;
  user_agent: string;
  /** ISO datetime string */
  created_at: string;
}

export interface UserLogResponse {
  authenticated: boolean;
  error: boolean;
  logs: LogEntry[];
}
