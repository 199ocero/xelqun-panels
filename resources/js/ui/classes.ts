export const menuClass =
    'relative z-50 min-w-52 overflow-hidden rounded-xq-md border border-xq-border bg-xq-popover p-1 text-sm text-xq-popover-foreground shadow-lg origin-(--reka-popper-transform-origin) data-[state=open]:animate-xq-pop-in data-[state=closed]:animate-xq-pop-out';

export const menuItemClass =
    'relative flex cursor-default items-center gap-2 rounded-xq-sm px-2 py-1.5 outline-none select-none transition-[scale] duration-(--xq-duration-fast) active:scale-[0.98]';

/** Size and look of the round icon buttons in the top bar and sidebar. */
export const iconButtonClass =
    'inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-full text-xq-muted-foreground transition-[color,background-color,scale] duration-(--xq-duration-fast) active:scale-90 [&_svg]:transition-transform [&_svg]:duration-(--xq-duration-base) [&_svg]:ease-(--xq-ease-spring) hover:[&_svg]:scale-110 hover:bg-xq-nav-hover hover:text-xq-foreground focus-visible:ring-2 focus-visible:ring-xq-ring focus-visible:outline-none';
