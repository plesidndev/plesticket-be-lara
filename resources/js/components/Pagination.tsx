import type { PaginationMeta } from '../types';

interface Props {
    meta: PaginationMeta | null;
    onPageChange: (page: number) => void;
}

export default function Pagination({ meta, onPageChange }: Props) {
    if (!meta || meta.pages <= 1) return null;

    return (
        <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>Showing page {meta.page} of {meta.pages} ({meta.total} total)</span>
            <div className="flex gap-2">
                <button
                    disabled={meta.page <= 1}
                    onClick={() => onPageChange(meta.page - 1)}
                    className="px-3 py-1 border rounded disabled:opacity-40 hover:bg-gray-50"
                >
                    Prev
                </button>
                <button
                    disabled={meta.page >= meta.pages}
                    onClick={() => onPageChange(meta.page + 1)}
                    className="px-3 py-1 border rounded disabled:opacity-40 hover:bg-gray-50"
                >
                    Next
                </button>
            </div>
        </div>
    );
}
