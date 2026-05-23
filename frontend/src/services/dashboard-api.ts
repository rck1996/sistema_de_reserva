import type { Booking } from '../types/booking';

export async function persistBookingChange(_booking?: unknown): Promise<Booking> {
  throw new Error('La persistencia legacy fue retirada. Usa /api/v1/bookings.php desde api-v1-client.ts.');
}
