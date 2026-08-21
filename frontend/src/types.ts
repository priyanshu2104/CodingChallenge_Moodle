// Shared TypeScript types used across components

export interface UserRow {
  name: string;
  surname: string;
  email: string;
  status: 'valid' | 'invalid';
  errors: string[];
  line: number;
}

export interface UploadResponse {
  total: number;
  valid: number;
  invalid: number;
  users: UserRow[];
}

export interface ImportResponse {
  inserted: number;
  skipped: number;
  errors: Array<{ line: number; email: string; reason: string }>;
}
