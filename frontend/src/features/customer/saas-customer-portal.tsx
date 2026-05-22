import { useQuery } from '@tanstack/react-query';
import { CalendarDays, Clock3, UserRound } from 'lucide-react';
import { Badge } from '../../components/ui/badge';
import { Card, CardContent, CardHeader } from '../../components/ui/card';
import { getSaasCustomerMe, listSaasCustomerBookings } from '../../services/api-v1-client';

type SaasCustomerPortalProps = {
  accessToken: string;
};

export function SaasCustomerPortal({ accessToken }: SaasCustomerPortalProps) {
  const profileQuery = useQuery({
    queryKey: ['saas-customer-me', accessToken],
    queryFn: () => getSaasCustomerMe(accessToken),
    enabled: Boolean(accessToken),
  });
  const bookingsQuery = useQuery({
    queryKey: ['saas-customer-bookings', accessToken],
    queryFn: () => listSaasCustomerBookings(accessToken),
    enabled: Boolean(accessToken),
  });
  const profile = profileQuery.data?.data;
  const bookings = bookingsQuery.data?.data ?? [];

  return (
    <div className="grid gap-6 xl:grid-cols-[.85fr_1.15fr]">
      <Card className="h-fit">
        <CardHeader>
          <Badge tone="emerald"><UserRound size={14} className="mr-2" /> Perfil cliente</Badge>
          <h2 className="mt-3 text-3xl font-semibold tracking-[-0.05em] text-white">Cuenta por empresa.</h2>
          <p className="mt-2 text-sm leading-6 text-slate-400">Este perfil se resuelve desde el JWT y `customers.user_id`, no desde un ID enviado por frontend.</p>
        </CardHeader>
        <CardContent>
          {profileQuery.isLoading ? <p className="text-sm text-slate-400">Cargando perfil...</p> : null}
          {profileQuery.isError ? <p className="rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{profileQuery.error.message}</p> : null}
          {profile ? (
            <div className="space-y-3">
              <Info label="Nombre" value={`${profile.first_name} ${profile.last_name}`} />
              <Info label="Correo" value={profile.email} />
              <Info label="Telefono" value={profile.phone || '-'} />
              <Info label="Empresa" value={`${profile.company_name} (${profile.company_slug})`} />
            </div>
          ) : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <Badge tone="cyan"><CalendarDays size={14} className="mr-2" /> Mis reservas</Badge>
          <h2 className="mt-3 text-3xl font-semibold tracking-[-0.05em] text-white">Agenda personal.</h2>
          <p className="mt-2 text-sm leading-6 text-slate-400">Solo aparecen reservas asociadas al cliente autenticado dentro de esta empresa.</p>
        </CardHeader>
        <CardContent>
          {bookingsQuery.isLoading ? <p className="text-sm text-slate-400">Cargando reservas...</p> : null}
          {bookingsQuery.isError ? <p className="rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{bookingsQuery.error.message}</p> : null}
          {!bookingsQuery.isLoading && bookings.length === 0 ? (
            <div className="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.03] p-8 text-sm text-slate-400">
              Todavia no tienes reservas en esta empresa.
            </div>
          ) : null}
          <div className="space-y-3">
            {bookings.map((booking) => (
              <article key={booking.id} className="rounded-[1.5rem] border border-white/10 bg-white/[0.045] p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h3 className="font-semibold text-white">{booking.service_name}</h3>
                    <p className="mt-1 text-sm text-slate-400">Con {booking.professional_name}</p>
                  </div>
                  <Badge tone={booking.status === 'confirmed' ? 'emerald' : booking.status === 'cancelled' ? 'rose' : 'amber'}>{booking.status}</Badge>
                </div>
                <div className="mt-4 flex flex-wrap gap-2 text-sm text-slate-400">
                  <span className="inline-flex items-center gap-2 rounded-full bg-black/20 px-3 py-1"><Clock3 size={14} /> {new Date(booking.starts_at).toLocaleString('es-CL')}</span>
                  <span className="rounded-full bg-black/20 px-3 py-1">${Number(booking.service_price).toLocaleString('es-CL')}</span>
                </div>
              </article>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
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
