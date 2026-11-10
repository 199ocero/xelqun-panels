import type { XelqunPlugin } from '@xelqun/forms';
import {
    Bell,
    ChartColumn,
    ClipboardList,
    Copy,
    Download,
    FileText,
    FolderTree,
    House,
    Layers,
    LayoutDashboard,
    Package,
    Palette,
    RotateCcw,
    Shield,
    ShoppingCart,
    Truck,
    Unlink,
    Upload,
    Zap,
} from '@lucide/vue';
import XqPanelEntry from './components/XqPanelEntry.vue';

/**
 * Goes in the `plugins` of `createXelqunForms()`. The icons are the ones a
 * sidebar usually wants that forms' set lacks. Add others with its `icons` option.
 */
export function panelsPlugin(): XelqunPlugin {
    return {
        id: 'xelqun/panels',
        components: { 'xelqun/entry': XqPanelEntry },
        icons: {
            bell: Bell,
            'chart-column': ChartColumn,
            'clipboard-list': ClipboardList,
            copy: Copy,
            download: Download,
            'file-text': FileText,
            'folder-tree': FolderTree,
            house: House,
            layers: Layers,
            'layout-dashboard': LayoutDashboard,
            package: Package,
            palette: Palette,
            'rotate-ccw': RotateCcw,
            shield: Shield,
            'shopping-cart': ShoppingCart,
            truck: Truck,
            unlink: Unlink,
            upload: Upload,
            zap: Zap,
        },
    };
}
