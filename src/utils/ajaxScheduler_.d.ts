export type AjaxHttpMethod =
  | 'GET'
  | 'POST'
  | 'POST_JSON'
  | 'POST_FORM'
  | 'PUT'
  | 'PATCH'
  | 'DELETE'
  | 'HEAD'
  | 'OPTIONS';

export interface AjaxScheduleEntry {
  url: string;
  method: AjaxHttpMethod;
  data?: any;
  includeCredentials?: boolean;
}

export interface AjaxScheduleOptions {
  includeCredentials?: boolean;
  method: AjaxHttpMethod;
  data?: any;
}
