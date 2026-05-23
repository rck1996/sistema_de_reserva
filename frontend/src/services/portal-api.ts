import type { DashboardSnapshot } from '../types/booking';

export type AuthState = {
  csrfToken: string;
  session: {
    authenticated: boolean;
    role: string;
    name: string;
  };
};

export type AdminManagementPayload = {
  csrfToken: string;
  settings: Record<string, string>;
  customers: Array<{ id: number; firstName: string; lastName: string; email: string; phone: string; username: string; notes: string }>;
  disciplines: Array<{ id: number; name: string; description: string; color: string; active: number }>;
  services: Array<{ id: number; disciplineId: number | null; name: string; description: string; price: number; durationMinutes: number; modality: string; color: string; active: number }>;
  professionals: Array<{ id: number; name: string }>;
};

function removedLegacyEndpoint(): never {
  throw new Error('El portal PHP/SQLite legacy fue retirado. Usa services/api-v1-client.ts contra /api/v1.');
}

export async function getAuthState(): Promise<AuthState> {
  return removedLegacyEndpoint();
}

export async function postAuth(_form?: FormData, _csrfToken?: string): Promise<AuthState & { message?: string; username?: string }> {
  return removedLegacyEndpoint();
}

export async function createCustomerBooking(_input?: unknown): Promise<{ ok: boolean; message: string; booking?: unknown; waitlist?: unknown }> {
  return removedLegacyEndpoint();
}

export async function getPublicData(): Promise<any> {
  return removedLegacyEndpoint();
}

export async function getAdminDashboard(): Promise<DashboardSnapshot> {
  return removedLegacyEndpoint();
}

export async function getAdminManagement(): Promise<AdminManagementPayload> {
  return removedLegacyEndpoint();
}

export async function saveAdminManagement(_form?: FormData, _csrfToken?: string): Promise<AdminManagementPayload> {
  return removedLegacyEndpoint();
}

export async function getCustomerDashboard(): Promise<any> {
  return removedLegacyEndpoint();
}

export async function getStaffDashboard(): Promise<any> {
  return removedLegacyEndpoint();
}
