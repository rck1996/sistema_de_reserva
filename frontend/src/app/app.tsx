import { QueryClient, QueryClientProvider, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarDays, Clock3, History, LockKeyhole, LogOut, Settings, Sparkles, UserRound, WandSparkles } from 'lucide-react';
import { FormEvent, lazy, Suspense, useEffect, useMemo, useState } from 'react';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader } from '../components/ui/card';
import { Input, Select, Textarea } from '../components/ui/input';
import { BookingDrawer } from '../features/booking/booking-drawer';
import { Metrics } from '../features/dashboard/metrics';
import { AppShell } from '../layouts/app-shell';
import { createCustomerBooking, getAdminDashboard, getAuthState, getCustomerDashboard, getPublicData, getStaffDashboard, postAuth } from '../services/portal-api';
import { useBookingStore } from '../store/booking-store';
import type { Booking, Professional, Service } from '../types/booking';

const queryClient = new QueryClient();
const BookingCalendar = lazy(() => import('../features/calendar/booking-calendar').then((module) => ({ default: module.BookingCalendar })));

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <Portal />
    </QueryClientProvider>
  );
}

function Portal() {
  const queryClient = useQueryClient();
  const [route, setRoute] = useState(() => window.location.pathname === '/' ? 'home' : window.location.pathname.replace('/', ''));
  const auth = useQuery({ queryKey: ['auth'], queryFn: getAuthState });
  const csrfToken = auth.data?.csrfToken ?? '';

  useEffect(() => {
    const onPop = () => setRoute(window.location.pathname === '/' ? 'home' : window.location.pathname.replace('/', ''));
    window.addEventListener('popstate', onPop);
    return () => window.removeEventListener('popstate', onPop);
  }, []);

  const navigate = (next: string) => {
    const path = next === 'home' ? '/' : `/${next}`;
    window.history.pushState(null, '', path);
    setRoute(next);
  };

  const logout = useMutation({
    mutationFn: () => {
      const form = new FormData();
      form.set('action', 'logout');
      return postAuth(form, csrfToken);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries();
      navigate('home');
    },
  });

  if (route === 'admin') return <AdminPage onNavigate={navigate} onLogout={() => logout.mutate()} />;
  if (route === 'cliente') return <CustomerPage onNavigate={navigate} onLogout={() => logout.mutate()} />;
  if (route === 'profesional') return <StaffPage onNavigate={navigate} onLogout={() => logout.mutate()} />;
  if (route === 'login') return <LoginPage csrfToken={csrfToken} onNavigate={navigate} />;
  return <HomePage csrfToken={csrfToken} onNavigate={navigate} session={auth.data?.session} />;
}

