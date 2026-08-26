import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';

import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

interface FieldProps {
    label: ReactNode;
    hint?: string;
    error?: string;
    children: ReactNode;
    className?: string;
}

export function Field({ label, hint, error, children, className }: FieldProps) {
    return (
        <label className={cn('grid gap-1.5 text-sm font-medium text-foreground', className)}>
            <span>{label}</span>
            {children}
            {(error || hint) && <span className={cn('text-xs font-normal text-muted-foreground', error && 'text-destructive')}>{error || hint}</span>}
        </label>
    );
}

export function TextField(props: InputHTMLAttributes<HTMLInputElement>) {
    return <Input {...props} />;
}

export function TextAreaField(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <Textarea {...props} />;
}

export function NativeSelect({ className, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select
            className={cn('h-8 w-full rounded-lg border border-input bg-background px-2.5 text-sm text-foreground outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50', className)}
            {...props}
        />
    );
}
