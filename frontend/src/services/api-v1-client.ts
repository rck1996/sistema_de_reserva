const API_BASE_URL = import.meta.env.VITE_API_URL || '/php-api/api/v1';

export async function apiV1<T>(path: string, options: RequestInit = {}, accessToken = ''): Promise<T> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(accessToken ? { Authorization: `Bearer ${accessToken}` } : {}),
      ...(options.headers ?? {}),
    },
  });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};

  if (!response.ok || payload.ok === false) {
    throw new Error(payload.error || `API v1 error ${response.status}`);
  }

  return payload as T;
}

export type SaasUser = {
  id: string;
  company_id: string;
  company_slug: string;
  company_name: string;
  email: string;
  username: string;
  role: 'super_admin' | 'admin_empresa' | 'staff' | 'customer';
};

export type SaasAuthResponse = {
  ok: true;
  access_token: string;
  refresh_token: string;
  token_type: 'Bearer';
  expires_in: number;
  user: SaasUser;
};

export type SaasMeResponse = {
  ok: true;
  claims: {
    user_id: string;
    company_id: string;
    role: string;
    iat: number;
    exp: number;
  };
  user: SaasUser;
};

export function loginSaas(input: { companySlug: string; email: string; password: string }) {
  return apiV1<SaasAuthResponse>('/auth/login.php', {
    method: 'POST',
    body: JSON.stringify({
      company_slug: input.companySlug,
      email: input.email,
      password: input.password,
    }),
  });
}

export function registerSaasCustomer(input: {
  firstName: string;
  lastName: string;
  email: string;
  password: string;
  phone?: string;
  notes?: string;
}) {
  return apiV1<SaasAuthResponse>('/auth/customer-register.php', {
    method: 'POST',
    body: JSON.stringify({
      first_name: input.firstName,
      last_name: input.lastName,
      email: input.email,
      password: input.password,
      phone: input.phone ?? '',
      notes: input.notes ?? '',
    }),
  });
}

export function loginSaasCustomer(input: { email: string; password: string }) {
  return apiV1<SaasAuthResponse>('/auth/customer-login.php', {
    method: 'POST',
    body: JSON.stringify(input),
  });
}

export function refreshSaas(refreshToken: string) {
  return apiV1<SaasAuthResponse>('/auth/refresh.php', {
    method: 'POST',
    body: JSON.stringify({ refresh_token: refreshToken }),
  });
}

export function logoutSaas(refreshToken: string) {
  return apiV1<{ ok: true }>('/auth/logout.php', {
    method: 'POST',
    body: JSON.stringify({ refresh_token: refreshToken }),
  });
}

export function getSaasMe(accessToken: string) {
  return apiV1<SaasMeResponse>('/auth/me.php', {}, accessToken);
}

export type SaasService = {
  id: string;
  company_id: string;
  discipline_id?: string | null;
  name: string;
  description: string;
  price: string;
  duration_minutes: number;
  modality: string;
  color: string;
  is_active: boolean;
  discipline_name?: string | null;
};

export type SaasDiscipline = {
  id: string;
  company_id: string;
  name: string;
  description: string;
  color: string;
  is_active: boolean;
};

export type SaasProfessional = {
  id: string;
  company_id: string;
  name: string;
  email: string;
  phone: string;
  bio: string;
  calendar_color: string;
  booking_capacity: number;
  accepts_waitlist: boolean;
  is_active: boolean;
  services: Array<{ id: string; name: string; discipline_id?: string | null }>;
};

export type SaasCustomer = {
  id: string;
  company_id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  notes: string;
  is_active: boolean;
};

export type SaasCustomerProfile = SaasCustomer & {
  user_id: string;
  avatar_url: string;
  created_at: string;
};

export type MarketplaceCompany = {
  id: string;
  slug: string;
  name: string;
  display_name: string;
  tagline: string;
  description: string;
  logo_url: string;
  cover_url?: string;
  primary_color: string;
  accent_color: string;
  city: string;
  contact_email: string;
  contact_phone: string;
};

export type MarketplaceCompanyDetail = MarketplaceCompany & {
  address: string;
  website_url: string;
  disciplines: SaasDiscipline[];
  services: SaasService[];
  professionals: Array<SaasProfessional & { services: Array<{ service_id: string; service_name: string; base_price: string; custom_price?: string | null; effective_price: string }> }>;
};

export type SaasBooking = {
  id: string;
  company_id: string;
  company_name?: string;
  company_slug?: string;
  customer_id: string;
  customer_profile_id?: string;
  professional_id: string;
  service_id: string;
  starts_at: string;
  ends_at: string;
  status: 'pending' | 'confirmed' | 'in_progress' | 'completed' | 'no_show' | 'cancelled';
  notes: string;
  customer_first_name: string;
  customer_last_name: string;
  customer_email: string;
  professional_name: string;
  professional_color: string;
  service_name: string;
  service_price: string;
  service_duration_minutes: number;
};

