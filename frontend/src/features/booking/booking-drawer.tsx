import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { Clock3, CreditCard, History, MessageSquareText, UserRound } from 'lucide-react';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Drawer } from '../../components/ui/drawer';
import { Select, Textarea } from '../../components/ui/input';
import { persistBookingChange } from '../../services/dashboard-api';
import { useBookingStore } from '../../store/booking-store';
import type { BookingStatus } from '../../types/booking';

const schema = z.object({
  status: z.enum(['pending', 'confirmed', 'in_progress', 'completed', 'no_show', 'cancelled']),
  notes: z.string().max(500),
});

type FormValues = z.infer<typeof schema>;

const statusLabels: Record<BookingStatus, string> = {
  pending: 'Pendiente',
  confirmed: 'Confirmada',
  in_progress: 'En progreso',
  completed: 'Completada',
  no_show: 'No asistio',
  cancelled: 'Cancelada',
};

type BookingDrawerProps = {
  csrfToken?: string;
  isDemo: boolean;
};

export function BookingDrawer({ csrfToken, isDemo }: BookingDrawerProps) {
  const selectedBookingId = useBookingStore((state) => state.selectedBookingId);
  const setSelectedBooking = useBookingStore((state) => state.setSelectedBooking);
  const updateBookingStatus = useBookingStore((state) => state.updateBookingStatus);
  const updateBookingNotes = useBookingStore((state) => state.updateBookingNotes);
  const booking = useBookingStore((state) => state.bookings.find((item) => item.id === selectedBookingId));

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    values: {
      status: booking?.status ?? 'pending',
      notes: booking?.notes ?? '',
    },
  });

  const submit = form.handleSubmit(async (values) => {
    if (!booking) return;
    const previousStatus = booking.status;
    const previousNotes = booking.notes;
    updateBookingStatus(booking.id, values.status);
    updateBookingNotes(booking.id, values.notes);
    if (!isDemo && csrfToken) {
      try {
        await persistBookingChange({
          csrfToken,
          bookingId: booking.id,
          professionalId: booking.professionalId,
          status: values.status,
          start: booking.start,
          end: booking.end,
          notes: values.notes,
        });
      } catch (_error) {
        updateBookingStatus(booking.id, previousStatus);
        updateBookingNotes(booking.id, previousNotes);
      }
    }
  });

  return (
    <Drawer
      open={Boolean(booking)}
      title={booking?.serviceName ?? 'Reserva'}
      description={booking ? `${booking.customerName} con ${booking.professionalName}` : undefined}
      onClose={() => setSelectedBooking(null)}
    >
      {booking ? (
        <form className="space-y-6" onSubmit={submit}>
          <div className="grid gap-3 sm:grid-cols-2">
            <InfoCard icon={UserRound} label="Cliente" value={booking.customerName} />
            <InfoCard icon={Clock3} label="Horario" value={new Date(booking.start).toLocaleString('es-CL')} />
            <InfoCard icon={CreditCard} label="Ingreso" value={`$${booking.revenue.toLocaleString('es-CL')}`} />
            <InfoCard icon={MessageSquareText} label="Canal" value="Email activo / WhatsApp opcional" />
          </div>

          <div className="rounded-[1.5rem] border border-white/10 bg-white/[0.05] p-4">
            <div className="mb-3 flex items-center justify-between gap-3">
              <p className="text-sm font-semibold text-white">Edicion inline</p>
              <Badge tone={isDemo ? 'amber' : 'cyan'}>{isDemo ? 'Modo demo' : 'CSRF activo'}</Badge>
            </div>
            <label className="text-sm font-medium text-slate-300">
              Estado
              <Select className="mt-2" {...form.register('status')}>
                {Object.entries(statusLabels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </Select>
            </label>
            <label className="mt-4 block text-sm font-medium text-slate-300">
              Notas internas
              <Textarea className="mt-2" {...form.register('notes')} />
            </label>
            <div className="mt-4 flex gap-3">
              <Button variant="primary" type="submit">Guardar</Button>
              <Button type="button" onClick={() => setSelectedBooking(null)}>Cerrar</Button>
            </div>
          </div>

          <div className="rounded-[1.5rem] border border-white/10 bg-white/[0.05] p-4">
            <div className="flex items-center gap-2 text-sm font-semibold text-white">
              <History size={16} />
              Timeline reciente
            </div>
            <div className="mt-4 space-y-3 text-sm text-slate-400">
              <p>Reserva creada desde portal cliente.</p>
              <p>Recordatorio pendiente de envio.</p>
              <p>Ultima edicion sincronizada en calendario.</p>
            </div>
          </div>
        </form>
      ) : null}
    </Drawer>
  );
}

function InfoCard({ icon: Icon, label, value }: { icon: typeof UserRound; label: string; value: string }) {
  return (
    <div className="rounded-[1.35rem] border border-white/10 bg-white/[0.05] p-4">
      <Icon className="text-cyan-200" size={18} />
      <p className="mt-3 text-xs uppercase tracking-[0.22em] text-slate-500">{label}</p>
      <p className="mt-1 break-words text-sm font-semibold text-white">{value}</p>
    </div>
  );
}