function HomePage({ csrfToken, onNavigate, session }: { csrfToken: string; onNavigate: (route: string) => void; session?: { authenticated: boolean; role: string; name: string } }) {
  const publicData = useQuery({ queryKey: ['public-data'], queryFn: getPublicData });
  const data = publicData.data;
  const register = useMutation({
    mutationFn: (form: FormData) => postAuth(form, csrfToken),
    onSuccess: () => onNavigate('login'),
  });

  return (
    <PublicFrame onNavigate={onNavigate} session={session}>
      <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <Card className="p-7 sm:p-10">
          <Badge tone="cyan" className="gap-2"><Sparkles size={14} /> SaaS de reservas</Badge>
          <h1 className="mt-5 max-w-4xl text-balance text-5xl font-semibold tracking-[-0.07em] text-white sm:text-7xl">
            {data?.brand.heroTitle || 'Sistema de reservas moderno, configurable y multidisciplinario.'}
          </h1>
          <p className="mt-6 max-w-3xl text-lg leading-8 text-slate-400">{data?.brand.heroSubtitle || 'Centraliza agenda, servicios, clientes y profesionales con una experiencia premium.'}</p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Button variant="primary" onClick={() => onNavigate('login')}>Ingresar</Button>
            <Button onClick={() => document.getElementById('registro')?.scrollIntoView({ behavior: 'smooth' })}>Crear cliente</Button>
          </div>
        </Card>
        <Card className="p-6">
          <p className="text-sm text-slate-400">Estado</p>
          <p className="mt-3 text-3xl font-semibold text-white">{data?.brand.displayName || 'Sistema Reserva'}</p>
          <div className="mt-6 grid gap-3">
            <Badge tone="emerald">Horario {data?.hours.opening ?? '09:00'} - {data?.hours.closing ?? '20:00'}</Badge>
            <Badge tone="violet">{data?.disciplines.length ?? 0} disciplinas</Badge>
            <Badge tone="cyan">{data?.professionals.length ?? 0} profesionales</Badge>
          </div>
        </Card>
      </section>

      <Section title="Servicios" eyebrow="Catalogo visual">
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {(data?.services ?? []).map((service) => (
            <Card key={service.id} className="overflow-hidden">
              <div className="h-36 bg-white/[0.04]" style={{ background: `linear-gradient(135deg, ${service.color}66, rgba(255,255,255,.06))` }} />
              <CardContent>
                <Badge tone="slate">{service.durationMinutes} min</Badge>
                <h3 className="mt-4 text-xl font-semibold text-white">{service.name}</h3>
                <p className="mt-2 line-clamp-3 text-sm leading-6 text-slate-400">{service.description}</p>
                <p className="mt-4 font-semibold text-cyan-100">${Number(service.price).toLocaleString('es-CL')}</p>
              </CardContent>
            </Card>
          ))}
        </div>
      </Section>

      <Section title="Equipo" eyebrow="Profesionales">
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {(data?.professionals ?? []).map((professional) => (
            <Card key={professional.id} className="p-5">
              <div className="flex items-start gap-4">
                <div className="grid h-12 w-12 place-items-center rounded-2xl bg-white text-sm font-black text-zinc-950">{professional.name.slice(0, 2).toUpperCase()}</div>
                <div>
                  <h3 className="font-semibold text-white">{professional.name}</h3>
                  <p className="mt-2 text-sm leading-6 text-slate-400">{professional.bio || 'Profesional activo en la plataforma.'}</p>
                </div>
              </div>
            </Card>
          ))}
        </div>
      </Section>

      <Section title="Crear cuenta cliente" eyebrow="Registro" id="registro">
        <Card className="p-6">
          <form className="grid gap-4 md:grid-cols-2" onSubmit={(event) => submitForm(event, register.mutate, 'register-customer')}>
            <Input name="first_name" placeholder="Nombre" required />
            <Input name="last_name" placeholder="Apellido" required />
            <Input name="phone" placeholder="+56900000000" required />
            <Input name="email" type="email" placeholder="correo@dominio.cl" required />
            <Input name="username" placeholder="Usuario opcional" />
            <Input name="password" type="password" placeholder="Contraseña" required minLength={8} />
            <Button className="md:col-span-2" variant="primary" type="submit">Crear cuenta</Button>
          </form>
        </Card>
      </Section>
    </PublicFrame>
  );
}

