import type { FormSchema } from '@xelqun/forms';
import type { TableSchema } from '@xelqun/tables';

export interface PanelNavigationItem {
    label: string;
    icon: string | null;
    url: string | null;
    active: boolean;
    badge: string | null;
}

export interface PanelNavigationGroup {
    label: string | null;
    items: PanelNavigationItem[];
}

export interface PanelSidebarCard {
    title: string;
    icon: string | null;
    badge: string | null;
    value: string | null;
    note: string | null;
    progress: number | null;
    footer: string | null;
    link: { label: string; url: string } | null;
}

/** What every page of a panel shares. Hosts read it as `xelqunPanel`. */
export interface PanelShell {
    id: string;
    url: string;
    brand: { name: string };
    theme: { primary: string | null; font: string };
    navigation: PanelNavigationGroup[];
    sidebarFooter: PanelSidebarCard | null;
    user: { name: string; email: string } | null;
}

export type PanelContent =
    | { type: 'table'; schema: TableSchema }
    | { type: 'actions'; schema: FormSchema };

export interface PanelBreadcrumb {
    label: string;
    url?: string;
}

export interface PanelPageData {
    title: string;
    eyebrow: string | null;
    subheading: string | null;
    breadcrumbs: PanelBreadcrumb[];
    headerActions: FormSchema | null;
    content: PanelContent[];
}
