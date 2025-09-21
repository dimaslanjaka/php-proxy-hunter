export interface LogsResponse {
  logs: LogEntry[];
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
