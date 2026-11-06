import type { FormSchema } from '@xelqun/forms';
import type { TableSchema } from '@xelqun/tables';

/** What every page of a panel shares. Hosts read it as `xelqunPanel`. */
export interface PanelShell {
    id: string;
    url: string;
    brand: { name: string };
    user: { name: string; email: string } | null;
}

export type PanelContent =
    | { type: 'table'; schema: TableSchema }
    | { type: 'actions'; schema: FormSchema };

export interface PanelPageData {
    title: string;
    headerActions: FormSchema | null;
    content: PanelContent[];
}
