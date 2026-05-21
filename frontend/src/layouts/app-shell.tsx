import { BarChart3, CalendarDays, Command, LayoutDashboard, Search, Settings, UsersRound, type LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
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
  commandItems?: ShellCommandItem[];
};

export type ShellCommandItem = {
  id: string;
  label: string;
  description?: string;
  icon?: LucideIcon;
  keywords?: string[];
  action: () => void;
};

export function AppShell({ children, activeSection = 'dashboard', onSectionChange, onNavigateHome, navItems = defaultNavItems, commandItems = [] }: AppShellProps) {
  const [commandOpen, setCommandOpen] = useState(false);
  const selectSection = (section: string) => {
    onSectionChange?.(section);
  };
  const navigationCommands = useMemo<ShellCommandItem[]>(() => navItems.map((item) => ({
    id: `nav-${item.section}`,
    label: `Ir a ${item.label}`,
    description: 'Navegacion',
    icon: item.icon,
    keywords: [item.label, item.section],
    action: () => selectSection(item.section),
  })), [navItems, onSectionChange]);
  const commands = useMemo<ShellCommandItem[]>(() => [
    ...navigationCommands,
    ...(onNavigateHome ? [{
      id: 'nav-home',
      label: 'Ir al inicio publico',
      description: 'Navegacion',
      icon: LayoutDashboard,
      keywords: ['home', 'inicio', 'publico'],
      action: onNavigateHome,
    }] : []),
    ...commandItems,
  ], [commandItems, navigationCommands, onNavigateHome]);

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setCommandOpen((open) => !open);
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, []);

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
        <button className="mt-auto rounded-[1.5rem] border border-cyan-300/15 bg-cyan-300/10 p-4 text-left transition hover:bg-cyan-300/15" onClick={() => setCommandOpen(true)} type="button">
          <div className="flex items-center gap-2 text-sm font-semibold text-cyan-100">
            <Command size={16} />
            Command palette
          </div>
          <p className="mt-2 text-xs leading-5 text-cyan-100/70">Presiona Ctrl+K para acciones rapidas, busqueda y navegacion.</p>
        </button>
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
      <CommandPalette open={commandOpen} commands={commands} onClose={() => setCommandOpen(false)} />
    </div>
  );
}

function CommandPalette({ open, commands, onClose }: { open: boolean; commands: ShellCommandItem[]; onClose: () => void }) {
  const [query, setQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);
  const filteredCommands = useMemo(() => {
    const normalized = normalizeText(query);
    if (!normalized) return commands;
    return commands.filter((command) => normalizeText([
      command.label,
      command.description ?? '',
      ...(command.keywords ?? []),
    ].join(' ')).includes(normalized));
  }, [commands, query]);

  useEffect(() => {
    if (open) {
      setQuery('');
      setSelectedIndex(0);
    }
  }, [open]);

  useEffect(() => {
    setSelectedIndex(0);
  }, [query]);

  useEffect(() => {
    if (!open) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
      }
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setSelectedIndex((index) => Math.min(index + 1, Math.max(filteredCommands.length - 1, 0)));
      }
      if (event.key === 'ArrowUp') {
        event.preventDefault();
        setSelectedIndex((index) => Math.max(index - 1, 0));
      }
      if (event.key === 'Enter') {
        event.preventDefault();
        const command = filteredCommands[selectedIndex];
        if (command) {
          command.action();
          onClose();
        }
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [filteredCommands, onClose, open, selectedIndex]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 bg-black/60 px-4 py-16 backdrop-blur-xl" role="dialog" aria-modal="true" aria-label="Paleta de comandos" onMouseDown={onClose}>
      <div className="mx-auto max-w-2xl overflow-hidden rounded-[2rem] border border-white/10 bg-zinc-950/95 shadow-2xl shadow-black/60" onMouseDown={(event) => event.stopPropagation()}>
        <div className="flex items-center gap-3 border-b border-white/10 px-5 py-4">
          <Search className="text-cyan-200" size={20} />
          <input
            autoFocus
            className="w-full bg-transparent text-base text-white outline-none placeholder:text-slate-500"
            placeholder="Buscar accion, pantalla o modulo..."
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
          <kbd className="rounded-lg border border-white/10 bg-white/[0.06] px-2 py-1 text-xs text-slate-400">Esc</kbd>
        </div>
        <div className="max-h-[28rem] overflow-y-auto p-2">
          {filteredCommands.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-white/10 p-5 text-sm text-slate-400">No hay acciones para esa busqueda.</div>
          ) : filteredCommands.map((command, index) => {
            const Icon = command.icon ?? Command;
            return (
              <button
                key={command.id}
                className={cn(
                  'flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-left transition',
                  selectedIndex === index ? 'bg-white text-zinc-950' : 'text-slate-300 hover:bg-white/[0.07] hover:text-white',
                )}
                onMouseEnter={() => setSelectedIndex(index)}
                onClick={() => {
                  command.action();
                  onClose();
                }}
                type="button"
              >
                <span className={cn('grid h-10 w-10 place-items-center rounded-xl', selectedIndex === index ? 'bg-zinc-950/10' : 'bg-white/[0.06]')}>
                  <Icon size={18} />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-semibold">{command.label}</span>
                  {command.description ? <span className={cn('mt-0.5 block truncate text-xs', selectedIndex === index ? 'text-zinc-700' : 'text-slate-500')}>{command.description}</span> : null}
                </span>
                {selectedIndex === index ? <kbd className="rounded-lg bg-zinc-950/10 px-2 py-1 text-xs">Enter</kbd> : null}
              </button>
            );
          })}
        </div>
        <div className="flex flex-wrap items-center gap-2 border-t border-white/10 px-5 py-3 text-xs text-slate-500">
          <span>Ctrl+K abre/cierra</span>
          <span>Flechas navegan</span>
          <span>Enter ejecuta</span>
        </div>
      </div>
    </div>
  );
}

function normalizeText(value: string) {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}
