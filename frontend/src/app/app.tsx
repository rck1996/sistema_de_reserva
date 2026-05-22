import { QueryClient, QueryClientProvider, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Activity, ArrowUpRight, CalendarDays, Clock3, History, LockKeyhole, LogOut, Settings, Sparkles, UserRound, WandSparkles } from 'lucide-react';
import { FormEvent, lazy, Suspense, useEffect, useMemo, useState } from 'react';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader } from '../components/ui/card';
import { Input, Select, Textarea } from '../components/ui/input';
import { BookingDrawer } from '../features/booking/booking-drawer';
import { useSaasAuthStore } from '../features/auth/saas-auth-store';
import { Metrics } from '../features/dashboard/metrics';
import { AppShell } from '../layouts/app-shell';
import { createSaasDemoBooking, getSaasDashboard, getSaasMe, listSaasBookings, listSaasCustomers, listSaasDisciplines, listSaasProfessionals, listSaasServices, loginSaas, loginSaasCustomer, logoutSaas, refreshSaas, registerSaasCustomer } from '../services/api-v1-client';
import { createCustomerBooking, getAdminDashboard, getAdminManagement, getAuthState, getCustomerDashboard, getPublicData, getStaffDashboard, postAuth, saveAdminManagement } from '../services/portal-api';
import { useBookingStore } from '../store/booking-store';
import type { Booking, Professional, Service } from '../types/booking';

const queryClient = new QueryClient();
const BookingCalendar = lazy(() => import('../features/calendar/booking-calendar').then((module) => ({ default: module.BookingCalendar })));
const SaasCalendar = lazy(() => import('../features/calendar/saas-calendar').then((module) => ({ default: module.SaasCalendar })));
const SaasCustomerPortal = lazy(() => import('../features/customer/saas-customer-portal').then((module) => ({ default: module.SaasCustomerPortal })));
const SaasCustomers = lazy(() => import('../features/customers/saas-customers').then((module) => ({ default: module.SaasCustomers })));

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
  if (route === 'saas-login') return <SaasLoginPage onNavigate={navigate} />;
  if (route === 'saas-dashboard') return <SaasDashboardPage onNavigate={navigate} />;
  if (route === 'saas-calendar') return <SaasCalendarPage onNavigate={navigate} />;
  if (route === 'saas-customer') return <SaasCustomerPage onNavigate={navigate} />;
  if (route === 'saas-customers') return <SaasCustomersPage onNavigate={navigate} />;
  if (route === 'saas-customer-register') return <SaasCustomerRegisterPage onNavigate={navigate} />;
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
            <Button onClick={() => onNavigate('saas-login')}>Probar API v1 JWT</Button>
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

