import { ArrowDownRight, ArrowUpRight, CalendarClock, CircleDollarSign, UsersRound, Zap } from 'lucide-react';
import { Card } from '../../components/ui/card';
import type { Booking, DashboardMetrics, Professional } from '../../types/booking';

type MetricsProps = {
  bookings: Booking[];
  professionals: Professional[];
  metrics?: DashboardMetrics;
};

const currency = new Intl.NumberFormat('es-CL', {
  style: 'currency',
  currency: 'CLP',
  maximumFractionDigits: 0,
});

export function Metrics({ bookings, professionals, metrics }: MetricsProps) {
  const activeBookings = metrics?.activeBookings ?? bookings.filter((booking) => !['cancelled', 'completed'].includes(booking.status)).length;
  const revenue = metrics?.estimatedRevenue ?? bookings.reduce((total, booking) => total + booking.revenue, 0);
  const averageUtilization = Math.round(
    professionals.reduce((total, professional) => total + professional.utilization, 0) / professionals.length,
  );

  const cards = [
    { label: 'Reservas activas', value: activeBookings.toString(), hint: '+12% vs semana anterior', icon: CalendarClock, trend: 'up' },
    { label: 'Ingresos estimados', value: currency.format(revenue), hint: 'Servicios confirmados', icon: CircleDollarSign, trend: 'up' },
    { label: 'Ocupacion staff', value: `${averageUtilization}%`, hint: 'Promedio ponderado', icon: UsersRound, trend: 'down' },
    { label: 'Tiempo respuesta', value: '1.8s', hint: 'Feedback inmediato', icon: Zap, trend: 'up' },
  ] as const;

  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      {cards.map((card) => (
        <div
          key={card.label}
        >
          <Card className="p-5">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-sm text-slate-400">{card.label}</p>
                <p className="mt-3 text-3xl font-semibold tracking-tight text-white">{card.value}</p>
              </div>
              <div className="grid h-11 w-11 place-items-center rounded-2xl border border-white/10 bg-white/[0.08] text-cyan-200">
                <card.icon size={20} />
              </div>
            </div>
            <div className="mt-5 flex items-center gap-2 text-sm text-slate-400">
              {card.trend === 'up' ? <ArrowUpRight className="text-emerald-300" size={16} /> : <ArrowDownRight className="text-amber-300" size={16} />}
              {card.hint}
            </div>
          </Card>
        </div>
      ))}
    </div>
  );
}
