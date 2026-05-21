import { create } from 'zustand';
import type { Booking, BookingStatus } from '../types/booking';
import { bookings as initialBookings } from '../services/mock-data';

type BookingState = {
  bookings: Booking[];
  selectedBookingId: string | null;
  setBookings: (bookings: Booking[]) => void;
  setSelectedBooking: (bookingId: string | null) => void;
  updateBookingStatus: (bookingId: string, status: BookingStatus) => void;
  moveBooking: (bookingId: string, start: string, end: string) => void;
  updateBookingNotes: (bookingId: string, notes: string) => void;
};

export const useBookingStore = create<BookingState>((set) => ({
  bookings: initialBookings,
  selectedBookingId: null,
  setBookings: (bookings) => set({ bookings }),
  setSelectedBooking: (bookingId) => set({ selectedBookingId: bookingId }),
  updateBookingStatus: (bookingId, status) =>
    set((state) => ({
      bookings: state.bookings.map((booking) => (booking.id === bookingId ? { ...booking, status } : booking)),
    })),
  moveBooking: (bookingId, start, end) =>
    set((state) => ({
      bookings: state.bookings.map((booking) => (booking.id === bookingId ? { ...booking, start, end } : booking)),
    })),
  updateBookingNotes: (bookingId, notes) =>
    set((state) => ({
      bookings: state.bookings.map((booking) => (booking.id === bookingId ? { ...booking, notes } : booking)),
    })),
}));
