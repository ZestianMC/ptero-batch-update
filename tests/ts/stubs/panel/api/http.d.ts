import type { AxiosInstance } from 'axios';
declare const http: { get: (url: string, config?: any) => Promise<any>; post: (url: string, data?: any, config?: any) => Promise<any> };
export default http;
export function httpErrorToHuman(error: any): string;
