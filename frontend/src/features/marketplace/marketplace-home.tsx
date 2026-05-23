import { useMutation, useQuery } from '@tanstack/react-query';
import { Building2, CheckCircle2, MapPin, Search, Sparkles, UserRound } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Badge } from '../../components/ui/badge';
import { Button } from '../../components/ui/button';
import { Card, CardContent, CardHeader } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import { useSaasAuthStore } from '../auth/saas-auth-store';
import { createMarketplaceBooking, enrollMarketplaceCompany, getMarketplaceCompany, listMarketplaceCompanies, type MarketplaceCompany } from '../../services/api-v1-client';

type MarketplaceHomeProps = {
  onNavigate: (route: string) => void;
};

export function MarketplaceHome({ onNavigate }: MarketplaceHomeProps) {
  const { accessToken, user, hydrate } = useSaasAuthStore();
  const [search, setSearch] = useState('');
  const [selectedSlug, setSelectedSlug] = useState('demo');
  const [selectedDisciplineId, setSelectedDisciplineId] = useState('');
  const [selectedServiceId, setSelectedServiceId] = useState('');
  const [selectedProfessionalId, setSelectedProfessionalId] = useState('');
  const [startsAt, setStartsAt] = useState('');
  const companiesQuery = useQuery({ queryKey: ['marketplace-companies'], queryFn: listMarketplaceCompanies });
  const detailQuery = useQuery({
    queryKey: ['marketplace-company', selectedSlug],
    queryFn: () => getMarketplaceCompany(selectedSlug),
    enabled: Boolean(selectedSlug),
  });
  const enroll = useMutation({ mutationFn: (slug: string) => enrollMarketplaceCompany(accessToken, slug) });
  const booking = useMutation({
    mutationFn: () => createMarketplaceBooking(accessToken, {
      company_slug: selectedSlug,
      service_id: selectedServiceId,
      professional_id: selectedProfessionalId,
      starts_at: startsAt,
      notes: 'Reserva creada desde marketplace.',
    }),
  });
  useEffect(() => hydrate(), [hydrate]);
  const companies = companiesQuery.data?.data ?? [];
  const filteredCompanies = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return companies;
    return companies.filter((company) => [
      company.display_name,
      company.name,
      company.tagline,
      company.description,
      company.city,
    ].join(' ').toLowerCase().includes(term));
  }, [companies, search]);
  const selectedCompany = detailQuery.data?.data;
  const services = selectedCompany?.services ?? [];
  const disciplines = selectedCompany?.disciplines ?? [];
  const filteredServices = selectedDisciplineId ? services.filter((service) => service.discipline_id === selectedDisciplineId) : services;
  const eligibleProfessionals = (selectedCompany?.professionals ?? []).filter((professional) =>
    !selectedServiceId || professional.services.some((service) => ((service as { service_id?: string; id?: string }).service_id ?? (service as { id?: string }).id) === selectedServiceId),
  );
  const canBook = Boolean(accessToken && user?.role === 'customer' && selectedServiceId && selectedProfessionalId && startsAt);

  useEffect(() => {
    setSelectedDisciplineId('');
    setSelectedServiceId('');
    setSelectedProfessionalId('');
    setStartsAt('');
  }, [selectedSlug]);

  useEffect(() => {
    setSelectedServiceId('');
    setSelectedProfessionalId('');
  }, [selectedDisciplineId]);

  useEffect(() => {
    setSelectedProfessionalId('');
  }, [selectedServiceId]);

  return (
    <div className="space-y-6">
      <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_25rem]">
        <Card className="overflow-hidden p-7 sm:p-10">
          <Badge tone="cyan"><Sparkles size={14} className="mr-2" /> Marketplace de reservas</Badge>
          <h1 className="mt-5 max-w-5xl text-balance text-5xl font-semibold tracking-[-0.07em] text-white sm:text-7xl">
            Busca empresas, inscríbete y reserva desde una sola cuenta.
          </h1>
          <p className="mt-6 max-w-3xl text-lg leading-8 text-slate-400">
            El cliente tiene una cuenta global. Cada empresa mantiene su perfil, servicios, profesionales y disponibilidad independiente.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Button variant="primary" onClick={() => onNavigate('customer-register')}>Crear cuenta cliente</Button>
            <Button onClick={() => onNavigate('customer-login')}>Entrar como cliente</Button>
            <Button onClick={() => onNavigate('company-admin-login')}>Acceso empresa</Button>
          </div>
        </Card>
        <Card className="p-6">
          <p className="text-sm text-slate-400">Flujo marketplace</p>
          <div className="mt-5 space-y-3 text-sm text-slate-300">
            <FlowStep text="1. Cliente crea cuenta global" />
            <FlowStep text="2. Busca una empresa" />
            <FlowStep text="3. Revisa disciplinas, servicios y profesionales" />
            <FlowStep text="4. Se inscribe en la empresa" />
            <FlowStep text="5. Reserva en horarios disponibles" />
          </div>
        </Card>
      </section>

      <Card>
        <CardHeader className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <Badge tone="violet"><Building2 size={14} className="mr-2" /> Empresas</Badge>
            <h2 className="mt-3 text-3xl font-semibold tracking-[-0.05em] text-white">Directorio público</h2>
            <p className="mt-2 text-sm leading-6 text-slate-400">Cada empresa controla su perfil, branding, catálogo, profesionales y disponibilidad.</p>
          </div>
          <label className="relative min-w-0 lg:w-96">
            <Search className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" size={17} />
            <Input className="pl-11" placeholder="Buscar por empresa, ciudad o servicio" value={search} onChange={(event) => setSearch(event.target.value)} />
          </label>
        </CardHeader>
        <CardContent>
          {companiesQuery.isLoading ? <p className="text-sm text-slate-400">Cargando empresas...</p> : null}
          {companiesQuery.isError ? <p className="rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{companiesQuery.error.message}</p> : null}
          <div className="grid gap-4 lg:grid-cols-[.9fr_1.1fr]">
            <div className="grid gap-3">
              {filteredCompanies.map((company) => (
                <CompanyCard key={company.id} company={company} active={company.slug === selectedSlug} onSelect={() => setSelectedSlug(company.slug)} />
              ))}
            </div>

            <Card className="min-h-[32rem] bg-black/20">
              {detailQuery.isLoading ? <CardContent><p className="text-sm text-slate-400">Cargando perfil...</p></CardContent> : null}
              {selectedCompany ? (
                <>
                  <div className="h-40 border-b border-white/10" style={{ background: `linear-gradient(135deg, ${selectedCompany.primary_color}88, ${selectedCompany.accent_color}55)` }} />
                  <CardContent>
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                      <div>
                        <Badge tone="cyan">{selectedCompany.slug}</Badge>
                        <h3 className="mt-3 text-3xl font-semibold text-white">{selectedCompany.display_name || selectedCompany.name}</h3>
                        <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-400">{selectedCompany.description || selectedCompany.tagline}</p>
                      </div>
                      <Button
                        variant="primary"
                        disabled={!accessToken || user?.role !== 'customer' || enroll.isPending}
                        onClick={() => enroll.mutate(selectedCompany.slug)}
                      >
                        {enroll.isPending ? 'Inscribiendo...' : 'Inscribirme'}
                      </Button>
                    </div>
                    {!accessToken || user?.role !== 'customer' ? <p className="mt-3 text-xs text-amber-200">Debes entrar como cliente para inscribirte y reservar.</p> : null}
                    {enroll.data ? <p className="mt-3 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 p-3 text-sm text-emerald-100">Inscripción activa en {selectedCompany.display_name || selectedCompany.name}.</p> : null}
                    {enroll.error ? <p className="mt-3 rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{enroll.error.message}</p> : null}

                    <div className="mt-6 grid gap-4 xl:grid-cols-3">
                      <CatalogBlock title="Disciplinas" items={selectedCompany.disciplines.map((item) => ({ id: item.id, title: item.name, subtitle: item.description }))} />
                      <CatalogBlock title="Servicios" items={selectedCompany.services.map((item) => ({ id: item.id, title: item.name, subtitle: `$${Number(item.price).toLocaleString('es-CL')} · ${item.duration_minutes} min` }))} />
                      <CatalogBlock title="Profesionales" items={selectedCompany.professionals.map((item) => ({ id: item.id, title: item.name, subtitle: `${item.services.length} servicios` }))} />
                    </div>
                    <BookingPanel
                      accessToken={accessToken}
                      booking={booking}
                      canBook={canBook}
                      disciplines={disciplines}
                      eligibleProfessionals={eligibleProfessionals}
                      filteredServices={filteredServices}
                      onNavigate={onNavigate}
                      selectedDisciplineId={selectedDisciplineId}
                      selectedProfessionalId={selectedProfessionalId}
                      selectedServiceId={selectedServiceId}
                      setSelectedDisciplineId={setSelectedDisciplineId}
                      setSelectedProfessionalId={setSelectedProfessionalId}
                      setSelectedServiceId={setSelectedServiceId}
                      setStartsAt={setStartsAt}
                      startsAt={startsAt}
                      userRole={user?.role}
                    />
                  </CardContent>
                </>
              ) : null}
            </Card>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function FlowStep({ text }: { text: string }) {
  return <div className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] p-3"><CheckCircle2 size={16} className="text-emerald-200" />{text}</div>;
}

function CompanyCard({ company, active, onSelect }: { company: MarketplaceCompany; active: boolean; onSelect: () => void }) {
  return (
    <button className={`rounded-[1.5rem] border p-4 text-left transition ${active ? 'border-cyan-300/40 bg-cyan-400/10' : 'border-white/10 bg-white/[0.045] hover:bg-white/[0.07]'}`} onClick={onSelect}>
      <div className="flex items-start justify-between gap-4">
        <div>
          <h3 className="font-semibold text-white">{company.display_name || company.name}</h3>
          <p className="mt-2 line-clamp-2 text-sm leading-6 text-slate-400">{company.tagline || company.description}</p>
        </div>
        <span className="h-3 w-3 rounded-full" style={{ background: company.primary_color }} />
      </div>
      <p className="mt-3 flex items-center gap-2 text-xs text-slate-500"><MapPin size={13} /> {company.city || 'Sin ciudad'}</p>
    </button>
  );
}

function BookingPanel({
  accessToken,
  booking,
  canBook,
  disciplines,
  eligibleProfessionals,
  filteredServices,
  onNavigate,
  selectedDisciplineId,
  selectedProfessionalId,
  selectedServiceId,
  setSelectedDisciplineId,
  setSelectedProfessionalId,
  setSelectedServiceId,
  setStartsAt,
  startsAt,
  userRole,
}: {
  accessToken: string;
  booking: {
    data?: unknown;
    error: unknown;
    isPending: boolean;
    mutate: (value?: void) => void;
  };
  canBook: boolean;
  disciplines: Array<{ id: string; name: string }>;
  eligibleProfessionals: Array<{ id: string; name: string }>;
  filteredServices: Array<{ id: string; name: string; price: string; duration_minutes: number }>;
  onNavigate: (route: string) => void;
  selectedDisciplineId: string;
  selectedProfessionalId: string;
  selectedServiceId: string;
  setSelectedDisciplineId: (value: string) => void;
  setSelectedProfessionalId: (value: string) => void;
  setSelectedServiceId: (value: string) => void;
  setStartsAt: (value: string) => void;
  startsAt: string;
  userRole?: string;
}) {
  return (
    <div className="mt-6 rounded-[1.5rem] border border-white/10 bg-white/[0.045] p-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p className="text-sm font-semibold text-white">Reservar en esta empresa</p>
          <p className="mt-1 text-xs text-slate-500">Primero inscríbete. Luego elige servicio, profesional y horario.</p>
        </div>
        <Badge tone={accessToken && userRole === 'customer' ? 'emerald' : 'amber'}>{accessToken && userRole === 'customer' ? 'Cliente conectado' : 'Login cliente requerido'}</Badge>
      </div>
      <div className="mt-4 grid gap-3 lg:grid-cols-2">
        <select className="rounded-2xl border border-white/10 bg-zinc-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-300/50" value={selectedDisciplineId} onChange={(event) => setSelectedDisciplineId(event.target.value)}>
          <option value="">Todas las disciplinas</option>
          {disciplines.map((discipline) => <option key={discipline.id} value={discipline.id}>{discipline.name}</option>)}
        </select>
        <select className="rounded-2xl border border-white/10 bg-zinc-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-300/50" value={selectedServiceId} onChange={(event) => setSelectedServiceId(event.target.value)}>
          <option value="">Seleccionar servicio</option>
          {filteredServices.map((service) => <option key={service.id} value={service.id}>{service.name} · ${Number(service.price).toLocaleString('es-CL')} · {service.duration_minutes} min</option>)}
        </select>
        <select className="rounded-2xl border border-white/10 bg-zinc-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-300/50" value={selectedProfessionalId} onChange={(event) => setSelectedProfessionalId(event.target.value)} disabled={!selectedServiceId}>
          <option value="">Seleccionar profesional</option>
          {eligibleProfessionals.map((professional) => <option key={professional.id} value={professional.id}>{professional.name}</option>)}
        </select>
        <Input type="datetime-local" value={startsAt} onChange={(event) => setStartsAt(event.target.value)} />
      </div>
      <div className="mt-4 flex flex-wrap gap-3">
        <Button variant="primary" disabled={!canBook || booking.isPending} onClick={() => booking.mutate(undefined)}>
          {booking.isPending ? 'Reservando...' : 'Reservar horario'}
        </Button>
        {!accessToken || userRole !== 'customer' ? <Button onClick={() => onNavigate('customer-login')}>Entrar como cliente</Button> : null}
      </div>
      {booking.data ? <p className="mt-3 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 p-3 text-sm text-emerald-100">Reserva creada en estado pendiente. Puedes verla en tu agenda personal.</p> : null}
      {booking.error ? <p className="mt-3 rounded-2xl border border-rose-300/20 bg-rose-400/10 p-3 text-sm text-rose-100">{(booking.error as Error).message}</p> : null}
    </div>
  );
}

function CatalogBlock({ title, items }: { title: string; items: Array<{ id: string; title: string; subtitle: string }> }) {
  return (
    <div className="rounded-[1.5rem] border border-white/10 bg-white/[0.04] p-4">
      <p className="text-sm font-semibold text-white">{title}</p>
      <div className="mt-3 space-y-2">
        {items.length === 0 ? <p className="text-sm text-slate-500">Sin datos publicados.</p> : items.slice(0, 4).map((item) => (
          <div key={item.id} className="rounded-2xl bg-black/20 p-3">
            <p className="text-sm font-semibold text-white">{item.title}</p>
            <p className="mt-1 line-clamp-2 text-xs text-slate-400">{item.subtitle}</p>
          </div>
        ))}
      </div>
    </div>
  );
}
