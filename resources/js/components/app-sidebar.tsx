import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Shield, CreditCard, Wallet, FileText } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props as { auth?: { user?: { is_admin?: boolean } } };
    const isAdmin = auth?.user?.is_admin ?? false;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Transactions',
            href: dashboard(),
            icon: CreditCard,
        },
        {
            title: 'Withdrawals',
            href: '/admin/withdrawals',
            icon: Wallet,
        },
    ];

    if (isAdmin) {
        mainNavItems.push({
            title: 'Admin Settings',
            href: '/admin/settings',
            icon: Shield,
        });
    }

    const footerNavItems: NavItem[] = [
        {
            title: 'API Documentation',
            href: 'https://developers.malipopay.co.tz/api/',
            icon: FileText,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
