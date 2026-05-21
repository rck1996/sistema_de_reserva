import type { InputHTMLAttributes, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
import { cn } from '../../lib/cn';

const controlClass =
  'w-full rounded-2xl border border-white/10 bg-white/[0.07] px-4 py-3 text-sm text-white shadow-inner shadow-black/10 outline-none transition placeholder:text-slate-500 hover:border-white/15 focus:border-cyan-300/50 focus:bg-white/[0.10]';

export function Input({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={cn(controlClass, className)} {...props} />;
}

export function Select({ className, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select className={cn(controlClass, className)} {...props} />;
}

export function Textarea({ className, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea className={cn(controlClass, 'min-h-28 resize-none', className)} {...props} />;
}
