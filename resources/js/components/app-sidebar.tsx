import { Link, usePage } from '@inertiajs/react';
import { Bell, BookOpen, Bot, Briefcase, BriefcaseBusiness, Building2, CalendarRange, ClipboardCheck, Download, FolderTree, LayoutGrid, LifeBuoy, MapPinned, Megaphone, Package, ReceiptText, ShieldCheck, UserCircle } from 'lucide-react';

import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { hasAnyPermission, hasAnyRole } from '@/lib/access';
import { dashboard } from '@/routes';
import { type NavItem, type SharedData } from '@/types';

import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Beneficiaries',
        href: '/beneficiaries',
        icon: UserCircle,
        requiredPermissions: ['domain.beneficiaries.view', 'domain.beneficiaries.manage'],
    },
    {
        title: 'Citizen Access',
        href: '/citizen-access/intakes',
        icon: LifeBuoy,
        requiredPermissions: ['domain.citizen-access.view', 'domain.citizen-access.manage'],
    },
    {
        title: 'Stakeholders',
        href: '/stakeholders',
        icon: UserCircle,
        requiredPermissions: ['domain.stakeholders.view', 'domain.stakeholders.manage'],
    },
    {
        title: 'Organization',
        href: '/organization',
        icon: Building2,
        requiredPermissions: ['domain.organization.view', 'domain.organization.manage'],
    },
    {
        title: 'Facilitators',
        href: '/facilitators',
        icon: UserCircle,
        requiredPermissions: ['domain.facilitators.view', 'domain.facilitators.manage'],
    },
    {
        title: 'Human Resources',
        href: '/human-resources',
        icon: Building2,
        requiredPermissions: ['domain.human-resources.view', 'domain.human-resources.manage'],
    },
    {
        title: 'Human Capital',
        href: '/human-capital/dashboard',
        icon: MapPinned,
        requiredPermissions: ['domain.human-capital.view', 'domain.human-capital.manage', 'domain.members.view', 'domain.members.manage'],
    },
    {
        title: 'Intelligence',
        href: '/intelligence',
        icon: Bot,
        requiredPermissions: ['domain.intelligence.view', 'domain.intelligence.manage'],
    },
    {
        title: 'Assets',
        href: '/assets',
        icon: Package,
        requiredPermissions: ['domain.assets.view', 'domain.assets.manage'],
    },
    {
        title: 'Programs',
        href: '/programs',
        icon: BookOpen,
        requiredPermissions: ['domain.programs.view', 'domain.programs.manage'],
    },
    {
        title: 'Projects',
        href: '/projects',
        icon: Briefcase,
        requiredPermissions: ['domain.projects.view', 'domain.projects.manage'],
    },
    {
        title: 'Service Delivery',
        href: '/service-delivery',
        icon: ClipboardCheck,
        requiredPermissions: ['domain.service-delivery.view', 'domain.service-delivery.manage', 'domain.programs.view', 'domain.projects.view', 'domain.beneficiaries.view'],
    },
    {
        title: 'Business Development',
        href: '/business-development',
        icon: BriefcaseBusiness,
        requiredPermissions: ['domain.business-development.view', 'domain.business-development.manage'],
    },
    {
        title: 'Events',
        href: '/events',
        icon: CalendarRange,
        requiredPermissions: ['domain.events.view', 'domain.events.manage'],
    },
    {
        title: 'Task Management',
        href: '/task-management',
        icon: LifeBuoy,
        requiredPermissions: ['domain.task-management.view', 'domain.task-management.manage'],
    },
    {
        title: 'Marketing',
        href: '/marketing',
        icon: Megaphone,
        requiredPermissions: ['domain.marketing.view', 'domain.marketing.manage'],
    },
    {
        title: 'Finance',
        href: '/finance/travel-claims',
        icon: ReceiptText,
        requiredPermissions: ['domain.finance.view', 'domain.finance.manage', 'travel-claims.submit'],
    },
    {
        title: 'Notifications',
        href: '/notifications',
        icon: Bell,
    },
    {
        title: 'Document Library',
        href: '/organization/document-library',
        icon: FolderTree,
        requiredPermissions: [
            'domain.organization.view',
            'domain.organization.manage',
            'domain.programs.view',
            'domain.programs.manage',
            'domain.projects.view',
            'domain.projects.manage',
            'domain.beneficiaries.view',
            'domain.beneficiaries.manage',
            'domain.stakeholders.view',
            'domain.stakeholders.manage',
            'domain.human-resources.view',
            'domain.human-resources.manage',
        ],
    },
    {
        title: 'Official Vault',
        href: '/organization/documents',
        icon: Download,
        requiredPermissions: ['domain.organization.view', 'domain.organization.manage'],
    },
    {
        title: 'Facilitator Activities',
        href: '/project-locations/dashboard',
        icon: ClipboardCheck,
        requiredPermissions: ['project-activities.view'],
    },
    {
        title: 'Access Control',
        href: '/access-control/roles',
        icon: ShieldCheck,
        requiredRoles: ['super-admin', 'super admin'],
    },
];

export function AppSidebar() {
    const { auth, notifications } = usePage<SharedData>().props;
    const user = auth?.user;
    const isAdminUser = hasAnyRole(user, ['super-admin', 'super admin', 'admin']);
    const isBusinessDevelopmentUser = hasAnyPermission(user, [
        'domain.business-development.view',
        'domain.business-development.manage',
    ]);
    const restrictBeneficiariesForBds = isBusinessDevelopmentUser && !isAdminUser;

    const itemsWithBadges = mainNavItems.map((item) => ({
        ...item,
        badgeCount: item.href === '/notifications' ? (notifications?.unread_count ?? 0) : undefined,
    }));

    const visibleMainNavItems = itemsWithBadges.filter(
        (item) =>
            hasAnyRole(user, item.requiredRoles ?? []) &&
            hasAnyPermission(user, item.requiredPermissions ?? []) &&
            (!restrictBeneficiariesForBds || item.href !== '/beneficiaries'),
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-4 border-b border-[#ECECEC] px-3 py-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="border border-[#ECECEC] bg-[#F7F7F7] p-4 text-sm leading-6 text-[#4B5563] group-data-[collapsible=icon]:hidden">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#C8102E]">
                        Internal ERP
                    </p>
                    <p className="mt-2">
                        Delivery operations, governance workflows, and institutional records in one workspace.
                    </p>
                </div>
            </SidebarHeader>

            <SidebarContent className="px-1 py-3">
                <NavMain items={visibleMainNavItems} />
            </SidebarContent>

            <SidebarFooter className="border-t border-[#ECECEC] px-3 py-3">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
