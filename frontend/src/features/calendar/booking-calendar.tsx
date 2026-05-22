import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';
import type { EventApi } from '@fullcalendar/core';
import { useMemo, useState } from 'react';
import { Card, CardContent, CardHeader } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Select } from '../../components/ui/input';
import { persistBookingChange } from '../../services/dashboard-api';
import { useBookingStore } from '../../store/booking-store';
import type { BookingStatus } from '../../types/booking';

const statusColor: Record<BookingStatus, string> = {
  pending: '#f59e0b',
  confirmed: '#22d3ee',
  in_progress: '#8b5cf6',
  completed: '#10b981',
  no_show: '#fb7185',
  cancelled: '#64748b',
};

const statusLabel: Record<BookingStatus, string> = {
  pending: 'Pendiente',
  confirmed: 'Confirmada',
  in_progress: 'En progreso',
  completed: 'Completada',
  no_show: 'No asistio',
  cancelled: 'Cancelada',
};

type BookingCalendarProps = {
  csrfToken?: string;
  isDemo: boolean;
  canEdit?: boolean;
  showFilters?: boolean;
  eyebrow?: string;
  title?: string;
  description?: string;
};

export function BookingCalendar({
  csrfToken,
  isDemo,
  canEdit = true,
  showFilters = true,
  eyebrow = 'Calendario operativo',
  title = 'Agenda viva con acciones rapidas',
  description,
}: BookingCalendarProps) {
  const bookings = useBookingStore((state) => state.bookings);
  const moveBooking = useBookingStore((state) => state.moveBooking);
  const setSelectedBooking = useBookingStore((state) => state.setSelectedBooking);
  const [professionalFilter, setProfessionalFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');
  const [serviceFilter, setServiceFilter] = useState('all');

  const professionals = useMemo(() => uniqueOptions(bookings.map((booking) => ({ id: booking.professionalId, name: booking.professionalName }))), [bookings]);
  const services = useMemo(() => uniqueOptions(bookings.map((booking) => ({ id: booking.serviceId, name: booking.serviceName }))), [bookings]);
  const filteredBookings = useMemo(() => bookings.filter((booking) => {
    if (professionalFilter !== 'all' && booking.professionalId !== professionalFilter) return false;
    if (statusFilter !== 'all' && booking.status !== statusFilter) return false;
    if (serviceFilter !== 'all' && booking.serviceId !== serviceFilter) return false;
    return true;
  }), [bookings, professionalFilter, serviceFilter, statusFilter]);

  const events = filteredBookings.map((booking) => ({
    id: booking.id,
    title: `${booking.serviceName} - ${booking.customerName}`,
    start: booking.start,
    end: booking.end,
    backgroundColor: booking.color || statusColor[booking.status],
    borderColor: booking.color || statusColor[booking.status],
    extendedProps: booking,
  }));

  const handleMove = async ({ event, revert }: { event: EventApi; revert: () => void }) => {
    if (!event.start || !event.end) {
      revert();
      return;
    }
    const booking = bookings.find((item) => item.id === event.id);
    if (!booking) {
      revert();
      return;
    }
    const previousStart = booking.start;
    const previousEnd = booking.end;
    moveBooking(event.id, event.start.toISOString(), event.end.toISOString());
    if (!isDemo && csrfToken) {
      try {
        await persistBookingChange({
          csrfToken,
          bookingId: booking.id,
          professionalId: booking.professionalId,
          status: booking.status,
          start: event.start.toISOString(),
          end: event.end.toISOString(),
          notes: booking.notes,
        });
      } catch (_error) {
        moveBooking(event.id, previousStart, previousEnd);
        revert();
      }
    }
  };

  return (
    <Card className="min-w-0 overflow-hidden">
      <CardHeader className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200/70">{eyebrow}</p>
          <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">{title}</h2>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
            {description ?? (isDemo
              ? 'Estas viendo datos demo. Inicia sesion para operar datos reales.'
              : 'Datos reales sincronizados con el backend PHP. Drag, resize y drawer lateral actualizan reservas con CSRF.'
            )}
          </p>
        </div>
        {showFilters ? <div className="grid w-full min-w-0 gap-3 sm:grid-cols-3 xl:max-w-[34rem]">
          <Select aria-label="Filtrar profesional" value={professionalFilter} onChange={(event) => setProfessionalFilter(event.target.value)}>
            <option value="all">Todos los profesionales</option>
            {professionals.map((professional) => <option key={professional.id} value={professional.id}>{professional.name}</option>)}
          </Select>
          <Select aria-label="Filtrar estado" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
            <option value="all">Todos los estados</option>
            {Object.entries(statusLabel).map(([status, label]) => <option key={status} value={status}>{label}</option>)}
          </Select>
          <Select aria-label="Filtrar servicio" value={serviceFilter} onChange={(event) => setServiceFilter(event.target.value)}>
            <option value="all">Todos los servicios</option>
            {services.map((service) => <option key={service.id} value={service.id}>{service.name}</option>)}
          </Select>
        </div> : null}
      </CardHeader>
      <CardContent>
        <div className="mb-4 flex flex-wrap items-center gap-2">
          {Object.entries(statusLabel).map(([status, label]) => (
            <Badge key={status} tone={status === 'completed' ? 'emerald' : status === 'cancelled' ? 'rose' : 'slate'}>
              <span className="mr-2 h-2 w-2 rounded-full" style={{ backgroundColor: statusColor[status as BookingStatus] }} />
              {label}
            </Badge>
          ))}
          {showFilters ? <Badge tone="cyan">{filteredBookings.length} de {bookings.length} reservas visibles</Badge> : null}
        </div>
        {showFilters && bookings.length > 0 && filteredBookings.length === 0 ? (
          <div className="mb-4 rounded-[1.35rem] border border-dashed border-white/15 bg-white/[0.03] p-4 text-sm text-slate-400">
            No hay reservas que coincidan con los filtros seleccionados.
          </div>
        ) : null}
        <div className="min-w-0 overflow-hidden rounded-[1.5rem] border border-white/10 bg-black/15 p-3">
          <FullCalendar
            plugins={[dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin]}
            initialView={window.innerWidth < 760 ? 'listWeek' : 'timeGridWeek'}
            headerToolbar={{
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            }}
            buttonText={{ today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Dia', list: 'Agenda' }}
            locale="es"
            height="auto"
            slotMinTime="08:00:00"
            slotMaxTime="21:00:00"
            scrollTime="09:00:00"
            expandRows
            nowIndicator
            editable={canEdit}
            selectable
            eventDurationEditable={canEdit}
            events={events}
            eventDrop={canEdit ? handleMove : undefined}
            eventResize={canEdit ? handleMove : undefined}
            eventClick={({ event }) => setSelectedBooking(event.id)}
          />
        </div>
      </CardContent>
    </Card>
  );
}

function uniqueOptions(options: Array<{ id: string; name: string }>) {
  const map = new Map<string, string>();
  options.forEach((option) => {
    if (option.id && !map.has(option.id)) {
      map.set(option.id, option.name);
    }
  });

  return Array.from(map, ([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name, 'es'));
}
