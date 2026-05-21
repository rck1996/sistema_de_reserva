import type { Booking, BookingStatus, DashboardSnapshot, Professional, Service } from '../types/booking';

type Session = { authenticated: boolean; role: 'guest' | 'admin' | 'customer' | 'staff'; name: string };
type AuthState = { ok: boolean; csrfToken: string; session: Session };

const backendStatus: Record<string, BookingStatus> = {
  pendiente: 'pending',
  confirmada: 'confirmed',
  en_progreso: 'in_progress',
  completada: 'completed',
  no_asistio: 'no_show',
  cancelada: 'cancelled',
  pending: 'pending',
  confirmed: 'confirmed',
  in_progress: 'in_progress',
  completed: 'completed',
  no_show: 'no_show',
  cancelled: 'cancelled',
};

export async function getAuthState(): Promise<AuthState> {
  const response = await fetch('/php-api/api/auth-json.php', { credentials: 'include' });
  return readJson<AuthState>(response);
}

export async function postAuth(form: FormData, csrfToken: string): Promise<AuthState & { message?: string; username?: string }> {
  let token = csrfToken;
  if (!token) {
    token = (await getAuthState()).csrfToken;
  }
  form.set('csrf_token', token);
  const body = new URLSearchParams();
  form.forEach((value, key) => {
    body.set(key, String(value));
  });

  const response = await fetch('/php-api/api/auth-json.php', {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body,
  });
  const payload = await readJson<AuthState & { error?: string; message?: string; username?: string }>(response);
  if (!response.ok || !payload.ok) {
    throw new Error(payload.error || 'No se pudo completar la accion');
  }
  return payload;
}

export async function createCustomerBooking(input: {
  csrfToken: string;
  professionalId: string;
  serviceId: string;
  date: string;
  time: string;
  notes?: string;
}) {
  const body = new URLSearchParams({
    csrf_token: input.csrfToken,
    professional_id: input.professionalId,
    id_servicio: input.serviceId,
    dia: input.date,
    hora: input.time,
    notas_reserva: input.notes ?? '',
    estado_reserva: 'pendiente',
    waitlist_on_failure: '1',
  });

  const response = await fetch('/php-api/bookings/api.php?accion=agendar_customer', {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body,
  });
  const payload = await readJson<{ ok: boolean; error?: string; message?: string; id_evento?: number; waitlist?: boolean }>(response);
  if (!response.ok || !payload.ok) {
    throw new Error(payload.error || 'No se pudo crear la reserva');
  }
  return payload;
}

export async function getPublicData() {
  const response = await fetch('/php-api/api/public-data.php', { credentials: 'include' });
  const payload = await readJson<any>(response);
  if (!response.ok || !payload.ok) throw new Error('No se pudo cargar inicio');
  return payload as {
    ok: true;
    csrfToken: string;
    brand: { name: string; displayName: string; tagline: string; heroTitle: string; heroSubtitle: string; businessType: string; contactEmail: string; contactPhone: string };
    hours: { opening: string; closing: string; slot_interval: number };
    disciplines: Array<{ id: number; name: string; description: string; color: string }>;
    services: Array<{ id: number; disciplineId: number; name: string; description: string; price: number; durationMinutes: number; modality: string; image: string; color: string }>;
    professionals: Array<{ id: number; name: string; bio: string; email: string; phone: string; color: string }>;
  };
}

export async function getAdminDashboard(): Promise<DashboardSnapshot> {
  const response = await fetch('/php-api/api/saas-dashboard.php', { credentials: 'include' });
  if (response.status === 401) throw new Error('admin_required');
  const payload = await readJson<DashboardSnapshot>(response);
  if (!response.ok || !payload.ok) throw new Error('No se pudo cargar admin');
  return payload;
}

export async function getCustomerDashboard() {
  const response = await fetch('/php-api/api/customer-dashboard.php', { credentials: 'include' });
  if (response.status === 401) throw new Error('customer_required');
  const payload = await readJson<any>(response);
  if (!response.ok || !payload.ok) throw new Error('No se pudo cargar cliente');
  return {
    ...payload,
    bookings: payload.bookings.map((booking: any) => ({
      id: String(booking.id),
      customerName: payload.profile.name,
      professionalId: String(booking.professionalId),
      professionalName: booking.professionalName,
      serviceId: String(booking.serviceId),
      serviceName: booking.serviceName,
      status: backendStatus[booking.status] ?? 'confirmed',
      start: new Date(booking.start).toISOString(),
      end: new Date(booking.end).toISOString(),
      notes: booking.notes ?? '',
      revenue: Number(booking.revenue ?? 0),
      color: booking.color,
    })) as Booking[],
  };
}

export async function getStaffDashboard() {
  const response = await fetch('/php-api/api/staff-dashboard.php', { credentials: 'include' });
  if (response.status === 401) throw new Error('staff_required');
  const payload = await readJson<any>(response);
  if (!response.ok || !payload.ok) throw new Error('No se pudo cargar profesional');
  return {
    ...payload,
    professionals: [{ id: String(payload.profile.id), name: payload.profile.name, role: payload.profile.scheduleSummary, disciplines: [], services: [], utilization: 80, status: 'available' }] as Professional[],
    services: payload.services.map((service: any) => ({
      id: String(service.id),
      disciplineId: '',
      name: service.name,
      durationMinutes: Number(service.durationMinutes ?? 0),
      price: Number(service.price ?? 0),
      color: service.color || '#22d3ee',
    })) as Service[],
    bookings: payload.bookings.map((booking: any) => ({
      id: String(booking.id),
      customerName: booking.customerName,
      professionalId: String(payload.profile.id),
      professionalName: payload.profile.name,
      serviceId: String(booking.serviceId),
      serviceName: booking.serviceName,
      status: backendStatus[booking.status] ?? 'confirmed',
      start: new Date(booking.start).toISOString(),
      end: new Date(booking.end).toISOString(),
      notes: booking.notes ?? '',
      revenue: Number(booking.revenue ?? 0),
      color: booking.color,
    })) as Booking[],
  };
}

async function readJson<T>(response: Response): Promise<T> {
  const text = await response.text();
  if (text.trim() === '') {
    throw new Error(`Respuesta vacia del servidor (${response.status})`);
  }

  try {
    return JSON.parse(text) as T;
  } catch (_error) {
    const preview = text.replace(/\s+/g, ' ').slice(0, 180);
    throw new Error(`Respuesta no JSON del servidor (${response.status}): ${preview}`);
  }
}
