import type { Booking, Discipline, Professional, Service } from '../types/booking';

function bookingDate(dayOffset: number, hour: number, minute = 0) {
  const date = new Date();
  date.setDate(date.getDate() + dayOffset);
  date.setHours(hour, minute, 0, 0);
  return date.toISOString();
}

export const disciplines: Discipline[] = [
  { id: 'wellness', name: 'Wellness' },
  { id: 'consulting', name: 'Consultoria' },
  { id: 'health', name: 'Salud' },
  { id: 'education', name: 'Educacion' },
];

export const services: Service[] = [
  { id: 'srv-1', disciplineId: 'wellness', name: 'Sesion integral', durationMinutes: 60, price: 48000, color: '#22d3ee' },
  { id: 'srv-2', disciplineId: 'consulting', name: 'Diagnostico experto', durationMinutes: 45, price: 65000, color: '#8b5cf6' },
  { id: 'srv-3', disciplineId: 'health', name: 'Evaluacion inicial', durationMinutes: 50, price: 52000, color: '#10b981' },
  { id: 'srv-4', disciplineId: 'education', name: 'Mentoria personalizada', durationMinutes: 90, price: 78000, color: '#f59e0b' },
];

export const professionals: Professional[] = [
  { id: 'pro-1', name: 'Amelia Torres', role: 'Especialista senior', disciplines: ['wellness', 'health'], services: ['srv-1', 'srv-3'], utilization: 86, status: 'busy' },
  { id: 'pro-2', name: 'Mateo Silva', role: 'Consultor principal', disciplines: ['consulting', 'education'], services: ['srv-2', 'srv-4'], utilization: 72, status: 'available' },
  { id: 'pro-3', name: 'Renata Vidal', role: 'Profesional multidisciplinaria', disciplines: ['wellness', 'consulting'], services: ['srv-1', 'srv-2'], utilization: 58, status: 'available' },
];

export const bookings: Booking[] = [
  {
    id: 'bk-1001',
    customerName: 'Camila Fuentes',
    professionalId: 'pro-1',
    professionalName: 'Amelia Torres',
    serviceId: 'srv-1',
    serviceName: 'Sesion integral',
    status: 'confirmed',
    start: bookingDate(0, 10),
    end: bookingDate(0, 11),
    notes: 'Prefiere recordatorio por WhatsApp. Cliente recurrente.',
    revenue: 48000,
  },
  {
    id: 'bk-1002',
    customerName: 'Joaquin Herrera',
    professionalId: 'pro-2',
    professionalName: 'Mateo Silva',
    serviceId: 'srv-2',
    serviceName: 'Diagnostico experto',
    status: 'pending',
    start: bookingDate(0, 13, 30),
    end: bookingDate(0, 14, 15),
    notes: 'Primera visita. Completar ficha antes de confirmar.',
    revenue: 65000,
  },
  {
    id: 'bk-1003',
    customerName: 'Valentina Rojas',
    professionalId: 'pro-3',
    professionalName: 'Renata Vidal',
    serviceId: 'srv-4',
    serviceName: 'Mentoria personalizada',
    status: 'completed',
    start: bookingDate(-1, 15),
    end: bookingDate(-1, 16, 30),
    notes: 'Solicito plan mensual.',
    revenue: 78000,
  },
];
