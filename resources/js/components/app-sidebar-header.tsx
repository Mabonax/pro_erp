import { usePage } from '@inertiajs/react';
import { Bell, CircleHelp } from 'lucide-react';

import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    type BreadcrumbItem as BreadcrumbItemType,
    type SharedData,
} from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth, notifications } = usePage<SharedData>().props;
    const initials = (auth?.user?.name ?? 'SA')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

    return (
        <header className="sticky top-0 z-20 shrink-0 bg-[#FAFAFB]/95 px-5 py-3 backdrop-blur transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14 md:px-6">
            <div className="flex h-16 items-center justify-between gap-4 rounded-lg border border-[#ECECEC] bg-white px-4 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
                <div className="flex min-w-0 items-center gap-4">
                    <SidebarTrigger className="h-9 w-9 rounded-md border border-[#ECECEC] text-[#111111] hover:bg-[#F3F4F6]" />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
                <div className="flex shrink-0 items-center gap-4">
                    <a
                        href="/notifications"
                        className="relative flex size-9 items-center justify-center rounded-full text-[#111827] hover:bg-[#FDECEE] hover:text-[#C8102E]"
                        aria-label="Notifications"
                    >
                        <Bell className="size-5" />
                        {(notifications?.unread_count ?? 0) > 0 ? (
                            <span className="absolute top-1.5 right-1.5 size-2.5 rounded-full bg-[#D20A1E]" />
                        ) : null}
                    </a>
                    <button
                        type="button"
                        className="flex size-9 items-center justify-center rounded-full text-[#111827] hover:bg-[#FDECEE] hover:text-[#C8102E]"
                        aria-label="Help"
                    >
                        <CircleHelp className="size-5" />
                    </button>
                    <div className="flex size-11 items-center justify-center rounded-full bg-[#111827] text-sm font-semibold text-white">
                        {initials || 'SA'}
                    </div>
                </div>
            </div>
        </header>
    );
}
