<?php

namespace Xelqun\Panels\View;

/**
 * Where Panel::renderHook() can put a Vue component.
 *
 * @api
 */
enum RenderHook: string
{
    case SidebarStart = 'sidebar.start';
    case SidebarEnd = 'sidebar.end';
    case TopbarStart = 'topbar.start';
    case TopbarEnd = 'topbar.end';
    case ContentStart = 'content.start';
    case ContentEnd = 'content.end';
    case AuthFormBefore = 'auth-form.before';
    case AuthFormAfter = 'auth-form.after';

    /**
     * Whether it shows to someone who hasn't signed in.
     */
    public function isPublic(): bool
    {
        return in_array($this, [self::AuthFormBefore, self::AuthFormAfter], true);
    }
}
