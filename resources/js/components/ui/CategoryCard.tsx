import { THEME } from '../../constants/colors';
import type { CategoryItem } from '../../constants/categories';

interface CategoryCardProps {
    category: CategoryItem;
    onClick?: () => void;
}

export default function CategoryCard({ category, onClick }: CategoryCardProps) {
    return (
        <button
            onClick={onClick}
            className={`flex flex-col items-center gap-2 p-3 rounded-2xl ${THEME.bgCard} border ${THEME.border} hover:border-violet-500/50 transition-all hover:scale-[1.03]`}
        >
            <div className={`w-12 h-12 rounded-xl bg-linear-to-br ${category.gradient} flex items-center justify-center shadow-lg`}>
                <span className="text-2xl">{category.icon}</span>
            </div>
            <span className={`text-[11px] font-semibold ${THEME.textSecondary}`}>{category.name}</span>
        </button>
    );
}
