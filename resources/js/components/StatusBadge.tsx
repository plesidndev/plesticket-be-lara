import type { VerificationStatus } from '../types';

const colors: Record<VerificationStatus, string> = {
    pending:   'bg-yellow-100 text-yellow-800',
    verified:  'bg-green-100  text-green-800',
    rejected:  'bg-red-100    text-red-800',
    suspended: 'bg-gray-100   text-gray-800',
};

export default function StatusBadge({ status }: { status: VerificationStatus }) {
    return (
        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${colors[status] ?? 'bg-gray-100 text-gray-600'}`}>
            {status}
        </span>
    );
}
