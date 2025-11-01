export interface AjaxScheduleEntry {
  url: string;
  method?: 'GET' | 'POST_JSON' | 'POST_FORM' | string;
  data?: any;
  includeCredentials?: boolean;
}

export interface AjaxScheduleOptions {
  includeCredentials?: boolean;
  method?: 'GET' | 'POST_JSON' | 'POST_FORM' | string;
  data?: any;
}
