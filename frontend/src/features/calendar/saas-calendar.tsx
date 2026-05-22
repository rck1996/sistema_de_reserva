import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';
import type { EventApi } from '@fullcalendar/core';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarDays, CheckCircle2, Clock3, PanelRightOpen, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Card, CardContent, CardHeader } from '../../components/ui/card';
import { Drawer } from '../../components/ui/drawer';
import { Select } from '../../components/ui/input';
import { listSaasBookings, rescheduleSaasBooking, updateSaasBookingStatus, type SaasBooking } from '../../services/api-v1-client';

const statusColor: Record<SaasBooking['status'], string> = {
  pending: '#f59e0b',
  confirmed: '#22d3ee',
  in_progress: '#8b5cf6',
  completed: '#10b981',
  no_show: '#fb7185',
  cancelled: '#64748b',
};

const statusLabel: Record<SaasBooking['status'], string> = {
  pending: 'Pendiente',
  confirmed: 'Confirmada',
  in_progress: 'En progreso',
  completed: 'Completada',
  no_show: 'No asistio',
  cancelled: 'Cancelada',
};

type SaasCalendarProps = {
  accessToken: string;
};

export function SaasCalendar({ accessToken }: SaasCalendarProps) {
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState('');
  const [professionalFilter, setProfessionalFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');
  const [serviceFilter, setServiceFilter] = useState('all');
  const bookingsQuery = useQuery({
    queryKey: ['saas-bookings', accessToken],
    queryFn: () => listSaasBookings(accessToken),
    enabled: Boolean(accessToken),
  });
  const bookings = bookingsQuery.data?.data ?? [];
  const selectedBooking = bookings.find((booking) => booking.id === selectedId);

  const statusMutation = useMutation({
    mutationFn: (input: { id: string; status: SaasBooking['status'] }) => updateSaasBookingStatus(accessToken, input),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['saas-bookings', accessToken] });
      await queryClient.invalidateQueries({ queryKey: ['saas-dashboard', accessToken] });
    },
  });
  const rescheduleMutation = useMutation({
    mutationFn: (input: { id: string; starts_at: string }) => rescheduleSaasBooking(accessToken, input),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['saas-bookings', accessToken] });
      await queryClient.invalidateQueries({ queryKey: ['saas-dashboard', accessToken] });
    },
  });

  const professionals = useMemo(() => uniqueOptions(bookings.map((booking) => ({ id: booking.professional_id, name: booking.professional_name }))), [bookings]);
  const services = useMemo(() => uniqueOptions(bookings.map((booking) => ({ id: booking.service_id, name: booking.service_name }))), [bookings]);
  const filteredBookings = useMemo(() => bookings.filter((booking) => {
    if (professionalFilter !== 'all' && booking.professional_id !== professionalFilter) return false;
    if (statusFilter !== 'all' && booking.status !== statusFilter) return false;
    if (serviceFilter !== 'all' && booking.service_id !== serviceFilter) return false;
    return true;
  }), [bookings, professionalFilter, serviceFilter, statusFilter]);

  const events = filteredBookings.map((booking) => ({
    id: booking.id,
    title: `${booking.service_name} - ${booking.customer_first_name} ${booking.customer_last_name}`,
    start: booking.starts_at,
    end: booking.ends_at,
    backgroundColor: booking.professional_color || statusColor[booking.status],
    borderColor: statusColor[booking.status],
    extendedProps: booking,
  }));

  const handleMove = ({ event, revert }: { event: EventApi; revert: () => void }) => {
    if (!event.start) {
      revert();
      return;
    }

    rescheduleMutation.mutate(
      { id: event.id, starts_at: event.start.toISOString() },
      { onError: revert },
    );
  };

  return (
    <>
      <Card className="min-w-0 overflow-hidden">
        <CardHeader className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
          <div>
            <Badge tone="cyan"><CalendarDays size={14} className="mr-2" /> Calendario SaaS v1</Badge>
            <h2 className="mt-3 text-3xl font-semibold tracking-[-0.05em] text-white">Reservas operativas desde PostgreSQL.</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Filtra por profesional, estado o servicio. Arrastra una reserva para reprogramarla con validacion anti-solapamiento en backend.</p>
          </div>
          <div className="grid w-full min-w-0 gap-3 sm:grid-cols-3 xl:max-w-[38rem]">
            <Select value={professionalFilter} onChange={(event) => setProfessionalFilter(event.target.value)} aria-label="Filtrar profesional">
              <option value="all">Todos los profesionales</option>
              {professionals.map((professional) => <option key={professional.id} value={professional.id}>{professional.name}</option>)}
            </Select>
            <Select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} aria-label="Filtrar estado">
              <option value="all">Todos los estados</option>
              {Object.entries(statusLabel).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </Select>
            <Select value={serviceFilter} onChange={(event) => setServiceFilter(event.target.value)} aria-label="Filtrar servicio">
              <option value="all">Todos los servicios</option>
              {services.map((service) => <option key={service.id} value={service.id}>{service.name}</option>)}
            </Select>
          </div>
        </CardHeader>
        <CardContent>
          <div className="mb-4 flex flex-wrap items-center gap-2">
            {Object.entries(statusLabel).map(([status, label]) => (
              <Badge key={status} tone={status === 'completed' ? 'emerald' : status === 'cancelled' ? 'rose' : 'slate'}>
                <span className="mr-2 h-2 w-2 rounded-full" style={{ backgroundColor: statusColor[status as SaasBooking['status']] }} />
                {label}
              </Badge>
            ))}
            <Badge tone="cyan">{filteredBookings.length} de {bookings.length} reservas</Badge>
          </div>
          {bookingsQuery.isLoading ? <div className="rounded-[1.5rem] border border-white/10 bg-white/[0.04] p-8 text-sm text-slate-400">Cargando calendario...</div> : null}
          {bookingsQuery.isError ? <div className="rounded-[1.5rem] border border-rose-300/20 bg-rose-400/10 p-4 text-sm text-rose-100">{bookingsQuery.error.message}</div> : null}
          {!bookingsQuery.isLoading && bookings.length === 0 ? <div className="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.03] p-8 text-sm text-slate-400">No hay reservas en PostgreSQL para este tenant.</div> : null}
          {bookings.length > 0 ? (
            <div className="min-w-0 overflow-hidden rounded-[1.5rem] border border-white/10 bg-black/15 p-3">
              <FullCalendar
                plugins={[dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin]}
                initialView={window.innerWidth < 760 ? 'listWeek' : 'timeGridWeek'}
                headerToolbar={{ left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' }}
                buttonText={{ today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Dia', list: 'Agenda' }}
                locale="es"
                height="auto"
                slotMinTime="08:00:00"
                slotMaxTime="21:00:00"
                scrollTime="09:00:00"
                expandRows
                nowIndicator
                editable
                eventDurationEditable={false}
                events={events}
                eventDrop={handleMove}
                eventClick={({ event }) => setSelectedId(event.id)}
              />
            </div>
          ) : null}
        </CardContent>
      </Card>

      <Drawer
        open={Boolean(selectedBooking)}
        title={selectedBooking?.service_name ?? 'Reserva'}
        description={selectedBooking ? `${selectedBooking.customer_first_name} ${selectedBooking.customer_last_name} con ${selectedBooking.professional_name}` : undefined}
        onClose={() => setSelectedId('')}
      >
        {selectedBooking ? (
          <div className="space-y-5">
            <div className="grid gap-3 sm:grid-cols-2">
              <Info label="Cliente" value={`${selectedBooking.customer_first_name} ${selectedBooking.customer_last_name}`} />
              <Info label="Profesional" value={selectedBooking.professional_name} />
              <Info label="Inicio" value={new Date(selectedBooking.starts_at).toLocaleString('es-CL')} />
              <Info label="Fin" value={new Date(selectedBooking.ends_at).toLocaleString('es-CL')} />
            </div>
            <div className="rounded-[1.5rem] border border-white/10 bg-white/[0.05] p-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-white">Estado de reserva</p>
                  <p className="mt-1 text-xs text-slate-500">Se actualiza por API v1 y refresca dashboard/calendario.</p>
                </div>
                <Badge tone={selectedBooking.status === 'confirmed' ? 'emerald' : selectedBooking.status === 'cancelled' ? 'rose' : 'amber'}>{statusLabel[selectedBooking.status]}</Badge>
              </div>
              <div className="mt-4 grid gap-2 sm:grid-cols-2">
                <Button onClick={() => statusMutation.mutate({ id: selectedBooking.id, status: 'confirmed' })} disabled={statusMutation.isPending}><CheckCircle2 size={16} className="mr-2" /> Confirmar</Button>
                <Button onClick={() => statusMutation.mutate({ id: selectedBooking.id, status: 'completed' })} disabled={statusMutation.isPending}>Completar</Button>
                <Button onClick={() => statusMutation.mutate({ id: selectedBooking.id, status: 'in_progress' })} disabled={statusMutation.isPending}><Clock3 size={16} className="mr-2" /> En progreso</Button>
                <Button variant="danger" onClick={() => statusMutation.mutate({ id: selectedBooking.id, status: 'cancelled' })} disabled={statusMutation.isPending}><XCircle size={16} className="mr-2" /> Cancelar</Button>
              </div>
            </div>
            <div className="rounded-[1.5rem] border border-white/10 bg-white/[0.05] p-4">
              <div className="flex items-center gap-2 text-sm font-semibold text-white"><PanelRightOpen size={16} /> Contexto</div>
              <p className="mt-3 text-sm leading-6 text-slate-400">{selectedBooking.notes || 'Sin notas internas.'}</p>
              <p className="mt-3 text-xs text-slate-500">Ingreso estimado: ${Number(selectedBooking.service_price).toLocaleString('es-CL')}</p>
            </div>
            {statusMutation.error ? <p className="rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{statusMutation.error.message}</p> : null}
            {rescheduleMutation.error ? <p className="rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{rescheduleMutation.error.message}</p> : null}
          </div>
        ) : null}
      </Drawer>
    </>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[1.35rem] border border-white/10 bg-white/[0.05] p-4">
      <p className="text-xs uppercase tracking-[0.22em] text-slate-500">{label}</p>
      <p className="mt-2 break-words text-sm font-semibold text-white">{value}</p>
    </div>
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
