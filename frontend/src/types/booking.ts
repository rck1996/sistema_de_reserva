export type BookingStatus = 'pending' | 'confirmed' | 'in_progress' | 'completed' | 'no_show' | 'cancelled';

export type Discipline = {
  id: string;
  name: string;
};

export type Service = {
  id: string;
  disciplineId: string;
  name: string;
  durationMinutes: number;
  price: number;
  color: string;
};

export type Professional = {
  id: string;
  name: string;
  role: string;
  disciplines: string[];
  services: string[];
  utilization: number;
  status: 'available' | 'busy' | 'offline';
};

export type Booking = {
  id: string;
  customerName: string;
  professionalId: string;
  professionalName: string;
  serviceId: string;
  serviceName: string;
  status: BookingStatus;
  start: string;
  end: string;
  notes: string;
  revenue: number;
  color?: string;
  disciplineName?: string;
};

export type DashboardMetrics = {
  activeBookings: number;
  estimatedRevenue: number;
  cancelledBookings: number;
  upcomingBookings: number;
};

export type DashboardSnapshot = {
  ok: boolean;
  authenticated: boolean;
  csrfToken?: string;
  metrics?: DashboardMetrics;
  bookings: Booking[];
  professionals: Professional[];
  services: Service[];
};
