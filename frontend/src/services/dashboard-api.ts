import type { BookingStatus, DashboardSnapshot } from '../types/booking';

const statusToBackend: Record<BookingStatus, string> = {
  pending: 'pendiente',
  confirmed: 'confirmada',
  in_progress: 'en_progreso',
  completed: 'completada',
  no_show: 'no_asistio',
  cancelled: 'cancelada',
};

export async function fetchDashboardSnapshot(): Promise<DashboardSnapshot> {
  const response = await fetch('/php-api/api/saas-dashboard.php', {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });

  if (response.status === 401) {
    throw new Error('admin_session_required');
  }

  const payload = (await response.json()) as DashboardSnapshot;
  if (!response.ok || !payload.ok) {
    throw new Error('No se pudo cargar el dashboard real');
  }

  return payload;
}

export async function persistBookingChange(input: {
  csrfToken: string;
  bookingId: string;
  professionalId: string;
  status: BookingStatus;
  start: string;
  end: string;
  notes: string;
}) {
  const response = await fetch('/php-api/bookings/api.php?accion=update_event_admin', {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': input.csrfToken,
    },
    body: new URLSearchParams({
      id_evento: input.bookingId,
      id_professional: input.professionalId,
      estado_reserva: statusToBackend[input.status],
      notas_reserva: input.notes,
      start: input.start,
      end: input.end,
      csrf_token: input.csrfToken,
    }).toString(),
  });

  const payload = (await response.json()) as { ok?: boolean; error?: string };
  if (!response.ok || !payload.ok) {
    throw new Error(payload.error || 'No se pudo persistir la reserva');
  }
}
