import type { HTMLAttributes } from 'react';
import { cn } from '../../lib/cn';

type BadgeTone = 'slate' | 'cyan' | 'violet' | 'emerald' | 'amber' | 'rose';

const tones: Record<BadgeTone, string> = {
  slate: 'border-white/10 bg-white/[0.07] text-slate-300',
  cyan: 'border-cyan-300/20 bg-cyan-400/10 text-cyan-200',
  violet: 'border-violet-300/20 bg-violet-400/10 text-violet-200',
  emerald: 'border-emerald-300/20 bg-emerald-400/10 text-emerald-200',
  amber: 'border-amber-300/20 bg-amber-400/10 text-amber-200',
  rose: 'border-rose-300/20 bg-rose-400/10 text-rose-200',
};

type BadgeProps = HTMLAttributes<HTMLSpanElement> & {
  tone?: BadgeTone;
};

export function Badge({ className, tone = 'slate', ...props }: BadgeProps) {
  return (
    <span
      className={cn('inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold', tones[tone], className)}
      {...props}
    />
  );
}
