import { BarChart3, CalendarDays, Command, LayoutDashboard, Settings, UsersRound, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '../lib/cn';

export type ShellNavItem = {
  label: string;
  icon: LucideIcon;
  section: string;
};

const defaultNavItems: ShellNavItem[] = [
  { label: 'Dashboard', icon: LayoutDashboard, section: 'dashboard' },
  { label: 'Calendario', icon: CalendarDays, section: 'calendario' },
  { label: 'Clientes', icon: UsersRound, section: 'clientes' },
  { label: 'Estadisticas', icon: BarChart3, section: 'estadisticas' },
  { label: 'Configuracion', icon: Settings, section: 'configuracion' },
];

type AppShellProps = {
  children: ReactNode;
  activeSection?: string;
  onSectionChange?: (section: string) => void;
  onNavigateHome?: () => void;
  navItems?: ShellNavItem[];
};

export function AppShell({ children, activeSection = 'dashboard', onSectionChange, onNavigateHome, navItems = defaultNavItems }: AppShellProps) {
  const selectSection = (section: string) => {
    onSectionChange?.(section);
  };

  return (
    <div className="min-h-screen">
      <aside className="fixed left-4 top-4 z-30 hidden h-[calc(100vh-2rem)] w-72 flex-col rounded-[2rem] border border-white/10 bg-white/[0.06] p-4 shadow-2xl shadow-black/30 backdrop-blur-2xl xl:flex">
        <button className="flex items-center gap-3 rounded-2xl px-2 py-3 text-left transition hover:bg-white/[0.06]" onClick={onNavigateHome} type="button">
          <div className="grid h-11 w-11 place-items-center rounded-2xl bg-white text-sm font-black text-zinc-950">SR</div>
          <div>
            <p className="text-sm font-semibold text-white">Sistema Reserva</p>
            <p className="text-xs text-slate-400">SaaS Control Plane</p>
          </div>
        </button>
        <nav className="mt-8 space-y-1">
          {navItems.map((item) => (
            <button
              key={item.label}
              onClick={() => selectSection(item.section)}
              type="button"
              className={cn(
                'group flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold transition',
                activeSection === item.section ? 'bg-white text-zinc-950 shadow-xl shadow-white/10' : 'text-slate-400 hover:bg-white/[0.07] hover:text-white',
              )}
            >
              <item.icon size={18} />
              {item.label}
            </button>
          ))}
        </nav>
        <div className="mt-auto rounded-[1.5rem] border border-cyan-300/15 bg-cyan-300/10 p-4">
          <div className="flex items-center gap-2 text-sm font-semibold text-cyan-100">
            <Command size={16} />
            Command palette
          </div>
          <p className="mt-2 text-xs leading-5 text-cyan-100/70">Presiona Ctrl+K para acciones rapidas, busqueda y navegacion.</p>
        </div>
      </aside>

      <header className="sticky top-0 z-20 border-b border-white/10 bg-zinc-950/60 backdrop-blur-2xl xl:hidden">
        <div className="flex items-center justify-between px-4 py-4">
          <div className="flex items-center gap-3">
            <div className="grid h-10 w-10 place-items-center rounded-2xl bg-white text-sm font-black text-zinc-950">SR</div>
            <div>
              <p className="text-sm font-semibold text-white">Sistema Reserva</p>
              <p className="text-xs text-slate-400">React app</p>
            </div>
          </div>
          <button className="rounded-2xl border border-white/10 bg-white/[0.07] px-3 py-2 text-sm font-semibold text-slate-200" onClick={() => selectSection(navItems[0]?.section ?? 'dashboard')} type="button">{navItems[0]?.label ?? 'Dashboard'}</button>
        </div>
      </header>

      <main
        className="w-full px-4 py-6 sm:px-6 lg:px-8 xl:ml-80 xl:max-w-[calc(100vw-21rem)]"
      >
        {children}
      </main>

      <nav className="fixed inset-x-3 bottom-3 z-30 grid grid-cols-4 rounded-[1.5rem] border border-white/10 bg-zinc-950/80 p-2 shadow-2xl shadow-black/40 backdrop-blur-2xl xl:hidden">
        {navItems.slice(0, 4).map((item) => (
          <button key={item.label} onClick={() => selectSection(item.section)} type="button" className={cn('grid place-items-center gap-1 rounded-2xl px-2 py-2 text-[11px] font-semibold', activeSection === item.section ? 'bg-white text-zinc-950' : 'text-slate-400')}>
            <item.icon size={17} />
            {item.label}
          </button>
        ))}
      </nav>
    </div>
  );
}
