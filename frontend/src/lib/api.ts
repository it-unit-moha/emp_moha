import axios from "axios";
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || "http://localhost:8000",
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: "application/json" },
});
export function errorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    if (!error.response)
      return "Unable to reach GALMS. Check your connection and that the API is running.";
    if (error.response.status === 401)
      return "Your session has ended. Please sign in again.";
    if (error.response.status === 403)
      return "Your role does not have permission for this action.";
    if (error.response.status === 419)
      return "Your security session expired. Refresh the page and try again.";
    return (
      error.response.data?.message || "The request could not be completed."
    );
  }
  return "An unexpected error occurred.";
}
export function fieldErrors(error: unknown): Record<string, string[]> {
  return axios.isAxiosError(error) ? error.response?.data?.errors || {} : {};
}
export interface User {
  id: number;
  name: string;
  email: string;
  role: "Super Admin" | "Admin" | "HR Officer" | "Authorized Viewer";
}
export interface Master {
  id: number;
  name: string;
  status?: number | null;
}
export interface Employee {
  id: number;
  name: string | null;
  nic: string | null;
  emp_no: string | null;
  email: string | null;
  address: string | null;
  contact_no: string | null;
  dob: string | null;
  gender: string | null;
  division: number | null;
  unit: string | null;
  designation: number | null;
  division_name?: string;
  unit_name?: string;
  designation_name?: string;
  is_active: number | null;
}
export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
  };
  links: Record<string, string | null>;
}
export const endpoint = (path: string) => `/api/v1/${path}`;
