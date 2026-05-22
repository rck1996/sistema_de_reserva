import { create } from 'zustand';
import type { SaasUser } from '../../services/api-v1-client';

const STORAGE_KEY = 'sistema_reserva_saas_auth';

type SaasAuthState = {
  accessToken: string;
  refreshToken: string;
  user: SaasUser | null;
  hydrate: () => void;
  setSession: (session: { accessToken: string; refreshToken: string; user: SaasUser }) => void;
  clearSession: () => void;
};

export const useSaasAuthStore = create<SaasAuthState>((set) => ({
  accessToken: '',
  refreshToken: '',
  user: null,
  hydrate: () => {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return;
    try {
      const session = JSON.parse(raw);
      set({
        accessToken: String(session.accessToken ?? ''),
        refreshToken: String(session.refreshToken ?? ''),
        user: session.user ?? null,
      });
    } catch (_error) {
      window.localStorage.removeItem(STORAGE_KEY);
    }
  },
  setSession: (session) => {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    set(session);
  },
  clearSession: () => {
    window.localStorage.removeItem(STORAGE_KEY);
    set({ accessToken: '', refreshToken: '', user: null });
  },
}));