function LoginPage({ csrfToken, onNavigate }: { csrfToken: string; onNavigate: (route: string) => void }) {
  const [mode, setMode] = useState<'customer' | 'staff' | 'admin'>('customer');
  const queryClient = useQueryClient();
  const login = useMutation({
    mutationFn: (form: FormData) => postAuth(form, csrfToken),
    onSuccess: async (payload) => {
      await queryClient.invalidateQueries({ queryKey: ['auth'] });
      const role = payload.session.role;
      onNavigate(role === 'admin' ? 'admin' : role === 'staff' ? 'profesional' : 'cliente');
    },
  });
  const action = mode === 'admin' ? 'login-admin' : mode === 'staff' ? 'login-staff' : 'login-customer';

  return (
    <PublicFrame onNavigate={onNavigate}>
      <div className="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_.9fr]">
        <Card className="p-8">
          <Badge tone="cyan"><LockKeyhole size={14} className="mr-2" /> Acceso seguro</Badge>
          <h1 className="mt-5 text-5xl font-semibold tracking-[-0.06em] text-white">Entrar al sistema</h1>
          <p className="mt-4 text-slate-400">Selecciona el tipo de cuenta. Las sesiones siguen protegidas por cookies HttpOnly y CSRF.</p>
        </Card>
        <Card className="p-6">
          <div className="grid grid-cols-3 gap-2">
            {(['customer', 'staff', 'admin'] as const).map((item) => <Button key={item} variant={mode === item ? 'primary' : 'secondary'} onClick={() => setMode(item)}>{item === 'customer' ? 'Cliente' : item === 'staff' ? 'Profesional' : 'Admin'}</Button>)}
          </div>
          <form className="mt-6 space-y-4" onSubmit={(event) => submitForm(event, login.mutate, action)}>
            {mode === 'admin' ? <Input name="email" type="email" placeholder="admin@sistema.local" required /> : <Input name="username" placeholder={mode === 'staff' ? 'pro1' : 'usuario cliente'} required />}
            <Input name="password" type="password" placeholder="Contraseña" required />
            {login.error ? <p className="rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{login.error.message}</p> : null}
            <Button className="w-full" variant="primary" type="submit">Ingresar</Button>
          </form>
        </Card>
      </div>
    </PublicFrame>
  );
}

function AdminPage({ onNavigate, onLogout }: { onNavigate: (route: string) => void; onLogout: () => void }) {
  const setBookings = useBookingStore((state) => state.setBookings);
  const query = useQuery({ queryKey: ['admin-dashboard'], queryFn: getAdminDashboard, retry: false });
  useEffect(() => { if (query.data?.bookings) setBookings(query.data.bookings); }, [query.data, setBookings]);
  if (query.isError) return <LoginRequired role="admin" onNavigate={onNavigate} />;
  return <DashboardShell title="Admin" onNavigate={onNavigate} onLogout={onLogout} data={query.data} loading={query.isLoading} />;
}

function CustomerPage({ onNavigate, onLogout }: { onNavigate: (route: string) => void; onLogout: () => void }) {
  const queryClient = useQueryClient();
  const setBookings = useBookingStore((state) => state.setBookings);
  const bookings = useBookingStore((state) => state.bookings);
  const [activeSection, setActiveSection] = useState('overview');
  const query = useQuery({ queryKey: ['customer-dashboard'], queryFn: getCustomerDashboard, retry: false });
  useEffect(() => { if (query.data?.bookings) setBookings(query.data.bookings); }, [query.data, setBookings]);
  if (query.isError) return <LoginRequired role="cliente" onNavigate={onNavigate} />;
  const data = query.data ? mapCustomerDashboard(query.data) : undefined;
  const navItems = [
    { label: 'Inicio', icon: Sparkles, section: 'overview' },
    { label: 'Reservar', icon: WandSparkles, section: 'reservar' },
    { label: 'Mi agenda', icon: CalendarDays, section: 'agenda' },
    { label: 'Historial', icon: History, section: 'historial' },
    { label: 'Perfil', icon: UserRound, section: 'perfil' },
  ];

  return (
    <AppShell activeSection={activeSection} onSectionChange={setActiveSection} onNavigateHome={() => onNavigate('home')} navItems={navItems}>
      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Badge tone="cyan">Cliente</Badge>
          <h1 className="mt-3 text-4xl font-semibold tracking-[-0.05em] text-white">Tu espacio de reservas</h1>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Agenda, reprograma y revisa tus proximas atenciones desde una vista simple y tactil.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button onClick={() => setActiveSection('reservar')} variant="primary"><CalendarDays size={16} className="mr-2" /> Nueva reserva</Button>
          <Button variant="danger" onClick={onLogout}><LogOut size={16} className="mr-2" /> Salir</Button>
        </div>
      </div>

      {query.isLoading || !data ? <CalendarSkeleton /> : (
        <div className="space-y-6 pb-24 xl:pb-6">
          {activeSection === 'overview' ? <CustomerOverview bookings={bookings} services={data.services} professionals={data.professionals} onReserve={() => setActiveSection('reservar')} /> : null}
          {activeSection === 'reservar' ? <CustomerBookingWizard data={data} onBooked={async () => { await queryClient.invalidateQueries({ queryKey: ['customer-dashboard'] }); window.setTimeout(() => setActiveSection('agenda'), 1400); }} /> : null}
          {activeSection === 'agenda' ? <CustomerAgenda csrfToken={data.csrfToken} bookings={bookings} onReserve={() => setActiveSection('reservar')} /> : null}
          {activeSection === 'historial' ? <CustomerHistory bookings={bookings} /> : null}
          {activeSection === 'perfil' ? <CustomerProfile profile={data.profile} hours={data.hours} /> : null}
        </div>
      )}
      <BookingDrawer csrfToken={data?.csrfToken} isDemo={!data?.csrfToken} />
    </AppShell>
  );
}

function mapCustomerDashboard(payload: any) {
  return {
    csrfToken: payload.csrfToken as string,
    profile: payload.profile as { id: number; name: string },
    hours: payload.hours as { opening: string; closing: string; slot_interval: number },
    disciplines: (payload.disciplines ?? []).map((discipline: any) => ({ id: String(discipline.id), name: discipline.name })) as Array<{ id: string; name: string }>,
    services: (payload.services ?? []).map((service: any) => ({
      id: String(service.id),
      disciplineId: String(service.disciplineId ?? ''),
      name: service.name,
      durationMinutes: Number(service.durationMinutes),
      price: Number(service.price),
      color: service.color || '#22d3ee',
    })) as Service[],
    professionals: (payload.professionals ?? []).map((professional: any) => ({
      id: String(professional.id),
      name: professional.name,
      role: 'Profesional',
      disciplines: String(professional.disciplineIds ?? '').split(',').filter(Boolean),
      services: String(professional.serviceIds ?? '').split(',').filter(Boolean),
      utilization: 60,
      status: 'available' as const,
    })) as Professional[],
  };
}

function CustomerOverview({ bookings, services, professionals, onReserve }: { bookings: Booking[]; services: Service[]; professionals: Professional[]; onReserve: () => void }) {
  const nextBooking = bookings.filter((booking) => new Date(booking.start).getTime() >= Date.now()).sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime())[0];
  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
      <div className="space-y-6">
        <div className="grid gap-4 md:grid-cols-3">
          <Card className="p-5"><p className="text-sm text-slate-400">Reservas activas</p><p className="mt-3 text-4xl font-semibold text-white">{bookings.filter((booking) => !['cancelled', 'completed'].includes(booking.status)).length}</p></Card>
          <Card className="p-5"><p className="text-sm text-slate-400">Servicios disponibles</p><p className="mt-3 text-4xl font-semibold text-white">{services.length}</p></Card>
          <Card className="p-5"><p className="text-sm text-slate-400">Profesionales</p><p className="mt-3 text-4xl font-semibold text-white">{professionals.length}</p></Card>
        </div>
        <Card className="overflow-hidden p-6">
          <Badge tone="cyan"><Clock3 size={14} className="mr-2" /> Proxima atencion</Badge>
          {nextBooking ? (
            <div className="mt-5 rounded-[1.5rem] border border-white/10 bg-white/[0.06] p-5">
              <h2 className="text-2xl font-semibold text-white">{nextBooking.serviceName}</h2>
              <p className="mt-2 text-sm text-slate-400">{formatDateTime(nextBooking.start)} con {nextBooking.professionalName}</p>
              <Button className="mt-5" onClick={() => useBookingStore.getState().setSelectedBooking(nextBooking.id)}>Ver detalle</Button>
            </div>
          ) : (
            <div className="mt-5 rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.03] p-6">
              <h2 className="text-2xl font-semibold text-white">Aun no tienes reservas</h2>
              <p className="mt-2 text-sm leading-6 text-slate-400">Crea una reserva guiada por disciplina, servicio, profesional y horario. Si el horario no esta libre, se enviara a lista de espera cuando corresponda.</p>
              <Button className="mt-5" variant="primary" onClick={onReserve}>Reservar horario</Button>
            </div>
          )}
        </Card>
      </div>
      <Card className="p-6">
        <p className="text-xs font-semibold uppercase tracking-[0.28em] text-violet-200/70">Servicios destacados</p>
        <div className="mt-5 space-y-3">
          {services.slice(0, 5).map((service) => <ListRow key={service.id} title={service.name} subtitle={`${service.durationMinutes} min - $${service.price.toLocaleString('es-CL')}`} />)}
          {services.length === 0 ? <EmptyState text="No hay servicios activos para reservar." /> : null}
        </div>
      </Card>
    </div>
  );
}

