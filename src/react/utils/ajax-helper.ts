import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';

// Axios instance configured to send cookies/credentials by default
const client: AxiosInstance = axios.create({
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest'
  }
});

async function get<T = any>(url: string, config: AxiosRequestConfig = {}): Promise<T> {
  const res: AxiosResponse<T> = await client.get<T>(url, config);
  return res.data;
}

async function post<T = any>(url: string, data: any = {}, config: AxiosRequestConfig = {}): Promise<T> {
  const res: AxiosResponse<T> = await client.post<T>(url, data, config);
  return res.data;
}

async function postForm<T = any>(
  url: string,
  params: URLSearchParams | Record<string, string> = {},
  config: AxiosRequestConfig = {}
): Promise<T> {
  const body = params instanceof URLSearchParams ? params.toString() : new URLSearchParams(params).toString();
  const headers = { 'Content-Type': 'application/x-www-form-urlencoded', ...(config.headers || {}) };
  const res: AxiosResponse<T> = await client.post<T>(url, body, { ...config, headers });
  return res.data;
}

async function postJson<T = any>(url: string, data: any = {}, config: AxiosRequestConfig = {}): Promise<T> {
  const headers = { 'Content-Type': 'application/json', ...(config.headers || {}) };
  const res: AxiosResponse<T> = await client.post<T>(url, data, { ...config, headers });
  return res.data;
}

function setBaseUrl(url: string): void {
  client.defaults.baseURL = url;
}

function setHeader(name: string, value: string): void {
  (client.defaults.headers as any).common[name] = value;
}

export { client as axiosInstance, get, get as axiosGet, post, postForm, postJson, setBaseUrl, setHeader };
export default client;
