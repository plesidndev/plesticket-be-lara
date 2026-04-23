export const COLORS = {
    primary: '#7C3AED',
    primaryLight: '#A78BFA',
    primaryDark: '#5B21B6',
    accent: '#EC4899',
    accentLight: '#F9A8D4',
    bg: '#09090B',
    surface: '#18181B',
    surfaceAlt: '#27272A',
    border: '#3F3F46',
    textPrimary: '#FAFAFA',
    textSecondary: '#A1A1AA',
    textMuted: '#71717A',
    success: '#10B981',
    warning: '#F59E0B',
    error: '#EF4444',
} as const;

// All values use standard Tailwind class names so the scanner picks them up reliably
export const THEME = {
    bgPage: 'bg-zinc-950',
    bgCard: 'bg-zinc-900',
    bgCardAlt: 'bg-zinc-800',
    textPrimary: 'text-zinc-50',
    textSecondary: 'text-zinc-400',
    textMuted: 'text-zinc-500',
    border: 'border-zinc-800',
    gradientPrimary: 'from-violet-600 to-pink-500',
    gradientCard: 'from-violet-600/20 to-pink-500/10',
    accentText: 'text-violet-400',
    accentBg: 'bg-violet-600',
    accentBgLight: 'bg-violet-500/20',
} as const;