function CustomerBookingWizard({ data, onBooked }: { data: ReturnType<typeof mapCustomerDashboard>; onBooked: () => Promise<void> }) {
  const [disciplineId, setDisciplineId] = useState(data.disciplines[0]?.id ?? '');
  const [serviceId, setServiceId] = useState('');
  const [professionalId, setProfessionalId] = useState('');
  const [message, setMessage] = useState('');
  const filteredServices = data.services.filter((service) => !disciplineId || service.disciplineId === disciplineId);
  const availableProfessionals = data.professionals.filter((professional) => !serviceId || professional.services.includes(serviceId));
  const selectedService = data.services.find((service) => service.id === serviceId);
  const today = new Date().toISOString().slice(0, 10);
  const booking = useMutation({
    mutationFn: (form: FormData) => createCustomerBooking({
      csrfToken: data.csrfToken,
      professionalId: String(form.get('professional_id') ?? ''),
      serviceId: String(form.get('id_servicio') ?? ''),
      date: String(form.get('dia') ?? ''),
      time: String(form.get('hora') ?? ''),
      notes: String(form.get('notas_reserva') ?? ''),
    }),
    onSuccess: async (payload) => {
      setMessage(payload.waitlist ? (payload.message ?? 'Horario enviado a lista de espera.') : 'Reserva creada correctamente. Ya aparece en tu agenda.');
      await onBooked();
    },
  });

  useEffect(() => {
    if (!filteredServices.some((service) => service.id === serviceId)) {
      setServiceId(filteredServices[0]?.id ?? '');
    }
  }, [disciplineId, filteredServices, serviceId]);

  useEffect(() => {
    if (!availableProfessionals.some((professional) => professional.id === professionalId)) {
      setProfessionalId(availableProfessionals[0]?.id ?? '');
    }
  }, [availableProfessionals, professionalId, serviceId]);

  return (
    <Card className="overflow-hidden">
      <CardHeader>
        <p className="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200/70">Reserva guiada</p>
        <h2 className="mt-2 text-3xl font-semibold tracking-tight text-white">Elige el servicio correcto sin ruido</h2>
        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Al seleccionar una disciplina solo aparecen sus servicios. Luego se muestran los profesionales habilitados para ese servicio.</p>
      </CardHeader>
      <CardContent>
        <form className="grid gap-4 lg:grid-cols-2" onSubmit={(event) => submitForm(event, booking.mutate, '')}>
          <label className="space-y-2">
            <span className="text-sm font-semibold text-slate-300">Disciplina</span>
            <Select value={disciplineId} onChange={(event) => setDisciplineId(event.target.value)} required>
              {data.disciplines.map((discipline) => <option key={discipline.id} value={discipline.id}>{discipline.name}</option>)}
            </Select>
          </label>
          <label className="space-y-2">
            <span className="text-sm font-semibold text-slate-300">Servicio</span>
            <Select name="id_servicio" value={serviceId} onChange={(event) => setServiceId(event.target.value)} required>
              {filteredServices.map((service) => <option key={service.id} value={service.id}>{service.name} - {service.durationMinutes} min</option>)}
            </Select>
          </label>
          <label className="space-y-2">
            <span className="text-sm font-semibold text-slate-300">Profesional</span>
            <Select name="professional_id" value={professionalId} onChange={(event) => setProfessionalId(event.target.value)} required>
              {availableProfessionals.map((professional) => <option key={professional.id} value={professional.id}>{professional.name}</option>)}
            </Select>
          </label>
          <div className="grid gap-4 sm:grid-cols-2">
            <label className="space-y-2">
              <span className="text-sm font-semibold text-slate-300">Fecha</span>
              <Input name="dia" type="date" min={today} defaultValue={today} required />
            </label>
            <label className="space-y-2">
              <span className="text-sm font-semibold text-slate-300">Hora</span>
              <Input name="hora" type="time" defaultValue={data.hours.opening ?? '09:00'} required />
            </label>
          </div>
          <label className="space-y-2 lg:col-span-2">
            <span className="text-sm font-semibold text-slate-300">Notas opcionales</span>
            <Textarea name="notas_reserva" placeholder="Indica preferencias, contexto o restricciones importantes." />
          </label>
          {selectedService ? <div className="rounded-[1.35rem] border border-white/10 bg-white/[0.05] p-4 text-sm text-slate-300 lg:col-span-2">Resumen: {selectedService.name}, {selectedService.durationMinutes} min, ${selectedService.price.toLocaleString('es-CL')}.</div> : null}
          {availableProfessionals.length === 0 ? <EmptyState text="No hay profesionales habilitados para este servicio. Selecciona otro servicio o revisa la configuracion del staff." /> : null}
          {booking.error ? <p className="rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100 lg:col-span-2">{booking.error.message}</p> : null}
          {message ? <p className="rounded-2xl border border-emerald-300/20 bg-emerald-400/10 p-3 text-sm text-emerald-100 lg:col-span-2">{message}</p> : null}
          <Button className="lg:col-span-2" variant="primary" type="submit" disabled={booking.isPending || availableProfessionals.length === 0}>{booking.isPending ? 'Creando reserva...' : 'Confirmar reserva'}</Button>
        </form>
      </CardContent>
    </Card>
  );
}

