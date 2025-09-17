export interface LogsResponse {
  page: number;
  per_page: number;
  count: number;
  logs: LogEntry[];
}

export type LogEntry = UserLog | PackageLog;

export interface UserLog {
  id: number;
  user_id: number;
  timestamp: string; // e.g. "2025-09-17 16:53:22"
  log_level: string; // e.g. "INFO"
  message: string;
  source: string;
  extra_info: string | null;
  log_type: string | null;
  log_time: string; // same as timestamp
}

export interface PackageLog {
  id: number;
  action: string; // e.g. "add"
  package_id: number;
  details: string; // JSON string of package details
  created_at: string; // e.g. "2025-09-17 15:36:58"
  log_time: string;
}
