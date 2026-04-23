import { THEME } from '../../constants/colors';

interface SectionHeaderProps {
    title: string;
    onSeeAll?: () => void;
}

export default function SectionHeader({ title, onSeeAll }: SectionHeaderProps) {
    return (
        <div className="flex items-center justify-between px-5 mb-3">
            <h2 className={`text-base font-bold ${THEME.textPrimary}`}>{title}</h2>
            {onSeeAll && (
                <button
                    onClick={onSeeAll}
                    className={`text-xs font-semibold ${THEME.accentText} hover:opacity-80 transition-opacity`}
                >
                    See all →
                </button>
            )}
        </div>
    );
}