function CustomerAgenda({ csrfToken, bookings, onReserve }: { csrfToken?: string; bookings: Booking[]; onReserve: () => void }) {
  return (
    <div className="space-y-6">
      {bookings.length === 0 ? (
        <Card className="p-6">
          <h2 className="text-2xl font-semibold text-white">Agenda visual sin reservas</h2>
          <p className="mt-2 text-sm leading-6 text-slate-400">Cuando confirmes una reserva aparecera aqui con vista semanal, diaria y agenda movil.</p>
          <Button className="mt-5" variant="primary" onClick={onReserve}>Crear primera reserva</Button>
        </Card>
      ) : null}
      <CalendarSection csrfToken={csrfToken} canEdit={false} showFilters={false} title="Tu agenda visual" description="Revisa tus reservas por semana, dia o lista movil. Para cambiar un horario abre el detalle de la reserva." />
    </div>
  );
}

function CustomerHistory({ bookings }: { bookings: Booking[] }) {
  return <Card><CardHeader><p className="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-200/70">Historial</p><h2 className="mt-2 text-2xl font-semibold text-white">Todas tus reservas</h2></CardHeader><CardContent className="space-y-3">{bookings.length === 0 ? <EmptyState text="Todavia no hay reservas registradas." /> : bookings.map((booking) => <ListRow key={booking.id} title={booking.serviceName} subtitle={`${formatDateTime(booking.start)} - ${booking.professionalName} - ${booking.status}`} />)}</CardContent></Card>;
}

