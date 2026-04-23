export interface CategoryItem {
    id: number;
    name: string;
    icon: string;
    gradient: string;
}

export const CATEGORIES: CategoryItem[] = [
    { id: 1,  name: 'Music',     icon: '🎵', gradient: 'from-pink-500 to-rose-600' },
    { id: 2,  name: 'Sports',    icon: '⚽', gradient: 'from-green-500 to-emerald-600' },
    { id: 3,  name: 'Arts',      icon: '🎨', gradient: 'from-purple-500 to-violet-600' },
    { id: 4,  name: 'Food',      icon: '🍕', gradient: 'from-orange-500 to-amber-600' },
    { id: 5,  name: 'Tech',      icon: '💻', gradient: 'from-blue-500 to-cyan-600' },
    { id: 6,  name: 'Business',  icon: '💼', gradient: 'from-indigo-500 to-blue-600' },
    { id: 7,  name: 'Comedy',    icon: '😄', gradient: 'from-yellow-500 to-orange-500' },
    { id: 8,  name: 'Film',      icon: '🎬', gradient: 'from-red-500 to-pink-600' },
    { id: 9,  name: 'Education', icon: '📚', gradient: 'from-teal-500 to-green-600' },
];

export const AD_BANNERS = [
    {
        id: 1,
        title: 'Summer Music Festival 2026',
        subtitle: 'The biggest music event of the year',
        cta: 'Get Tickets',
        gradient: 'from-violet-700 via-purple-600 to-pink-600',
        emoji: '🎸',
    },
    {
        id: 2,
        title: 'Food & Culinary Expo',
        subtitle: '200+ vendors · live cooking shows',
        cta: 'Explore Now',
        gradient: 'from-orange-600 via-red-500 to-pink-500',
        emoji: '🍜',
    },
    {
        id: 3,
        title: 'Tech Innovation Summit',
        subtitle: 'Meet the builders of tomorrow',
        cta: 'Register Free',
        gradient: 'from-cyan-600 via-blue-600 to-violet-600',
        emoji: '🚀',
    },
] as const;