export type SaasDashboard = {
  metrics: {
    total_bookings: string;
    today_bookings: string;
    upcoming_bookings: string;
    cancelled_bookings: string;
    active_customers: string;
    active_professionals: string;
    active_services: string;
    estimated_revenue: string;
  };
  bookings_by_status: Array<{ status: string; total: string }>;
  upcoming_bookings: Array<{
    id: string;
    starts_at: string;
    ends_at: string;
    status: string;
    customer_first_name: string;
    customer_last_name: string;
    professional_name: string;
    professional_color: string;
    service_name: string;
  }>;
  top_services: Array<{ id: string; name: string; color: string; total: string }>;
  staff_load: Array<{ id: string; name: string; calendar_color: string; upcoming_total: string }>;
};

export function listSaasServices(accessToken: string) {
  return apiV1<{ ok: true; data: SaasService[] }>('/services.php', {}, accessToken);
}

export function listSaasDisciplines(accessToken: string) {
  return apiV1<{ ok: true; data: SaasDiscipline[] }>('/disciplines.php', {}, accessToken);
}

export function listSaasProfessionals(accessToken: string) {
  return apiV1<{ ok: true; data: SaasProfessional[] }>('/professionals.php', {}, accessToken);
}

export function listSaasCustomers(accessToken: string) {
  return apiV1<{ ok: true; data: SaasCustomer[] }>('/customers.php', {}, accessToken);
}

export function createSaasCustomer(accessToken: string, input: {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  notes?: string;
}) {
  return apiV1<{ ok: true; data: SaasCustomer }>('/customers.php', {
    method: 'POST',
    body: JSON.stringify({
      ...input,
      is_active: true,
    }),
  }, accessToken);
}

export function updateSaasCustomer(accessToken: string, input: {
  id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  notes?: string;
  is_active: boolean;
}) {
  return apiV1<{ ok: true; data: SaasCustomer }>('/customers.php', {
    method: 'PATCH',
    body: JSON.stringify(input),
  }, accessToken);
}

export function listSaasBookings(accessToken: string) {
  return apiV1<{ ok: true; data: SaasBooking[] }>('/bookings.php', {}, accessToken);
}

export function updateSaasBookingStatus(accessToken: string, input: { id: string; status: SaasBooking['status'] }) {
  return apiV1<{ ok: true; data: SaasBooking }>('/bookings.php', {
    method: 'PATCH',
    body: JSON.stringify(input),
  }, accessToken);
}

export function rescheduleSaasBooking(accessToken: string, input: { id: string; starts_at: string }) {
  return apiV1<{ ok: true; data: SaasBooking }>('/bookings.php', {
    method: 'PATCH',
    body: JSON.stringify(input),
  }, accessToken);
}

export function getSaasDashboard(accessToken: string) {
  return apiV1<{ ok: true; data: SaasDashboard }>('/dashboard.php', {}, accessToken);
}

export function getSaasCustomerMe(accessToken: string) {
  return apiV1<{ ok: true; data: SaasCustomerProfile }>('/customer/me.php', {}, accessToken);
}

export function listSaasCustomerBookings(accessToken: string) {
  return apiV1<{ ok: true; data: SaasBooking[] }>('/customer/bookings.php', {}, accessToken);
}

export function listMarketplaceCompanies() {
  return apiV1<{ ok: true; data: MarketplaceCompany[] }>('/marketplace/companies.php');
}

export function getMarketplaceCompany(slug: string) {
  return apiV1<{ ok: true; data: MarketplaceCompanyDetail }>(`/marketplace/companies.php?slug=${encodeURIComponent(slug)}`);
}

export function enrollMarketplaceCompany(accessToken: string, companySlug: string) {
  return apiV1<{ ok: true; data: { company: MarketplaceCompanyDetail } }>('/marketplace/enroll.php', {
    method: 'POST',
    body: JSON.stringify({ company_slug: companySlug }),
  }, accessToken);
}

export async function createSaasDemoBooking(accessToken: string) {
  const [customers, professionals] = await Promise.all([
    listSaasCustomers(accessToken),
    listSaasProfessionals(accessToken),
  ]);
  const professional = professionals.data.find((item) => item.services.length > 0);
  const customer = customers.data[0];

  if (!customer || !professional || professional.services.length === 0) {
    throw new Error('Faltan clientes, profesionales o servicios demo para crear reserva.');
  }

  const startsAt = new Date();
  startsAt.setDate(startsAt.getDate() + 7);
  startsAt.setHours(15, 30, 0, 0);

  return apiV1<{ ok: true; data: SaasBooking }>('/bookings.php', {
    method: 'POST',
    body: JSON.stringify({
      customer_id: customer.id,
      professional_id: professional.id,
      service_id: professional.services[0].id,
      starts_at: startsAt.toISOString(),
      status: 'confirmed',
      notes: 'Reserva creada desde prueba React API v1.',
    }),
  }, accessToken);
}