function CustomerProfile({ profile, hours }: { profile: { id: number; name: string }; hours: { opening: string; closing: string } }) {
  return (
    <Card className="p-6">
      <div className="flex items-start gap-4">
        <div className="grid h-14 w-14 place-items-center rounded-2xl bg-white text-sm font-black text-zinc-950">{profile.name.slice(0, 2).toUpperCase() || 'CL'}</div>
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200/70">Perfil cliente</p>
          <h2 className="mt-2 text-2xl font-semibold text-white">{profile.name || 'Cliente'}</h2>
          <p className="mt-2 text-sm text-slate-400">Horario operativo: {hours.opening} - {hours.closing}. La edicion avanzada de datos personales queda como siguiente modulo.</p>
        </div>
      </div>
    </Card>
  );
}

function StaffPage({ onNavigate, onLogout }: { onNavigate: (route: string) => void; onLogout: () => void }) {
  const setBookings = useBookingStore((state) => state.setBookings);
  const query = useQuery({ queryKey: ['staff-dashboard'], queryFn: getStaffDashboard, retry: false });
  useEffect(() => { if (query.data?.bookings) setBookings(query.data.bookings); }, [query.data, setBookings]);
  if (query.isError) return <LoginRequired role="profesional" onNavigate={onNavigate} />;
  return <DashboardShell title="Profesional" onNavigate={onNavigate} onLogout={onLogout} data={query.data} loading={query.isLoading} />;
}

function DashboardShell({ title, onNavigate, onLogout, data, loading }: { title: string; onNavigate: (route: string) => void; onLogout: () => void; data?: { csrfToken?: string; metrics?: any; bookings: Booking[]; professionals: Professional[]; services: Service[] }; loading: boolean }) {
  const [activeSection, setActiveSection] = useState('dashboard');
  const bookings = useBookingStore((state) => state.bookings);
  const metrics = useMemo(() => data?.metrics ?? { activeBookings: bookings.filter((b) => !['cancelled', 'completed'].includes(b.status)).length, estimatedRevenue: bookings.reduce((sum, b) => sum + b.revenue, 0), cancelledBookings: 0, upcomingBookings: bookings.length }, [bookings, data]);
  return (
    <AppShell activeSection={activeSection} onSectionChange={setActiveSection} onNavigateHome={() => onNavigate('home')}>
      <div className="mb-6 flex items-center justify-between gap-4">
        <div>
          <Badge tone="cyan">{title}</Badge>
          <h1 className="mt-3 text-4xl font-semibold tracking-[-0.05em] text-white">Panel {title.toLowerCase()}</h1>
        </div>
        <div className="flex gap-2">
          <Button onClick={() => onNavigate('home')}>Inicio</Button>
          <Button variant="danger" onClick={onLogout}><LogOut size={16} className="mr-2" /> Salir</Button>
        </div>
      </div>
      {loading ? <CalendarSkeleton /> : (
        <div className="space-y-6 pb-24 xl:pb-6">
          {activeSection === 'dashboard' ? <DashboardOverview bookings={bookings} professionals={data?.professionals ?? []} services={data?.services ?? []} metrics={metrics} csrfToken={data?.csrfToken} /> : null}
          {activeSection === 'calendario' ? <CalendarSection csrfToken={data?.csrfToken} /> : null}
          {activeSection === 'clientes' ? <PeopleSection title={title} bookings={bookings} professionals={data?.professionals ?? []} /> : null}
          {activeSection === 'estadisticas' ? <StatsSection bookings={bookings} services={data?.services ?? []} /> : null}
          {activeSection === 'configuracion' ? <ConfigSection title={title} onNavigateHome={() => onNavigate('home')} /> : null}
        </div>
      )}
      <BookingDrawer csrfToken={data?.csrfToken} isDemo={!data?.csrfToken} />
    </AppShell>
  );
}

