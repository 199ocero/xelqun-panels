/** The soft colors behind icons and on status chips. */
export const toneClasses: Record<string, string> = {
    primary: 'bg-xq-primary/12 text-xq-primary',
    success: 'bg-xq-success/12 text-xq-success',
    warning: 'bg-xq-warning/12 text-xq-warning',
    danger: 'bg-xq-destructive/12 text-xq-destructive',
    info: 'bg-xq-info/12 text-xq-info',
    gray: 'bg-xq-secondary text-xq-secondary-foreground',
};

/** The same names, for text with no background. */
export const textToneClasses: Record<string, string> = {
    primary: 'text-xq-primary',
    success: 'text-xq-success',
    warning: 'text-xq-warning',
    danger: 'text-xq-destructive',
    info: 'text-xq-info',
    gray: 'text-xq-muted-foreground',
};