function SaasLoginPage({ onNavigate }: { onNavigate: (route: string) => void }) {
  const { accessToken, refreshToken, user, hydrate, setSession, clearSession } = useSaasAuthStore();
  const [message, setMessage] = useState('');
  const [resourcePreview, setResourcePreview] = useState('');
  useEffect(() => hydrate(), [hydrate]);
  const login = useMutation({
    mutationFn: (form: FormData) => loginSaas({
      companySlug: String(form.get('company_slug') ?? 'demo'),
      email: String(form.get('email') ?? ''),
      password: String(form.get('password') ?? ''),
    }),
    onSuccess: (payload) => {
      setSession({ accessToken: payload.access_token, refreshToken: payload.refresh_token, user: payload.user });
      setMessage('Login JWT correcto. Token guardado en localStorage para pruebas.');
    },
  });
  const customerLogin = useMutation({
    mutationFn: (form: FormData) => loginSaasCustomer({
      email: String(form.get('customer_email') ?? '').toLowerCase(),
      password: String(form.get('customer_password') ?? ''),
    }),
    onSuccess: (payload) => {
      setSession({ accessToken: payload.access_token, refreshToken: payload.refresh_token, user: payload.user });
      setMessage('Login cliente marketplace correcto.');
    },
  });
  const me = useMutation({
    mutationFn: () => getSaasMe(accessToken),
    onSuccess: (payload) => setMessage(`Sesion valida: ${payload.user.email} / ${payload.user.role}`),
  });
  const refresh = useMutation({
    mutationFn: () => refreshSaas(refreshToken),
    onSuccess: (payload) => {
      setSession({ accessToken: payload.access_token, refreshToken: payload.refresh_token, user: payload.user });
      setMessage('Refresh token rotado correctamente.');
    },
  });
  const logout = useMutation({
    mutationFn: () => logoutSaas(refreshToken),
    onSuccess: () => {
      clearSession();
      setMessage('Sesion SaaS cerrada.');
    },
  });
  const services = useMutation({
    mutationFn: () => listSaasServices(accessToken),
    onSuccess: (payload) => {
      setResourcePreview(JSON.stringify(payload.data.slice(0, 5), null, 2));
      setMessage(`Servicios tenant cargados: ${payload.data.length}`);
    },
  });
  const disciplines = useMutation({
    mutationFn: () => listSaasDisciplines(accessToken),
    onSuccess: (payload) => {
      setResourcePreview(JSON.stringify(payload.data.slice(0, 5), null, 2));
      setMessage(`Disciplinas tenant cargadas: ${payload.data.length}`);
    },
  });
  const professionals = useMutation({
    mutationFn: () => listSaasProfessionals(accessToken),
    onSuccess: (payload) => {
      setResourcePreview(JSON.stringify(payload.data.slice(0, 5), null, 2));
      setMessage(`Profesionales tenant cargados: ${payload.data.length}`);
    },
  });
  const customers = useMutation({
    mutationFn: () => listSaasCustomers(accessToken),
    onSuccess: (payload) => {
      setResourcePreview(JSON.stringify(payload.data.slice(0, 5), null, 2));
      setMessage(`Clientes tenant cargados: ${payload.data.length}`);
    },
  });
  const bookings = useMutation({
    mutationFn: () => listSaasBookings(accessToken),
    onSuccess: (payload) => {
      setResourcePreview(JSON.stringify(payload.data.slice(0, 5), null, 2));
      setMessage(`Reservas tenant cargadas: ${payload.data.length}`);
    },
  });
  const createBooking = useMutation({
    mutationFn: () => createSaasDemoBooking(accessToken),
    onSuccess: (payload) => {
      setResourcePreview(JSON.stringify(payload.data, null, 2));
      setMessage(`Reserva creada: ${payload.data.service_name} / ${payload.data.professional_name}`);
    },
  });
  const currentError = login.error || customerLogin.error || me.error || refresh.error || logout.error || services.error || disciplines.error || professionals.error || customers.error || bookings.error || createBooking.error;

  return (
    <PublicFrame onNavigate={onNavigate}>
      <div className="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[.9fr_1.1fr]">
        <Card className="p-8">
          <Badge tone="violet"><LockKeyhole size={14} className="mr-2" /> API v1 JWT</Badge>
          <h1 className="mt-5 text-5xl font-semibold tracking-[-0.06em] text-white">Prueba SaaS Auth</h1>
          <p className="mt-4 text-sm leading-6 text-slate-400">Esta pantalla prueba la nueva autenticacion PostgreSQL/JWT sin reemplazar todavia el login legacy. Sirve para validar Fase 2 desde navegador.</p>
          <div className="mt-6 rounded-[1.5rem] border border-white/10 bg-white/[0.05] p-4 text-sm text-slate-300">
            <p className="font-semibold text-white">Demo</p>
            <p className="mt-2">Empresa: <span className="text-cyan-100">demo</span></p>
            <p>Email: <span className="text-cyan-100">admin@demo.local</span></p>
            <p>Clave: <span className="text-cyan-100">Admin12345</span></p>
          </div>
        </Card>
        <Card className="p-6">
          <form className="space-y-4" onSubmit={(event) => submitForm(event, login.mutate, '')}>
            <Input name="company_slug" defaultValue="demo" placeholder="company_slug" required />
            <Input name="email" type="email" defaultValue="admin@demo.local" placeholder="admin@demo.local" required />
            <Input name="password" type="password" defaultValue="Admin12345" placeholder="Clave" required />
            <Button className="w-full" variant="primary" type="submit" disabled={login.isPending}>{login.isPending ? 'Validando...' : 'Login API v1'}</Button>
          </form>
          <form className="mt-5 space-y-4 rounded-[1.5rem] border border-white/10 bg-white/[0.04] p-4" onSubmit={(event) => submitForm(event, customerLogin.mutate, '')}>
            <p className="text-sm font-semibold text-white">Login cliente marketplace</p>
            <Input name="customer_email" type="email" placeholder="cliente@dominio.cl" required />
            <Input name="customer_password" type="password" placeholder="Clave cliente" required />
            <Button className="w-full" type="submit" disabled={customerLogin.isPending}>{customerLogin.isPending ? 'Validando cliente...' : 'Login cliente global'}</Button>
          </form>
          <div className="mt-5 grid gap-3 sm:grid-cols-3">
            <Button onClick={() => me.mutate()} disabled={!accessToken || me.isPending}>Probar me</Button>
            <Button onClick={() => refresh.mutate()} disabled={!refreshToken || refresh.isPending}>Refresh</Button>
            <Button variant="danger" onClick={() => logout.mutate()} disabled={!refreshToken || logout.isPending}>Logout</Button>
          </div>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <Button variant="primary" onClick={() => onNavigate('saas-dashboard')} disabled={!accessToken}>Abrir dashboard SaaS v1</Button>
            <Button variant="primary" onClick={() => onNavigate('saas-calendar')} disabled={!accessToken}>Abrir calendario SaaS v1</Button>
            <Button variant="primary" onClick={() => onNavigate('saas-customers')} disabled={!accessToken}>Abrir clientes SaaS v1</Button>
            <Button variant="primary" onClick={() => onNavigate('saas-customer')} disabled={!accessToken || user?.role !== 'customer'}>Portal cliente SaaS</Button>
            <Button onClick={() => onNavigate('saas-customer-register')}>Registro cliente SaaS</Button>
          </div>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <Button onClick={() => disciplines.mutate()} disabled={!accessToken || disciplines.isPending}>Listar disciplinas tenant</Button>
            <Button onClick={() => services.mutate()} disabled={!accessToken || services.isPending}>Listar servicios tenant</Button>
            <Button onClick={() => professionals.mutate()} disabled={!accessToken || professionals.isPending}>Listar profesionales tenant</Button>
            <Button onClick={() => customers.mutate()} disabled={!accessToken || customers.isPending}>Listar clientes tenant</Button>
            <Button onClick={() => bookings.mutate()} disabled={!accessToken || bookings.isPending}>Listar reservas tenant</Button>
            <Button variant="primary" onClick={() => createBooking.mutate()} disabled={!accessToken || createBooking.isPending}>Crear reserva demo</Button>
          </div>
          {message ? <p className="mt-5 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 p-3 text-sm text-emerald-100">{message}</p> : null}
          {currentError ? <p className="mt-5 rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{currentError.message}</p> : null}
          <div className="mt-5 rounded-[1.5rem] border border-white/10 bg-black/20 p-4 text-xs leading-6 text-slate-400">
            <p className="font-semibold text-white">Sesion actual</p>
            <p>Usuario: {user?.email ?? 'sin sesion'}</p>
            <p>Empresa: {user?.company_slug ?? '-'}</p>
            <p>Rol: {user?.role ?? '-'}</p>
            <p>Access token: {accessToken ? `${accessToken.slice(0, 24)}...` : '-'}</p>
          </div>
          {resourcePreview ? <pre className="mt-5 max-h-72 overflow-auto rounded-[1.5rem] border border-white/10 bg-black/30 p-4 text-xs text-slate-300">{resourcePreview}</pre> : null}
        </Card>
      </div>
    </PublicFrame>
  );
}

function SaasCustomerRegisterPage({ onNavigate }: { onNavigate: (route: string) => void }) {
  const { setSession } = useSaasAuthStore();
  const register = useMutation({
    mutationFn: (form: FormData) => registerSaasCustomer({
      firstName: String(form.get('first_name') ?? ''),
      lastName: String(form.get('last_name') ?? ''),
      email: String(form.get('email') ?? '').toLowerCase(),
      password: String(form.get('password') ?? ''),
      phone: String(form.get('phone') ?? ''),
      notes: 'Registro cliente SaaS v1',
    }),
    onSuccess: (payload) => {
      setSession({ accessToken: payload.access_token, refreshToken: payload.refresh_token, user: payload.user });
    },
  });

  return (
    <PublicFrame onNavigate={onNavigate}>
      <div className="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[.9fr_1.1fr]">
        <Card className="p-8">
          <Badge tone="emerald">Marketplace cliente</Badge>
          <h1 className="mt-5 text-5xl font-semibold tracking-[-0.06em] text-white">Registro cliente SaaS v1.</h1>
          <p className="mt-4 text-sm leading-6 text-slate-400">El cliente crea una cuenta global en el marketplace. Luego puede buscar empresas e inscribirse para reservar.</p>
          <div className="mt-6 rounded-[1.5rem] border border-white/10 bg-white/[0.05] p-4 text-sm text-slate-300">
            <p className="font-semibold text-white">Modelo actual</p>
              <p className="mt-2">Login global: email + password</p>
              <p>Inscripcion posterior: cliente ↔ empresa mediante membresia.</p>
          </div>
        </Card>
        <Card className="p-6">
            <form className="space-y-4" onSubmit={(event) => submitForm(event, register.mutate, '')}>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input name="first_name" placeholder="Nombre" required />
              <Input name="last_name" placeholder="Apellido" required />
            </div>
            <Input name="email" type="email" placeholder="cliente@dominio.cl" required />
            <Input name="phone" placeholder="+56900000000" />
            <Input name="password" type="password" placeholder="Clave minimo 8 caracteres" required minLength={8} />
            {register.error ? <p className="rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{register.error.message}</p> : null}
            {register.data ? (
              <div className="rounded-2xl border border-emerald-300/20 bg-emerald-400/10 p-3 text-sm text-emerald-100">
                Cliente registrado y autenticado como {register.data.user.email} / {register.data.user.role}.
              </div>
            ) : null}
            <div className="flex flex-wrap gap-3">
              <Button variant="primary" type="submit" disabled={register.isPending}>{register.isPending ? 'Registrando...' : 'Registrar cliente'}</Button>
              <Button type="button" onClick={() => onNavigate('saas-customer')} disabled={!register.data}>Abrir portal cliente</Button>
              <Button type="button" onClick={() => onNavigate('saas-login')}>Volver a login API</Button>
            </div>
          </form>
        </Card>
      </div>
    </PublicFrame>
  );
}

function SaasCustomerPage({ onNavigate }: { onNavigate: (route: string) => void }) {
  const { accessToken, user, hydrate, clearSession } = useSaasAuthStore();
  useEffect(() => hydrate(), [hydrate]);

  if (!accessToken || user?.role !== 'customer') {
    return (
      <PublicFrame onNavigate={onNavigate}>
        <Card className="mx-auto max-w-2xl p-8">
          <Badge tone="rose">Cuenta cliente requerida</Badge>
          <h1 className="mt-4 text-4xl font-semibold tracking-[-0.05em] text-white">Entra o registrate como cliente para ver tu portal.</h1>
          <div className="mt-6 flex flex-wrap gap-3">
            <Button variant="primary" onClick={() => onNavigate('saas-customer-register')}>Registrar cliente</Button>
            <Button onClick={() => onNavigate('saas-login')}>Login API v1</Button>
          </div>
        </Card>
      </PublicFrame>
    );
  }

  return (
    <PublicFrame onNavigate={onNavigate}>
      <div className="mx-auto max-w-7xl space-y-6">
        <Card className="p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <Badge tone="emerald">Portal cliente</Badge>
              <h1 className="mt-3 text-5xl font-semibold tracking-[-0.06em] text-white">Tu cuenta en {user.company_name}.</h1>
              <p className="mt-3 max-w-3xl text-sm leading-6 text-slate-400">Sesion cliente marketplace. El siguiente paso es mostrar agenda global de todas sus empresas inscritas.</p>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button onClick={() => onNavigate('saas-login')}>Pruebas API</Button>
              <Button variant="danger" onClick={() => { clearSession(); onNavigate('saas-login'); }}>Cerrar JWT</Button>
            </div>
          </div>
        </Card>
        <Suspense fallback={<CalendarSkeleton />}>
          <SaasCustomerPortal accessToken={accessToken} />
        </Suspense>
      </div>
    </PublicFrame>
  );
}

function SaasCustomersPage({ onNavigate }: { onNavigate: (route: string) => void }) {
  const { accessToken, user, hydrate, clearSession } = useSaasAuthStore();
  useEffect(() => hydrate(), [hydrate]);

  if (!accessToken) {
    return (
      <PublicFrame onNavigate={onNavigate}>
        <Card className="mx-auto max-w-2xl p-8">
          <Badge tone="rose">Sesion requerida</Badge>
          <h1 className="mt-4 text-4xl font-semibold tracking-[-0.05em] text-white">Entra con JWT para gestionar clientes SaaS.</h1>
          <Button className="mt-6" variant="primary" onClick={() => onNavigate('saas-login')}>Ir a login API v1</Button>
        </Card>
      </PublicFrame>
    );
  }

  return (
    <PublicFrame onNavigate={onNavigate}>
      <div className="mx-auto max-w-7xl space-y-6">
        <SaasTopBar
          eyebrow="Clientes"
          title="CRM operativo SaaS."
          description={`Sesion: ${user?.email ?? '-'} · Empresa: ${user?.company_name ?? '-'}.`}
          onNavigate={onNavigate}
          onLogout={() => { clearSession(); onNavigate('saas-login'); }}
        />
        <Suspense fallback={<CalendarSkeleton />}>
          <SaasCustomers accessToken={accessToken} />
        </Suspense>
      </div>
    </PublicFrame>
  );
}

function SaasCalendarPage({ onNavigate }: { onNavigate: (route: string) => void }) {
  const { accessToken, user, hydrate, clearSession } = useSaasAuthStore();
  useEffect(() => hydrate(), [hydrate]);

  if (!accessToken) {
    return (
      <PublicFrame onNavigate={onNavigate}>
        <Card className="mx-auto max-w-2xl p-8">
          <Badge tone="rose">Sesion requerida</Badge>
          <h1 className="mt-4 text-4xl font-semibold tracking-[-0.05em] text-white">Entra con JWT para ver el calendario SaaS.</h1>
          <Button className="mt-6" variant="primary" onClick={() => onNavigate('saas-login')}>Ir a login API v1</Button>
        </Card>
      </PublicFrame>
    );
  }

  return (
    <PublicFrame onNavigate={onNavigate}>
      <div className="mx-auto max-w-7xl space-y-6">
        <SaasTopBar
          eyebrow="Calendario operativo"
          title="Agenda SaaS multiempresa."
          description={`Sesion: ${user?.email ?? '-'} · Empresa: ${user?.company_name ?? '-'}. Esta vista usa JWT, PostgreSQL y API v1.`}
          onNavigate={onNavigate}
          onLogout={() => { clearSession(); onNavigate('saas-login'); }}
        />
        <Suspense fallback={<CalendarSkeleton />}>
          <SaasCalendar accessToken={accessToken} />
        </Suspense>
      </div>
    </PublicFrame>
  );
}

function SaasTopBar({
  eyebrow,
  title,
  description,
  onNavigate,
  onLogout,
}: {
  eyebrow: string;
  title: string;
  description: string;
  onNavigate: (route: string) => void;
  onLogout: () => void;
}) {
  return (
    <Card className="p-6">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <Badge tone="violet">{eyebrow}</Badge>
          <h1 className="mt-3 text-5xl font-semibold tracking-[-0.06em] text-white">{title}</h1>
          <p className="mt-3 max-w-3xl text-sm leading-6 text-slate-400">{description}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button onClick={() => onNavigate('saas-dashboard')}>Dashboard</Button>
          <Button onClick={() => onNavigate('saas-calendar')}>Calendario</Button>
          <Button onClick={() => onNavigate('saas-customers')}>Clientes</Button>
          <Button onClick={() => onNavigate('saas-login')}>Pruebas API</Button>
          <Button variant="danger" onClick={onLogout}>Cerrar JWT</Button>
        </div>
      </div>
    </Card>
  );
}

function SaasDashboardPage({ onNavigate }: { onNavigate: (route: string) => void }) {
  const { accessToken, user, hydrate, clearSession } = useSaasAuthStore();
  useEffect(() => hydrate(), [hydrate]);
  const dashboard = useQuery({
    queryKey: ['saas-dashboard', accessToken],
    queryFn: () => getSaasDashboard(accessToken),
    enabled: Boolean(accessToken),
    retry: false,
  });

  if (!accessToken) {
    return (
      <PublicFrame onNavigate={onNavigate}>
        <Card className="mx-auto max-w-2xl p-8">
          <Badge tone="rose">Sesion requerida</Badge>
          <h1 className="mt-4 text-4xl font-semibold tracking-[-0.05em] text-white">Entra con JWT para ver el dashboard SaaS.</h1>
          <Button className="mt-6" variant="primary" onClick={() => onNavigate('saas-login')}>Ir a login API v1</Button>
        </Card>
      </PublicFrame>
    );
  }

  const data = dashboard.data?.data;
  const metrics = data?.metrics;
  const metricCards = [
    { label: 'Reservas totales', value: metrics?.total_bookings ?? '0', tone: 'cyan' as const },
    { label: 'Proximas activas', value: metrics?.upcoming_bookings ?? '0', tone: 'emerald' as const },
    { label: 'Clientes activos', value: metrics?.active_customers ?? '0', tone: 'violet' as const },
    { label: 'Ingresos estimados', value: `$${Number(metrics?.estimated_revenue ?? 0).toLocaleString('es-CL')}`, tone: 'amber' as const },
  ];

  return (
    <PublicFrame onNavigate={onNavigate}>
      <div className="mx-auto max-w-7xl space-y-6">
        <Card className="overflow-hidden">
          <div className="grid gap-6 p-6 lg:grid-cols-[1fr_auto] lg:p-8">
            <div>
              <Badge tone="cyan"><Activity size={14} className="mr-2" /> Dashboard SaaS v1</Badge>
              <h1 className="mt-5 text-5xl font-semibold tracking-[-0.06em] text-white">Operacion en tiempo real sobre PostgreSQL.</h1>
              <p className="mt-4 max-w-3xl text-sm leading-6 text-slate-400">Vista inicial protegida por JWT y aislada por empresa. Los datos vienen desde API v1, no desde SQLite legacy.</p>
            </div>
            <div className="rounded-[1.5rem] border border-white/10 bg-black/20 p-4 text-sm text-slate-300">
              <p className="font-semibold text-white">{user?.company_name ?? 'Empresa'}</p>
              <p className="mt-2">{user?.email}</p>
              <p className="mt-1 text-slate-500">{user?.role}</p>
              <Button className="mt-4 w-full" variant="danger" onClick={() => { clearSession(); onNavigate('saas-login'); }}>Cerrar JWT</Button>
            </div>
          </div>
        </Card>

        {dashboard.isLoading ? <CalendarSkeleton /> : null}
        {dashboard.isError ? <Card className="p-6 text-rose-100">No se pudo cargar dashboard API v1: {dashboard.error.message}</Card> : null}
        {data ? (
          <>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              {metricCards.map((metric) => (
                <Card key={metric.label} className="p-5">
                  <Badge tone={metric.tone}>{metric.label}</Badge>
                  <p className="mt-5 text-4xl font-semibold tracking-[-0.05em] text-white">{metric.value}</p>
                </Card>
              ))}
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between gap-4">
                    <div>
                      <p className="text-sm font-semibold text-white">Proximas reservas</p>
                      <p className="mt-1 text-sm text-slate-500">Agenda activa de la empresa autenticada.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Button onClick={() => onNavigate('saas-calendar')}><ArrowUpRight size={16} className="mr-2" /> Calendario</Button>
                      <Button onClick={() => onNavigate('saas-customers')}>Clientes</Button>
                    </div>
                  </div>
                </CardHeader>
                <CardContent className="space-y-3">
                  {data.upcoming_bookings.length === 0 ? <p className="text-sm text-slate-500">Sin reservas proximas.</p> : null}
                  {data.upcoming_bookings.map((booking) => (
                    <div key={booking.id} className="rounded-[1.5rem] border border-white/10 bg-white/[0.04] p-4">
                      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                          <p className="font-semibold text-white">{booking.service_name}</p>
                          <p className="mt-1 text-sm text-slate-400">{booking.customer_first_name} {booking.customer_last_name} con {booking.professional_name}</p>
                        </div>
                        <Badge tone={booking.status === 'confirmed' ? 'emerald' : 'amber'}>{booking.status}</Badge>
                      </div>
                      <p className="mt-3 text-xs text-slate-500">{new Date(booking.starts_at).toLocaleString('es-CL')}</p>
                    </div>
                  ))}
                </CardContent>
              </Card>

              <div className="space-y-6">
                <Card>
                  <CardHeader><p className="text-sm font-semibold text-white">Estados</p></CardHeader>
                  <CardContent className="space-y-3">
                    {data.bookings_by_status.map((item) => (
                      <div key={item.status} className="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-3 text-sm">
                        <span className="text-slate-300">{item.status}</span>
                        <span className="font-semibold text-white">{item.total}</span>
                      </div>
                    ))}
                  </CardContent>
                </Card>
                <Card>
                  <CardHeader><p className="text-sm font-semibold text-white">Carga staff</p></CardHeader>
                  <CardContent className="space-y-3">
                    {data.staff_load.map((staff) => (
                      <div key={staff.id} className="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-3 text-sm">
                        <span className="flex items-center gap-2 text-slate-300"><span className="h-2.5 w-2.5 rounded-full" style={{ background: staff.calendar_color }} />{staff.name}</span>
                        <span className="font-semibold text-white">{staff.upcoming_total}</span>
                      </div>
                    ))}
                  </CardContent>
                </Card>
              </div>
            </div>
          </>
        ) : null}
      </div>
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
            <label className="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/[0.05] p-3 text-sm text-slate-300">
              <input name="remember_me" value="1" type="checkbox" className="mt-1 h-4 w-4 rounded border-white/20 bg-zinc-950 accent-cyan-300" />
              <span><span className="font-semibold text-white">Recordarme por 30 dias</span><span className="mt-1 block text-xs leading-5 text-slate-500">Usalo solo en equipos personales. En equipos compartidos deja esta opcion desactivada.</span></span>
            </label>
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
    <AppShell
      activeSection={activeSection}
      onSectionChange={setActiveSection}
      onNavigateHome={() => onNavigate('home')}
      navItems={navItems}
      commandItems={[
        { id: 'customer-new-booking', label: 'Crear nueva reserva', description: 'Cliente', icon: CalendarDays, keywords: ['reservar', 'hora', 'agenda'], action: () => setActiveSection('reservar') },
        { id: 'customer-open-agenda', label: 'Abrir mi agenda', description: 'Cliente', icon: Clock3, keywords: ['calendario', 'reservas'], action: () => setActiveSection('agenda') },
        { id: 'customer-open-profile', label: 'Ver mi perfil', description: 'Cliente', icon: UserRound, keywords: ['cuenta', 'datos'], action: () => setActiveSection('perfil') },
        { id: 'customer-logout', label: 'Cerrar sesion', description: 'Sesion', icon: LogOut, keywords: ['salir', 'logout'], action: onLogout },
      ]}
    >
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
    <AppShell
      activeSection={activeSection}
      onSectionChange={setActiveSection}
      onNavigateHome={() => onNavigate('home')}
      commandItems={[
        { id: 'shell-calendar', label: 'Abrir calendario', description: title, icon: CalendarDays, keywords: ['agenda', 'reservas'], action: () => setActiveSection('calendario') },
        { id: 'shell-people', label: title === 'Admin' ? 'Abrir clientes y staff' : 'Abrir clientes', description: title, icon: UserRound, keywords: ['clientes', 'personas', 'staff'], action: () => setActiveSection('clientes') },
        { id: 'shell-stats', label: 'Abrir estadisticas', description: title, icon: Sparkles, keywords: ['metricas', 'analitica', 'kpi'], action: () => setActiveSection('estadisticas') },
        { id: 'shell-config', label: 'Abrir configuracion', description: title, icon: Settings, keywords: ['ajustes', 'branding', 'servicios'], action: () => setActiveSection('configuracion') },
        { id: 'shell-logout', label: 'Cerrar sesion', description: 'Sesion', icon: LogOut, keywords: ['salir', 'logout'], action: onLogout },
      ]}
    >
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
  const queryClient = useQueryClient();
  const [message, setMessage] = useState('');
  const [customerId, setCustomerId] = useState('');
  const [disciplineId, setDisciplineId] = useState('');
  const [serviceId, setServiceId] = useState('');
  const query = useQuery({ queryKey: ['admin-management'], queryFn: getAdminManagement, enabled: title === 'Admin', retry: false });
  const save = useMutation({
    mutationFn: (form: FormData) => saveAdminManagement(form, query.data?.csrfToken ?? ''),
    onSuccess: async () => {
      setMessage('Cambios guardados correctamente.');
      await queryClient.invalidateQueries({ queryKey: ['admin-management'] });
      await queryClient.invalidateQueries({ queryKey: ['admin-dashboard'] });
    },
  });

  if (title !== 'Admin') {
    return (
      <Card className="p-6">
        <div className="flex items-start gap-4">
          <div className="grid h-12 w-12 place-items-center rounded-2xl border border-white/10 bg-white/[0.07] text-cyan-200"><Settings size={20} /></div>
          <div>
            <h2 className="text-2xl font-semibold text-white">Preferencias {title.toLowerCase()}</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">La configuracion operativa completa esta restringida a administracion. Esta vista queda reservada para preferencias del rol.</p>
            <Button className="mt-5" onClick={onNavigateHome}>Volver al inicio</Button>
          </div>
        </div>
      </Card>
    );
  }

  if (query.isLoading || !query.data) return <CalendarSkeleton />;
  const data = query.data;
  const selectedCustomer = data.customers.find((customer) => String(customer.id) === customerId);
  const selectedDiscipline = data.disciplines.find((discipline) => String(discipline.id) === disciplineId);
  const selectedService = data.services.find((service) => String(service.id) === serviceId);

  return (
    <div className="space-y-6">
      <Card className="overflow-hidden p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <Badge tone="cyan"><Settings size={14} className="mr-2" /> Configuracion SaaS</Badge>
            <h2 className="mt-4 text-3xl font-semibold tracking-tight text-white">Centro de control migrado a React</h2>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Branding, reglas operativas, clientes, disciplinas y servicios ya se administran con API JSON y CSRF. Los formularios conservan validaciones del backend PHP.</p>
          </div>
          <div className="grid grid-cols-3 gap-2 text-center">
            <MiniStat label="Clientes" value={data.customers.length} />
            <MiniStat label="Servicios" value={data.services.length} />
            <MiniStat label="Staff" value={data.professionals.length} />
          </div>
        </div>
        {message ? <p className="mt-5 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 p-3 text-sm text-emerald-100">{message}</p> : null}
        {save.error ? <p className="mt-5 rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{save.error.message}</p> : null}
      </Card>

      <div className="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_minmax(24rem,.7fr)]">
        <div className="space-y-6">
          <Card>
            <CardHeader>
              <p className="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200/70">Branding y reglas</p>
              <h3 className="mt-2 text-2xl font-semibold text-white">Identidad configurable</h3>
            </CardHeader>
            <CardContent>
              <form className="grid gap-4 lg:grid-cols-2" onSubmit={(event) => submitForm(event, save.mutate, 'settings')}>
                <Input name="business_name" defaultValue={data.settings.business_name} placeholder="Nombre comercial" />
                <Input name="app_name" defaultValue={data.settings.app_name} placeholder="Nombre app" />
                <Input name="business_tagline" defaultValue={data.settings.business_tagline} placeholder="Tagline" className="lg:col-span-2" />
                <Input name="hero_title" defaultValue={data.settings.hero_title} placeholder="Titulo principal" className="lg:col-span-2" />
                <Textarea name="hero_subtitle" defaultValue={data.settings.hero_subtitle} placeholder="Subtitulo principal" className="lg:col-span-2" />
                <Input name="contact_email" defaultValue={data.settings.contact_email} placeholder="contacto@dominio.cl" />
                <Input name="contact_phone" defaultValue={data.settings.contact_phone} placeholder="+56900000000" />
                <Input name="business_type" defaultValue={data.settings.business_type} placeholder="Tipo de negocio" />
                <Input name="app_timezone" defaultValue={data.settings.app_timezone || 'America/Santiago'} placeholder="Timezone" />
                <Input name="opening_time" type="time" defaultValue={data.settings.opening_time || '09:00'} />
                <Input name="closing_time" type="time" defaultValue={data.settings.closing_time || '18:00'} />
                <Input name="slot_interval" type="number" min={5} step={5} defaultValue={data.settings.slot_interval || '30'} placeholder="Intervalo" />
                <Input name="booking_notice" type="number" min={0} defaultValue={data.settings.booking_notice || '0'} placeholder="Aviso minimo" />
                <Input name="global_buffer_min" type="number" min={0} defaultValue={data.settings.global_buffer_min || '0'} placeholder="Buffer global" />
                <Input name="reminder_hours_before" type="number" min={0} defaultValue={data.settings.reminder_hours_before || '24'} placeholder="Recordatorio horas" />
                <Select name="notifications_email_enabled" defaultValue={data.settings.notifications_email_enabled || '0'}><option value="0">Email inactivo</option><option value="1">Email activo</option></Select>
                <Select name="notifications_whatsapp_enabled" defaultValue={data.settings.notifications_whatsapp_enabled || '0'}><option value="0">WhatsApp inactivo por defecto</option><option value="1">WhatsApp activo manual</option></Select>
                <Button className="lg:col-span-2" variant="primary" disabled={save.isPending}>Guardar configuracion</Button>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <p className="text-xs font-semibold uppercase tracking-[0.28em] text-violet-200/70">Clientes</p>
              <h3 className="mt-2 text-2xl font-semibold text-white">Crear o editar cliente</h3>
            </CardHeader>
            <CardContent>
              <Select value={customerId} onChange={(event) => setCustomerId(event.target.value)} aria-label="Seleccionar cliente">
                <option value="">Nuevo cliente</option>
                {data.customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.firstName} {customer.lastName}</option>)}
              </Select>
              <form key={customerId || 'new-customer'} className="mt-4 grid gap-4 lg:grid-cols-2" onSubmit={(event) => submitForm(event, save.mutate, 'client')}>
                <input type="hidden" name="id_cliente" value={selectedCustomer?.id ?? ''} />
                <Input name="nombre_cliente" defaultValue={selectedCustomer?.firstName ?? ''} placeholder="Nombre" required />
                <Input name="apellido_cliente" defaultValue={selectedCustomer?.lastName ?? ''} placeholder="Apellido" required />
                <Input name="telefono_cliente" defaultValue={selectedCustomer?.phone ?? '+56900000000'} placeholder="+56900000000" required />
                <Input name="correo_cliente" type="email" defaultValue={selectedCustomer?.email ?? ''} placeholder="correo@dominio.cl" required />
                <Input name="user_cliente" defaultValue={selectedCustomer?.username ?? ''} placeholder="Usuario" />
                {!selectedCustomer ? <Input name="pass_cliente" type="password" placeholder="Clave temporal opcional" /> : null}
                <Textarea name="notas_cliente" defaultValue={selectedCustomer?.notes ?? ''} placeholder="Notas internas" className="lg:col-span-2" />
                <Button className="lg:col-span-2" variant="primary" disabled={save.isPending}>{selectedCustomer ? 'Actualizar cliente' : 'Crear cliente'}</Button>
              </form>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <p className="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-200/70">Disciplinas</p>
              <h3 className="mt-2 text-2xl font-semibold text-white">Catalogo base</h3>
            </CardHeader>
            <CardContent>
              <Select value={disciplineId} onChange={(event) => setDisciplineId(event.target.value)} aria-label="Seleccionar disciplina">
                <option value="">Nueva disciplina</option>
                {data.disciplines.map((discipline) => <option key={discipline.id} value={discipline.id}>{discipline.name}</option>)}
              </Select>
              <form key={disciplineId || 'new-discipline'} className="mt-4 space-y-4" onSubmit={(event) => submitForm(event, save.mutate, 'discipline')}>
                <input type="hidden" name="id_disciplina" value={selectedDiscipline?.id ?? ''} />
                <Input name="nombre_disciplina" defaultValue={selectedDiscipline?.name ?? ''} placeholder="Nombre disciplina" required />
                <Textarea name="descripcion_disciplina" defaultValue={selectedDiscipline?.description ?? ''} placeholder="Descripcion" />
                <div className="grid gap-3 sm:grid-cols-2">
                  <Input name="color_disciplina" type="color" defaultValue={selectedDiscipline?.color ?? '#22d3ee'} />
                  <Select name="activa" defaultValue={String(selectedDiscipline?.active ?? 1)}><option value="1">Activa</option><option value="0">Inactiva</option></Select>
                </div>
                <Button className="w-full" variant="primary" disabled={save.isPending}>{selectedDiscipline ? 'Actualizar disciplina' : 'Crear disciplina'}</Button>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <p className="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200/70">Servicios</p>
              <h3 className="mt-2 text-2xl font-semibold text-white">Oferta vendible</h3>
            </CardHeader>
            <CardContent>
              <Select value={serviceId} onChange={(event) => setServiceId(event.target.value)} aria-label="Seleccionar servicio">
                <option value="">Nuevo servicio</option>
                {data.services.map((service) => <option key={service.id} value={service.id}>{service.name}</option>)}
              </Select>
              <form key={serviceId || 'new-service'} className="mt-4 space-y-4" onSubmit={(event) => submitForm(event, save.mutate, 'service')}>
                <input type="hidden" name="id_servicio" value={selectedService?.id ?? ''} />
                <Select name="id_disciplina" defaultValue={String(selectedService?.disciplineId ?? '')}>
                  <option value="">Sin disciplina</option>
                  {data.disciplines.filter((discipline) => Number(discipline.active) === 1).map((discipline) => <option key={discipline.id} value={discipline.id}>{discipline.name}</option>)}
                </Select>
                <Input name="nombre_servicio" defaultValue={selectedService?.name ?? ''} placeholder="Nombre servicio" required />
                <Textarea name="descripcion_servicio" defaultValue={selectedService?.description ?? ''} placeholder="Descripcion" />
                <div className="grid gap-3 sm:grid-cols-2">
                  <Input name="precio_servicio" type="number" min={0} defaultValue={String(selectedService?.price ?? 0)} placeholder="Precio" />
                  <Input name="duracion_minutos" type="number" min={15} step={5} defaultValue={String(selectedService?.durationMinutes ?? 60)} placeholder="Duracion" />
                  <Input name="modalidad_servicio" defaultValue={selectedService?.modality ?? 'Presencial'} placeholder="Modalidad" />
                  <Input name="color" type="color" defaultValue={selectedService?.color ?? '#22d3ee'} />
                  <Select name="activo" defaultValue={String(selectedService?.active ?? 1)}><option value="1">Activo</option><option value="0">Inactivo</option></Select>
                </div>
                <Button className="w-full" variant="primary" disabled={save.isPending}>{selectedService ? 'Actualizar servicio' : 'Crear servicio'}</Button>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}

function ListRow({ title, subtitle }: { title: string; subtitle: string }) {
  return <div className="rounded-[1.35rem] border border-white/10 bg-white/[0.05] p-4"><p className="font-semibold text-white">{title}</p><p className="mt-1 text-sm text-slate-400">{subtitle}</p></div>;
}

function MiniStat({ label, value }: { label: string; value: number }) {
  return <div className="min-w-20 rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3"><p className="text-2xl font-semibold text-white">{value}</p><p className="text-xs text-slate-400">{label}</p></div>;
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