function DashboardOverview({ bookings, professionals, services, metrics, csrfToken }: { bookings: Booking[]; professionals: Professional[]; services: Service[]; metrics: any; csrfToken?: string }) {
  return (
    <>
      <Metrics bookings={bookings} professionals={professionals} metrics={metrics} />
      <div className="grid min-w-0 gap-6 2xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.42fr)]">
        <Suspense fallback={<CalendarSkeleton />}><BookingCalendar csrfToken={csrfToken} isDemo={!csrfToken} /></Suspense>
        <RightRail professionals={professionals} services={services} />
      </div>
    </>
  );
}

function CalendarSection({ csrfToken, canEdit = true, showFilters = true, title, description }: { csrfToken?: string; canEdit?: boolean; showFilters?: boolean; title?: string; description?: string }) {
  return <Suspense fallback={<CalendarSkeleton />}><BookingCalendar csrfToken={csrfToken} isDemo={!csrfToken} canEdit={canEdit} showFilters={showFilters} title={title} description={description} /></Suspense>;
}

function PeopleSection({ title, bookings, professionals }: { title: string; bookings: Booking[]; professionals: Professional[] }) {
  const customers = Array.from(new Set(bookings.map((booking) => booking.customerName))).filter(Boolean);
  return (
    <div className="grid gap-6 xl:grid-cols-2">
      <Card><CardHeader><p className="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200/70">Clientes</p><h2 className="mt-2 text-2xl font-semibold text-white">{title === 'Cliente' ? 'Tu historial' : 'Clientes con reservas'}</h2></CardHeader><CardContent className="space-y-3">{customers.length === 0 ? <EmptyState text="No hay clientes visibles todavia." /> : customers.map((customer) => <ListRow key={customer} title={customer} subtitle={`${bookings.filter((booking) => booking.customerName === customer).length} reservas`} />)}</CardContent></Card>
      <Card><CardHeader><p className="text-xs font-semibold uppercase tracking-[0.28em] text-violet-200/70">Equipo</p><h2 className="mt-2 text-2xl font-semibold text-white">Profesionales</h2></CardHeader><CardContent className="space-y-3">{professionals.length === 0 ? <EmptyState text="No hay profesionales para mostrar." /> : professionals.map((professional) => <ListRow key={professional.id} title={professional.name} subtitle={professional.role} />)}</CardContent></Card>
    </div>
  );
}

function StatsSection({ bookings, services }: { bookings: Booking[]; services: Service[] }) {
  const completed = bookings.filter((booking) => booking.status === 'completed').length;
  const cancelled = bookings.filter((booking) => booking.status === 'cancelled').length;
  const revenue = bookings.reduce((sum, booking) => sum + booking.revenue, 0);
  return (
    <div className="grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
      <Card className="p-6"><p className="text-sm text-slate-400">Ingresos</p><p className="mt-3 text-5xl font-semibold text-white">${revenue.toLocaleString('es-CL')}</p><p className="mt-3 text-sm text-slate-400">{completed} completadas · {cancelled} canceladas</p></Card>
      <Card><CardHeader><p className="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-200/70">Servicios</p><h2 className="mt-2 text-2xl font-semibold text-white">Rendimiento por servicio</h2></CardHeader><CardContent className="space-y-3">{services.map((service) => <ListRow key={service.id} title={service.name} subtitle={`${bookings.filter((booking) => booking.serviceId === service.id).length} reservas · ${service.durationMinutes} min`} />)}</CardContent></Card>
    </div>
  );
}

function ConfigSection({ title, onNavigateHome }: { title: string; onNavigateHome: () => void }) {
  return (
    <Card className="p-6">
      <div className="flex items-start gap-4">
        <div className="grid h-12 w-12 place-items-center rounded-2xl border border-white/10 bg-white/[0.07] text-cyan-200"><Settings size={20} /></div>
        <div>
          <h2 className="text-2xl font-semibold text-white">Configuracion {title.toLowerCase()}</h2>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Esta seccion queda conectada como destino real del sidebar. La edicion profunda de branding, servicios y reglas aun vive en el backend PHP legado y se ira migrando por modulos.</p>
          <Button className="mt-5" onClick={onNavigateHome}>Volver al inicio</Button>
        </div>
      </div>
    </Card>
  );
}

function ListRow({ title, subtitle }: { title: string; subtitle: string }) {
  return <div className="rounded-[1.35rem] border border-white/10 bg-white/[0.05] p-4"><p className="font-semibold text-white">{title}</p><p className="mt-1 text-sm text-slate-400">{subtitle}</p></div>;
}

