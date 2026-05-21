import type { ButtonHTMLAttributes } from 'react';
import { cn } from '../../lib/cn';

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: ButtonVariant;
};

const variants: Record<ButtonVariant, string> = {
  primary: 'bg-white text-zinc-950 shadow-[0_18px_50px_rgba(255,255,255,0.12)] hover:bg-cyan-50',
  secondary: 'border border-white/10 bg-white/[0.07] text-slate-100 hover:bg-white/[0.11]',
  ghost: 'text-slate-300 hover:bg-white/[0.07] hover:text-white',
  danger: 'border border-rose-400/20 bg-rose-500/10 text-rose-200 hover:bg-rose-500/15',
};

export function Button({ className, variant = 'secondary', ...props }: ButtonProps) {
  return (
    <button
      className={cn(
        'inline-flex h-11 items-center justify-center rounded-2xl px-4 text-sm font-semibold transition duration-200 hover:-translate-y-0.5 disabled:pointer-events-none disabled:opacity-55',
        variants[variant],
        className,
      )}
      {...props}
    />
  );
}
