import type { XelqunPlugin } from '@xelqun/forms';
import {
    ChartColumn,
    ClipboardList,
    FileText,
    FolderTree,
    House,
    Layers,
    LayoutDashboard,
    Package,
    Palette,
    ShoppingCart,
    Tag,
    Truck,
    Users,
    Zap,
} from 'lucide-vue-next';
import XqPanelEntry from './components/XqPanelEntry.vue';

/**
 * Goes in the `plugins` of `createXelqunForms()`. The icons are the ones a
 * sidebar usually wants, on top of forms' set. Add others with its `icons` option.
 */
export function panelsPlugin(): XelqunPlugin {
    return {
        id: 'xelqun/panels',
        components: { 'xelqun-panels/entry': XqPanelEntry },
        icons: {
            'chart-column': ChartColumn,
            'clipboard-list': ClipboardList,
            'file-text': FileText,
            'folder-tree': FolderTree,
            house: House,
            layers: Layers,
            'layout-dashboard': LayoutDashboard,
            package: Package,
            palette: Palette,
            'shopping-cart': ShoppingCart,
            tag: Tag,
            truck: Truck,
            users: Users,
            zap: Zap,
        },
    };
}