function EmptyState({ text }: { text: string }) {
  return <div className="rounded-[1.35rem] border border-dashed border-white/15 bg-white/[0.03] p-5 text-sm text-slate-400">{text}</div>;
}

function RightRail({ professionals, services }: { professionals: Professional[]; services: Service[] }) {
  return <aside className="min-w-0 space-y-6"><Card><CardHeader><p className="text-xs font-semibold uppercase tracking-[0.28em] text-violet-200/70">Equipo</p><h2 className="mt-2 text-xl font-semibold text-white">Estado</h2></CardHeader><CardContent className="space-y-3">{professionals.map((p) => <div key={p.id} className="rounded-[1.35rem] border border-white/10 bg-white/[0.05] p-4"><p className="font-semibold text-white">{p.name}</p><p className="text-sm text-slate-400">{p.role}</p></div>)}</CardContent></Card><Card><CardHeader><p className="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-200/70">Servicios</p><h2 className="mt-2 text-xl font-semibold text-white">Catalogo</h2></CardHeader><CardContent className="space-y-3">{services.map((s) => <div key={s.id} className="flex items-center justify-between gap-4 rounded-[1.35rem] border border-white/10 bg-white/[0.05] p-4"><div><p className="font-semibold text-white">{s.name}</p><p className="text-sm text-slate-400">{s.durationMinutes} min · ${s.price.toLocaleString('es-CL')}</p></div><span className="h-3 w-3 rounded-full" style={{ backgroundColor: s.color }} /></div>)}</CardContent></Card></aside>;
}

function PublicFrame({ children, onNavigate, session }: { children: React.ReactNode; onNavigate: (route: string) => void; session?: { authenticated: boolean; role: string; name: string } }) {
  return <main className="mx-auto max-w-[1500px] space-y-10 px-4 py-5 sm:px-6 lg:px-8"><header className="flex flex-wrap items-center justify-between gap-3 rounded-[2rem] border border-white/10 bg-white/[0.06] p-3 backdrop-blur-2xl"><button className="flex items-center gap-3 px-3" onClick={() => onNavigate('home')}><span className="grid h-11 w-11 place-items-center rounded-2xl bg-white text-sm font-black text-zinc-950">SR</span><span className="text-left"><span className="block text-sm font-semibold text-white">Sistema Reserva</span><span className="block text-xs text-slate-400">SaaS premium</span></span></button><nav className="flex flex-wrap gap-2"><Button onClick={() => onNavigate('home')}>Inicio</Button><Button onClick={() => onNavigate('login')}>Acceso</Button>{session?.authenticated ? <Button variant="primary" onClick={() => onNavigate(session.role === 'admin' ? 'admin' : session.role === 'staff' ? 'profesional' : 'cliente')}>Mi panel</Button> : null}</nav></header>{children}</main>;
}

function Section({ title, eyebrow, children, id }: { title: string; eyebrow: string; children: React.ReactNode; id?: string }) {
  return <section id={id}><div className="mb-5"><p className="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200/70">{eyebrow}</p><h2 className="mt-2 text-3xl font-semibold tracking-tight text-white">{title}</h2></div>{children}</section>;
}

function LoginRequired({ role, onNavigate }: { role: string; onNavigate: (route: string) => void }) {
  return <PublicFrame onNavigate={onNavigate}><Card className="mx-auto max-w-xl p-8 text-center"><UserRound className="mx-auto text-cyan-200" /><h1 className="mt-4 text-3xl font-semibold text-white">Debes iniciar sesion como {role}</h1><p className="mt-3 text-slate-400">Esta pantalla ya vive en React, pero mantiene sesiones seguras del backend.</p><Button className="mt-6" variant="primary" onClick={() => onNavigate('login')}>Ir al acceso</Button></Card></PublicFrame>;
}

function CalendarSkeleton() {
  return <Card className="min-h-[28rem] animate-pulse p-6"><div className="h-6 w-56 rounded-full bg-white/10" /><div className="mt-4 h-4 w-96 max-w-full rounded-full bg-white/10" /><div className="mt-8 grid gap-3 sm:grid-cols-7">{Array.from({ length: 28 }).map((_, i) => <div key={i} className="h-16 rounded-2xl bg-white/[0.06]" />)}</div></Card>;
}

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat('es-CL', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

function submitForm(event: FormEvent<HTMLFormElement>, mutate: (form: FormData) => void, action: string) {
  event.preventDefault();
  const form = new FormData(event.currentTarget);
  if (action) form.set('action', action);
  mutate(form);
}
